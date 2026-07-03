<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerDocumentResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Notifications\CustomerAccountCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * List customers, scoped by who's asking:
     *   - admin/super-admin (customers.view): every customer.
     *   - agent (customers.view_assigned only): only their own customers.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $user = $request->user();

        $query = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('national_id', 'like', "%{$term}%");
                });
            });

        if ($user->can('customers.view')) {
            $query->when($request->filled('agent_id'), fn($q) => $q->where('agent_id', $request->integer('agent_id')));
        } elseif ($user->can('customers.view_assigned')) {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $customers = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    /**
     * Create a customer account — full automated flow:
     *
     *  1. Generate a random secure password.
     *  2. Create a User row (email + hashed password + role = customer).
     *  3. Create the Customer row linked to that User.
     *  4. Assign the agent (auto-link if the creator is an agent).
     *  5. Notify the customer via email (and optionally SMS/WhatsApp —
     *     see CustomerAccountCreated) with their login credentials.
     *
     * Steps 1–4 run inside a single DB transaction so a notification
     * failure never leaves a half-created record behind.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $plainPassword = Str::password(12); // e.g. "aB3$xQ9mLp2!"

        $customer = DB::transaction(function () use ($request, $plainPassword) {
            // 1. Create the User account.
            $user = User::create([
                'name'               => $request->validated('name'),
                'email'              => $request->validated('email'),
                'phone'              => $request->validated('phone'),
                'password'           => Hash::make($plainPassword),
                'is_active'          => true,
                'email_verified_at'  => now(), // staff-created accounts are pre-verified
            ]);

            $user->assignRole('customer');

            // 2. Determine agent_id.
            $agentId = null;
            if ($request->user()->can('customers.view')) {
                // Admin/super-admin: use explicitly provided agent_id (may be null).
                $agentId = $request->validated('agent_id');
            } elseif ($request->user()->agent) {
                // Agent creating a customer: auto-link to themselves.
                $agentId = $request->user()->agent->id;
            }

            // 3. Create the Customer profile, linked to the new User.
            $customer = Customer::create([
                'user_id'     => $user->id,
                'agent_id'    => $agentId,
                'name'        => $request->validated('name'),
                'email'       => $request->validated('email'),
                'phone'       => $request->validated('phone'),
                'national_id' => $request->validated('national_id'),
                'passport_no' => $request->validated('passport_no'),
                'address'     => $request->validated('address'),
            ]);

            return $customer;
        });

        // 4. Send credentials notification OUTSIDE the transaction so a
        //    mail/queue failure doesn't roll back the created records.
        //    The notification is queued (implements ShouldQueue), so this
        //    line returns immediately even if the mail server is slow.
        try {
            $customer->user->notify(new CustomerAccountCreated($plainPassword));
        } catch (\Throwable $e) {
            // Log but don't fail the request — the account IS created.
            // Staff can trigger a password reset manually if needed.
            logger()->error('Failed to send customer account notification', [
                'customer_id' => $customer->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'تم إنشاء حساب العميل وإرسال بيانات الدخول بنجاح',
            'data'    => new CustomerResource($customer->load(['agent', 'user'])),
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->load(['agent', 'orders.car', 'customerDocuments']);

        if ($request->user()->can('customer_payments.view') || $request->user()->can('customer_payments.view_own')) {
            $customer->load('payments');
        }

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        // Keep the linked User's name/phone in sync with the customer profile.
        if ($customer->user) {
            $customer->user->update(array_filter([
                'name'  => $request->validated('name'),
                'phone' => $request->validated('phone'),
            ]));
        }

        return response()->json([
            'message' => 'تم تحديث بيانات العميل بنجاح',
            'data'    => new CustomerResource($customer->load('agent')),
        ]);
    }

    /**
     * Delete a customer. Blocked if they have any orders.
     * Also soft-deletes the linked User account so they can no longer log in.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف العميل لوجود طلبات مرتبطة به',
            ], 422);
        }

        DB::transaction(function () use ($customer) {
            $customer->user?->delete(); // soft-delete the User account too
            $customer->delete();
        });

        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }

    // ------------------------------------------------------------------
    // Customer Documents (profile files)
    // ------------------------------------------------------------------

    /**
     * List all documents attached to a customer's profile.
     */
    public function documents(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json([
            'data' => CustomerDocumentResource::collection(
                $customer->customerDocuments()->latest()->get()
            ),
        ]);
    }

    /**
     * Upload one or more documents to a customer's profile.
     *
     * Accepts multipart/form-data with field `files[]` (multiple files).
     * Each file is stored under `customer-documents/{customer_id}/`
     * on the configured default Storage disk (usually `local` in dev,
     * `s3` in production — set FILESYSTEM_DISK in .env).
     */
    public function uploadDocuments(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $request->validate([
            'files'          => ['required', 'array', 'min:1', 'max:10'],
            'files.*'        => ['required', 'file', 'max:10240'], // 10 MB per file
            'titles'         => ['nullable', 'array'],
            'titles.*'       => ['nullable', 'string', 'max:150'],
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $index => $file) {
            $path = $file->store("customer-documents/{$customer->id}", 'public');

            $doc = CustomerDocument::create([
                'customer_id' => $customer->id,
                'title'       => $request->input("titles.{$index}")
                    ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path'   => $path,
                'file_type'   => $file->getMimeType(),
                'file_size'   => $this->humanFileSize($file->getSize()),
                'uploaded_by' => $request->user()->id,
            ]);

            $uploaded[] = $doc;
        }

        return response()->json([
            'message' => 'تم رفع ' . count($uploaded) . ' ملف/ملفات بنجاح',
            'data'    => CustomerDocumentResource::collection(collect($uploaded)),
        ], 201);
    }

    /**
     * Delete a single customer document.
     * Removes both the DB record and the physical file.
     */
    public function deleteDocument(Customer $customer, CustomerDocument $document): JsonResponse
    {
        $this->authorize('update', $customer);

        if ($document->customer_id !== $customer->id) {
            return response()->json(['message' => 'الملف لا ينتمي لهذا العميل'], 404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'تم حذف الملف بنجاح']);
    }

    // ------------------------------------------------------------------

    protected function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
