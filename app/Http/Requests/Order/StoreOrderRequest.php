<?php

namespace App\Http\Requests\Order;

use App\Models\Car;
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
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Cross-field / business-rule validation:
     *
     *  1. The chosen car must not already be tied to another order. The
     *     `orders` migration has NO unique constraint on car_id (verified
     *     against the uploaded migration), so this rule is the only thing
     *     preventing the same car from being sold to two customers at
     *     once — enforced here at the application layer instead.
     *  2. The car must not already be marked `sold` / `delivered`.
     *  3. If the requester is an agent (not a full customers.view admin),
     *     the chosen customer must actually belong to them — an agent
     *     cannot create an order on behalf of a customer they don't manage.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $carId = $this->input('car_id');

            if ($carId) {
                $car = Car::findByKey($carId);

                if ($car) {
                    if ($car->order()->exists()) {
                        $validator->errors()->add('car_id', 'هذه السيارة مرتبطة بطلب آخر مسبقًا');
                    } elseif (in_array($car->status, [Car::STATUS_SOLD, Car::STATUS_DELIVERED], true)) {
                        $validator->errors()->add('car_id', 'هذه السيارة غير متاحة للبيع حاليًا');
                    }
                }
            }

            $customerId = $this->input('customer_id');
            $user = $this->user();

            if ($customerId && ! $user->can('customers.view') && $user->agent) {
                $customer = \App\Models\Customer::findByKey($customerId);

                if ($customer && $customer->agent_id !== $user->agent->id) {
                    $validator->errors()->add('customer_id', 'لا يمكنك إنشاء طلب لعميل غير تابع لك');
                }
            }
        });
    }
}
