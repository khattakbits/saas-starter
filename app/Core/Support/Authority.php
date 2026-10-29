<?php

namespace App\Core\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class Authority
{
    public static function isSuper(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /** @return string[] */
    public static function permissionsOf(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->all();
    }

    /** Wo permissions jo actor ke paas nahi hain (super-admin ke liye hamesha khali) */
    public static function missing(User $actor, array $names): array
    {
        if (self::isSuper($actor)) {
            return [];
        }

        return array_values(array_diff($names, self::permissionsOf($actor)));
    }

    /** Roles sirf wo jin ki saari permissions actor ke paas hon; super-admin role sirf super-admin de */
    public static function canAssignRoles(User $actor, array $roleNames): bool
    {
        if (self::isSuper($actor)) {
            return true;
        }
        if (in_array('super-admin', $roleNames, true)) {
            return false;
        }

        $own = self::permissionsOf($actor);
        $roles = Role::query()->whereIn('name', $roleNames)->with('permissions:id,name')->get();

        foreach ($roles as $role) {
            if (array_diff($role->permissions->pluck('name')->all(), $own)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, array{name: string, permissions: int}> */
    public static function assignableRoles(User $actor): array
    {
        $own = self::isSuper($actor) ? null : self::permissionsOf($actor);

        return Role::query()->with('permissions:id,name')->orderBy('name')->get()
            ->filter(fn ($r) => $own === null
                || ($r->name !== 'super-admin' && ! array_diff($r->permissions->pluck('name')->all(), $own)))
            ->map(fn ($r) => ['name' => $r->name, 'permissions' => $r->permissions->count()])
            ->values()
            ->all();
    }

    /** Doosre user ko sirf wo edit kar sakta hai jis ka ikhtiyar actor se zyada na ho (password-takeover se bachao) */
    public static function canManageUser(User $actor, User $target): bool
    {
        if (self::isSuper($actor) || $target->is($actor)) {
            return true;
        }
        if ($target->hasRole('super-admin')) {
            return false;
        }

        return ! array_diff(self::permissionsOf($target), self::permissionsOf($actor));
    }

    /** Apni role ya apne se bari role edit/delete nahi ho sakti */
    public static function canEditRole(User $actor, Role $role): bool
    {
        if (self::isSuper($actor)) {
            return true;
        }
        if ($role->name === 'super-admin') {
            return false;
        }
        if ($actor->loadMissing('roles')->roles->contains('id', $role->id)) {
            return false;
        }

        $role->loadMissing('permissions');

        return ! array_diff($role->permissions->pluck('name')->all(), self::permissionsOf($actor));
    }
}
