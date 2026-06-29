<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a third-party service provider used by the company
 * (e.g. shipping line, customs broker, transport company, workshop).
 *
 * NOTE: Named ServiceProviderModel to avoid any ambiguity with Laravel's
 * own Illuminate\Support\ServiceProvider base class. Adjust the class/file
 * name back to ServiceProvider if you prefer — there is no actual
 * collision since namespaces differ, but the distinct name avoids
 * confusion when reading the codebase.
 */
class ServiceProviderModel extends Model
{
    use HasFactory;

    protected $table = 'service_providers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'provider_type',
        'address',
        'notes',
    ];

    /**
     * Expenses billed by this service provider.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'service_provider_id');
    }
}
