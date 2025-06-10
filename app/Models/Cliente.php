<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
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
        'telefono_principal',
        'correo_principal',
        'tipo_cliente',

        'pais',
        'provincia',
        'poblacion',
        'codigo_postal',
        'direccion',

        'usuario_id'
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

    protected $table = 'clientes';

    /**
     * Relación uno a muchos con direcciones de envío.
     */
    public function direcciones_envio()
    {
        return $this->hasMany(DireccionEnvio::class);
    }

    /**
     * Relación uno a muchos con personas de contacto.
     */
    public function personas_contacto()
    {
        return $this->hasMany(PersonaContacto::class);
    }
}
