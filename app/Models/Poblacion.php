<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Poblacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'codigo',
        'provincia_id',
    ];

    protected $table = 'poblaciones';

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }
}
