<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Referencia extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

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
        'sector',
        'tarifa',
        'en_negociacion',
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
        'tipo_certificacion',
        'tipo_certificacion_industrial',
        'guia_sanidad',
        'finca',
        'precio',
        'precio_horas',
        'estado_facturacion',
        'tipo_cantidad',
        'trabajo_lluvia'
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

    protected $table = 'referencias';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('referencia')
            ->setDescriptionForEvent(fn(string $eventName) => "Referencia {$eventName}");
    }

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
        return $this->belongsToMany(User::class, 'referencias_users', 'referencia_id', 'user_id');
    }

    public function partesTransporte()
    {
        return $this->hasMany(\App\Models\ParteTrabajoSuministroTransporte::class, 'referencia_id');
    }

    public function partesMaquina()
    {
        return $this->hasMany(\App\Models\ParteTrabajoSuministroOperacionMaquina::class, 'referencia_id');
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class);
    }

    public function getEstadoMostrarAttribute(): string
    {
        return match ($this->estado) {
            'abierto' => 'Abierto',
            'en_proceso' => 'En proceso',
            'cerrado' => 'Cerrado',
            default => ucfirst($this->estado ?? 'Desconocido'),
        };
    }

    public function getIntervinienteAttribute(): string
    {
        return $this->proveedor?->razon_social
            ?? $this->cliente?->razon_social
            ?? 'Sin interviniente';
    }

    protected function trabajoLluvia(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value === 'si',
            set: fn($value) => $value ? 'si' : 'no',
        );
    }
}
