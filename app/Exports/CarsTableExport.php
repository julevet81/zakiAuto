<?php

namespace App\Exports;

use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Flat, single-row-per-car export matching the exact column set requested:
 * brand, model, finition, manufacture_year, color, vin,
 * foreign_purchase_price, tracking_number, customer_full_name,
 * customer_passport_no, customer_national_id, shipping_price, arrival_date.
 *
 * This is a COST-TIER report end to end (it includes foreign_purchase_price
 * and shipping_price), so CarsTableController gates the whole thing behind
 * cars.view_cost (super-admin only) — there's no point building a
 * "redacted" variant of a report whose entire reason for existing is the
 * cost columns.
 *
 * Shares the same filterable query as CarsTableController::buildQuery() so
 * the JSON table view and the Excel export always reflect identical rows.
 */
class CarsTableExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    /**
     * @param  Builder<Car>  $query  Pre-built, pre-filtered query — see
     *                               CarsTableController::buildQuery().
     */
    public function __construct(protected Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'العلامة التجارية',
            'الموديل',
            'الفئة (Finition)',
            'سنة الصنع',
            'اللون',
            'رقم الهيكل (VIN)',
            'سعر الشراء (تكلفة)',
            'رقم التتبع',
            'اسم العميل الكامل',
            'رقم جواز السفر',
            'رقم البطاقة الوطنية',
            'المالك الأول',
            'جواز سفر المالك الأول',
            'الرقم الوطني للمالك الأول',
            'المالك الحالي',
            'جواز سفر المالك الحالي',
            'الرقم الوطني للمالك الحالي',
            'تكلفة الشحن',
            'تاريخ الوصول',
        ];
    }

    /**
     * @param  Car  $car
     * @return array<int, mixed>
     */
    public function map($car): array
    {
        $customer = $car->order?->customer;
        $firstCustomer = $car->firstOrder?->customer;
        $currentCustomer = $car->currentOrder?->customer;

        return [
            $car->brand,
            $car->model,
            $car->finition,
            $car->manufacture_year,
            $car->color,
            $car->vin,
            (float) $car->foreign_purchase_price,
            $car->tracking_number,
            $customer?->name,
            $customer?->passport_no,
            $customer?->national_id,
            $firstCustomer?->name,
            $firstCustomer?->passport_no,
            $firstCustomer?->national_id,
            $currentCustomer?->name,
            $currentCustomer?->passport_no,
            $currentCustomer?->national_id,
            (float) $car->shipping_cost,
            $car->arrival_date?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00', // foreign_purchase_price
            'R' => '#,##0.00', // shipping_price
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
