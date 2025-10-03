<?php

// app/Models/State.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class State extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function statuses()
    {
        return $this->hasMany(UserStatus::class);
    }
}
