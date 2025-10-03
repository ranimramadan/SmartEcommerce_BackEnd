<?php

namespace App\Models;

use App\Models\Concerns\HasRolesPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesPermissions;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
    
}
