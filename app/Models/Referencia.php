<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;

class Referencia extends Authenticatable
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
        'proveedor_id',
        'cliente_id',
        'producto_especie',
        'producto_tipo',
        'formato',
        'tipo_servicio',
        'cantidad_aprox',
        'estado',
        'observaciones',
        'contacto_nombre',
        'contacto_telefono',
        'contacto_email',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'referencias_users', 'referencia_id', 'user_id')->withTimestamps()->withTrashed();
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

    protected $table = 'referencias';
}
