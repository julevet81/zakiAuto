<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_and_get_full_url_for_customer_document(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Fake public storage disk
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($user);

        // Create a customer
        $customer = Customer::create([
            'name' => 'John Doe',
            'phone' => '0500000000',
            'email' => 'john@example.com',
        ]);

        // Create a dummy file to upload
        $file = UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/customers/{$customer->id}/documents", [
            'files' => [$file],
            'titles' => ['Passport Copy'],
        ]);

        $response->assertCreated();

        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id',
                    'customer_id',
                    'title',
                    'file_type',
                    'file_size',
                    'url',
                    'uploaded_by',
                    'created_at',
                ]
            ]
        ]);

        // Get the url from the response
        $uploadedDoc = $response->json('data.0');
        $this->assertEquals('Passport Copy', $uploadedDoc['title']);
        
        // Assert url is a full URL and matches public storage URL
        $this->assertStringStartsWith('http', $uploadedDoc['url']);
        $this->assertStringContainsString('/storage/customer-documents/', $uploadedDoc['url']);

        // Let's assert that the file is indeed stored in the public disk fake
        $document = $customer->customerDocuments()->first();
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_can_upload_single_file_for_customer_document(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Fake public storage disk
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($user);

        // Create a customer
        $customer = Customer::create([
            'name' => 'Jane Doe',
            'phone' => '0500000001',
            'email' => 'jane@example.com',
        ]);

        // Create a dummy file to upload
        $file = UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf');

        // Sending files as a single file, not an array
        $response = $this->postJson("/api/customers/{$customer->id}/documents", [
            'files' => $file,
            'titles' => ['Passport Copy'],
        ]);

        $response->assertCreated();

        $uploadedDoc = $response->json('data.0');
        $this->assertEquals('Passport Copy', $uploadedDoc['title']);
        $this->assertStringStartsWith('http', $uploadedDoc['url']);

        $document = $customer->customerDocuments()->first();
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_customer_document_url_uses_the_current_request_host(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        config(['app.url' => 'http://localhost']);
        Storage::fake('public');

        $user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($user);

        $customer = Customer::create([
            'name' => 'Host Aware Customer',
            'phone' => '0500000002',
            'email' => 'host-aware@example.com',
        ]);

        $file = UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf');

        $response = $this->postJson("http://api.zakiauto.test/api/customers/{$customer->id}/documents", [
            'files' => [$file],
            'titles' => ['Passport Copy'],
        ]);

        $response->assertCreated();
        $this->assertStringStartsWith(
            'http://api.zakiauto.test/storage/customer-documents/',
            $response->json('data.0.url')
        );
    }

    public function test_fallback_storage_route_serves_stored_file(): void
    {
        Storage::fake('public');

        // Save a dummy file in public disk
        Storage::disk('public')->put('customer-documents/test.txt', 'Hello World');

        $response = $this->get('/storage/customer-documents/test.txt');

        $response->assertOk();
        $this->assertEquals('Hello World', $response->streamedContent());
    }
}
