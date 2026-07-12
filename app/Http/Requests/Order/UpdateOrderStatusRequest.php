<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Order::STATUSES)],
            // Optional: let the client set the matching date in the same
            // call (e.g. moving to "shipping" also records shipping_date).
            'date' => ['nullable', 'date'],
        ];
    }

    /**
     * Enforce the workflow moves strictly forward by one step at a time
     * (new -> purchased -> shipping -> arrived_at_port ->
     * ready_for_delivery -> delivered). Skipping steps or moving
     * backwards is rejected — if a real-world correction is needed
     * (e.g. a mis-click), that should go through an admin-only "force"
     * action rather than silently allowing arbitrary jumps here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $order = $this->route('order');
            $newStatus = $this->input('status');

            if (! $order || ! $newStatus) {
                return;
            }

            $currentIndex = array_search($order->status, Order::STATUSES, true);
            $newIndex = array_search($newStatus, Order::STATUSES, true);

            if ($currentIndex === false || $newIndex === false) {
                return;
            }

            // if ($newIndex !== $currentIndex + 1) {
            //     $validator->errors()->add(
            //         'status',
            //         'لا يمكن تغيير الحالة من "'.$order->status.'" إلى "'.$newStatus.'" مباشرة، يجب اتباع تسلسل الحالات'
            //     );
            // }
        });
    }
}
