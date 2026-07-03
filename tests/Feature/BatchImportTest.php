<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\CarExpense;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\ContainerOpener;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BatchImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private ContainerOpener $containerOpener;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // Retrieve the user created by the seeder
        $this->user = User::where('email', 'superadmin@zaki.com')->first();

        // Create a supplier and container opener
        $this->supplier = Supplier::create([
            'name' => 'Tokyo Auto Export',
            'phone' => '+81300010001',
            'email' => 'sales@tokyo-auto.test',
            'address' => 'Tokyo, Japan',
        ]);

        $this->containerOpener = ContainerOpener::create([
            'name' => 'Port Clear DZ',
            'phone' => '0553003001',
            'email' => 'clearance@portclear.test',
            'address' => 'Algiers Port',
            'nif' => 'NIF-CL-001',
        ]);
    }

    /**
     * Test importing batch and cars successfully.
     */
    public function test_import_batch_and_cars_successfully(): void
    {
        Sanctum::actingAs($this->user);

        // Create spreadsheet with one valid row and one invalid row
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
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
            'تكلفة الشحن',
            'تاريخ الوصول',
        ];

        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
        }

        // Row 2: Valid car & customer
        $row2 = [
            'Toyota', // Brand
            'Camry', // Model
            'GLE', // Finition
            2023, // Year
            'Silver', // Color
            'VIN12345678901234', // VIN
            15000, // Purchase Price
            'TRACK123', // Tracking Number
            'Ahmad Zaki', // Customer Name
            'P123456', // Passport No
            'N123456', // National ID
            1500, // Shipping Cost
            '2026-07-03', // Arrival Date
        ];

        foreach ($row2 as $colIndex => $value) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 2, $value);
        }

        // Row 3: Invalid car (should be skipped but not fail the entire import)
        $row3 = [
            '', // Empty Brand (invalid)
            'Corolla',
            'Active',
            2022,
            'Red',
            'VINERR123456',
            12000,
            'TRACKERR',
            'Ahmad Bad',
            'P999999',
            'N999999',
            1000,
            '2026-07-03',
        ];

        foreach ($row3 as $colIndex => $value) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 3, $value);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $file = new UploadedFile(
            $tempFile,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/batches/import', [
            'supplier_id' => $this->supplier->id,
            'container_opener_id' => $this->containerOpener->id,
            'batch_number' => 'BATCH-TEST-IMPORT-1',
            'purchase_date' => '2026-07-03',
            'total_cost_foreign' => 15000,
            'notes' => 'Import test notes',
            'file' => $file,
        ]);

        unlink($tempFile);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'batch',
                'created',
                'skipped',
                'errors',
            ],
        ]);

        $this->assertEquals(1, $response->json('data.created'));
        $this->assertEquals(1, $response->json('data.skipped'));
        $this->assertCount(1, $response->json('data.errors'));
        $this->assertEquals(3, $response->json('data.errors.0.row')); // Row 3 had errors

        // Verify database records
        $this->assertDatabaseHas('batches', [
            'batch_number' => 'BATCH-TEST-IMPORT-1',
            'supplier_id' => $this->supplier->id,
            'cars_count' => 1,
        ]);

        $batch = Batch::where('batch_number', 'BATCH-TEST-IMPORT-1')->first();

        $this->assertDatabaseHas('cars', [
            'batch_id' => $batch->id,
            'supplier_id' => $this->supplier->id,
            'container_opener_id' => $this->containerOpener->id,
            'brand' => 'Toyota',
            'model' => 'Camry',
            'finition' => 'GLE',
            'manufacture_year' => 2023,
            'color' => 'Silver',
            'vin' => 'VIN12345678901234',
            'foreign_purchase_price' => 15000,
            'sale_price' => 15000,
            'tracking_number' => 'TRACK123',
            'status' => Car::STATUS_RESERVED,
        ]);

        $car = Car::where('vin', 'VIN12345678901234')->first();

        // Verify Shipping cost CarExpense was created
        $this->assertDatabaseHas('car_expenses', [
            'car_id' => $car->id,
            'expense_type' => 'شحن',
            'local_amount' => 1500.00,
        ]);

        // Verify Customer was created/matched
        $this->assertDatabaseHas('customers', [
            'name' => 'Ahmad Zaki',
            'passport_no' => 'P123456',
            'national_id' => 'N123456',
        ]);

        $customer = Customer::where('passport_no', 'P123456')->first();

        // Verify Order was created
        $order = Order::where('car_id', $car->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals(Order::STATUS_NEW, $order->status);
        $this->assertEquals('2026-07-03', $order->purchase_date->format('Y-m-d'));
        $this->assertEquals(15000.00, $order->remaining_amount);
        $this->assertEquals($this->user->id, $order->created_by);
    }
}
