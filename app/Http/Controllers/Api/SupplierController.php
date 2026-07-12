<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * List suppliers with optional search and relation counts.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->withCount(['batches', 'cars'])
            ->with('batches') // SupplierResource computes total_remaining from this relation when loaded
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id');

        $suppliers = $query->paginate($request->integer('per_page', 15));

        return response()->json(SupplierResource::collection($suppliers)->response()->getData(true));
    }

    /**
     * Create a new supplier.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة المورد بنجاح',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    /**
     * Show a single supplier with its batches, cars, and payments.
     */
    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $supplier->load(['batches', 'cars', 'payments']);

        return response()->json([
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Update an existing supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات المورد بنجاح',
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Delete a supplier.
     *
     * Blocked if the supplier has any linked batches or cars, to protect
     * historical financial/operational records from being orphaned.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->batches()->exists() || $supplier->cars()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف المورد لوجود دفعات أو سيارات مرتبطة به',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'تم حذف المورد بنجاح',
        ]);
    }
}
