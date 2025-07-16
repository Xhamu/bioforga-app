<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use function PHPUnit\Framework\isNull;


class Camion extends Model
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
        'matricula_cabeza',
        'matricula_remolque',
        'proveedor_id',
        'es_propio'
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

    protected $table = 'camiones';

    public function getMarcaModeloAttribute()
    {
        return $this->marca . ' ' . $this->modelo;
    }

    public function usuarios()
    {
        return $this->belongsToMany(\App\Models\User::class);
    }

    protected function getProveedorMostrarAttribute()
    {
        if (!is_null($this->proveedor_id)) {
            $proveedor = Proveedor::find($this->proveedor_id);

            return $proveedor->razon_social;
        }

        return 'Bioforga';
    }
}
