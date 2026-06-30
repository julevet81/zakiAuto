<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentTransaction\StoreAgentTransactionRequest;
use App\Http\Resources\AgentTransactionResource;
use App\Models\AgentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentTransactionController extends Controller
{
    /**
     * List ledger entries across all agents (admin) or just the
     * requester's own ledger (agent with view_own only).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgentTransaction::class);

        $user = $request->user();

        $query = AgentTransaction::query()
            ->with(['agent:id,name', 'customerPayment', 'treasuryTransaction'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date('date_to')));

        if ($user->can('agent_transactions.view')) {
            $query->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->integer('agent_id')));
        } else {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $transactions = $query->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json(AgentTransactionResource::collection($transactions)->response()->getData(true));
    }

    /**
     * Record a manual ledger entry (commission, cash advance, correction)
     * against an agent, chaining off their most recent balance.
     */
    public function store(StoreAgentTransactionRequest $request): JsonResponse
    {
        $transaction = DB::transaction(function () use ($request) {
            $agentId = $request->validated('agent_id');
            $direction = $request->validated('direction');
            $amount = (float) $request->validated('amount');

            $previousBalance = (float) (AgentTransaction::query()
                ->where('agent_id', $agentId)
                ->latest('id')
                ->value('current_balence') ?? 0);

            $currentBalance = $direction === AgentTransaction::DIRECTION_IN
                ? $previousBalance + $amount
                : $previousBalance - $amount;

            return AgentTransaction::create([
                'agent_id' => $agentId,
                'direction' => $direction,
                'amount' => $amount,
                'previous_balence' => $previousBalance,
                'current_balence' => $currentBalance,
                'transaction_date' => $request->validated('transaction_date'),
                'attachment' => $request->validated('attachment'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'تم تسجيل الحركة بنجاح',
            'data' => new AgentTransactionResource($transaction->load(['agent', 'creator'])),
        ], 201);
    }

    public function show(AgentTransaction $agentTransaction): JsonResponse
    {
        $this->authorize('view', $agentTransaction);

        $agentTransaction->load(['agent', 'customerPayment', 'treasuryTransaction', 'creator']);

        return response()->json([
            'data' => new AgentTransactionResource($agentTransaction),
        ]);
    }

    /**
     * Delete a MANUAL ledger entry only. Entries that originated from a
     * customer payment (payment_id set) or a treasury remittance
     * (transaction_id set) are part of the automated, cross-referenced
     * financial trail and must be reversed through their origin (delete
     * the customer payment / void the remittance) rather than directly
     * here, to avoid breaking the chain those other records still point to.
     */
    public function destroy(AgentTransaction $agentTransaction): JsonResponse
    {
        $this->authorize('delete', $agentTransaction);

        if ($agentTransaction->payment_id !== null || $agentTransaction->transaction_id !== null) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه الحركة مباشرة لأنها ناتجة تلقائيًا عن دفعة أو تحويل، يجب التعامل معها من مصدرها',
            ], 422);
        }

        $agentTransaction->delete();

        return response()->json(['message' => 'تم حذف الحركة بنجاح']);
    }
}
