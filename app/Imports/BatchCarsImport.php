<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Entry point handed to Excel::import().
 *
 * IMPORTANT: the uploaded workbook can contain more than one sheet (the
 * shipped template has a "Batch Import" sheet with the car data AND a
 * "Notes" sheet with free-text instructions). If this class only
 * implemented ToCollection, Laravel Excel would invoke collection() once
 * PER SHEET, and each call OVERWRITES $rows instead of appending to it —
 * so whichever sheet is processed last silently wins, and the real car
 * rows get replaced by garbage (or vice-versa depending on sheet order).
 *
 * WithMultipleSheets lets us pin the import to sheet index 0 ("Batch
 * Import") explicitly. Any other sheet in the workbook (Notes, etc.) is
 * simply never touched.
 */
class BatchCarsImport implements WithMultipleSheets
{
    public BatchCarsRowsImport $rowsImport;

    public function __construct()
    {
        $this->rowsImport = new BatchCarsRowsImport();
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            0 => $this->rowsImport,
        ];
    }

    /**
     * Convenience passthrough so callers can keep using $import->rows.
     *
     * @return Collection<int, Collection<int, mixed>>
     */
    public function getRows(): Collection
    {
        return $this->rowsImport->rows;
    }
}

/**
 * Raw reader for the actual "Batch Import" sheet.
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
class BatchCarsRowsImport implements ToCollection, WithStartRow
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
