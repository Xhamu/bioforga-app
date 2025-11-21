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
        // === 1) ENTRADAS: descargas en este almacén desde referencia ===
        // Cada fila = un lote FIFO con fecha
        $entradasRows = DB::table('carga_transportes as ct')
            ->join(
                'parte_trabajo_suministro_transportes as pt',
                'pt.id',
                '=',
                'ct.parte_trabajo_suministro_transporte_id'
            )
            ->join('referencias as r', 'r.id', '=', 'ct.referencia_id')
            ->whereNull('ct.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('pt.almacen_id', $almacen->id)      // se DESCARGÓ en este almacén
            ->whereNull('pt.cliente_id')                // no se descargó en cliente
            ->whereNotNull('ct.referencia_id')          // venía de referencia
            ->orderByRaw('ct.created_at asc, ct.id asc')
            ->get([
                'r.tipo_certificacion as cert_raw',
                'r.producto_especie   as esp_raw',
                'ct.cantidad',
                'ct.created_at as fecha',
            ]);

        $entradasByKey = [];
        $lotesFIFO = []; // cola global FIFO (con fecha)

        foreach ($entradasRows as $row) {
            $certLabel = $this->mapCertToLabel($row->cert_raw);
            $espLabel = $this->mapEspToLabel($row->esp_raw);
            $key = "{$certLabel}|{$espLabel}";
            $qty = (float) $row->cantidad;

            if ($qty <= 0) {
                continue;
            }

            $entradasByKey[$key] = ($entradasByKey[$key] ?? 0.0) + $qty;

            $lotesFIFO[] = [
                'key' => $key,
                'qty' => $qty,
                'fecha' => $row->fecha,
            ];
        }

        // === 2) TODAS LAS SALIDAS (origen almacén -> cliente) ===
        // Luego en PHP separamos:
        //   - con snapshot válido (se suma por cert|esp)
        //   - sin snapshot / snapshot vacío (van a FIFO si hay stock suficiente)
        $salidasRows = DB::table('carga_transportes as ct')
            ->join(
                'parte_trabajo_suministro_transportes as pt',
                'pt.id',
                '=',
                'ct.parte_trabajo_suministro_transporte_id'
            )
            ->whereNull('ct.deleted_at')
            ->where('ct.almacen_id', $almacen->id)
            ->whereNotNull('pt.cliente_id')
            ->orderByRaw('ct.created_at asc, ct.id asc')
            ->get([
                'ct.cantidad',
                'ct.asignacion_cert_esp',
                'ct.created_at as fecha',
            ]);

        $salidasSnapshotByKey = [];
        $salidasNoSnapRows = [];
        $salidasBrutas = 0.0; // TODAS las salidas (para resumen real)

        foreach ($salidasRows as $row) {
            $qty = (float) $row->cantidad;
            if ($qty <= 0) {
                continue;
            }

            // SIEMPRE sumamos esta salida al total global
            $salidasBrutas += $qty;

            $snapRaw = $row->asignacion_cert_esp;

            // Sin snapshot → lo guardamos para tratar FIFO después
            if (is_null($snapRaw) || $snapRaw === '') {
                $salidasNoSnapRows[] = $row;
                continue;
            }

            // Snapshot presente → lo intentamos decodificar
            if (is_string($snapRaw)) {
                $detalle = json_decode($snapRaw, true) ?: [];
            } elseif (is_array($snapRaw)) {
                $detalle = $snapRaw;
            } else {
                $detalle = [];
            }

            if (empty($detalle)) {
                // Snapshot vacío o inválido → lo tratamos como sin snapshot
                $salidasNoSnapRows[] = $row;
                continue;
            }

            foreach ($detalle as $a) {
                $cert = strtoupper(trim((string) ($a['certificacion'] ?? '')));
                $esp = strtoupper(trim((string) ($a['especie'] ?? '')));
                $q = (float) ($a['cantidad'] ?? 0);

                if (!$cert || !$esp || $q <= 0) {
                    continue;
                }

                $key = "{$cert}|{$esp}";
                $salidasSnapshotByKey[$key] = ($salidasSnapshotByKey[$key] ?? 0.0) + $q;
            }
        }

        // === 3) SALIDAS SIN snapshot: FIFO SOBRE LA COLA GLOBAL DE ENTRADAS,
        //     PERO SOLO SI HAY STOCK SUFICIENTE PARA CUBRIR TODA LA SALIDA ===
        $consumoNoSnapByKey = [];

        foreach ($salidasNoSnapRows as $rowSalida) {
            $rest = (float) $rowSalida->cantidad;
            $fechaSalida = $rowSalida->fecha;

            if ($rest <= 0) {
                continue;
            }

            // 3.1) Calculamos cuánto stock FIFO queda disponible
            //      en entradas con fecha <= fecha de la salida
            $stockDisponible = 0.0;
            foreach ($lotesFIFO as $lote) {
                if ($lote['qty'] <= 0) {
                    continue;
                }
                if ($lote['fecha'] > $fechaSalida) {
                    continue;
                }
                $stockDisponible += $lote['qty'];
            }

            // Si NO hay suficiente stock para cubrir toda la salida,
            // NO consumimos nada por FIFO. Toda esa salida queda
            // "sin trazabilidad" (solo cuenta en salidas_totales).
            if ($stockDisponible + 1e-6 < $rest) {
                continue;
            }

            // 3.2) Sí hay stock suficiente → aplicamos FIFO real
            //      sobre los lotes con fecha <= fechaSalida
            for ($i = 0; $i < count($lotesFIFO) && $rest > 0; $i++) {
                if ($lotesFIFO[$i]['qty'] <= 0) {
                    continue;
                }
                if ($lotesFIFO[$i]['fecha'] > $fechaSalida) {
                    continue;
                }

                $usa = min($lotesFIFO[$i]['qty'], $rest);
                $key = $lotesFIFO[$i]['key'];

                $consumoNoSnapByKey[$key] = ($consumoNoSnapByKey[$key] ?? 0.0) + $usa;

                $lotesFIFO[$i]['qty'] -= $usa;
                $rest -= $usa;
            }

            // Aquí, por construcción, $rest debería acabar en 0, porque
            // ya hemos comprobado que stockDisponible >= cantidad de la salida.
        }

        // === 4) AJUSTES MANUALES (regularizaciones) ===
        $ajustesRows = DB::table('ajustes_stock')
            ->where('almacen_intermedio_id', $almacen->id)
            ->select('certificacion', 'especie', DB::raw('SUM(delta_m3) as total'))
            ->groupBy('certificacion', 'especie')
            ->get();

        $ajustesByKey = [];
        foreach ($ajustesRows as $a) {
            $key = strtoupper(trim($a->certificacion)) . '|' . strtoupper(trim($a->especie));
            $ajustesByKey[$key] = (float) $a->total;
        }

        // === 5) SALIDAS finales = snapshot + FIFO sin snapshot trazable ===
        $salidasByKey = [];
        $allSalidaKeys = array_unique(array_merge(
            array_keys($salidasSnapshotByKey),
            array_keys($consumoNoSnapByKey),
        ));

        foreach ($allSalidaKeys as $key) {
            $salidasByKey[$key] =
                (float) ($salidasSnapshotByKey[$key] ?? 0.0) +
                (float) ($consumoNoSnapByKey[$key] ?? 0.0);
        }

        // === 6) DISPONIBLE = ENTRADAS - SALIDAS + AJUSTES ===
        $disponible = [];
        $allKeys = array_unique(array_merge(
            array_keys($entradasByKey),
            array_keys($salidasByKey),
            array_keys($ajustesByKey),
        ));

        foreach ($allKeys as $key) {
            $in = (float) ($entradasByKey[$key] ?? 0.0);
            $out = (float) ($salidasByKey[$key] ?? 0.0);
            $adj = (float) ($ajustesByKey[$key] ?? 0.0);

            // Si quieres ver negativos “cantosos”, quita el max(0.0, ...)
            $disponible[$key] = max(0.0, $in - $out + $adj);
        }

        return [
            'entradas' => $entradasByKey,
            'salidas_total' => $salidasBrutas,   // TODAS las salidas (camiones)
            'salidas' => $salidasByKey,    // detalle por cert|esp (solo las trazadas)
            'ajustes' => $ajustesByKey,
            'disponible' => $disponible,      // por cert|esp (con FIFO y ajustes)
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
