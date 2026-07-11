<?php

namespace App\Http\Controllers\Api;

use App\Exports\CarsTableExport;
use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CarsTableController extends Controller
{
    /**
     * This entire report is cost-tier data end to end — it always includes
     * foreign_purchase_price and shipping_price (both cost figures) — so,
     * unlike the general /cars endpoint (which redacts cost columns per
     * audience), there is no reduced/redacted version of this report.
     * It's gated wholesale behind cars.view_cost (super-admin only, per
     * the explicit "Admin: كل الصلاحيات ما عدا أسعار الشراء والشحن"
     * requirement).
     */
    protected function authorizeTableAccess(Request $request): void
    {
        if (! $request->user()->can('cars.view_cost')) {
            abort(403, 'لا تملك الصلاحية اللازمة لعرض هذا التقرير');
        }
    }

    /**
     * JSON view of the flat cars table — the exact column set requested:
     * brand, model, finition, manufacture_year, color, vin,
     * foreign_purchase_price, tracking_number, customer full
     * name/passport/national id, shipping_price, arrival_date.
     *
     * One row per car. shipping_price is pre-aggregated via withSum()
     * rather than relying on Car::getShippingPriceAttribute() here, to
     * avoid an N+1 query per row across a potentially large table.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeTableAccess($request);

        $cars = $this->buildQuery($request)
            ->paginate($request->integer('per_page', 50));

        $rows = collect($cars->items())->map(fn (Car $car) => $this->toRow($car));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $cars->currentPage(),
                'last_page' => $cars->lastPage(),
                'per_page' => $cars->perPage(),
                'total' => $cars->total(),
            ],
        ]);
    }

    /**
     * Export the same filtered table to an .xlsx file.
     *
     * Reuses buildQuery() (unpaginated) so the exported rows always match
     * exactly what the JSON view would show for the same filters, minus
     * pagination — the export is intentionally NOT paginated, since the
     * entire point of an export is to get every matching row in one file.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeTableAccess($request);

        $query = $this->buildQuery($request);

        $filename = 'cars-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new CarsTableExport($query), $filename);
    }

    /**
     * Shared, filterable base query for both the JSON view and the Excel
     * export. Eager-loads exactly what's needed to build a row (order +
     * customer for the customer columns, shipping expense sum), and
     * nothing else — this query is never used to build a full CarResource.
     *
     * @return Builder<Car>
     */
    protected function buildQuery(Request $request): Builder
    {
        return Car::query()
            ->with(['order.customer', 'firstOrder.customer', 'currentOrder.customer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', 'like', '%'.$request->string('brand').'%'))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('vin'), fn ($q) => $q->where('vin', $request->string('vin')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('brand', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('vin', 'like', "%{$term}%")
                        ->orWhere('tracking_number', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('arrival_date_from'), fn ($q) => $q->whereDate('arrival_date', '>=', $request->date('arrival_date_from')))
            ->when($request->filled('arrival_date_to'), fn ($q) => $q->whereDate('arrival_date', '<=', $request->date('arrival_date_to')))
            ->orderByDesc('id');
    }

    /**
     * Map a single Car (with its eager-loaded relations/sums) to the flat
     * row shape used by both the JSON view and CarsTableExport::map() —
     * kept in sync deliberately: CarsTableExport re-implements the same
     * mapping against a single Car since Maatwebsite's FromQuery streams
     * rows directly from the query rather than going through this method
     * (see the class doc on CarsTableExport for why they're not shared
     * via a trait: the column ORDER must stay hardcoded and explicit in
     * both places to avoid silently reordering the Excel file).
     *
     * @return array<string, mixed>
     */
    protected function toRow(Car $car): array
    {
        $customer = $car->order?->customer;
        $firstCustomer = $car->firstOrder?->customer;
        $currentCustomer = $car->currentOrder?->customer;

        return [
            'id' => $car->id,
            'brand' => $car->brand,
            'model' => $car->model,
            'finition' => $car->finition,
            'manufacture_year' => $car->manufacture_year,
            'color' => $car->color,
            'vin' => $car->vin,
            'foreign_purchase_price' => (float) $car->foreign_purchase_price,
            'tracking_number' => $car->tracking_number,
            'customer_full_name' => $customer?->name,
            'customer_passport_no' => $customer?->passport_no,
            'customer_national_id' => $customer?->national_id,
            'first_owner_name' => $firstCustomer?->name,
            'first_owner_passport_no' => $firstCustomer?->passport_no,
            'first_owner_national_id' => $firstCustomer?->national_id,
            'current_owner_name' => $currentCustomer?->name,
            'current_owner_passport_no' => $currentCustomer?->passport_no,
            'current_owner_national_id' => $currentCustomer?->national_id,
            'shipping_price' => (float) $car->shipping_cost,
            'arrival_date' => $car->arrival_date?->format('Y-m-d'),
        ];
    }
}
