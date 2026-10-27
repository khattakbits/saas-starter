<?php

namespace App\Core\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TableQuery
{
    /**
     * $config = [
     *   'sortable' => ['name' => 'name', 'status' => 'is_active'],   // frontend key => DB column (NOT NULL, indexed)
     *   'default'  => 'name',
     *   'dir'      => 'asc',                                         // default direction
     *   'filters'  => ['event', 'status' => fn ($q, $v) => ...],     // column naam ya closure
     *   'search'   => fn ($q, string $term) => ...,
     *   'count'    => true,                                          // total ginna (bohat bari tables par false)
     * ]
     */
    public static function json(Builder $query, Request $request, array $config, string $resource): AnonymousResourceCollection
    {
        self::filter($query, $request, $config);

        $extra = [];
        if ($config['count'] ?? false) {
            $extra['total'] = (clone $query)->count();
        }

        self::sort($query, $request, $config);
        $perPage = min(2500, max(10, (int) $request->query('per_page', 20)));

        return $resource::collection($query->cursorPaginate($perPage)->withQueryString())->additional($extra);
    }

    private static function filter(Builder $query, Request $request, array $config): void
    {
        $values = (array) $request->query('f', []);

        foreach ($config['filters'] ?? [] as $key => $def) {
            $name = is_int($key) ? $def : $key;
            $value = $values[$name] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $def instanceof Closure ? $def($query, $value) : $query->where($name, $value);
        }

        $term = trim((string) $request->query('q', ''));
        if ($term !== '' && isset($config['search'])) {
            ($config['search'])($query, addcslashes($term, '%_\\'));
        }
    }

    private static function sort(Builder $query, Request $request, array $config): void
    {
        $map = $config['sortable'];
        $column = $map[(string) $request->query('sort', $config['default'])] ?? $map[$config['default']];
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? $request->query('dir') : ($config['dir'] ?? 'desc');

        $query->orderBy($column, $dir);
        if ($column !== 'id') {
            $query->orderBy('id', $dir);   // tie-break, cursor ke liye zaroori
        }
    }
}