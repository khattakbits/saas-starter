<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Audit;
use App\Core\Support\TableQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Settings\Http\Requests\UserRequest;
use App\Modules\Settings\Http\Resources\UserRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Users/Index', [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function data(Request $request)
    {
        $query = User::query()
            ->setEagerLoads([])                      // User model ke $with ko list mein nahi chahiye
            ->with('roles:id,name')
            ->select('id', 'name', 'email', 'is_active', 'last_login_at');

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'name' => 'name', 'email' => 'email', 'status' => 'is_active'],
            'default' => 'name',
            'dir' => 'asc',
            'count' => true,
            'filters' => [
                'status' => function ($q, $v) {
                    if (in_array($v, ['active', 'disabled'], true)) {
                        $q->where('is_active', $v === 'active');
                    }
                },
                'role' => fn($q, $v) => $q->whereHas('roles', fn($r) => $r->where('name', $v)),
            ],
            'search' => fn($q, $term) => $q->where(fn($w) => $w->where('name', 'like', $term . '%')->orWhere('email', 'like', $term . '%')),
        ], UserRow::class);
    }

    public function create(Request $request)
    {
        return Inertia::render('Settings/Users/Form', [
            'user' => null,
            'roles' => $this->roleOptions($request),
        ]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = User::forceCreate([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],            // 'hashed' cast khud hash karta hai
            'is_active' => $request->boolean('is_active', true),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        Audit::log('user.created', $user, $user->email, ['roles' => $data['roles'] ?? []]);

        return redirect("/settings/users/{$user->id}")->with('success', 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        abort_if(!$request->user()->hasRole('super-admin') && $user->hasRole('super-admin'), 403);

        return Inertia::render('Settings/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'is_self' => $user->is($request->user()),
                'last_login' => $user->last_login_at?->format('d-m-Y H:i') ?? 'Never',
            ],
            'roles' => $this->roleOptions($request),
        ]);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        $attributes = [
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'is_active' => $request->boolean('is_active'),
        ];
        if (!empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->forceFill($attributes)->save();
        $user->syncRoles($data['roles'] ?? []);

        Audit::log('user.updated', $user, $user->email, ['password_changed' => !empty($data['password'])]);

        return back()->with('success', 'User saved.');
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'active' => ['required', 'boolean'],
        ]);

        $active = $request->boolean('active');
        $actor = $request->user();
        $changed = 0;
        $skipped = 0;

        foreach (User::query()->whereIn('id', $data['ids'])->get() as $u) {
            if ($u->is($actor) || $u->hasRole('super-admin')) {
                $skipped++;
                continue;
            }
            if ((bool) $u->is_active === $active) {
                continue;
            }
            $u->forceFill(['is_active' => $active])->save();
            Audit::log('user.status', $u, $u->email, ['active' => $active]);
            $changed++;
        }

        $message = "{$changed} user(s) " . ($active ? 'enabled' : 'disabled') . '.';
        if ($skipped) {
            $message .= " {$skipped} skipped (your own account and super admins are changed one by one).";
        }

        return back()->with('success', $message);
    }

    private function roleOptions(Request $request): array
    {
        return Role::query()
            ->when(!$request->user()->hasRole('super-admin'), fn($q) => $q->where('name', '!=', 'super-admin'))
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn($r) => ['name' => $r->name, 'permissions' => $r->permissions_count])
            ->all();
    }
}