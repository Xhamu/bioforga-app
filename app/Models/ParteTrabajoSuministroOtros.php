<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ParteTrabajoSuministroOtros extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'descripcion',

        'fecha_hora_inicio_otros',
        'gps_inicio_otros',

        'fecha_hora_fin_otros',
        'gps_fin_otros',

        'observaciones'
    ];

    protected $casts = [
        'fecha_hora_inicio_otros' => 'datetime',
        'fecha_hora_fin_otros' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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

    protected $table = 'parte_trabajo_suministro_otros';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
