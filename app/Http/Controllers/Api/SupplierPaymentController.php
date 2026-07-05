<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Http\Requests\SupplierPayment\UpdateSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\Batch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    /**
     * List supplier payments, filterable by supplier / batch / date range.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $payments = SupplierPayment::query()
            ->with(['supplier:id,name,phone', 'batch:id,batch_number,status,exchange_rate'])
            ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('batch_id'), fn($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(SupplierPaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Record a payment to a supplier using FIFO batch distribution.
     *
     * The user provides only: supplier_id, amount_foreign, exchange_rate,
     * payment_date. The system then:
     *
     *   1. Fetches this supplier's open batches ordered oldest-first
     *      (by purchase_date ASC, then id ASC), excluding cost_allocated
     *      batches (those are already fully settled).
     *
     *   2. Fills each batch sequentially:
     *        remaining_batch = total_cost_foreign − already_paid_foreign
     *        if amount >= remaining_batch:
     *            pay off the whole remaining → batch becomes fully_paid
     *            carry forward the leftover to the next batch
     *        else:
     *            pay what's available → batch stays partial
     *            stop (nothing left to distribute)
     *
     *   3. Creates one SupplierPayment row per affected batch, each with
     *      the portion of amount_foreign allocated to that batch.
     *      amount_local for each portion = portion_foreign × exchange_rate
     *      (same rate for the whole payment operation).
     *
     *   4. Calls syncBatch() on every touched batch to recompute its
     *      exchange_rate (weighted average) and update its status.
     *
     *   5. Posts a single "out" treasury movement for the TOTAL
     *      amount_local paid (the sum across all batch portions), because
     *      from the company's cash-flow perspective it is one payment
     *      event, even though it's split across multiple batches internally.
     *
     * Edge-cases:
     *   - No open batches for this supplier → 422 with clear message.
     *   - amount_foreign exceeds the total outstanding across all open
     *     batches → all batches are fully paid; the surplus is reported
     *     in the response so the accountant is aware of the overpayment.
     *   - A batch with no total_cost_foreign set → skipped (cannot
     *     determine its remaining balance without a target cost).
     */
    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $createdPayments = [];
        $surplus         = 0.0;

        DB::transaction(function () use ($request, &$createdPayments, &$surplus) {
            $supplierId   = $request->validated('supplier_id');
            $exchangeRate = (float) $request->validated('exchange_rate');
            $paymentDate  = $request->validated('payment_date');
            $attachment   = $request->validated('attachment');
            $notes        = $request->validated('notes');
            $userId       = $request->user()->id;

            // Remaining amount still to be distributed (starts as the full payment).
            $remaining = (float) $request->validated('amount_foreign');

            // Fetch open batches for this supplier, oldest-first.
            // "Open" means NOT cost_allocated (settled) and having a
            // total_cost_foreign set (otherwise we can't know the target).
            $batches = Batch::query()
                ->where('supplier_id', $supplierId)
                ->where('status', Batch::STATUS_PARTIAL)
                ->whereNotNull('total_cost_foreign')
                ->where('total_cost_foreign', '>', 0)
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get();

            if ($batches->isEmpty()) {
                // Also check fully_paid ones in case of overpayment correction,
                // but if truly nothing open → reject.
                abort(422, 'لا توجد دفعات استيراد مفتوحة لهذا المورد تحتاج سداد. تأكد من إدخال total_cost_foreign لكل دفعة.');
            }

            $totalAmountLocal = 0.0;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $totalCost   = (float) $batch->total_cost_foreign;
                $alreadyPaid = (float) $batch->payments()->sum('amount_foreign');
                $batchNeeds  = round($totalCost - $alreadyPaid, 4);

                if ($batchNeeds <= 0) {
                    // This batch is already fully paid but status not yet
                    // updated — syncBatch will fix it; skip allocation.
                    $this->syncBatch($batch->id);
                    continue;
                }

                // How much of the remaining payment goes to this batch.
                $allocated = min($remaining, $batchNeeds);
                $remaining = round($remaining - $allocated, 4);

                $amountLocal = round($allocated * $exchangeRate, 2);
                $totalAmountLocal += $amountLocal;

                $payment = SupplierPayment::create([
                    'batch_id'       => $batch->id,
                    'supplier_id'    => $supplierId,
                    'amount_foreign' => $allocated,
                    'exchange_rate'  => $exchangeRate,
                    'amount_local'   => $amountLocal,
                    'attachment'     => $attachment,
                    'payment_date'   => $paymentDate,
                    'notes'          => $notes,
                    'created_by'     => $userId,
                ]);

                $this->syncBatch($batch->id);

                $createdPayments[] = $payment->load(['batch:id,batch_number,status,exchange_rate', 'supplier:id,name']);
            }

            // Capture any amount that exceeded all open batches.
            $surplus = $remaining;

            // Single treasury "out" movement for the full disbursed amount.
            if ($totalAmountLocal > 0) {
                $supplier = Supplier::find($supplierId, ['id', 'name']);
                $this->postTreasuryMovementDirect(
                    supplierId: $supplierId,
                    supplierName: $supplier?->name ?? (string) $supplierId,
                    totalAmountLocal: $totalAmountLocal,
                    paymentDate: $paymentDate,
                    userId: $userId,
                    paymentIds: collect($createdPayments)->pluck('id')->toArray(),
                );
            }
        });

        if (empty($createdPayments)) {
            return response()->json([
                'message' => 'لم يتم توزيع أي مبلغ — جميع الدفعات مكتملة السداد أو غير مهيأة',
            ], 422);
        }

        $response = [
            'message'          => 'تم توزيع الدفعة على ' . count($createdPayments) . ' دفعة/دفعات استيراد بنجاح (FIFO)',
            'payments_created' => SupplierPaymentResource::collection(collect($createdPayments)),
            'distribution_summary' => collect($createdPayments)->map(fn($p) => [
                'batch_number'   => $p->batch->batch_number ?? '—',
                'batch_status'   => $p->batch->status       ?? '—',
                'amount_foreign' => (float) $p->amount_foreign,
                'amount_local'   => (float) $p->amount_local,
                'exchange_rate'  => (float) $p->exchange_rate,
            ]),
        ];

        if ($surplus > 0) {
            $response['warning'] = "المبلغ المدفوع يتجاوز إجمالي المستحق بمقدار {$surplus} وحدة أجنبية. لا توجد دفعات مفتوحة لاستيعاب الفائض.";
        }

        return response()->json($response, 201);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('view', $supplierPayment);

        $supplierPayment->load(['supplier', 'batch', 'creator']);

        return response()->json([
            'data' => new SupplierPaymentResource($supplierPayment),
        ]);
    }

    /**
     * Update an existing payment's amount or exchange_rate.
     * Re-syncs the parent batch after any change.
     */
    public function update(UpdateSupplierPaymentRequest $request, SupplierPayment $supplierPayment): JsonResponse
    {
        DB::transaction(function () use ($request, $supplierPayment) {
            $amountForeign = $request->has('amount_foreign')
                ? (float) $request->validated('amount_foreign')
                : (float) $supplierPayment->amount_foreign;

            $exchangeRate = $request->has('exchange_rate')
                ? (float) $request->validated('exchange_rate')
                : (float) $supplierPayment->exchange_rate;

            $amountLocal = $request->filled('amount_local')
                ? (float) $request->validated('amount_local')
                : round($amountForeign * $exchangeRate, 2);

            $oldBatchId = $supplierPayment->batch_id;

            $supplierPayment->update(array_filter([
                'batch_id'       => $request->validated('batch_id'),
                'supplier_id'    => $request->validated('supplier_id'),
                'amount_foreign' => $amountForeign,
                'exchange_rate'  => $exchangeRate,
                'amount_local'   => $amountLocal,
                'attachment'     => $request->validated('attachment'),
                'payment_date'   => $request->validated('payment_date'),
                'notes'          => $request->validated('notes'),
            ], fn($v) => $v !== null) + ['amount_local' => $amountLocal]);

            $this->syncBatch($oldBatchId);
            if ($supplierPayment->batch_id !== $oldBatchId) {
                $this->syncBatch($supplierPayment->batch_id);
            }
        });

        return response()->json([
            'message' => 'تم تحديث دفعة المورد بنجاح',
            'data'    => new SupplierPaymentResource($supplierPayment->load(['supplier', 'batch', 'creator'])),
        ]);
    }

    /**
     * Soft-delete a payment, post a reversal entry, and recompute the batch.
     */
    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('delete', $supplierPayment);

        DB::transaction(function () use ($supplierPayment) {
            $batchId = $supplierPayment->batch_id;
            $this->postTreasuryReversal($supplierPayment);
            $supplierPayment->delete();
            $this->syncBatch($batchId);
        });

        return response()->json([
            'message' => 'تم حذف دفعة المورد بنجاح، وتم تسجيل حركة عكسية في الخزينة',
        ]);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Recompute exchange_rate + total_paid + status on one batch.
     */
    protected function syncBatch(int $batchId): void
    {
        $batch = Batch::whereKey($batchId)->first();
        if (! $batch) {
            return;
        }

        $batch->recomputeExchangeRate(save: true);
    }

    /**
     * Post a single "out" treasury movement for the total amount disbursed
     * across all batches in one FIFO payment operation.
     *
     * @param array<int> $paymentIds  IDs of all SupplierPayment rows created
     */
    protected function postTreasuryMovementDirect(
        int $supplierId,
        string $supplierName,
        float $totalAmountLocal,
        string $paymentDate,
        int $userId,
        array $paymentIds,
    ): void {
        $prev = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);

        // source_id: use the first payment ID as the anchor reference.
        TreasuryTransaction::create([
            'direction'        => TreasuryTransaction::DIRECTION_OUT,
            'amount'           => $totalAmountLocal,
            'previous_balence' => $prev,
            'current_balence'  => $prev - $totalAmountLocal,
            'source_type'      => TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
            'source_id'        => $paymentIds[0] ?? 0,
            'transaction_date' => $paymentDate,
            'notes'            => 'دفعة لمورد: ' . $supplierName
                . ' | يشمل ' . count($paymentIds) . ' دفعة/دفعات استيراد'
                . ' (IDs: ' . implode(',', $paymentIds) . ')',
            'created_by'       => $userId,
        ]);
    }

    /**
     * Post an "in" treasury movement when a payment is deleted (append-only ledger).
     */
    protected function postTreasuryReversal(SupplierPayment $payment): void
    {
        $prev = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);

        TreasuryTransaction::create([
            'direction'        => TreasuryTransaction::DIRECTION_IN,
            'amount'           => $payment->amount_local,
            'previous_balence' => $prev,
            'current_balence'  => $prev + (float) $payment->amount_local,
            'source_type'      => TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
            'source_id'        => $payment->id,
            'transaction_date' => now()->toDateString(),
            'notes'            => 'إلغاء دفعة مورد محذوفة رقم #' . $payment->id,
            'created_by'       => $payment->created_by,
        ]);
    }
}
