<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::query()
            ->with(['serviceProvider'])
            ->when($request->filled('car_id'), fn ($q) => $q->where('car_id', $request->integer('car_id')))
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('expense_type'), fn ($q) => $q->where('expense_type', $request->string('expense_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('date_to')))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(ExpenseResource::collection($expenses)->response()->getData(true));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = DB::transaction(function () use ($request) {
            $expense = Expense::create($request->validated() + ['created_by' => $request->user()->id]);

            // Deduct from general treasury:
            $prev = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
            $amount = (float) $expense->amount;

            $notes = 'تسجيل مصروف: ' . $expense->expense_type;
            if ($expense->service_provider_id && $expense->serviceProvider) {
                $notes .= ' | مقدم الخدمة: ' . $expense->serviceProvider->name;
            }
            if ($expense->car_id && $expense->car) {
                $notes .= ' | للسيارة: ' . ($expense->car->vin ?? $expense->car->id);
            } elseif ($expense->order_id && $expense->order) {
                $notes .= ' | للطلب رقم: ' . ($expense->order->order_number ?? $expense->order->id);
            }
            if ($expense->notes) {
                $notes .= ' | ' . $expense->notes;
            }

            TreasuryTransaction::create([
                'direction'        => TreasuryTransaction::DIRECTION_OUT,
                'amount'           => $amount,
                'previous_balence' => $prev,
                'current_balence'  => $prev - $amount,
                'source_type'      => TreasuryTransaction::SOURCE_EXPENSE,
                'source_id'        => $expense->id,
                'transaction_date' => $expense->expense_date ?? now()->toDateString(),
                'status'           => TreasuryTransaction::STATUS_APPROVED,
                'notes'            => $notes,
                'created_by'       => $request->user()->id,
            ]);

            return $expense;
        });

        return response()->json([
            'message' => 'تم تسجيل المصروف بنجاح',
            'data' => new ExpenseResource($expense->load(['serviceProvider', 'creator'])),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        $expense->load(['serviceProvider', 'creator', 'car', 'order']);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = DB::transaction(function () use ($request, $expense) {
            $oldAmount = (float) $expense->amount;
            $expense->update($request->validated());
            $newAmount = (float) $expense->amount;

            $hasTx = TreasuryTransaction::query()
                ->where('source_type', TreasuryTransaction::SOURCE_EXPENSE)
                ->where('source_id', $expense->id)
                ->exists();

            if ($hasTx) {
                if ($oldAmount !== $newAmount) {
                    // Reversal of the old amount (credit/in) using current date as requested
                    $prev = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
                    TreasuryTransaction::create([
                        'direction'        => TreasuryTransaction::DIRECTION_IN,
                        'amount'           => $oldAmount,
                        'previous_balence' => $prev,
                        'current_balence'  => $prev + $oldAmount,
                        'source_type'      => TreasuryTransaction::SOURCE_EXPENSE,
                        'source_id'        => $expense->id,
                        'transaction_date' => now()->toDateString(),
                        'status'           => TreasuryTransaction::STATUS_APPROVED,
                        'notes'            => 'إلغاء القيمة السابقة للمصروف المعدل رقم #' . $expense->id,
                        'created_by'       => $request->user()->id,
                    ]);

                    // Deduct the new amount (debit/out) using current date as requested
                    $prev = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
                    $notes = 'تسجيل القيمة الجديدة للمصروف رقم #' . $expense->id . ': ' . $expense->expense_type;
                    if ($expense->service_provider_id && $expense->serviceProvider) {
                        $notes .= ' | مقدم الخدمة: ' . $expense->serviceProvider->name;
                    }
                    if ($expense->car_id && $expense->car) {
                        $notes .= ' | للسيارة: ' . ($expense->car->vin ?? $expense->car->id);
                    } elseif ($expense->order_id && $expense->order) {
                        $notes .= ' | للطلب رقم: ' . ($expense->order->order_number ?? $expense->order->id);
                    }
                    if ($expense->notes) {
                        $notes .= ' | ' . $expense->notes;
                    }

                    TreasuryTransaction::create([
                        'direction'        => TreasuryTransaction::DIRECTION_OUT,
                        'amount'           => $newAmount,
                        'previous_balence' => $prev,
                        'current_balence'  => $prev - $newAmount,
                        'source_type'      => TreasuryTransaction::SOURCE_EXPENSE,
                        'source_id'        => $expense->id,
                        'transaction_date' => now()->toDateString(),
                        'status'           => TreasuryTransaction::STATUS_APPROVED,
                        'notes'            => $notes,
                        'created_by'       => $request->user()->id,
                    ]);
                } else {
                    // Update notes/date of the latest transaction to keep descriptions in sync
                    $latestTx = TreasuryTransaction::query()
                        ->where('source_type', TreasuryTransaction::SOURCE_EXPENSE)
                        ->where('source_id', $expense->id)
                        ->orderByDesc('id')
                        ->first();
                    if ($latestTx) {
                        $notes = 'تعديل مصروف: ' . $expense->expense_type;
                        if ($expense->service_provider_id && $expense->serviceProvider) {
                            $notes .= ' | مقدم الخدمة: ' . $expense->serviceProvider->name;
                        }
                        if ($expense->car_id && $expense->car) {
                            $notes .= ' | للسيارة: ' . ($expense->car->vin ?? $expense->car->id);
                        } elseif ($expense->order_id && $expense->order) {
                            $notes .= ' | للطلب رقم: ' . ($expense->order->order_number ?? $expense->order->id);
                        }
                        if ($expense->notes) {
                            $notes .= ' | ' . $expense->notes;
                        }
                        $latestTx->update([
                            'notes' => $notes,
                            'transaction_date' => $expense->expense_date ?? $latestTx->transaction_date,
                        ]);
                    }
                }
            } else {
                // If it didn't have a transaction (old record), create one now
                $prev = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
                $notes = 'تسجيل مصروف (تحديث): ' . $expense->expense_type;
                if ($expense->service_provider_id && $expense->serviceProvider) {
                    $notes .= ' | مقدم الخدمة: ' . $expense->serviceProvider->name;
                }
                if ($expense->car_id && $expense->car) {
                    $notes .= ' | للسيارة: ' . ($expense->car->vin ?? $expense->car->id);
                } elseif ($expense->order_id && $expense->order) {
                    $notes .= ' | للطلب رقم: ' . ($expense->order->order_number ?? $expense->order->id);
                }
                if ($expense->notes) {
                    $notes .= ' | ' . $expense->notes;
                }

                TreasuryTransaction::create([
                    'direction'        => TreasuryTransaction::DIRECTION_OUT,
                    'amount'           => $newAmount,
                    'previous_balence' => $prev,
                    'current_balence'  => $prev - $newAmount,
                    'source_type'      => TreasuryTransaction::SOURCE_EXPENSE,
                    'source_id'        => $expense->id,
                    'transaction_date' => $expense->expense_date ?? now()->toDateString(),
                    'status'           => TreasuryTransaction::STATUS_APPROVED,
                    'notes'            => $notes,
                    'created_by'       => $request->user()->id,
                ]);
            }
            return $expense;
        });

        return response()->json([
            'message' => 'تم تحديث المصروف بنجاح',
            'data' => new ExpenseResource($expense->load(['serviceProvider', 'creator'])),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        DB::transaction(function () use ($expense) {
            $hasTx = TreasuryTransaction::query()
                ->where('source_type', TreasuryTransaction::SOURCE_EXPENSE)
                ->where('source_id', $expense->id)
                ->exists();

            if ($hasTx) {
                $prev = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
                TreasuryTransaction::create([
                    'direction'        => TreasuryTransaction::DIRECTION_IN,
                    'amount'           => $expense->amount,
                    'previous_balence' => $prev,
                    'current_balence'  => $prev + (float) $expense->amount,
                    'source_type'      => TreasuryTransaction::SOURCE_EXPENSE,
                    'source_id'        => $expense->id,
                    'transaction_date' => now()->toDateString(), // Use current date for reversals as requested
                    'status'           => TreasuryTransaction::STATUS_APPROVED,
                    'notes'            => 'إلغاء مصروف محذوف رقم #' . $expense->id,
                    'created_by'       => Auth()->user->id ?? $expense->created_by,
                ]);
            }

            $expense->delete();
        });

        return response()->json(['message' => 'تم حذف المصروف بنجاح']);
    }
}
