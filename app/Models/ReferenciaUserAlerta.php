<?php

// app/Models/ReferenciaUserAlerta.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenciaUserAlerta extends Model
{
    protected $table = 'referencia_user_alertas';

    protected $fillable = [
        'referencia_id',
        'user_id',
        'accepted_at',
    ];

    public function referencia()
    {
        return $this->belongsTo(Referencia::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
