<?php

namespace App\Core\Support;

final class ModuleRegistry
{
    private ?array $modules = null;

    /** @return array<string, array{name: string, path: string, def: array}> */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $modules = [];
        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $file = $dir . '/menu.php';
            if (!is_file($file)) {
                continue;
            }
            $def = require $file;
            $modules[$def['key']] = ['name' => basename($dir), 'path' => $dir, 'def' => $def];
        }

        uasort($modules, fn($a, $b) => ($a['def']['order'] ?? 100) <=> ($b['def']['order'] ?? 100));

        return $this->modules = $modules;
    }
}