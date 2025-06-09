<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Pais extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'codigo_iso',
    ];

    protected $table = 'paises';

}
