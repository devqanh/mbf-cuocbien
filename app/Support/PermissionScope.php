<?php

namespace App\Support;

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Chống leo thang quyền ở /users và /roles: người không phải super_admin chỉ được cấp / sửa
 * trong PHẠM VI QUYỀN CỦA CHÍNH MÌNH. Nếu không, ai có users.update hoặc roles.update cũng
 * tự nâng mình lên toàn quyền (gán vai trò super_admin, thêm quyền cho vai trò đang giữ…).
 */
class PermissionScope
{
    public static function isSuper(User $u): bool
    {
        return $u->hasRole('super_admin');
    }

    /** Quyền trong $perms mà $actor KHÔNG có. */
    public static function missing(User $actor, iterable $perms): array
    {
        $own = $actor->getAllPermissions()->pluck('name')->all();
        return array_values(array_diff(collect($perms)->all(), $own));
    }

    /** Vai trò $actor được phép gán cho người khác: không phải super_admin, quyền nằm trong phạm vi của $actor. */
    public static function assignableRoles(User $actor)
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        if (self::isSuper($actor)) return $roles;
        return $roles->filter(fn ($r) => $r->name !== 'super_admin'
            && ! self::missing($actor, $r->permissions->pluck('name')))->values();
    }

    /** Sửa / xoá / reset 2FA tài khoản $target: không đụng super_admin hay người có quyền vượt mình. */
    public static function assertCanManageUser(User $actor, User $target): void
    {
        if (self::isSuper($actor)) return;
        if (self::isSuper($target)) {
            throw new BusinessRuleException('Chỉ Quản trị toàn quyền mới được thay đổi tài khoản Quản trị toàn quyền.', 403);
        }
        if (self::missing($actor, $target->getAllPermissions()->pluck('name'))) {
            throw new BusinessRuleException("Tài khoản {$target->name} có quyền vượt quá quyền của bạn — không thể thay đổi.", 403);
        }
    }

    /** Gán $roleNames cho $target (null = tài khoản mới). Không ai tự đổi vai trò của chính mình. */
    public static function assertCanAssignRoles(User $actor, array $roleNames, ?User $target = null): void
    {
        if ($target && $target->id === $actor->id) {
            $current = $target->roles->pluck('name')->sort()->values()->all();
            if ($current !== collect($roleNames)->sort()->values()->all()) {
                throw new BusinessRuleException('Không thể tự đổi vai trò của chính mình — nhờ quản trị khác thực hiện.', 403);
            }
            return;
        }
        if (self::isSuper($actor)) return;
        $allowed = self::assignableRoles($actor)->pluck('name')->all();
        $denied = array_diff($roleNames, $allowed);
        if ($denied) {
            throw new BusinessRuleException('Bạn không được gán vai trò: ' . implode(', ', $denied) . ' (vượt quá quyền của bạn).', 403);
        }
    }

    /** Tạo (role = null) / sửa / xoá vai trò với danh sách quyền $permissionNames. */
    public static function assertCanEditRole(User $actor, ?Role $role, array $permissionNames = []): void
    {
        if (self::isSuper($actor)) return;
        if ($role && $role->name === 'super_admin') {
            throw new BusinessRuleException('Chỉ Quản trị toàn quyền mới được sửa vai trò Quản trị toàn quyền.', 403);
        }
        if ($role && $actor->hasRole($role->name)) {
            throw new BusinessRuleException('Không thể sửa vai trò bạn đang giữ — nhờ quản trị khác thực hiện.', 403);
        }
        $perms = array_merge($permissionNames, $role ? $role->permissions->pluck('name')->all() : []);
        if ($missing = self::missing($actor, $perms)) {
            throw new BusinessRuleException('Vai trò này có quyền bạn không có (' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? '…' : '') . ') — không thể thay đổi.', 403);
        }
    }
}
