<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallerContacto extends Model
{
    protected $table = 'taller_contactos';

    protected $fillable = [
        'taller_id',
        'nombre',
        'telefono',
        'email',
        'cargo',
        'principal',
        'notas',
    ];

    protected $casts = [
        'principal' => 'bool',
    ];

    public function taller()
    {
        return $this->belongsTo(Taller::class);
    }
}
