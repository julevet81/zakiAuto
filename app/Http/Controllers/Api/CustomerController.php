<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            // Agent without the full customers.view permission: hard-scope
            // to their own agent record, ignoring any agent_id they send.
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $customers = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    /**
     * Create a customer.
     *
     * An agent creating a customer is automatically linked to themself —
     * they cannot pass agent_id (blocked in StoreCustomerRequest), so this
     * is the only way an agent-created customer ends up assigned at all.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $request->user()->can('customers.view') && $request->user()->agent) {
            $data['agent_id'] = $request->user()->agent->id;
        }

        $customer = Customer::create($data);

        return response()->json([
            'message' => 'تم إضافة العميل بنجاح',
            'data' => new CustomerResource($customer->load('agent')),
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->load(['agent', 'orders.car']);

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

        return response()->json([
            'message' => 'تم تحديث بيانات العميل بنجاح',
            'data' => new CustomerResource($customer->load('agent')),
        ]);
    }

    /**
     * Delete a customer. Blocked if they have any orders, to protect
     * historical sales/financial records from being orphaned.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف العميل لوجود طلبات مرتبطة به',
            ], 422);
        }

        $customer->delete();

        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }
}
