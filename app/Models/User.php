<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * The guard name used by Spatie Permission for this model.
     * Must match the guard configured for the "roles"/"permissions" given to API users.
     */
    protected string $guard_name = 'api';

    /**
     * Sync user updates with the linked agent profile if it exists.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->agent) {
                $user->agent->update([
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * If this user is an Agent, this links to their agent profile/ledger.
     */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * If this user is a Customer, this links to their customer profile/orders.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Convenience accessor: is this account active and allowed to log in.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
