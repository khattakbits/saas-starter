<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Settings\Http\Requests\UserRequest;
use App\Modules\Settings\Http\Resources\UserRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use KhattakBits\Core\Support\Audit;
use KhattakBits\Core\Support\Authority;
use KhattakBits\Core\Support\TableQuery;
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
                'role' => fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)),
            ],
            'search' => fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', $term.'%')->orWhere('email', 'like', $term.'%')),
        ], UserRow::class);
    }

    public function create(Request $request)
    {
        return Inertia::render('Settings/Users/Form', [
            'user' => null,
            'roles' => Authority::assignableRoles($request->user()),
        ]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($request, $data) {
            $user = User::forceCreate([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],            // 'hashed' cast khud hash karta hai
                'is_active' => $request->boolean('is_active', true),
            ]);
            $user->syncRoles($data['roles'] ?? []);
            Audit::log('user.created', $user, $user->email, ['roles' => $data['roles'] ?? []]);

            return $user;
        });

        return redirect("/settings/users/{$user->id}")->with('success', 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        abort_unless(Authority::canManageUser($request->user(), $user), 403);

        return Inertia::render('Settings/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->getRoleNames()->values(),
                'is_self' => $user->is($request->user()),
                'last_login' => $user->last_login_at?->toIso8601String(),
            ],
            'roles' => Authority::assignableRoles($request->user()),
        ]);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $user, $data) {
            $before = $this->snapshot($user);

            $attributes = ['name' => $data['name'], 'email' => strtolower($data['email'])];
            if ($request->has('is_active')) {
                $attributes['is_active'] = $request->boolean('is_active');   // na bheja to jaisa tha waisa
            }
            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->forceFill($attributes)->save();
            if ($request->has('roles')) {
                $user->syncRoles($data['roles'] ?? []);
            }

            Audit::log('user.updated', $user, $user->email, [
                'changes' => Audit::changes($before, $this->snapshot($user->load('roles'))),
                'password_changed' => ! empty($data['password']),
            ]);
        });

        return back()->with('success', 'User saved.');
    }

    private function snapshot(User $u): array
    {
        return [
            'name' => $u->name,
            'email' => $u->email,
            'is_active' => (bool) $u->is_active,
            'roles' => $u->getRoleNames()->sort()->values()->all(),
        ];
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:2500'],
            'ids.*' => ['integer'],
            'active' => ['required', 'boolean'],
        ]);

        $active = $request->boolean('active');
        $actor = $request->user();
        $changed = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $active, $actor, &$changed, &$skipped) {
            $users = User::query()->with(['roles.permissions', 'permissions'])->whereIn('id', $data['ids'])->get();

            foreach ($users as $u) {
                if ($u->is($actor) || $u->hasRole('super-admin') || ! Authority::canManageUser($actor, $u)) {
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
        });

        $message = "{$changed} user(s) ".($active ? 'enabled' : 'disabled').'.';
        if ($skipped) {
            $message .= " {$skipped} skipped (your own account, super admins and users with more access than you).";
        }

        return back()->with('success', $message);
    }
}
