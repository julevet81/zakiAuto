<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Car\StoreCarExpenseRequest;
use App\Http\Requests\Car\StoreCarRequest;
use App\Http\Requests\Car\UpdateCarRequest;
use App\Http\Resources\CarExpenseResource;
use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarController extends Controller
{
    /**
     * List cars with filters relevant to both staff (full data) and
     * customers/agents (sale-price-only browsing).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Car::class);

        $canSeeCosts = $request->user()->can('suppliers.view');

        $query = Car::query()
            ->when($canSeeCosts, fn ($q) => $q->with(['supplier', 'containerOpener']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', 'like', '%'.$request->string('brand').'%'))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('supplier_id') && $canSeeCosts, fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('vin'), fn ($q) => $q->where('vin', $request->string('vin')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('brand', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('vin', 'like', "%{$term}%");
                });
            })
            // Customers browsing the catalogue should only see cars not
            // already sold/reserved to someone else.
            ->when(! $canSeeCosts, fn ($q) => $q->whereNotIn('status', [Car::STATUS_SOLD]))
            ->orderByDesc('id');

        $cars = $query->paginate($request->integer('per_page', 15));

        return response()->json(CarResource::collection($cars)->response()->getData(true));
    }

    public function store(StoreCarRequest $request): JsonResponse
    {
        $car = Car::create($request->validated() + ['status' => $request->input('status', Car::STATUS_AVAILABLE)]);

        return response()->json([
            'message' => 'تم إضافة السيارة بنجاح',
            'data' => new CarResource($car->load(['supplier', 'containerOpener'])),
        ], 201);
    }

    public function show(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        $canSeeCosts = $request->user()->can('suppliers.view');

        $car->load(['documents']);
        if ($canSeeCosts) {
            $car->load(['supplier', 'containerOpener', 'expenses', 'generalExpenses', 'order']);
        }

        return response()->json([
            'data' => new CarResource($car),
        ]);
    }

    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $car->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات السيارة بنجاح',
            'data' => new CarResource($car->load(['supplier', 'containerOpener'])),
        ]);
    }

    public function destroy(Car $car): JsonResponse
    {
        $this->authorize('delete', $car);

        if ($car->order()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف السيارة لوجود طلب مرتبط بها',
            ], 422);
        }

        $car->delete();

        return response()->json(['message' => 'تم حذف السيارة بنجاح']);
    }

    /**
     * Add a cost line (customs, transport, repair...) to a car.
     */
    public function storeExpense(StoreCarExpenseRequest $request, Car $car): JsonResponse
    {
        $expense = $car->expenses()->create($request->validated());

        return response()->json([
            'message' => 'تم إضافة المصروف بنجاح',
            'data' => new CarExpenseResource($expense),
        ], 201);
    }

    /**
     * List cost lines for a car (separate endpoint, in case the listing
     * needs its own pagination/filtering independent of the car payload).
     */
    public function expenses(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        if (! $request->user()->can('suppliers.view')) {
            return response()->json(['message' => 'لا تملك الصلاحية اللازمة'], 403);
        }

        return response()->json([
            'data' => CarExpenseResource::collection($car->expenses()->orderByDesc('id')->get()),
        ]);
    }
}
