<?php

namespace App\Services;

use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use Illuminate\Support\Facades\DB;

class StockCalculator
{
    /**
     * Mapea los valores crudos de la BD a las ETIQUETAS usadas en prioridades_stock
     */
    private function mapCertToLabel(?string $raw): string
    {
        $raw = $raw ? strtolower(trim($raw)) : '';
        return match ($raw) {
            'sure_induestrial', 'sure_industrial', 'industrial' => 'SURE INDUSTRIAL',
            'sure_foresal', 'sure_forestal', 'forestal' => 'SURE FORESTAL',
            'pefc' => 'PEFC',
            'sbp' => 'SBP',
            default => 'SURE FORESTAL', // fallback razonable
        };
    }

    private function mapEspToLabel(?string $raw): string
    {
        $raw = $raw ? strtolower(trim($raw)) : '';
        return match ($raw) {
            'pino' => 'PINO',
            'eucalipto' => 'EUCALIPTO',
            'acacia' => 'ACACIA',
            'frondosa' => 'FRONDOSA',
            'otros' => 'OTROS',
            default => 'OTROS',
        };
    }

    /**
     * Calcula:
     *  - entradas: m³ por (CERT_LABEL|ESP_LABEL) descargados en el almacén
     *  - salidas_total: m³ totales cargados desde el almacén hacia cliente
     *  - disponible: entradas menos salidas repartidas por prioridad (también por LABEL)
     */
    public function calcular(AlmacenIntermedio $almacen): array
    {
        // === ENTRADAS: DESCARGAS EN ESTE ALMACÉN DESDE REFERENCIAS ===
        $entradas = \DB::table('carga_transportes as ct')
            ->join('parte_trabajo_suministro_transportes as pt', 'pt.id', '=', 'ct.parte_trabajo_suministro_transporte_id')
            ->join('referencias as r', 'r.id', '=', 'ct.referencia_id')
            ->whereNull('ct.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('pt.almacen_id', $almacen->id)      // se DESCARGÓ en este almacén
            ->whereNull('pt.cliente_id')                // no se descargó en cliente
            ->whereNotNull('ct.referencia_id')          // venía de referencia
            ->selectRaw('r.tipo_certificacion as cert_raw, r.producto_especie as esp_raw, SUM(ct.cantidad) as total_m3')
            ->groupBy('r.tipo_certificacion', 'r.producto_especie')
            ->get();

        $entradasByKey = [];
        foreach ($entradas as $row) {
            $certLabel = $this->mapCertToLabel($row->cert_raw);
            $espLabel = $this->mapEspToLabel($row->esp_raw);
            $key = "{$certLabel}|{$espLabel}";
            $entradasByKey[$key] = ($entradasByKey[$key] ?? 0.0) + (float) $row->total_m3;
        }

        // === SALIDAS: CARGAS DESDE ESTE ALMACÉN (solo con snapshot) ===
        $salidasRows = \DB::table('carga_transportes as ct')
            ->whereNull('ct.deleted_at')
            ->where('ct.almacen_id', $almacen->id)      // se CARGÓ en este almacén
            ->whereNull('ct.referencia_id')             // no viene de referencia (es salida)
            ->whereNotNull('ct.asignacion_cert_esp')    // snapshot guardado => debe restar
            ->get(['ct.id', 'ct.asignacion_cert_esp', 'ct.cantidad']);

        $salidasByKey = [];
        foreach ($salidasRows as $s) {
            $detalle = json_decode($s->asignacion_cert_esp ?? '[]', true);
            if (!is_array($detalle) || empty($detalle))
                continue;

            foreach ($detalle as $a) {
                $cert = strtoupper(trim((string) ($a['certificacion'] ?? '')));
                $esp = strtoupper(trim((string) ($a['especie'] ?? '')));
                $q = (float) ($a['cantidad'] ?? 0);
                if (!$cert || !$esp || $q <= 0)
                    continue;

                $key = "{$cert}|{$esp}";
                $salidasByKey[$key] = ($salidasByKey[$key] ?? 0.0) + $q;
            }
        }

        // === AJUSTES MANUALES (múltiples) ===
        // Tabla: ajustes_stock (almacen_intermedio_id, certificacion, especie, delta_m3, ...)
        $ajustesRows = \DB::table('ajustes_stock')
            ->where('almacen_intermedio_id', $almacen->id)
            ->select('certificacion', 'especie', \DB::raw('SUM(delta_m3) as total'))
            ->groupBy('certificacion', 'especie')
            ->get();

        $ajustesByKey = [];
        foreach ($ajustesRows as $a) {
            $key = strtoupper(trim($a->certificacion)) . '|' . strtoupper(trim($a->especie));
            $ajustesByKey[$key] = (float) $a->total;
        }

        // === DISPONIBLE = ENTRADAS - SALIDAS + AJUSTES ===
        $disponible = [];
        $keys = array_unique(array_merge(
            array_keys($entradasByKey),
            array_keys($salidasByKey),
            array_keys($ajustesByKey),
        ));

        foreach ($keys as $key) {
            $in = (float) ($entradasByKey[$key] ?? 0.0);
            $out = (float) ($salidasByKey[$key] ?? 0.0);
            $adj = (float) ($ajustesByKey[$key] ?? 0.0);
            // si quieres permitir negativo, quita max(0.0, ...)
            $disponible[$key] = max(0.0, $in - $out + $adj);
        }

        return [
            'entradas' => $entradasByKey,
            'salidas_total' => array_sum($salidasByKey),
            'salidas' => $salidasByKey,
            'ajustes' => $ajustesByKey,   // <- útil para tooltips/UI
            'disponible' => $disponible,
        ];
    }

    /**
     * Actualiza prioridades_stock.cantidad_disponible usando las ETIQUETAS (match directo).
     */
    public function actualizarPrioridades(AlmacenIntermedio $almacen): void
    {
        $calc = $this->calcular($almacen);
        $disponible = $calc['disponible'];

        DB::transaction(function () use ($almacen, $disponible) {
            // Traemos todas las filas del almacén y actualizamos por clave "<LABEL_CERT>|<LABEL_ESP>"
            $prioridades = PrioridadStock::where('almacen_intermedio_id', $almacen->id)
                ->get(['id', 'certificacion', 'especie', 'cantidad_disponible']);

            foreach ($prioridades as $p) {
                $key = "{$p->certificacion}|{$p->especie}";
                $nuevoValor = (float) ($disponible[$key] ?? 0.0);
                if ((float) $p->cantidad_disponible !== $nuevoValor) {
                    $p->update(['cantidad_disponible' => $nuevoValor]);
                }
            }
        });
    }

    /**
     * Devuelve disponibilidad (por etiquetas usadas en prioridades_stock).
     * Ej: disponiblePara($alm, 'SURE INDUSTRIAL','EUCALIPTO')
     */
    public function disponiblePara(AlmacenIntermedio $almacen, string $certLabel, string $espLabel): float
    {
        $calc = $this->calcular($almacen);
        $key = "{$certLabel}|{$espLabel}";
        return (float) ($calc['disponible'][$key] ?? 0.0);
    }

    public function disponiblePorReferencia(AlmacenIntermedio $almacen, string $certLabel, string $espLabel): array
    {
        $certLabel = strtoupper(trim($certLabel));
        $espLabel = strtoupper(trim($espLabel));
        $key = "{$certLabel}|{$espLabel}";

        // 1) ENTRADAS por referencia (descargas en almacén de una referencia), con fecha para FIFO
        $entradas = \DB::table('carga_transportes as ct')
            ->join('parte_trabajo_suministro_transportes as pt', 'pt.id', '=', 'ct.parte_trabajo_suministro_transporte_id')
            ->join('referencias as rf', 'rf.id', '=', 'ct.referencia_id')
            ->whereNull('ct.deleted_at')
            ->whereNull('rf.deleted_at')
            ->where('pt.almacen_id', $almacen->id)      // DESCARGÓ en este almacén
            ->whereNull('pt.cliente_id')                // no fue a cliente
            ->whereNotNull('ct.referencia_id')          // venía de referencia
            ->orderByRaw('ct.created_at asc, ct.id asc')
            ->get([
                'rf.id as referencia_id',
                'rf.referencia',
                'rf.tipo_certificacion as cert_raw',
                'rf.producto_especie   as esp_raw',
                'ct.cantidad',
                'ct.created_at',
            ])
            ->filter(function ($e) use ($certLabel, $espLabel) {
                $mapCert = fn($raw) => $this->mapCertToLabel($raw);
                $mapEsp = fn($raw) => $this->mapEspToLabel($raw);
                return $mapCert($e->cert_raw) === $certLabel && $mapEsp($e->esp_raw) === $espLabel;
            })
            ->values();

        // Colas FIFO por referencia
        $colas = []; // refId => [ ['qty'=>float,'at'=>string], ... ]
        $meta = []; // refId => ['referencia'=>string]
        foreach ($entradas as $e) {
            $rid = (int) $e->referencia_id;
            $colas[$rid] = $colas[$rid] ?? [];
            $meta[$rid] = $meta[$rid] ?? ['referencia' => $e->referencia];
            $colas[$rid][] = ['qty' => (float) $e->cantidad, 'at' => (string) $e->created_at];
        }

        // 2) SALIDAS históricas de esta clave (desde este almacén) por snapshot
        $salidasDeClave = \DB::table('carga_transportes as ct')
            ->whereNull('ct.deleted_at')
            ->where('ct.almacen_id', $almacen->id)
            ->whereNull('ct.referencia_id')           // salidas desde almacén
            ->whereNotNull('ct.asignacion_cert_esp')  // snapshot presente
            ->orderByRaw('ct.created_at asc, ct.id asc')
            ->pluck('ct.asignacion_cert_esp')
            ->map(fn($json) => json_decode($json, true) ?: [])
            ->flatMap(function ($arr) use ($key) {
                $acc = [];
                foreach ($arr as $a) {
                    $k = strtoupper(trim((string) ($a['certificacion'] ?? ''))) . '|' .
                        strtoupper(trim((string) ($a['especie'] ?? '')));
                    if ($k === $key) {
                        $q = (float) ($a['cantidad'] ?? 0);
                        if ($q > 0)
                            $acc[] = $q;
                    }
                }
                return $acc;
            })
            ->values()
            ->all();

        // 3) Consumir FIFO *dentro de la clave*: siempre el lote más antiguo entre todas las refs
        foreach ($salidasDeClave as $qSalida) {
            $rest = $qSalida;
            while ($rest > 0) {
                $bestRid = null;
                $bestAt = null;

                foreach ($colas as $rid => $lotes) {
                    while (!empty($lotes) && $lotes[0]['qty'] <= 0) {
                        array_shift($colas[$rid]);
                        $lotes = $colas[$rid];
                    }
                    if (empty($lotes))
                        continue;

                    $at = $lotes[0]['at'];
                    if ($bestAt === null || $at < $bestAt) {
                        $bestAt = $at;
                        $bestRid = $rid;
                    }
                }

                if ($bestRid === null)
                    break; // no queda stock en esta clave

                $disp = $colas[$bestRid][0]['qty'];
                $usa = min($disp, $rest);
                $colas[$bestRid][0]['qty'] -= $usa;
                $rest -= $usa;
                if ($colas[$bestRid][0]['qty'] <= 0) {
                    array_shift($colas[$bestRid]);
                }
            }
        }

        // 4) Resultado: disponible por referencia (sumatorio de lo que queda en colas)
        $res = [];
        foreach ($colas as $rid => $lotes) {
            $sum = 0.0;
            foreach ($lotes as $l)
                $sum += max(0.0, (float) $l['qty']);
            if ($sum > 0) {
                $res[] = [
                    'referencia_id' => $rid,
                    'referencia' => $meta[$rid]['referencia'] ?? (string) $rid,
                    'm3_disponible' => round($sum, 4),
                ];
            }
        }

        // Ya vienen en “orden de antigüedad” implícito (por cómo consumimos). Si quieres, ordénalo:
        // usort($res, fn($a,$b) => $a['m3_disponible'] <=> $b['m3_disponible']);
        return $res;
    }
}
