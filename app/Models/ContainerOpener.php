<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContainerOpener extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'nif',
        'notes',
    ];

    /**
     * Cars whose container this person/company cleared at port.
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }
}
