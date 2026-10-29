<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'contact_number',
        'password',
        'role',
        'status',
        'allow',
        'assigned_facility',
        'barangay',
        'municipality',
        'province',
        'region',
        'barangay_codes',
        'municipality_code',
        'province_code',
        'region_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'password'          => 'hashed',
            'allow'             => 'boolean',
            'barangay'          => 'array',
            'barangay_codes'    => 'array',
        ];
    }

    public function isAdministrator(): bool
    {
        return strtoupper((string) $this->role) === 'ADMINISTRATOR';
    }

    /**
     * Administrators, or users an admin has granted "allow" to,
     * may open Account Approvals.
     */
    public function canApproveAccounts(): bool
    {
        return $this->isAdministrator() || (bool) $this->allow;
    }
}