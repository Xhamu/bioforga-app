<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;


class ParteTrabajoSuministroTransporte extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'camion_id',
        'referencia_id',
        'fecha_hora_inicio_carga',
        'gps_inicio_carga',
        'fecha_hora_fin_carga',
        'gps_fin_carga',
        'cantidad',
        'cliente_id',
        'tipo_biomasa',
        'peso_neto',
        'cantidad_total',
        'albaran',
        'observaciones'
    ];

    protected $casts = [
        'fecha_hora_inicio_carga' => 'date',
        'fecha_hora_fin_carga' => 'date',
        'tipo_biomasa' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    protected $table = 'parte_trabajo_suministro_transportes';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function camion()
    {
        return $this->belongsTo(Camion::class, 'camion_id');
    }

    public function referencia()
    {
        return $this->belongsTo(Referencia::class, 'referencia_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cargas()
    {
        return $this->hasMany(CargaTransporte::class, 'parte_trabajo_suministro_transporte_id');
    }
}
