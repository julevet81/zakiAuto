<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $expense = Expense::create($request->validated() + ['created_by' => $request->user()->id]);

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
        $expense->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث المصروف بنجاح',
            'data' => new ExpenseResource($expense->load(['serviceProvider', 'creator'])),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json(['message' => 'تم حذف المصروف بنجاح']);
    }
}
