<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use ReflectionClass;
use ReflectionMethod;

class PoliciesShieldStyleSeeder extends Seeder
{
    // abilities estándar de Policies/Filament Shield
    protected array $abilityPrefixes = [
        'viewAny'         => 'view_any_',
        'view'            => 'view_',
        'create'          => 'create_',
        'update'          => 'update_',
        'delete'          => 'delete_',
        'deleteAny'       => 'delete_any_',
        'forceDelete'     => 'force_delete_',
        'forceDeleteAny'  => 'force_delete_any_',
        'restore'         => 'restore_',
        'restoreAny'      => 'restore_any_',
        'replicate'       => 'replicate_',
        'reorder'         => 'reorder_',
    ];

    // matriz rol → abilities permitidas
    protected array $roleMatrix = [
        'superadmin'     => ['*'], // todo
        'administración' => ['viewAny','view','create','update','delete','restore','replicate','reorder'],
        'técnico'        => ['viewAny','view','create','update'],
        'transportista'  => ['viewAny','view','create'],
    ];

    public function run(): void
    {
        // limpia caché de spatie/permission
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        $policies = Gate::policies(); // [Model::class => Policy::class]

        if (empty($policies)) {
            $this->command->warn('⚠️  No hay policies registradas. Revisa AuthServiceProvider::policies.');
        }

        $allPermissionNames = [];

        foreach ($policies as $modelClass => $policyClass) {
            $resourceSlug = $this->resourceSlugWithColons($modelClass); // p.ej. 'parte::trabajo::ayudante'
            $abilities    = $this->publicAbilityMethods($policyClass);

            foreach ($abilities as $ability) {
                if (!isset($this->abilityPrefixes[$ability])) {
                    // Si hay métodos custom en la Policy, los ignoramos aquí.
                    continue;
                }
                $permName = $this->abilityPrefixes[$ability] . $resourceSlug;

                Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => $guard]
                );
                $allPermissionNames[] = $permName;
            }
        }

        // Roles
        foreach ($this->roleMatrix as $roleName => $allowedAbilities) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);

            if (in_array('*', $allowedAbilities, true)) {
                $role->syncPermissions($allPermissionNames);
                continue;
            }

            // construye permisos para cada modelo con esos abilities
            $permsForRole = [];
            foreach ($policies as $modelClass => $_) {
                $slug = $this->resourceSlugWithColons($modelClass);
                foreach ($allowedAbilities as $ability) {
                    if (!isset($this->abilityPrefixes[$ability])) continue;
                    $name = $this->abilityPrefixes[$ability] . $slug;
                    if (Permission::where('name', $name)->exists()) {
                        $permsForRole[] = $name;
                    }
                }
            }
            $role->syncPermissions(array_values(array_unique($permsForRole)));
        }

        $this->command->info('✅ PoliciesShieldStyleSeeder: permisos (estilo Shield) y roles sincronizados.');
    }

    /**
     * Convierte App\Models\ParteTrabajoAyudante → "parte::trabajo::ayudante"
     */
    protected function resourceSlugWithColons(string $modelClass): string
    {
        // 'ParteTrabajoAyudante' → 'parte_trabajo_ayudante'
        $snake = Str::snake(class_basename($modelClass));
        // 'parte_trabajo_ayudante' → 'parte::trabajo::ayudante'
        return str_replace('_', '::', $snake);
    }

    /**
     * Devuelve los métodos públicos de la Policy relevantes como abilities.
     */
    protected function publicAbilityMethods(string $policyClass): array
    {
        $rc = new ReflectionClass($policyClass);

        return collect($rc->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')
            ->reject(fn ($name) => in_array($name, ['__construct','before'], true) || str_starts_with($name, '__'))
            ->values()
            ->all();
    }
}
