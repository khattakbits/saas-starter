<?php

namespace Database\Seeders;

use App\Core\Support\DocumentRegistry;
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

        foreach (app(DocumentRegistry::class)->all() as $cfg) {
            foreach (DocumentRegistry::ACTIONS as $action) {
                $names[] = $cfg['can'].'.'.$action;
            }
        }

        $names = array_values(array_unique($names));

        $created = [];
        foreach ($names as $name) {
            if (Permission::findOrCreate($name, 'web')->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        Role::findOrCreate('super-admin', 'web');   // Gate::before se sab kuch

        // Pehli baar poori permissions; baad mein sirf NAYI permissions add hoti hain, kisi se kuch hatayi nahi jati
        $defaults = [
            'manager' => $names,
            'viewer' => array_values(array_filter($names, fn ($n) => str_ends_with($n, '.view'))),
        ];
        foreach ($defaults as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $grant = $role->wasRecentlyCreated ? $perms : array_values(array_intersect($created, $perms));
            if ($grant) {
                $role->givePermissionTo($grant);
            }
        }
    }
}
