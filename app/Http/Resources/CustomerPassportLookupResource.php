<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing customer lookup response — returned by the unauthenticated
 * GET /lookup/customer/{passport_no} endpoint.
 *
 * Deliberately excludes any internal/operational data that has no value
 * to the customer themselves:
 *   - No supplier identities or cost/purchase prices (internal finance).
 *   - No agent_id / user_id (internal linking keys).
 *   - No created_by / creator fields (internal audit).
 *   - Payment amounts are included because a customer has an obvious
 *     legitimate interest in seeing what they've paid and what remains.
 *
 * @mixin \App\Models\Customer
 */
class CustomerPassportLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Customer identity ─────────────────────────────────────
            'name'        => $this->name,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'national_id' => $this->national_id,
            'passport_no' => $this->passport_no,
            'address'     => $this->address,

            // ── Financial summary ─────────────────────────────────────
            'total_paid'      => (float) $this->orders->sum('paid_amount'),
            'total_remaining' => (float) $this->orders->sum('remaining_amount'),

            // ── Orders ────────────────────────────────────────────────
            'orders' => $this->orders->map(fn($order) => [
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'status_label' => $this->statusLabel($order->status),

                // Full status timeline — which stages have been reached
                // and when, so the customer can see exactly where their
                // car is in the import/delivery pipeline.
                'timeline' => $this->buildTimeline($order),

                // ── Car details ───────────────────────────────────────
                'car' => $order->car ? [
                    'brand'            => $order->car->brand,
                    'model'            => $order->car->model,
                    'finition'         => $order->car->finition,
                    'manufacture_year' => $order->car->manufacture_year,
                    'color'            => $order->car->color,
                    'vin'              => $order->car->vin,
                    // sale_price is shown — the customer agreed to this
                    // price and has a right to see it. Purchase/cost
                    // price is never included (no car.foreign_purchase_price).
                    'sale_price'       => (float) $order->car->sale_price,
                    'tracking_number'  => $order->car->tracking_number,
                    'container_no'     => $order->car->container_no,
                    'car_status'       => $order->car->status,
                ] : null,

                // ── Financial details for this order ──────────────────
                'sale_price'       => $order->car
                    ? (float) $order->car->sale_price
                    : null,
                'paid_amount'      => (float) $order->paid_amount,
                'remaining_amount' => (float) $order->remaining_amount,

                // ── Payments made on this order ───────────────────────
                'payments' => $order->payments->map(fn($payment) => [
                    'amount'       => (float) $payment->amount,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    // Show whether the company received it directly or
                    // via an agent (useful for the customer to reconcile
                    // their own receipts), but do NOT expose the agent's
                    // identity or internal IDs.
                    'received_by'  => $payment->wasCollectedByAgent() ? 'agent' : 'company',
                    'notes'        => $payment->notes,
                ]),

                'notes'         => $order->notes,
                'purchase_date' => $order->purchase_date?->format('Y-m-d'),
            ]),
        ];
    }

    /**
     * Build a chronological timeline of status milestones for this order.
     * Each entry shows:
     *   - The stage name (Arabic label).
     *   - Whether it has been reached yet.
     *   - The date it was reached (where the order model stores one).
     *
     * This gives the customer a clear, sequential view of their car's
     * journey from "ordered" to "delivered".
     */
    protected function buildTimeline(\App\Models\Order $order): array
    {
        $statuses = \App\Models\Order::STATUSES;
        $currentIndex = array_search($order->status, $statuses, true);

        return [
            [
                'stage'    => 'new',
                'label'    => 'طلب جديد',
                'reached'  => true, // always true — order exists
                'date'     => $order->created_at?->format('Y-m-d'),
            ],
            [
                'stage'   => 'purchased',
                'label'   => 'تم الشراء',
                'reached' => $currentIndex >= array_search('purchased', $statuses, true),
                'date'    => $order->purchase_date?->format('Y-m-d'),
            ],
            [
                'stage'   => 'shipping',
                'label'   => 'قيد الشحن',
                'reached' => $currentIndex >= array_search('shipping', $statuses, true),
                'date'    => $order->shipping_date?->format('Y-m-d'),
            ],
            [
                'stage'   => 'arrived_at_port',
                'label'   => 'وصل إلى الميناء',
                'reached' => $currentIndex >= array_search('arrived_at_port', $statuses, true),
                'date'    => $order->arrival_date?->format('Y-m-d'),
            ],
            [
                'stage'   => 'ready_for_delivery',
                'label'   => 'جاهز للتسليم',
                'reached' => $currentIndex >= array_search('ready_for_delivery', $statuses, true),
                'date'    => null, // no dedicated column for this stage
            ],
            [
                'stage'   => 'delivered',
                'label'   => 'تم التسليم',
                'reached' => $currentIndex >= array_search('delivered', $statuses, true),
                'date'    => $order->delivery_date?->format('Y-m-d'),
            ],
        ];
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'new'                => 'طلب جديد',
            'purchased'          => 'تم الشراء',
            'shipping'           => 'قيد الشحن',
            'arrived_at_port'    => 'وصل إلى الميناء',
            'ready_for_delivery' => 'جاهز للتسليم',
            'delivered'          => 'تم التسليم',
            default              => $status,
        };
    }
}
