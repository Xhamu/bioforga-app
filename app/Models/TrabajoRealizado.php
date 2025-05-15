<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TrabajoRealizado extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    protected $table = 'trabajos_realizados';

    public function getDescripcionCortaAttribute()
    {
        return Str::limit($this->descripcion, 45);
    }

}
