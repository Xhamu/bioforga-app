<?php

// app/Support/StockNorm.php
namespace App\Support;

final class StockNorm
{
    public static function cert(?string $raw): string
    {
        $n = self::normalize($raw);
        $map = [
            'sureindustrial' => 'SURE INDUSTRIAL',
            'sureinduestrial' => 'SURE INDUSTRIAL', // typo frecuente
            'industrial' => 'SURE INDUSTRIAL',
            'sureforestal' => 'SURE FORESTAL',
            'sureforesal' => 'SURE FORESTAL',  // typo frecuente
            'forestal' => 'SURE FORESTAL',
            'sbp' => 'SBP',
            'pefc' => 'PEFC',
        ];
        foreach ($map as $needle => $val) {
            if (str_contains($n, $needle))
                return $val;
        }
        // fallback
        return strtoupper(trim((string) $raw)) ?: 'SURE FORESTAL';
    }

    public static function especieFromParte($parte): string
    {
        $raw = $parte?->tipo_biomasa;
        if (is_array($raw)) {
            $vals = collect($raw)->filter()->map(fn($v) => trim((string) $v))->values();
            if ($vals->count() === 1)
                $raw = $vals->first();
            else
                $raw = $vals->count() > 1 ? 'Mixto' : 'Sin especificar';
        }
        return self::especie($raw);
    }

    /** Normaliza especie desde texto genérico (prioridad, etc.) */
    public static function especie(?string $raw): string
    {
        $n = self::normalize($raw);
        return match (true) {
            str_contains($n, 'pino') => 'PINO',
            str_contains($n, 'eucalipt') => 'EUCALIPTO',
            str_contains($n, 'acacia') => 'ACACIA',
            str_contains($n, 'frond') => 'FRONDOSA',
            default => 'OTROS',
        };
    }

    private static function normalize(?string $s): string
    {
        $s = mb_strtolower((string) ($s ?? ''), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/[\s_\-]+/u', '', $s);
    }
}
