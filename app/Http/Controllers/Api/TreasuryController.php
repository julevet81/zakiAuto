<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTreasuryDepositRequest;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TreasuryController extends Controller
{
    /**
     * Current treasury balance + recent movements.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->agent) {
            \Illuminate\Support\Facades\Gate::authorize('treasury.view');
        }

        $query = TreasuryTransaction::query()
            ->with('creator:id,name')
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'),   fn($q) => $q->whereDate('transaction_date', '<=', $request->date('date_to')))
            ->when($request->filled('direction'), fn($q) => $q->where('direction', $request->string('direction')));

        if ($user->agent) {
            $agentId = $user->agent->id;
            $query->where(function ($q) use ($agentId) {
                $q->where(function ($sq) use ($agentId) {
                    $sq->where('source_type', TreasuryTransaction::SOURCE_AGENT_REMITTANCE)
                        ->whereIn('source_id', function ($sub) use ($agentId) {
                            $sub->select('id')
                                ->from('agent_transactions')
                                ->where('agent_id', $agentId);
                        });
                })
                ->orWhere(function ($sq) use ($agentId) {
                    $sq->where('source_type', TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT)
                        ->whereIn('source_id', function ($sub) use ($agentId) {
                            $sub->select('id')
                                ->from('customer_payments')
                                ->where('agent_id', $agentId);
                        });
                });
            });
        }

        $transactions = $query->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        $currentBalance = $user->agent
            ? (float) $user->agent->current_balance
            : (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);

        return response()->json([
            'current_balance' => $currentBalance,
            'data'            => $transactions->map(fn($tx) => [
                'id'               => $tx->id,
                'direction'        => $tx->direction,
                'amount'           => (float) $tx->amount,
                'previous_balence' => (float) $tx->previous_balence,
                'current_balence'  => (float) $tx->current_balence,
                'source_type'      => $tx->source_type,
                'source_id'        => $tx->source_id,
                'transaction_date' => $tx->transaction_date?->format('Y-m-d'),
                'notes'            => $tx->notes,
                'creator'          => $tx->creator ? [
                    'id'   => $tx->creator->id,
                    'name' => $tx->creator->name,
                ] : null,
                'created_at'       => $tx->created_at,
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
        ]);
    }

    /**
     * Deposit an arbitrary amount into the company treasury with no
     * conditions — used for opening balances, cash injections, capital
     * contributions, or any direct credit the accountant needs to record.
     *
     * No linked source is required (source_type = 'manual_deposit').
     * The posted entry is append-only and cannot be edited; if a
     * correction is needed, post a matching "out" entry instead.
     */
    public function deposit(StoreTreasuryDepositRequest $request): JsonResponse
    {
        $prev   = (float) (TreasuryTransaction::query()->latest('id')->value('current_balence') ?? 0);
        $amount = (float) $request->validated('amount');

        $transaction = TreasuryTransaction::create([
            'direction'        => TreasuryTransaction::DIRECTION_IN,
            'amount'           => $amount,
            'previous_balence' => $prev,
            'current_balence'  => $prev + $amount,
            'source_type'      => 'manual_deposit',
            'source_id'        => 0,
            'transaction_date' => $request->validated('transaction_date'),
            'notes'            => $request->validated('notes') ?? 'إيداع يدوي مباشر في الخزينة',
            'created_by'       => $request->user()->id,
        ]);

        return response()->json([
            'message'         => 'تم إيداع المبلغ في الخزينة بنجاح',
            'amount_deposited' => (float) $transaction->amount,
            'new_balance'      => (float) $transaction->current_balence,
            'transaction_id'   => $transaction->id,
            'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
        ], 201);
    }
}
