<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PausaParteTrabajo extends Model
{
    use HasFactory;

    protected $table = 'pausas_partes_trabajo';

    protected $fillable = [
        'inicio_pausa',
        'fin_pausa',
        'gps_inicio_pausa',
        'gps_fin_pausa',
    ];

    protected $casts = [
        'inicio_pausa' => 'datetime',
        'fin_pausa' => 'datetime',
    ];

    public function parteTrabajo(): MorphTo
    {
        return $this->morphTo();
    }
}
