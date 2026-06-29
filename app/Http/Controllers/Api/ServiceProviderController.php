<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceProvider\StoreServiceProviderRequest;
use App\Http\Requests\ServiceProvider\UpdateServiceProviderRequest;
use App\Http\Resources\ServiceProviderResource;
use App\Models\ServiceProviderModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceProviderModel::class);

        $providers = ServiceProviderModel::query()
            ->withCount('expenses')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where('name', 'like', "%{$term}%");
            })
            ->when($request->filled('provider_type'), function ($q) use ($request) {
                $q->where('provider_type', $request->string('provider_type'));
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(ServiceProviderResource::collection($providers)->response()->getData(true));
    }

    public function store(StoreServiceProviderRequest $request): JsonResponse
    {
        $provider = ServiceProviderModel::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة مزود الخدمة بنجاح',
            'data' => new ServiceProviderResource($provider),
        ], 201);
    }

    public function show(ServiceProviderModel $serviceProvider): JsonResponse
    {
        $this->authorize('view', $serviceProvider);

        return response()->json([
            'data' => new ServiceProviderResource($serviceProvider->loadCount('expenses')),
        ]);
    }

    public function update(UpdateServiceProviderRequest $request, ServiceProviderModel $serviceProvider): JsonResponse
    {
        $serviceProvider->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => new ServiceProviderResource($serviceProvider),
        ]);
    }

    public function destroy(ServiceProviderModel $serviceProvider): JsonResponse
    {
        $this->authorize('delete', $serviceProvider);

        if ($serviceProvider->expenses()->exists()) {
            return response()->json([
                'message' => 'لا يمكن الحذف لوجود مصاريف مرتبطة بهذا المزود',
            ], 422);
        }

        $serviceProvider->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
