<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TreasuryTransactionResource;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TreasuryController extends Controller
{
    /**
     * Display treasury dashboard stats and paginated transactions list.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('treasury.view');

        // 1. Calculate dashboard stats
        $latestTransaction = TreasuryTransaction::query()->latest('id')->first();
        $currentBalance = $latestTransaction ? (float) $latestTransaction->current_balence : 0.0;

        $stats = [
            'current_balance' => $currentBalance,
            'total_in' => (float) TreasuryTransaction::query()->where('direction', TreasuryTransaction::DIRECTION_IN)->sum('amount'),
            'total_out' => (float) TreasuryTransaction::query()->where('direction', TreasuryTransaction::DIRECTION_OUT)->sum('amount'),
            'in_count' => TreasuryTransaction::query()->where('direction', TreasuryTransaction::DIRECTION_IN)->count(),
            'out_count' => TreasuryTransaction::query()->where('direction', TreasuryTransaction::DIRECTION_OUT)->count(),
            'breakdown' => [
                'customer_payment' => (float) TreasuryTransaction::query()
                    ->where('source_type', TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT)
                    ->sum('amount'),
                'agent_remittance' => (float) TreasuryTransaction::query()
                    ->where('source_type', TreasuryTransaction::SOURCE_AGENT_REMITTANCE)
                    ->sum('amount'),
                'supplier_payment' => (float) TreasuryTransaction::query()
                    ->where('source_type', TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT)
                    ->sum('amount'),
                'expense' => (float) TreasuryTransaction::query()
                    ->where('source_type', TreasuryTransaction::SOURCE_EXPENSE)
                    ->sum('amount'),
            ],
        ];

        // 2. Fetch detailed transactions with filtering
        $query = TreasuryTransaction::query()
            ->with('creator')
            ->when($request->filled('direction'), fn($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('source_type'), fn($q) => $q->where('source_type', $request->string('source_type')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transaction_date', '<=', $request->date('date_to')));

        $transactions = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json([
            'stats' => $stats,
            'transactions' => TreasuryTransactionResource::collection($transactions)->response()->getData(true),
        ]);
    }
}
