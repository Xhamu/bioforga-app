<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasRoles, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'apellidos',
        'nif',
        'email',
        'password',
        'empresa_bioforga',
        'proveedor_id',
        'telefono',
        'sector',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'sector' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $table = 'users';

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function referencias()
    {
        return $this->belongsToMany(Referencia::class, 'referencias_users', 'user_id', 'referencia_id')->withTimestamps()->withTrashed();
    }

    public function camiones()
    {
        return $this->belongsToMany(\App\Models\Camion::class, 'camion_user', 'user_id', 'camion_id');
    }

    public function maquinas()
    {
        return $this->belongsToMany(\App\Models\Maquina::class, 'maquina_user');
    }

    public function getNombreApellidosAttribute()
    {
        return $this->name . ' ' . $this->apellidos;
    }

    public function canImpersonate(): bool
    {
        return $this->hasAnyRole(['superadmin', 'administración']);
    }

    public function canBeImpersonated(): bool
    {
        if ($this->hasRole('superadmin') && auth()->user()?->hasRole('administrador')) {
            return false;
        }

        return true;
    }

    public function statuses()
    {
        return $this->hasMany(\App\Models\UserStatus::class);
    }

    public function activeStatus()
    {
        return $this->hasOne(\App\Models\UserStatus::class)
            ->whereNull('ended_at')
            ->latestOfMany('started_at');
    }

}
