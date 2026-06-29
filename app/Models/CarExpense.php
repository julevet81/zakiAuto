<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'expense_type',
        'foreign_amount',
        'local_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'foreign_amount' => 'decimal:2',
            'local_amount' => 'decimal:2',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
