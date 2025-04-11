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
        'mantenimientos'
    ];

    protected $casts = [
        'averias' => 'array',
        'mantenimientos' => 'array',
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

    protected $table = 'maquinas';

    public function itvs()
    {
        return $this->hasMany(ITV_Maquinas::class);
    }

    public function getMarcaModeloAttribute()
    {
        return $this->marca . ' ' . $this->modelo;
    }

}
