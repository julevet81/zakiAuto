<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Raw reader for the "batch cars" import sheet.
 *
 * We deliberately do NOT use WithHeadingRow: the sheet's headers are Arabic
 * free text (e.g. "رقم الهيكل (VIN)") which does not slugify reliably into
 * ASCII array keys. Instead every row is read as a plain indexed collection
 * and mapped by POSITION in BatchCarsImportService, per this fixed column
 * order:
 *
 *   0  العلامة التجارية      -> brand
 *   1  الموديل               -> model
 *   2  الفئة (Finition)      -> finition
 *   3  سنة الصنع             -> manufacture_year
 *   4  اللون                 -> color
 *   5  رقم الهيكل (VIN)      -> vin
 *   6  سعر الشراء (تكلفة)    -> foreign_purchase_price
 *   7  رقم التتبع            -> tracking_number
 *   8  اسم العميل الكامل     -> customer_name
 *   9  رقم جواز السفر        -> passport_no
 *   10 رقم البطاقة الوطنية   -> national_id
 *   11 تكلفة الشحن           -> shipping_cost
 *   12 تاريخ الوصول          -> arrival_date
 *
 * Row 1 is the header row and is skipped via WithStartRow(2).
 *
 * Requires the maatwebsite/excel package:
 *   composer require maatwebsite/excel
 */
class BatchCarsImport implements ToCollection, WithStartRow
{
    /**
     * @var Collection<int, Collection<int, mixed>>
     */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        // Drop fully-empty trailing rows (common artifact of exported
        // sheets that have extra blank rows at the bottom).
        $this->rows = $rows
            ->filter(fn(Collection $row) => $row->filter(fn($cell) => $cell !== null && $cell !== '')->isNotEmpty())
            ->values();
    }
}
