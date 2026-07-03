<?php

namespace App\Services;

use App\Imports\BatchCarsImport;
use App\Models\Batch;
use App\Models\Car;
use App\Models\CarExpense;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BatchCarsImportService
{
    // Column positions — see BatchCarsImport for the full documented map.
    private const COL_BRAND = 0;
    private const COL_MODEL = 1;
    private const COL_FINITION = 2;
    private const COL_YEAR = 3;
    private const COL_COLOR = 4;
    private const COL_VIN = 5;
    private const COL_PURCHASE_PRICE = 6;
    private const COL_TRACKING_NUMBER = 7;
    private const COL_CUSTOMER_NAME = 8;
    private const COL_PASSPORT_NO = 9;
    private const COL_NATIONAL_ID = 10;
    private const COL_SHIPPING_COST = 11;
    private const COL_ARRIVAL_DATE = 12;

    /**
     * Create the Batch, then import every car row from the uploaded file.
     * Each row runs in its own DB transaction: a bad row (duplicate VIN,
     * missing required field, ...) is skipped and reported, the rest of
     * the file still imports.
     *
     * @param  array{supplier_id:int, container_opener_id:?int, batch_number:string, purchase_date:?string, total_cost_foreign:?float, notes:?string}  $data
     * @return array{batch: Batch, created: int, skipped: int, errors: array<int, array{row:int, errors: array<int,string>}>}
     */
    public function import(array $data, $file, int $createdBy): array
    {
        $import = new BatchCarsImport();
        Excel::import($import, $file);

        $containerOpenerId = $data['container_opener_id'] ?? null;
        $supplierId = $data['supplier_id'];

        // The batch itself is created up front from the manually-entered
        // fields and kept even if every row below fails — an empty batch
        // the staff can fix up is safer than silently discarding the
        // supplier/batch_number/purchase_date they already filled in.
        $batch = Batch::create([
            'supplier_id' => $supplierId,
            'batch_number' => $data['batch_number'],
            'purchase_date' => $data['purchase_date'] ?? null,
            'total_cost_foreign' => $data['total_cost_foreign'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'status' => Batch::STATUS_PENDING,
        ]);

        $created = 0;
        $errors = [];

        foreach ($import->rows as $index => $row) {
            // +1 for the header row skipped by WithStartRow(2), +1 more
            // because $index is 0-based — gives the real Excel row number.
            $rowNumber = $index + 2;

            try {
                DB::transaction(function () use ($row, $batch, $containerOpenerId, $supplierId, $createdBy) {
                    $this->importRow($row, $batch, $containerOpenerId, $supplierId, $createdBy);
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        $batch->update(['cars_count' => $batch->cars()->count()]);

        return [
            'batch' => $batch->fresh(['supplier']),
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function importRow(Collection $row, Batch $batch, ?int $containerOpenerId, int $supplierId, int $createdBy): void
    {
        $brand = trim((string) $row->get(self::COL_BRAND));
        $model = trim((string) $row->get(self::COL_MODEL));
        $finition = $this->nullableString($row->get(self::COL_FINITION));
        $year = $row->get(self::COL_YEAR);
        $color = $this->nullableString($row->get(self::COL_COLOR));
        $vin = $this->nullableString($row->get(self::COL_VIN));
        $purchasePrice = $row->get(self::COL_PURCHASE_PRICE);
        $trackingNumber = $this->nullableString($row->get(self::COL_TRACKING_NUMBER));
        $customerName = trim((string) $row->get(self::COL_CUSTOMER_NAME));
        $passportNo = $this->nullableString($row->get(self::COL_PASSPORT_NO));
        $nationalId = $this->nullableString($row->get(self::COL_NATIONAL_ID));
        $shippingCost = $row->get(self::COL_SHIPPING_COST);
        $arrivalDateRaw = $row->get(self::COL_ARRIVAL_DATE);

        // --- Per-row validation. Throwing here rolls back only this row's
        // transaction; the import loop catches it and moves to the next
        // row (per the "skip bad rows, keep the rest" behaviour chosen). ---
        if ($brand === '' || $model === '') {
            throw new \RuntimeException('العلامة التجارية والموديل حقلان إلزاميان');
        }
        if (! is_numeric($year)) {
            throw new \RuntimeException('سنة الصنع غير صالحة');
        }
        if (! is_numeric($purchasePrice) || (float) $purchasePrice < 0) {
            throw new \RuntimeException('سعر الشراء غير صالح');
        }
        if ($customerName === '') {
            throw new \RuntimeException('اسم العميل حقل إلزامي لإنشاء الطلب');
        }
        if ($vin !== null && Car::where('vin', $vin)->exists()) {
            throw new \RuntimeException("رقم الهيكل (VIN) مكرر: {$vin}");
        }

        $arrivalDate = $this->parseDate($arrivalDateRaw);

        // --- Car ---
        // NOTE / ASSUMPTION: the sheet has no "sale price" column. Since
        // each car is sold to a named customer at import time, sale_price
        // defaults here to foreign_purchase_price (0 margin) so the row is
        // valid immediately. If you apply a standard markup, replace this
        // with e.g. round($purchasePrice * 1.10, 2), or add a sale-price
        // column to the sheet and read it like the other columns above.
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplierId,
            'container_opener_id' => $containerOpenerId,
            'brand' => $brand,
            'model' => $model,
            'finition' => $finition,
            'manufacture_year' => (int) $year,
            'color' => $color,
            'vin' => $vin,
            'foreign_purchase_price' => (float) $purchasePrice,
            'sale_price' => (float) $purchasePrice, // see NOTE above
            'tracking_number' => $trackingNumber,
            'arrival_date' => $arrivalDate,
            'status' => Car::STATUS_RESERVED, // reserved for the customer created below
        ]);

        // --- Shipping cost line ---
        if (is_numeric($shippingCost) && (float) $shippingCost > 0) {
            // ASSUMPTION: "تكلفة الشحن" in the sheet is already in local
            // currency (paid domestically), so foreign_amount is 0. If it
            // should instead be a foreign-currency cost, swap the two
            // amounts / apply the batch exchange rate here.
            CarExpense::create([
                'car_id' => $car->id,
                'expense_type' => 'شحن',
                'foreign_amount' => 0,
                'local_amount' => (float) $shippingCost,
            ]);
        }

        // --- Customer: match existing by national_id or passport_no,
        // otherwise create a new customer record. ---
        $customer = null;
        if ($nationalId !== null) {
            $customer = Customer::where('national_id', $nationalId)->first();
        }
        if (! $customer && $passportNo !== null) {
            $customer = Customer::where('passport_no', $passportNo)->first();
        }
        if (! $customer) {
            $customer = Customer::create([
                'name' => $customerName,
                'national_id' => $nationalId,
                'passport_no' => $passportNo,
            ]);
        }

        // --- Order ---
        Order::create([
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_NEW,
            'purchase_date' => $batch->purchase_date,
            'arrival_date' => $arrivalDate,
            'paid_amount' => 0,
            'remaining_amount' => $car->sale_price,
            'created_by' => $createdBy,
        ]);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // Excel stores dates as numeric serials in some export paths;
            // handle both that and plain text dates.
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . now()->format('ym') . '-' . strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
