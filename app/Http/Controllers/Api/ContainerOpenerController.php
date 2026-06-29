<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContainerOpener\StoreContainerOpenerRequest;
use App\Http\Requests\ContainerOpener\UpdateContainerOpenerRequest;
use App\Http\Resources\ContainerOpenerResource;
use App\Models\ContainerOpener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerOpenerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContainerOpener::class);

        $openers = ContainerOpener::query()
            ->withCount('cars')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where('name', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(ContainerOpenerResource::collection($openers)->response()->getData(true));
    }

    public function store(StoreContainerOpenerRequest $request): JsonResponse
    {
        $opener = ContainerOpener::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة جهة فتح الحاويات بنجاح',
            'data' => new ContainerOpenerResource($opener),
        ], 201);
    }

    public function show(ContainerOpener $containerOpener): JsonResponse
    {
        $this->authorize('view', $containerOpener);

        return response()->json([
            'data' => new ContainerOpenerResource($containerOpener->loadCount('cars')),
        ]);
    }

    public function update(UpdateContainerOpenerRequest $request, ContainerOpener $containerOpener): JsonResponse
    {
        $containerOpener->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => new ContainerOpenerResource($containerOpener),
        ]);
    }

    public function destroy(ContainerOpener $containerOpener): JsonResponse
    {
        $this->authorize('delete', $containerOpener);

        if ($containerOpener->cars()->exists()) {
            return response()->json([
                'message' => 'لا يمكن الحذف لوجود سيارات مرتبطة بهذه الجهة',
            ], 422);
        }

        $containerOpener->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
