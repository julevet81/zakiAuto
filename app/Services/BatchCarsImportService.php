<?php

namespace App\Services;

use App\Exceptions\BatchCarsImportFailedException;
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
     * Create the Batch and import every car row from the uploaded file.
     * The whole import is atomic: if any car row fails, all records created
     * by this import are rolled back.
     *
     * @param  array{supplier_id:int, container_opener_id:?int, purchase_date:?string, total_cost_foreign:?float, notes:?string}  $data
     * @return array{batch: Batch, created: int, skipped: int, errors: array<int, array{row:int|null, errors: array<int,string>}>}
     */
    public function import(array $data, $file, int $createdBy): array
    {
        $import = new BatchCarsImport();
        Excel::import($import, $file);

        $containerOpenerId = $data['container_opener_id'] ?? null;
        $supplierId = $data['supplier_id'];

        return DB::transaction(function () use ($data, $import, $containerOpenerId, $supplierId, $createdBy) {
            $batch = Batch::create([
                'supplier_id' => $supplierId,
                'purchase_date' => $data['purchase_date'] ?? null,
                'total_cost_foreign' => $data['total_cost_foreign'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'status' => Batch::STATUS_PARTIAL,
            ]);

            $created = 0;
            $errors = [];

            if ($import->getRows()->isEmpty()) {
                throw new BatchCarsImportFailedException([
                    [
                        'row' => null,
                        'errors' => ['ملف الإكسل لا يحتوي على أي سيارات للاستيراد'],
                    ],
                ]);
            }

            foreach ($import->getRows() as $index => $row) {
                // WithStartRow(2) skips the header, so this maps back to Excel row numbers.
                $rowNumber = $index + 2;

                try {
                    $this->importRow($row, $batch, $containerOpenerId, $supplierId, $createdBy);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => [$e->getMessage()],
                    ];
                }
            }

            if ($errors !== [] || $created === 0) {
                if ($created === 0 && $errors === []) {
                    $errors[] = [
                        'row' => null,
                        'errors' => ['لم يتم إنشاء أي سيارة من ملف الإكسل'],
                    ];
                }

                throw new BatchCarsImportFailedException($errors);
            }

            $batch->update(['cars_count' => $batch->cars()->count()]);

            return [
                'batch' => $batch->fresh(['supplier']),
                'created' => $created,
                'skipped' => 0,
                'errors' => [],
            ];
        });
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
        $passportNo = $row->get(self::COL_PASSPORT_NO);
        $nationalId = $row->get(self::COL_NATIONAL_ID);
        $shippingCost = $row->get(self::COL_SHIPPING_COST);
        $arrivalDateRaw = $row->get(self::COL_ARRIVAL_DATE);

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
        if ($passportNo === '' ) {
            throw new \RuntimeException('يجب إدخال رقم جواز السفر للعميل');
        }
        if ($nationalId === '' ) {
            throw new \RuntimeException('يجب إدخال رقم الهوية الوطنية للعميل');
        }

        $arrivalDate = $this->parseDate($arrivalDateRaw);

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
            'sale_price' => (float) $purchasePrice,
            'tracking_number' => $trackingNumber,
            'arrival_date' => $arrivalDate,
            'status' => Car::STATUS_SHIPPING,
        ]);

        if (is_numeric($shippingCost) && (float) $shippingCost > 0) {
            CarExpense::create([
                'car_id' => $car->id,
                'expense_type' => 'شحن',
                'foreign_amount' => 0,
                'local_amount' => (float) $shippingCost,
            ]);
        }

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

        Order::create([
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
            'purchase_date' => $batch->purchase_date,
            'shipping_date' => now()->toDateString(),
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
