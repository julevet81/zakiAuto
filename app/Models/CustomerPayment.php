<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes;

    public const RECEIVED_BY_COMPANY = 'company';

    public const RECEIVED_BY_AGENT = 'agent';

    protected $fillable = [
        'order_id',
        'customer_id',
        'amount',
        'received_by',
        'agent_id',
        'remittance_id',
        'attachment',
        'payment_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The agent who physically collected this payment, when received_by
     * is "agent". Null when the company collected it directly.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The agent-transaction record representing the agent remitting this
     * collected payment back to the company (closes the loop between the
     * agent collecting cash and the company actually receiving it).
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(AgentTransaction::class, 'remittance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wasCollectedByAgent(): bool
    {
        return $this->received_by === self::RECEIVED_BY_AGENT;
    }
}
