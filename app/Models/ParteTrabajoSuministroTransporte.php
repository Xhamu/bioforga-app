<?php

namespace App\Models;

use Attribute;
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
        'cliente_id',
        'almacen_id',
        'tipo_biomasa',
        'peso_neto',
        'cantidad_total',
        'albaran',
        'carta_porte',
        'observaciones',
        'fecha_hora_descarga',
        'gps_descarga'
    ];

    protected $casts = [
        'fecha_hora_inicio_carga' => 'datetime',
        'fecha_hora_fin_carga' => 'datetime',
        'fecha_hora_descarga' => 'datetime',
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

    protected function getCargasTotalesAttribute()
    {
        return $this->cargas
            ->sortBy('created_at')
            ->values()
            ->map(function ($carga, $index) {
                $numero = $index + 1;

                // Determinar origen
                if ($carga->referencia_id && $carga->referencia) {
                    $origen = $carga->referencia->referencia . ' (' . $carga->referencia->ayuntamiento . ', ' . $carga->referencia->monte_parcela . ')';
                } elseif ($carga->almacen_id && $carga->almacen) {
                    $origen = $carga->almacen->referencia . ' (' . $carga->almacen->ayuntamiento . ', ' . $carga->almacen->monte_parcela . ')';
                } else {
                    $origen = '-';
                }

                // Cantidad
                $cantidad = number_format($carga->cantidad ?? 0, 2, ',', '.') . ' m³';

                // Horas
                $inicio = optional($carga->fecha_hora_inicio_carga)?->timezone('Europe/Madrid')->format('H:i') ?? '-';
                $fin = $carga->fecha_hora_fin_carga
                    ? $carga->fecha_hora_fin_carga->timezone('Europe/Madrid')->format('H:i')
                    : null;

                $finHtml = $fin
                    ? "<span class=\"text-gray-700\">Fin:</span> $fin<br>"
                    : '';

                return <<<HTML
                    <div class="mb-2 leading-5">
                        <strong>Carga $numero</strong><br>
                        <span class="text-gray-700"></span> $origen<br>
                        <span class="text-gray-700">Cantidad:</span> $cantidad<br>
                        <span class="text-gray-700">Inicio:</span> $inicio<br>
                        $finHtml
                    </div>
                HTML;
            })
            ->filter()
            ->implode('<hr class="my-2 border-gray-200" />') ?: '-';
    }

    protected function getGpsDescargaMostrarAttribute()
    {
        if ($this->gps_descarga) {
            return '
            <a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($this->gps_descarga) . '" 
               target="_blank" 
               rel="noopener noreferrer"
               aria-label="Ver ubicación en Google Maps"
               class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md shadow hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-1 transition">
                Ver ubicación
            </a>
        ';
        }

        return '-';
    }

    public function getUsuarioProveedorCamionAttribute()
    {
        // Usuario + Proveedor
        $nombre = $this->usuario?->name ?? '';
        $apellido = $this->usuario?->apellidos ?? '';
        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
        $proveedor = $this->usuario?->proveedor?->razon_social ?? '';

        $usuarioProveedor = "<span style='font-weight: bold;'>{$nombre} {$inicialApellido}</span>";
        if ($proveedor) {
            $usuarioProveedor .= "<br><span style='color: #666;'>{$proveedor}</span>";
        }

        // Camión
        $marca = $this->camion?->marca ?? '';
        $modelo = $this->camion?->modelo ?? '';
        $matricula_cabeza = $this->camion?->matricula_cabeza ?? '';
        $camion = trim("[$matricula_cabeza] - $marca $modelo");

        return "{$usuarioProveedor}<br><span>{$camion}</span>";
    }

    // App\Models\ParteTrabajoSuministroTransporte.php

    public function pesoNetoParaReferencia(?int $referenciaId): ?float
    {
        if (!$referenciaId) {
            return $this->peso_neto; // por si acaso
        }

        // Asegúrate de tener las cargas cargadas para evitar N+1
        $cargas = $this->relationLoaded('cargas') ? $this->cargas : $this->cargas()->get();

        $totalM3Parte = (float) $cargas->sum('cantidad');
        if ($totalM3Parte <= 0 || !$this->peso_neto) {
            // Si no hay m3 registrados o no hay peso_neto en el parte, devolvemos el total tal cual o null
            return $this->peso_neto ?: null;
        }

        $m3DeEstaRef = (float) $cargas->where('referencia_id', $referenciaId)->sum('cantidad');

        // Si todas las cargas son de esta referencia o sólo hay una carga, devuelve el total
        if ($m3DeEstaRef <= 0) {
            return 0.0; // No hay cargas de esta referencia
        }

        // Regla de tres
        return ($m3DeEstaRef / $totalM3Parte) * (float) $this->peso_neto;
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function almacen()
    {
        return $this->belongsTo(AlmacenIntermedio::class, 'almacen_id');
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
