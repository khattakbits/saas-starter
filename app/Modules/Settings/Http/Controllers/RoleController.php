<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Audit;
use App\Core\Support\PermissionCatalog;
use App\Core\Support\TableQuery;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\RoleRequest;
use App\Modules\Settings\Http\Resources\RoleRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Roles/Index');
    }

    public function data(Request $request)
    {
        $query = Role::query()->withCount(['users', 'permissions']);

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'name' => 'name'],
            'default' => 'name',
            'dir' => 'asc',
            'count' => true,
            'search' => fn($q, $term) => $q->where('name', 'like', $term . '%'),
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

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        Audit::log('role.created', $role, $role->name, ['permissions' => count($data['permissions'] ?? [])]);

        return redirect("/settings/roles/{$role->id}")->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
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

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        Audit::log('role.updated', $role, $role->name, ['permissions' => count($data['permissions'] ?? [])]);

        return back()->with('success', 'Role saved.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $deleted = 0;
        $skipped = 0;

        foreach (Role::query()->whereIn('id', $data['ids'])->get() as $role) {
            if ($role->name === 'super-admin' || $role->users()->exists()) {
                $skipped++;
                continue;
            }

            $name = $role->name;
            $role->delete();
            Audit::log('role.deleted', null, $name);
            $deleted++;
        }

        $message = "{$deleted} role(s) deleted.";
        if ($skipped) {
            $message .= " {$skipped} skipped (super admin and roles assigned to users cannot be deleted).";
        }

        return back()->with('success', $message);
    }

    public function destroy(Role $role)
    {
        abort_if($role->name === 'super-admin', 403);

        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Remove it from them first.');
        }

        $name = $role->name;
        $role->delete();
        Audit::log('role.deleted', null, $name);

        return redirect('/settings/roles')->with('success', 'Role deleted.');
    }
}