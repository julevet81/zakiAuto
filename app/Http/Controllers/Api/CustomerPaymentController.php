<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPayment\RemitCustomerPaymentRequest;
use App\Http\Requests\CustomerPayment\StoreCustomerPaymentRequest;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\AgentTransaction;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    /**
     * List customer payments, scoped by who's asking:
     *   - admin/super-admin (customer_payments.view): every payment.
     *   - customer/agent (customer_payments.view_own): only their own
     *     (as the payer, or as the agent who collected it).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerPayment::class);

        $user = $request->user();

        $query = CustomerPayment::query()
            ->with(['customer', 'agent'])
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')));

        if ($user->can('customer_payments.view')) {
            $query
                ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
                ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->integer('agent_id')))
                ->when($request->filled('is_remitted'), function ($q) use ($request) {
                    $request->boolean('is_remitted')
                        ? $q->whereNotNull('remittance_id')
                        : $q->whereNull('remittance_id');
                });
        } else {
            // Scoped to: payments made by this user's own customer record,
            // OR payments collected by this user's own agent record.
            $query->where(function ($q) use ($user) {
                $q->Where('agent_id', $user->agent?->id ?? 0);
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(CustomerPaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Record a customer payment against an order.
     *
     * Two distinct financial paths depending on `received_by`:
     *
     *   - "company": cash went straight to the company. Posts an "in"
     *     movement to treasury_transactions immediately.
     *
     *   - "agent": an agent physically collected cash on the company's
     *     behalf. This does NOT touch the treasury yet — the company
     *     hasn't actually received the money. Instead it posts an "out"
     *     ledger line to agent_transactions (the agent now "owes" this
     *     amount to the company, i.e. holds it on the company's behalf).
     *     The money only reaches treasury later, when the agent remits it
     *     — see remit().
     *
     * In both cases, the parent order's paid/remaining amounts are
     * recalculated from the live sum of its payments.
     */
    public function store(StoreCustomerPaymentRequest $request): JsonResponse
    {
        $payment = DB::transaction(function () use ($request) {
            /** @var CustomerPayment $payment */
            $payment = CustomerPayment::create([
                'order_id' => $request->validated('order_id'),
                'customer_id' => $request->validated('customer_id'),
                'amount' => $request->validated('amount'),
                'received_by' => $request->user()->id,
                'agent_id' => $request->validated('agent_id'),
                'attachment' => $request->validated('attachment'),
                'payment_date' => $request->validated('payment_date'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $order = Order::find($payment->order_id);
            $order?->recalculateBalance();

            if ($payment->wasCollectedByAgent()) {
                $this->postAgentLedgerEntry($payment, $request->user()->id);
            } else {
                $this->postTreasuryMovement($payment, $request->user()->id);
            }

            return $payment;
        });

        return response()->json([
            'message' => 'تم تسجيل دفعة العميل بنجاح',
            'data' => new CustomerPaymentResource($payment->load(['customer', 'agent', 'creator'])),
        ], 201);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('view', $customerPayment);

        $customerPayment->load(['customer', 'agent', 'order', 'creator']);

        return response()->json([
            'data' => new CustomerPaymentResource($customerPayment),
        ]);
    }

    /**
     * Soft-delete a payment, reverse whichever ledger it landed in (agent
     * ledger or treasury), and re-sync the parent order's balance.
     *
     * A payment that has ALREADY been remitted (remittance_id set) cannot
     * be deleted directly — the remittance itself represents a real
     * transfer of cash that already happened and has its own treasury
     * entry; voiding the underlying payment at that point would require
     * voiding the remittance first to keep the ledger consistent.
     */
    public function destroy(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('delete', $customerPayment);

        if ($customerPayment->remittance_id !== null) {
            return response()->json([
                'message' => 'لا يمكن حذف دفعة تم تحويلها للخزينة بالفعل، يجب إلغاء عملية التحويل أولاً',
            ], 422);
        }

        DB::transaction(function () use ($customerPayment) {
            if ($customerPayment->wasCollectedByAgent()) {
                $this->reverseAgentLedgerEntry($customerPayment);
            } else {
                $this->postTreasuryReversal($customerPayment);
            }

            $orderId = $customerPayment->order_id;
            $customerPayment->delete();

            Order::find($orderId)?->recalculateBalance();
        });

        return response()->json(['message' => 'تم حذف الدفعة بنجاح']);
    }

    /**
     * Mark a batch of agent-collected payments as remitted: the agent has
     * now physically handed this cash over to the company. This is the
     * moment the money actually reaches the company treasury.
     *
     * Posts ONE agent_transactions "in" entry (the agent's debt clears)
     * and ONE treasury_transactions "in" entry (the company now actually
     * holds the cash), cross-linked via the circular FKs
     * (customer_payments.remittance_id <-> agent_transactions.transaction_id)
     * exactly as modelled in the migration.
     */
    public function remit(RemitCustomerPaymentRequest $request, CustomerPayment $customerPayment): JsonResponse
    {
        if (! $customerPayment->wasCollectedByAgent()) {
            return response()->json([
                'message' => 'هذه الدفعة لم تُستلم بواسطة وكيل، لا حاجة لتحويلها',
            ], 422);
        }

        if ($customerPayment->remittance_id !== null) {
            return response()->json([
                'message' => 'تم تحويل هذه الدفعة مسبقًا',
            ], 422);
        }

        DB::transaction(function () use ($request, $customerPayment) {
            $date = $request->input('transaction_date', now()->toDateString());
            $userId = $request->user()->id;

            // Order matters here: TreasuryTransaction::SOURCE_AGENT_REMITTANCE
            // resolves source_id against the agent_transactions table (see
            // TreasuryTransaction::source()), so the AgentTransaction row
            // must exist BEFORE we create the TreasuryTransaction that
            // references it. We create the agent ledger entry first with a
            // temporary null transaction_id, then backfill it once the
            // treasury entry exists (mirrors the "nullable column added
            // first, constrained later" pattern already used by the
            // migration itself for these same circular FKs).

            // 1) The agent's "in" ledger entry: their debt to the company
            //    for this specific amount is now cleared.
            $agentPrevious = (float) (AgentTransaction::query()
                ->where('agent_id', $customerPayment->agent_id)
                ->latest('id')
                ->value('current_balence') ?? 0);
            $agentNew = $agentPrevious + (float) $customerPayment->amount;

            $agentTransaction = AgentTransaction::create([
                'agent_id' => $customerPayment->agent_id,
                'direction' => AgentTransaction::DIRECTION_IN,
                'amount' => $customerPayment->amount,
                'previous_balence' => $agentPrevious,
                'current_balence' => $agentNew,
                'payment_id' => $customerPayment->id,
                'transaction_id' => null,
                'transaction_date' => $date,
                'attachment' => $request->input('attachment'),
                'notes' => $request->input('notes', 'تحويل دفعة عميل رقم #'.$customerPayment->id.' للخزينة'),
                'created_by' => $userId,
            ]);

            // 2) Treasury actually receives the cash now, referencing the
            //    agent_transactions row created above as its source.
            $treasuryPrevious = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
            $treasuryNew = $treasuryPrevious + (float) $customerPayment->amount;

            $treasuryTransaction = TreasuryTransaction::create([
                'direction' => TreasuryTransaction::DIRECTION_IN,
                'amount' => $customerPayment->amount,
                'previous_balence' => $treasuryPrevious,
                'current_balence' => $treasuryNew,
                'source_type' => TreasuryTransaction::SOURCE_AGENT_REMITTANCE,
                'source_id' => $agentTransaction->id,
                'transaction_date' => $date,
                'notes' => 'تحويل دفعة عميل من الوكيل: '.($customerPayment->agent?->name ?? $customerPayment->agent_id),
                'created_by' => $userId,
            ]);

            // 3) Backfill the agent ledger entry's transaction_id now that
            //    the treasury entry exists, closing the circular reference.
            $agentTransaction->update(['transaction_id' => $treasuryTransaction->id]);

            $customerPayment->update(['remittance_id' => $agentTransaction->id]);
        });

        return response()->json([
            'message' => 'تم تأكيد تحويل الدفعة للخزينة بنجاح',
            'data' => new CustomerPaymentResource($customerPayment->fresh(['customer', 'agent', 'remittance'])),
        ]);
    }

    /**
     * Post the company-received "in" treasury movement for a
     * received_by=company payment.
     */
    protected function postTreasuryMovement(CustomerPayment $payment, int $userId): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance + (float) $payment->amount;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'notes' => 'دفعة من العميل: '.($payment->customer?->name ?? $payment->customer_id),
            'created_by' => $userId,
        ]);
    }

    /**
     * Post the agent's "out" ledger entry for a received_by=agent
     * payment: the agent now holds this cash on the company's behalf
     * (a liability from the agent to the company until remitted).
     */
    protected function postAgentLedgerEntry(CustomerPayment $payment, int $userId): void
    {
        $previousBalance = (float) (AgentTransaction::query()
            ->where('agent_id', $payment->agent_id)
            ->latest('id')
            ->value('current_balence') ?? 0);
        $newBalance = $previousBalance - (float) $payment->amount;

        AgentTransaction::create([
            'agent_id' => $payment->agent_id,
            'direction' => AgentTransaction::DIRECTION_OUT,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'payment_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'notes' => 'قبض دفعة عميل رقم #'.$payment->id.' لم تُحوَّل للخزينة بعد',
            'created_by' => $userId,
        ]);
    }

    /**
     * Reverse the treasury "in" movement when a company-received payment
     * is deleted, via a new "out" entry (ledger stays append-only).
     */
    protected function postTreasuryReversal(CustomerPayment $payment): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance - (float) $payment->amount;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => now()->toDateString(),
            'notes' => 'إلغاء دفعة عميل محذوفة رقم #'.$payment->id,
            'created_by' => $payment->created_by,
        ]);
    }

    /**
     * Reverse the agent's "out" ledger entry when an un-remitted
     * agent-collected payment is deleted, via a new "in" entry clearing
     * the agent's liability for that amount (ledger stays append-only).
     */
    protected function reverseAgentLedgerEntry(CustomerPayment $payment): void
    {
        $previousBalance = (float) (AgentTransaction::query()
            ->where('agent_id', $payment->agent_id)
            ->latest('id')
            ->value('current_balence') ?? 0);
        $newBalance = $previousBalance + (float) $payment->amount;

        AgentTransaction::create([
            'agent_id' => $payment->agent_id,
            'direction' => AgentTransaction::DIRECTION_IN,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'payment_id' => $payment->id,
            'transaction_date' => now()->toDateString(),
            'notes' => 'إلغاء قبض دفعة عميل محذوفة رقم #'.$payment->id,
            'created_by' => $payment->created_by,
        ]);
    }
}
