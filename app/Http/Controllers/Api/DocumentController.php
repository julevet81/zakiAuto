<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Car;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    /**
     * Documents are always accessed through their parent car — there is
     * no standalone "all documents" listing, and no DocumentPolicy of its
     * own. Visibility/authorship is entirely delegated to CarPolicy:
     * anyone who can view the car can see its documents, anyone who can
     * update the car can attach/remove documents.
     */
    public function index(Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        return response()->json([
            'data' => DocumentResource::collection($car->documents()->latest()->get()),
        ]);
    }

    public function store(StoreDocumentRequest $request, Car $car): JsonResponse
    {
        $document = $car->documents()->create($request->validated() + ['created_at' => now()]);

        return response()->json([
            'message' => 'تم إضافة الوثيقة بنجاح',
            'data' => new DocumentResource($document),
        ], 201);
    }

    public function destroy(Car $car, Document $document): JsonResponse
    {
        $this->authorize('update', $car);

        if ($document->car_id !== $car->id) {
            return response()->json(['message' => 'الوثيقة لا تعود لهذه السيارة'], 404);
        }

        $document->delete();

        return response()->json(['message' => 'تم حذف الوثيقة بنجاح']);
    }
}
