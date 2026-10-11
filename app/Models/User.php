<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // 👈 ১. এই লাইনটি ইম্পোর্ট করুন

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // 👈 ২. এখানে HasApiTokens যুক্ত করুন

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}