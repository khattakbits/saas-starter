<?php

namespace App\Core\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

final class PermissionCatalog
{
    private const STANDARD = ['view', 'create', 'update', 'delete'];

    public function __construct(private ModuleRegistry $registry)
    {
    }

    public function groups(): array
    {
        $moduleLabels = [];
        $moduleOrder = [];
        $docLabels = [];

        foreach ($this->registry->all() as $module) {
            $def = $module['def'];
            $moduleLabels[$def['key']] = $def['label'];
            $moduleOrder[] = $def['key'];
            foreach ($def['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    if ($item['can'] && str_ends_with($item['can'], '.view')) {
                        $docLabels[substr($item['can'], 0, -5)] = $item['label'];
                    }
                }
            }
        }

        $tree = [];
        foreach (Permission::query()->orderBy('name')->pluck('name') as $name) {
            $parts = explode('.', $name);
            $action = array_pop($parts);
            if (!$parts) {
                continue;
            }
            $tree[$parts[0]][implode('.', $parts)][] = $action;
        }

        $groups = [];
        foreach ($tree as $moduleKey => $docs) {
            $columns = [];
            $rows = [];
            foreach ($docs as $doc => $actions) {
                $columns = array_merge($columns, $actions);
                $rows[] = [
                    'key' => $doc,
                    'label' => $docLabels[$doc] ?? Str::headline(last(explode('.', $doc))),
                    'actions' => $actions,
                ];
            }
            $columns = array_values(array_unique($columns));
            usort($columns, fn($a, $b) => self::rank($a) <=> self::rank($b) ?: strcmp($a, $b));

            $order = array_search($moduleKey, $moduleOrder, true);
            $groups[] = [
                'order' => $order === false ? 999 : $order,
                'key' => $moduleKey,
                'label' => $moduleLabels[$moduleKey] ?? Str::headline($moduleKey),
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        usort($groups, fn($a, $b) => $a['order'] <=> $b['order']);

        return array_map(function ($g) {
            unset($g['order']);
            return $g;
        }, $groups);
    }

    private static function rank(string $action): int
    {
        $i = array_search($action, self::STANDARD, true);

        return $i === false ? 99 : $i;
    }
}