<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fecha_pedido',
        'operario_id',
        'maquina_id',
        'pieza_pedida',
        'unidades',
        'estado',
    ];

    protected $table = 'pedidos';

    /**
     * Relación: un pedido pertenece a un operario (usuario).
     */
    public function operario()
    {
        return $this->belongsTo(User::class, 'operario_id');
    }

    /**
     * Relación: un pedido pertenece a una máquina.
     */
    public function maquina()
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    public function getEstadoMostrarAttribute()
    {
        return match ($this->estado) {
            'pendiente' => '<span style="background-color:#facc15; color:#000; padding: 4px 8px; border-radius: 6px; font-weight: 600;">Pendiente</span>',
            'completado' => '<span style="background-color:#22c55e; color:#fff; padding: 4px 8px; border-radius: 6px; font-weight: 600;">Completado</span>',
            'cancelado' => '<span style="background-color:#ef4444; color:#fff; padding: 4px 8px; border-radius: 6px; font-weight: 600;">Cancelado</span>',
            default => '<span>' . $this->estado . '</span>',
        };
    }
}
