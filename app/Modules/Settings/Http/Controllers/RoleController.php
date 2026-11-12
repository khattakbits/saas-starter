<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Authority;
use App\Core\Support\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\RoleRequest;
use App\Modules\Settings\Http\Resources\RoleRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use KhattakBits\Core\Support\Audit;
use KhattakBits\Core\Support\TableQuery;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Roles/Index');
    }

    public function data(Request $request)
    {
        $query = Role::query()
            ->withCount(['users', 'permissions'])
            ->when(! Authority::isSuper($request->user()), fn ($q) => $q->with('permissions:id,name'));

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'name' => 'name'],
            'default' => 'name',
            'dir' => 'asc',
            'count' => true,
            'search' => fn ($q, $term) => $q->where('name', 'like', $term.'%'),
        ], RoleRow::class);
    }

    public function create()
    {
        return Inertia::render('Settings/Roles/Form', [
            'role' => null,
            'groups' => app(PermissionCatalog::class)->groups(),
            'granted' => [],
        ]);
    }

    public function store(RoleRequest $request)
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);
            Audit::log('role.created', $role, $role->name, ['permissions' => count($data['permissions'] ?? [])]);

            return $role;
        });

        return redirect("/settings/roles/{$role->id}")->with('success', 'Role created.');
    }

    public function edit(Request $request, Role $role)
    {
        abort_unless(Authority::canEditRole($request->user(), $role), 403);
        $role->load('permissions');

        return Inertia::render('Settings/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'system' => $role->name === 'super-admin',
                'users' => $role->users()->count(),
            ],
            'groups' => app(PermissionCatalog::class)->groups(),
            'granted' => $role->permissions->pluck('name')->values(),
        ]);
    }

    public function update(RoleRequest $request, Role $role)
    {
        abort_if($role->name === 'super-admin', 403);

        $data = $request->validated();

        DB::transaction(function () use ($role, $data) {
            $names = fn () => $role->permissions()->pluck('name')->sort()->values()->all();
            $before = ['name' => $role->name, 'permissions' => $names()];

            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);

            Audit::log('role.updated', $role, $role->name, [
                'changes' => Audit::changes($before, ['name' => $role->name, 'permissions' => $names()]),
            ]);
        });

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Request $request, Role $role)
    {
        abort_if($role->name === 'super-admin', 403);
        abort_unless(Authority::canEditRole($request->user(), $role), 403);

        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Remove it from them first.');
        }

        $name = $role->name;
        DB::transaction(function () use ($role, $name) {
            $role->delete();
            Audit::log('role.deleted', null, $name);
        });

        return redirect('/settings/roles')->with('success', 'Role deleted.');
    }
}
