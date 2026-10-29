<?php

namespace App\Core\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class DocumentRegistry
{
    public const ACTIONS = ['view', 'create', 'update', 'delete', 'submit', 'cancel', 'amend'];

    private ?array $docs = null;

    public function __construct(private ModuleRegistry $modules) {}

    /** @return array<string, array{type: string, label: string, model: string, can: string, url: ?string}> */
    public function all(): array
    {
        if ($this->docs !== null) {
            return $this->docs;
        }

        $docs = [];
        foreach ($this->modules->all() as $module) {
            foreach ($module['def']['documents'] ?? [] as $type => $cfg) {
                $docs[$type] = [
                    'type' => $type,
                    'label' => $cfg['label'],
                    'model' => $cfg['model'],
                    'can' => $cfg['can'],
                    'url' => $cfg['url'] ?? null,
                ];
            }
        }

        return $this->docs = $docs;
    }

    public function get(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    public function typeOf(string $class): string
    {
        foreach ($this->all() as $type => $cfg) {
            if ($cfg['model'] === $class) {
                return $type;
            }
        }

        return Str::snake(class_basename($class));
    }

    public function can(User $user, array $cfg, string $action): bool
    {
        return $user->can($cfg['can'].'.'.$action);
    }
}
