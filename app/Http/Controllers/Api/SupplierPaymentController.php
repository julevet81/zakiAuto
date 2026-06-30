<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Http\Requests\SupplierPayment\UpdateSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\Batch;
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
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(SupplierPaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Record a new payment to a supplier against one of their import batches.
     *
     * Atomically:
     *   1. Creates the supplier_payments row (amount_local = amount_foreign
     *      × exchange_rate unless overridden explicitly).
     *   2. Calls Batch::recomputeExchangeRate() which:
     *         - recomputes the BATCH's effective exchange_rate as a weighted
     *           average of all payments' rates (paid portion) plus the
     *           remaining unpaid amount at the highest rate seen (unpaid
     *           portion). See Batch::recomputeExchangeRate() for the full
     *           formula and edge-case handling.
     *         - keeps total_paid_amount_foreign in sync.
     *   3. Advances batch status pending → partial on the first payment.
     *      Automatically marks batch fully_paid when total_paid ≥
     *      total_cost_foreign (since we now have that column).
     *   4. Posts an "out" movement to treasury_transactions.
     */
    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $payment = DB::transaction(function () use ($request) {
            $amountForeign = (float) $request->validated('amount_foreign');
            $exchangeRate  = (float) $request->validated('exchange_rate');
            $amountLocal   = $request->filled('amount_local')
                ? (float) $request->validated('amount_local')
                : round($amountForeign * $exchangeRate, 2);

            /** @var SupplierPayment $payment */
            $payment = SupplierPayment::create([
                'batch_id'      => $request->validated('batch_id'),
                'supplier_id'   => $request->validated('supplier_id'),
                'amount_foreign' => $amountForeign,
                'exchange_rate' => $exchangeRate,
                'amount_local'  => $amountLocal,
                'attachment'    => $request->validated('attachment'),
                'payment_date'  => $request->validated('payment_date'),
                'notes'         => $request->validated('notes'),
                'created_by'    => $request->user()->id,
            ]);

            $this->syncBatch($payment->batch_id);
            $this->postTreasuryMovement($payment, $request->user()->id);

            return $payment;
        });

        return response()->json([
            'message' => 'تم تسجيل دفعة المورد بنجاح',
            'data'    => new SupplierPaymentResource($payment->load(['supplier', 'batch', 'creator'])),
        ], 201);
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
     *
     * Re-derives amount_local from (amount_foreign × exchange_rate) unless
     * explicitly overridden, then recomputes the parent batch's effective
     * exchange_rate from scratch via Batch::recomputeExchangeRate().
     *
     * Does NOT touch treasury — the original entry represents the real
     * cash flow at that point in time. Wrong amounts should be voided and
     * re-entered rather than silently corrected in place.
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
                'batch_id'      => $request->validated('batch_id'),
                'supplier_id'   => $request->validated('supplier_id'),
                'amount_foreign' => $amountForeign,
                'exchange_rate' => $exchangeRate,
                'amount_local'  => $amountLocal,
                'attachment'    => $request->validated('attachment'),
                'payment_date'  => $request->validated('payment_date'),
                'notes'         => $request->validated('notes'),
            ], fn ($v) => $v !== null) + ['amount_local' => $amountLocal]);

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
     * Soft-delete a payment, post a treasury reversal entry, and recompute
     * the parent batch's exchange_rate from the remaining payments.
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
     * Recompute exchange_rate (and total_paid_amount_foreign) on the batch
     * via Batch::recomputeExchangeRate(), then update status:
     *
     *   - pending  → partial       on first payment.
     *   - partial  → fully_paid    when total_paid ≥ total_cost_foreign
     *                              (auto-detect now possible since we have
     *                              total_cost_foreign on the batch).
     *   - partial  → pending       if all payments are removed.
     *   - fully_paid → partial     if a payment is removed and total drops
     *                              back below total_cost_foreign.
     *
     * Status only advances to fully_paid automatically; transitioning to
     * cost_allocated remains a deliberate manual action by staff.
     */
    protected function syncBatch(int $batchId): void
    {
        $batch = Batch::find($batchId);

        if (! $batch) {
            return;
        }

        // recomputeExchangeRate() also refreshes total_paid_amount_foreign
        // in the same single DB aggregate query — pass save: false so we
        // can append the status change before the one save() call.
        $batch->recomputeExchangeRate(save: false);

        $totalPaid = (float) $batch->total_paid_amount_foreign;
        $totalCost = (float) $batch->total_cost_foreign;

        if ($totalPaid <= 0) {
            if ($batch->status === Batch::STATUS_PARTIAL) {
                $batch->status = Batch::STATUS_PENDING;
            }
        } elseif ($totalCost > 0 && $totalPaid >= $totalCost) {
            if ($batch->status !== Batch::STATUS_COST_ALLOCATED) {
                // Only auto-advance to fully_paid; cost_allocated is set
                // manually when the cost has been distributed per-car.
                $batch->status = Batch::STATUS_FULLY_PAID;
            }
        } else {
            // Partial payment
            if (in_array($batch->status, [Batch::STATUS_PENDING, Batch::STATUS_FULLY_PAID], true)) {
                $batch->status = Batch::STATUS_PARTIAL;
            }
        }

        $batch->save();
    }

    /**
     * Post an "out" treasury movement for a new supplier payment.
     */
    protected function postTreasuryMovement(SupplierPayment $payment, int $userId): void
    {
        $prev = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);

        TreasuryTransaction::create([
            'direction'        => TreasuryTransaction::DIRECTION_OUT,
            'amount'           => $payment->amount_local,
            'previous_balence' => $prev,
            'current_balence'  => $prev - (float) $payment->amount_local,
            'source_type'      => TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
            'source_id'        => $payment->id,
            'transaction_date' => $payment->payment_date,
            'notes'            => 'دفعة لمورد: '.($payment->supplier?->name ?? $payment->supplier_id),
            'created_by'       => $userId,
        ]);
    }

    /**
     * Post a reversing "in" treasury movement when a supplier payment is
     * deleted (append-only ledger — never mutate historical entries).
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
            'notes'            => 'إلغاء دفعة مورد محذوفة رقم #'.$payment->id,
            'created_by'       => $payment->created_by,
        ]);
    }
}
