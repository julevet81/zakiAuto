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
            ->with(['supplier:id,name,phone', 'batch:id,batch_number,status'])
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
     * This performs four things atomically:
     *   1. Create the supplier_payments row (amount_local auto-computed
     *      from amount_foreign * exchange_rate if not given explicitly).
     *   2. Recompute the batch's running total_paid_amount_foreign from
     *      the sum of ALL its payments (never trust a client-sent total).
     *   3. Advance the batch status pending -> partial on first payment
     *      only. We deliberately do NOT auto-mark a batch "fully_paid",
     *      since `batches` has no "total cost" column to compare against
     *      — see SUPPLIER_PAYMENTS_NOTE.md. That transition stays manual.
     *   4. Post a matching "out" movement to the company treasury ledger,
     *      chained off the previous treasury balance so the running
     *      balance is always correct.
     */
    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $payment = DB::transaction(function () use ($request) {
            $amountForeign = (float) $request->validated('amount_foreign');
            $exchangeRate = (float) $request->validated('exchange_rate');
            $amountLocal = $request->filled('amount_local')
                ? (float) $request->validated('amount_local')
                : round($amountForeign * $exchangeRate, 2);

            /** @var SupplierPayment $payment */
            $payment = SupplierPayment::create([
                'batch_id' => $request->validated('batch_id'),
                'supplier_id' => $request->validated('supplier_id'),
                'amount_foreign' => $amountForeign,
                'exchange_rate' => $exchangeRate,
                'amount_local' => $amountLocal,
                'attachment' => $request->validated('attachment'),
                'payment_date' => $request->validated('payment_date'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $this->recalculateBatchTotals($payment->batch_id);

            $this->postTreasuryMovement($payment, $request->user()->id);

            return $payment;
        });

        return response()->json([
            'message' => 'تم تسجيل دفعة المورد بنجاح',
            'data' => new SupplierPaymentResource($payment->load(['supplier', 'batch', 'creator'])),
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
     * Update an existing payment.
     *
     * NOTE: editing amount_foreign/exchange_rate after the fact re-derives
     * amount_local (unless explicitly overridden) and re-syncs the parent
     * batch's running total — this keeps the batch total trustworthy even
     * after corrections, instead of drifting from manual edits.
     *
     * This intentionally does NOT touch the treasury ledger automatically:
     * the original treasury_transactions row already represents what
     * actually happened in the company's cash flow at that point in time.
     * Silently mutating historical ledger entries would corrupt the audit
     * trail. If a payment amount was genuinely wrong, void/delete it and
     * record a new, correct payment instead.
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

            // NOTE: array_filter(...!== null) means sending an explicit
            // `"notes": null` will NOT clear notes — only omitting the key
            // entirely leaves it untouched, same as omitting it. This is a
            // deliberate trade-off for this endpoint: the financially
            // load-bearing fields (amount_foreign/exchange_rate/amount_local)
            // are computed separately above and always included, so this
            // filter only affects descriptive fields (notes/attachment).
            // If you need a real "clear this field" semantic for notes,
            // switch to $request->has('notes') checks per-field instead.
            $supplierPayment->update(array_filter([
                'batch_id' => $request->validated('batch_id'),
                'supplier_id' => $request->validated('supplier_id'),
                'amount_foreign' => $amountForeign,
                'exchange_rate' => $exchangeRate,
                'amount_local' => $amountLocal,
                'attachment' => $request->validated('attachment'),
                'payment_date' => $request->validated('payment_date'),
                'notes' => $request->validated('notes'),
            ], fn ($value) => $value !== null) + ['amount_local' => $amountLocal]);

            // Re-sync totals for the (possibly two different) batches involved.
            $this->recalculateBatchTotals($oldBatchId);
            if ($supplierPayment->batch_id !== $oldBatchId) {
                $this->recalculateBatchTotals($supplierPayment->batch_id);
            }
        });

        return response()->json([
            'message' => 'تم تحديث دفعة المورد بنجاح',
            'data' => new SupplierPaymentResource($supplierPayment->load(['supplier', 'batch', 'creator'])),
        ]);
    }

    /**
     * Soft-delete a payment and re-sync its batch's running total.
     *
     * Like update(), this does NOT retroactively remove the original
     * treasury ledger entry — instead it posts a new, clearly-labelled
     * reversal entry. The ledger should read like a bank statement: every
     * real movement of cash stays visible, including corrections.
     */
    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('delete', $supplierPayment);

        DB::transaction(function () use ($supplierPayment) {
            $batchId = $supplierPayment->batch_id;

            $this->postTreasuryReversal($supplierPayment);

            $supplierPayment->delete();

            $this->recalculateBatchTotals($batchId);
        });

        return response()->json([
            'message' => 'تم حذف دفعة المورد بنجاح، وتم تسجيل حركة عكسية في الخزينة',
        ]);
    }

    /**
     * Recompute a batch's total_paid_amount_foreign from the live sum of
     * its (non-deleted) payments, and advance pending -> partial on the
     * first payment. Never auto-advances to fully_paid/cost_allocated —
     * see the class-level note on update().
     */
    protected function recalculateBatchTotals(int $batchId): void
    {
        $batch = Batch::find($batchId);

        if (! $batch) {
            return;
        }

        $totalForeign = (float) $batch->payments()->sum('amount_foreign');

        $batch->total_paid_amount_foreign = $totalForeign;

        if ($totalForeign > 0 && $batch->status === Batch::STATUS_PENDING) {
            $batch->status = Batch::STATUS_PARTIAL;
        }

        if ($totalForeign <= 0 && $batch->status === Batch::STATUS_PARTIAL) {
            // All payments for this batch were removed; revert to pending.
            $batch->status = Batch::STATUS_PENDING;
        }

        $batch->save();
    }

    /**
     * Post an "out" movement to the company treasury ledger for a new
     * supplier payment, chaining off the most recent treasury balance.
     */
    protected function postTreasuryMovement(SupplierPayment $payment, int $userId): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance - (float) $payment->amount_local;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => $payment->amount_local,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'notes' => 'دفعة لمورد: '.($payment->supplier?->name ?? $payment->supplier_id),
            'created_by' => $userId,
        ]);
    }

    /**
     * Post a reversing "in" movement when a supplier payment is deleted,
     * so the treasury ledger keeps balancing without ever mutating the
     * original entry.
     */
    protected function postTreasuryReversal(SupplierPayment $payment): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance + (float) $payment->amount_local;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => $payment->amount_local,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => now()->toDateString(),
            'notes' => 'إلغاء دفعة مورد محذوفة رقم #'.$payment->id,
            'created_by' => $payment->created_by,
        ]);
    }
}
