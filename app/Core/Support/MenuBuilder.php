<?php

namespace App\Core\Support;

use App\Models\User;
use KhattakBits\Core\Support\ModuleRegistry;

final class MenuBuilder
{
    public function __construct(private ModuleRegistry $registry) {}

    public function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $menu = [];
        foreach ($this->registry->all() as $module) {
            $def = $module['def'];
            $sections = [];

            foreach ($def['sections'] as $section) {
                $items = array_values(array_filter(
                    $section['items'],
                    fn ($i) => $i['can'] === null || $user->can($i['can'])
                ));
                if ($items) {
                    $sections[] = [
                        'title' => $section['title'],
                        'items' => array_map(fn ($i) => ['label' => $i['label'], 'href' => $i['href'], 'group' => $i['group'] ?? null], $items),
                    ];
                }
            }

            if ($sections) {
                $menu[] = [
                    'key' => $def['key'],
                    'label' => $def['label'],
                    'icon' => $def['icon'] ?? 'layers',
                    'prefix' => $def['prefix'],
                    'href' => $sections[0]['items'][0]['href'],   // module ka pehla item jis ka haq ho
                    'sections' => $sections,
                ];
            }
        }

        return $menu;
    }
}
