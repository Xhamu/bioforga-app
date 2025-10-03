<?php

// app/Models/UserStatus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'state_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function scopeActive($q)
    {
        return $q->whereNull('ended_at');
    }
}
