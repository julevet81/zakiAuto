<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentRequest;
use App\Http\Requests\Agent\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Http\Resources\AgentTransactionResource;
use App\Http\Resources\CustomerResource;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Agent::class);

        $agents = Agent::query()
            ->withCount(['customers', 'orders'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(AgentResource::collection($agents)->response()->getData(true));
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = Agent::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة الوكيل بنجاح',
            'data' => new AgentResource($agent),
        ], 201);
    }

    public function show(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $agent->loadCount(['customers', 'orders']);

        return response()->json([
            'data' => new AgentResource($agent),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $agent->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الوكيل بنجاح',
            'data' => new AgentResource($agent),
        ]);
    }

    /**
     * Delete an agent. Blocked if they have any customers or orders, to
     * avoid orphaning commission/ledger history.
     */
    public function destroy(Agent $agent): JsonResponse
    {
        $this->authorize('delete', $agent);

        if ($agent->customers()->exists() || $agent->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الوكيل لوجود عملاء أو طلبات مرتبطة به',
            ], 422);
        }

        $agent->delete();

        return response()->json(['message' => 'تم حذف الوكيل بنجاح']);
    }

    /**
     * List the customers assigned to this agent (explicit requirement:
     * "عرض العملاء التابعين لكل وكيل"), as its own endpoint so the UI can
     * paginate/filter it independently of the agent's main payload.
     *
     * Returns total_paid and total_remaining per customer via withSum()
     * (single aggregate query per column, no N+1).
     */
    public function customers(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $customers = $agent->customers()
            ->withCount('orders')
            ->withSum('payments as total_paid_sum', 'amount')
            ->withSum('orders as total_remaining_sum', 'remaining_amount')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(
            CustomerResource::collection($customers)->response()->getData(true)
        );
    }

    /**
     * Agent statement of account (explicit requirement: "عرض كشف حساب
     * الوكيل") — the full chronological ledger from agent_transactions,
     * each line already carrying its running previous/current balance.
     */
    public function statement(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $transactions = $agent->transactions()
            ->with(['customerPayment', 'treasuryTransaction'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date('date_to')))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'current_balance' => $agent->current_balance,
            ],
            'statement' => AgentTransactionResource::collection($transactions)->response()->getData(true),
        ]);
    }
}