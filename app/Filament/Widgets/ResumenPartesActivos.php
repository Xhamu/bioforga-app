<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ResumenPartesActivos extends Widget
{
    protected static string $view = 'filament.widgets.resumen-partes-activos';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $modelos = [
            \App\Models\ParteTrabajoAyudante::class => ['campo_fin' => 'fecha_hora_fin_ayudante', 'campo_inicio' => 'fecha_hora_inicio_ayudante'],
            \App\Models\ParteTrabajoSuministroAveria::class => ['campo_fin' => 'fecha_hora_fin_averia', 'campo_inicio' => 'fecha_hora_inicio_averia'],
            \App\Models\ParteTrabajoSuministroDesplazamiento::class => ['campo_fin' => 'fecha_hora_fin_desplazamiento', 'campo_inicio' => 'fecha_hora_inicio_desplazamiento'],
            \App\Models\ParteTrabajoSuministroOperacionMaquina::class => ['campo_fin' => 'fecha_hora_fin_trabajo', 'campo_inicio' => 'fecha_hora_inicio_trabajo'],
            \App\Models\ParteTrabajoSuministroOtros::class => ['campo_fin' => 'fecha_hora_fin_otros', 'campo_inicio' => 'fecha_hora_inicio_otros'],
            \App\Models\ParteTrabajoSuministroTransporte::class => ['campo_fin' => 'fecha_hora_fin_carga', 'campo_inicio' => 'fecha_hora_inicio_carga'],
            \App\Models\ParteTrabajoTallerMaquinaria::class => ['campo_fin' => 'fecha_hora_fin_taller_maquinaria', 'campo_inicio' => 'fecha_hora_inicio_taller_maquinaria'],
            \App\Models\ParteTrabajoTallerVehiculos::class => ['campo_fin' => 'fecha_hora_fin_taller_vehiculos', 'campo_inicio' => 'fecha_hora_inicio_taller_vehiculos'],
        ];

        $slugs = [
            \App\Models\ParteTrabajoAyudante::class => 'partes-trabajo-ayudantes',
            \App\Models\ParteTrabajoSuministroAveria::class => 'partes-trabajo-suministro-averia',
            \App\Models\ParteTrabajoSuministroDesplazamiento::class => 'partes-trabajo-suministro-desplazamiento',
            \App\Models\ParteTrabajoSuministroOperacionMaquina::class => 'partes-trabajo-suministro-operacion-maquina',
            \App\Models\ParteTrabajoSuministroOtros::class => 'partes-trabajo-suministro-otros',
            \App\Models\ParteTrabajoSuministroTransporte::class => 'partes-trabajo-suministro-transporte',
            \App\Models\ParteTrabajoTallerMaquinaria::class => 'partes-trabajo-taller-maquinaria',
            \App\Models\ParteTrabajoTallerVehiculos::class => 'partes-trabajo-taller-vehiculos',
        ];

        $labels = [
            \App\Models\ParteTrabajoAyudante::class => 'Ayudante',
            \App\Models\ParteTrabajoSuministroAveria::class => 'Avería',
            \App\Models\ParteTrabajoSuministroDesplazamiento::class => 'Desplazamiento',
            \App\Models\ParteTrabajoSuministroOperacionMaquina::class => 'Operación Máquina',
            \App\Models\ParteTrabajoSuministroOtros::class => 'Otros',
            \App\Models\ParteTrabajoSuministroTransporte::class => 'Transporte',
            \App\Models\ParteTrabajoTallerMaquinaria::class => 'Taller - Maquinaria',
            \App\Models\ParteTrabajoTallerVehiculos::class => 'Taller - Vehículos',
        ];
        $partesActivos = [];

        foreach ($modelos as $modelo => $campos) {
            if (isset($campos['especial']) && $modelo === \App\Models\ParteTrabajoSuministroTransporte::class) {
                $cargas = \App\Models\CargaTransporte::whereNull('fecha_hora_fin_carga')
                    ->with('parteTrabajoSuministroTransporte.usuario') // OJO: cadena de relaciones
                    ->get();

                foreach ($cargas as $carga) {
                    $parte = $carga->parteTrabajoSuministroTransporte;
                    if (!$parte)
                        continue;

                    $partesActivos[] = [
                        'id' => $parte->id,
                        'label' => $labels[\App\Models\ParteTrabajoSuministroTransporte::class],
                        'slug' => $slugs[\App\Models\ParteTrabajoSuministroTransporte::class],
                        'inicio' => $carga->fecha_hora_inicio_carga,
                        'usuario_nombre' => $parte->usuario?->name ?? 'Desconocido',
                    ];
                }

                continue;
            }

            $activos = $modelo::whereNull($campos['campo_fin'])
                ->with('usuario') // Añade esto
                ->get();

            foreach ($activos as $parte) {
                $partesActivos[] = [
                    'id' => $parte->id,
                    'label' => $labels[$modelo],
                    'slug' => $slugs[$modelo],
                    'inicio' => $parte->{$campos['campo_inicio']} ?? null,
                    'usuario_nombre' => $parte->usuario?->name ?? 'Desconocido',
                ];
            }
        }

        // Ordenar por inicio descendente
        usort($partesActivos, fn($a, $b) => strtotime($b['inicio']) <=> strtotime($a['inicio']));

        return [
            'total' => count($partesActivos),
            'partesActivos' => $partesActivos,
        ];
    }

}
