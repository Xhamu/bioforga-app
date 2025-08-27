<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;

class AlmacenIntermedio extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'referencia',
        'area',
        'provincia',
        'ayuntamiento',
        'monte_parcela',
        'ubicacion_gps',

        'producto_especie',
        'producto_tipo',
        'formato',
        'tipo_servicio',
        'cantidad_aprox',
        'observaciones',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'almacenes_users', 'almacen_id', 'user_id')->withTimestamps()->withTrashed();
    }

    public function entradasAlmacen()
    {
        return $this->hasMany(\App\Models\AlmacenEntrada::class, 'almacen_intermedio_id');
    }

    public function getEstadoMostrarAttribute()
    {
        $estado = $this->estado;

        if ($estado === 'abierto') {
            return 'Abierto';
        } else if ($estado === 'en_proceso') {
            return 'En proceso';
        } else if ($estado === 'cerrado') {
            return 'Cerrado';
        }
    }

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

    protected $table = 'almacenes_intermedios';
}
