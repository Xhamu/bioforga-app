<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Factura extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referencia_id',
        'numero',
        'fecha',
        'importe',
        'notas',
    ];

    protected $table = 'facturas';

    public function getDescripcionCortaAttribute()
    {
        return Str::limit($this->descripcion, 45);
    }

    public function referencia()
    {
        return $this->belongsTo(Referencia::class);
    }


}
