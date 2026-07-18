<?php

namespace App\Http\Requests\Order;

use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Cross-field / business-rule validation:
     *
     *  1. The car must not already be marked `sold` / `delivered`.
     *     Multiple orders for the same car are allowed so ownership can be
     *     transferred; OrderController decides whether that makes the car sold.
     *  2. If the requester is an agent (not a full customers.view admin),
     *     the chosen customer must actually belong to them - an agent
     *     cannot create an order on behalf of a customer they don't manage.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $carId = $this->input('car_id');

            if ($carId) {
                $car = Car::find($carId);

                if ($car) {
                    if (in_array($car->status, [Car::STATUS_SOLD, Car::STATUS_DELIVERED], true)) {
                        $validator->errors()->add('car_id', 'هذه السيارة غير متاحة للبيع حاليًا');
                    }
                }
            }

            $customerId = $this->input('customer_id');
            $user = $this->user();

            if ($customerId && $user->agent) {
                $customer = Customer::find($customerId);

                if ($customer && $customer->agent_id !== $user->agent->id) {
                    $validator->errors()->add('customer_id', 'لا يمكنك إنشاء طلب لعميل غير تابع لك');
                }

                if ($this->has('agent_id') && (int)$this->input('agent_id') !== $user->agent->id) {
                    $validator->errors()->add('agent_id', 'لا يمكنك إسناد الطلب لوكيل آخر');
                }
            }
        });
    }
}
