<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PartesTrabajoActivos extends Widget
{
    protected static string $view = 'filament.widgets.partes-trabajo-activos';
    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $userId = Auth::id();

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

        $totalActivos = 0;
        $partesActivos = [];

        foreach ($modelos as $modelo => $campos) {
            if ($modelo === \App\Models\ParteTrabajoSuministroTransporte::class) {
                // Cargas relacionadas con partes sin finalizar
                $cargas = \App\Models\CargaTransporte::whereNull('fecha_hora_fin_carga')
                    ->whereHas('parteTrabajoSuministroTransporte', function ($query) use ($userId) {
                        $query->where('usuario_id', $userId);
                    })
                    ->with('parteTrabajoSuministroTransporte')
                    ->get();

                foreach ($cargas as $carga) {
                    $parte = $carga->parteTrabajoSuministroTransporte;

                    $partesActivos[] = [
                        'id' => $parte->id,
                        'modelo' => \App\Models\ParteTrabajoSuministroTransporte::class,
                        'label' => $labels[\App\Models\ParteTrabajoSuministroTransporte::class],
                        'slug' => $slugs[\App\Models\ParteTrabajoSuministroTransporte::class],
                        'inicio' => $carga->fecha_hora_inicio_carga,
                    ];
                }

                $totalActivos += $cargas->count();

                continue; // Saltamos al siguiente modelo
            }

            // Resto de modelos normales
            $query = $modelo::whereNull($campos['campo_fin'])->where('usuario_id', $userId);

            foreach ($query->get() as $parte) {
                $partesActivos[] = [
                    'id' => $parte->id,
                    'modelo' => $modelo,
                    'label' => $labels[$modelo] ?? class_basename($modelo),
                    'slug' => $slugs[$modelo] ?? null,
                    'inicio' => $parte->{$campos['campo_inicio']} ?? null,
                ];
            }

            $totalActivos += $query->count();
        }

        return [
            'activos' => $totalActivos,
            'parte' => $partesActivos[0] ?? null,
            'partesActivos' => $partesActivos,
        ];
    }
}