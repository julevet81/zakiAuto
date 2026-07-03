<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\StoreBatchRequest;
use App\Http\Requests\Batch\UpdateBatchRequest;
use App\Http\Requests\Batch\ImportBatchCarsRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Services\BatchCarsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Batch::class);

        $batches = Batch::query()
            ->with('supplier')
            ->withCount('cars')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('batch_number', 'like', '%'.$request->string('search').'%');
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(BatchResource::collection($batches)->response()->getData(true));
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $batch = Batch::create(
            $request->validated()
            + ['status' => $request->input('status', Batch::STATUS_PENDING)]
        );

        // exchange_rate stays NULL at creation — no payments exist yet.
        // It will be computed the first time a supplier_payment is saved.

        return response()->json([
            'message' => 'تم إنشاء دفعة الاستيراد بنجاح',
            'data' => new BatchResource($batch->load('supplier')),
        ], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        $this->authorize('view', $batch);

        $batch->load(['supplier', 'cars', 'payments']);

        return response()->json([
            'data' => new BatchResource($batch),
        ]);
    }

    public function update(UpdateBatchRequest $request, Batch $batch): JsonResponse
    {
        $batch->update($request->validated());

        // If total_cost_foreign was changed, the exchange_rate formula's
        // denominator has changed — recompute immediately so the stored
        // value stays consistent with the new target cost, even if no
        // new payment was recorded in this request.
        if ($request->has('total_cost_foreign')) {
            $batch->recomputeExchangeRate(save: true);
        }

        return response()->json([
            'message' => 'تم تحديث دفعة الاستيراد بنجاح',
            'data' => new BatchResource($batch->fresh(['supplier'])),
        ]);
    }

    public function destroy(Batch $batch): JsonResponse
    {
        $this->authorize('delete', $batch);

        if ($batch->cars()->exists() || $batch->payments()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الدفعة لوجود سيارات أو دفعات مالية مرتبطة بها',
            ], 422);
        }

        $batch->delete();

        return response()->json(['message' => 'تم حذف الدفعة بنجاح']);
    }

    public function import(ImportBatchCarsRequest $request, BatchCarsImportService $importService): JsonResponse
    {
        $result = $importService->import(
            $request->validated(),
            $request->file('file'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'تم استيراد دفعة الاستيراد والسيارات بنجاح',
            'data' => [
                'batch' => new BatchResource($result['batch']),
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ],
        ], 201);
    }
}
