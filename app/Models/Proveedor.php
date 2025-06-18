<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'razon_social',
        'nif',
        'telefono',
        'email',
        'tipo_servicio',

        'pais',
        'provincia',
        'poblacion',
        'codigo_postal',
        'direccion',

        'nombre_contacto',
        'cargo_contacto',
        'telefono_contacto',
        'email_contacto',

        'usuario_id',
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

    protected $table = 'proveedores';

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'proveedor_id');
    }
}
