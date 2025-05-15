<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;

class AcopiosObservados extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'area',
        'provincia',
        'ayuntamiento',
        'monte_parcela',

        'producto_especie',
        'producto_tipo',
        'formato',
        'tipo_servicio',
        'cantidad_aprox',

        'contacto_nombre',
        'contacto_telefono',
        'contacto_email',

        'observaciones',
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

    protected $table = 'acopios_observados';

    protected function getUbicacionAttribute()
    {
        return $this->monte_parcela . ' (' . $this->ayuntamiento . ', ' . $this->provincia . ')';
    }

    protected function getProductoMostrarAttribute()
    {
        return ucfirst($this->producto_especie) . ' (' . ucfirst($this->producto_tipo) . ')';
    }
}
