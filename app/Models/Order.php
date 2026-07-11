<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /**
     * Order lifecycle, exactly as specified in the requirements:
     * new -> purchased -> shipping -> arrived_at_port -> ready_for_delivery -> delivered
     */
    public const STATUS_NEW = 'new';

    public const STATUS_PURCHASED = 'purchased';

    public const STATUS_SHIPPING = 'shipping';

    public const STATUS_ARRIVED_AT_PORT = 'arrived_at_port';

    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    /**
     * Ordered list of valid statuses, used to validate forward transitions.
     */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PURCHASED,
        self::STATUS_SHIPPING,
        self::STATUS_ARRIVED_AT_PORT,
        self::STATUS_READY_FOR_DELIVERY,
        self::STATUS_DELIVERED,
    ];

    protected $fillable = [
        'order_number',
        'customer_id',
        'car_id',
        'agent_id',
        'status',
        'purchase_date',
        'shipping_date',
        'arrival_date',
        'delivery_date',
        'paid_amount',
        'remaining_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'shipping_date' => 'date',
            'arrival_date' => 'date',
            'delivery_date' => 'date',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Payments the customer has made towards this specific order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Expenses tied to fulfilling this order (delivery, paperwork, etc.).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The single invoice issued for this order, if any.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Recalculate paid/remaining amounts from the actual payments table,
     * and persist them. Call this after creating/voiding a payment so the
     * order's cached totals never drift from the ledger.
     */
    public function recalculateBalance(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $price = (float) $this->car?->sale_price + (float) ($this->car?->total_expenses ?? 0);

        $this->forceFill([
            'paid_amount' => $paid,
            'remaining_amount' => max($price - $paid, 0),
        ])->save();
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
