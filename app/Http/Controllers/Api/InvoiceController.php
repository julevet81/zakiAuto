<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $user = $request->user();

        $query = Invoice::query()->with('order');

        // Same scoping pattern as OrderController: a customer without
        // full orders.view only ever sees invoices for their own orders.
        if (! $user->can('orders.view')) {
            $query->whereHas('order', fn ($q) => $q->where('customer_id', $user->customer?->id ?? 0));
        }

        $invoices = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json(InvoiceResource::collection($invoices)->response()->getData(true));
    }

    /**
     * Generate an invoice from an existing order's current totals.
     * Snapshots total/paid/remaining at generation time — the invoice is
     * a point-in-time financial document, not a live mirror of the order.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $order = Order::with('car')->find($request->validated('order_id'));

        $totalAmount = (float) $order->car->sale_price + (float) $order->car->total_expenses;
        $paidAmount = (float) $order->paid_amount;
        $remaining = max($totalAmount - $paidAmount, 0);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0
                ? Invoice::STATUS_PAID
                : ($paidAmount > 0 ? Invoice::STATUS_PARTIAL : Invoice::STATUS_UNPAID),
        ]);

        return response()->json([
            'message' => 'تم إنشاء الفاتورة بنجاح',
            'data' => new InvoiceResource($invoice->load('order')),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('order.customer', 'order.car');

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Re-sync an invoice's paid/remaining/status from its order's current
     * state — useful after a new payment is recorded post-invoicing.
     */
    public function refresh(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $order = $invoice->order;
        $paidAmount = (float) $order->paid_amount;
        $remaining = max((float) $invoice->total_amount - $paidAmount, 0);

        $invoice->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0
                ? Invoice::STATUS_PAID
                : ($paidAmount > 0 ? Invoice::STATUS_PARTIAL : Invoice::STATUS_UNPAID),
        ]);

        return response()->json([
            'message' => 'تم تحديث الفاتورة بنجاح',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json(['message' => 'تم حذف الفاتورة بنجاح']);
    }

    protected function generateInvoiceNumber(): string
    {
        do {
            $candidate = 'INV-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (Invoice::where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
