<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;


class Maquina extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'marca',
        'modelo',
        'tipo_trabajo',
        'operario_id',
        'proveedor_id',
        'averias',
        'mantenimientos',
        'tipo_consumo',
        'tipo_horas',
        'numero_bastidor',
        'numero_motor',
        'fabricante',
        'anio_fabricacion',
        'color',
        'numero_serie',
        'matricula'
    ];

    protected $casts = [
        'tipo_consumo' => 'array',
        'tipo_horas' => 'array',
        'averias' => 'array',
        'mantenimientos' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected $table = 'maquinas';

    public function itvs()
    {
        return $this->hasMany(ITV_Maquinas::class);
    }

    public function operarios()
    {
        return $this->belongsToMany(User::class, 'maquina_user');
    }

    public function getMarcaModeloAttribute()
    {
        return $this->marca . ' ' . $this->modelo;
    }

}
