<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentTransaction extends Model
{
    use HasFactory, SoftDeletes;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'agent_id',
        'direction',
        'amount',
        'previous_balence',
        'current_balence',
        'payment_id',
        'transaction_id',
        'transaction_date',
        'attachment',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'previous_balence' => 'decimal:2',
            'current_balence' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The customer payment this ledger line relates to, when this entry
     * represents a payment the agent collected on the company's behalf.
     */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'payment_id');
    }

    /**
     * The treasury transaction this ledger line was posted to (e.g. when
     * the agent remits collected cash into the company treasury).
     */
    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class, 'transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCredit(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }
}
