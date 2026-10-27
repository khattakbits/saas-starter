<?php

namespace Database\Seeders;

use App\Core\Support\ModuleRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [];
        foreach (app(ModuleRegistry::class)->all() as $module) {
            foreach ($module['def']['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    if ($item['can']) {
                        $names[] = $item['can'];
                    }
                }
            }
            array_push($names, ...($module['def']['permissions'] ?? []));
        }
        $names = array_values(array_unique($names));

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super-admin', 'web');   // Gate::before se sab kuch

        // Pehli baar hi permissions milti hain; baad mein Roles screen se manage hoti hain
        $defaults = [
            'manager' => $names,
            'viewer' => array_values(array_filter($names, fn($n) => str_ends_with($n, '.view'))),
        ];
        foreach ($defaults as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($perms);
            }
        }
    }
}