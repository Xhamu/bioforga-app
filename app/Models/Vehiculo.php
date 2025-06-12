<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;


class Vehiculo extends Model
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
        'matricula',
        'conductor_habitual',

        'usuario_id'
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
        return [
            'conductor_habitual' => 'array',
        ];
    }

    protected $table = 'vehiculos';

    public function itvs()
    {
        return $this->hasMany(ITV_Vehiculos::class);
    }

    public function getMarcaModeloAttribute()
    {
        return $this->marca . ' ' . $this->modelo;
    }
}
