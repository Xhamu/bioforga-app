<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class EliminarLogsDuplicados extends Command
{
    protected $signature = 'logs:eliminar-duplicados';
    protected $description = 'Elimina registros duplicados de activity_log basados en log_name, description y created_at (sin microsegundos)';

    public function handle()
    {
        $this->info('Buscando duplicados en la tabla activity_log...');

        $logs = DB::table('activity_log')
            ->select('id', 'log_name', 'description', DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") as fecha'))
            ->orderBy('created_at')
            ->get();

        $hashes = [];
        $toDelete = [];

        foreach ($logs as $log) {
            $hash = md5($log->log_name . $log->description . $log->fecha);

            if (isset($hashes[$hash])) {
                $toDelete[] = $log->id;
            } else {
                $hashes[$hash] = true;
            }
        }

        if (!empty($toDelete)) {
            Activity::whereIn('id', $toDelete)->delete();
            $this->info("Eliminados " . count($toDelete) . " registros duplicados.");
        } else {
            $this->info("No se encontraron duplicados.");
        }
    }
}
