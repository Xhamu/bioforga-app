<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE referencias
            MODIFY estado ENUM('abierto','en_proceso','cerrado','cerrado_no_procede')
            NOT NULL DEFAULT 'abierto'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE referencias
            MODIFY estado ENUM('abierto','en_proceso','cerrado')
            NOT NULL DEFAULT 'abierto'
        ");
    }
};
