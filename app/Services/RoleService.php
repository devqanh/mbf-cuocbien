<?php

namespace App\Services;

use App\Exceptions\Domain\BusinessRuleException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleService
{
    /** Các role hệ thống — không cho đổi tên/xoá. */
    public const SYSTEM_ROLES = ['super_admin'];

    /**
     * Tạo role mới. Nếu $name rỗng, tự sinh slug từ display_name.
     */
    public function create(string $displayName, ?string $name, array $permissionNames = []): Role
    {
        $slug = $this->ensureUniqueSlug(! empty($name) ? $name : $this->makeSlug($displayName));

        $role = Role::create([
            'name'         => $slug,
            'display_name' => $displayName,
            'guard_name'   => 'web',
        ]);
        $role->syncPermissions($permissionNames);

        return $role;
    }

    /**
     * Update role. Không cho đổi tên role hệ thống.
     */
    public function update(Role $role, string $displayName, ?string $name, array $permissionNames = []): Role
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            // Khoá tên — chỉ cho đổi display_name + permissions
            $newName = $role->name;
        } else {
            $newName = ! empty($name) ? $name : $this->makeSlug($displayName);
            if ($newName !== $role->name) {
                $newName = $this->ensureUniqueSlug($newName, $role->id);
            }
        }

        $role->update([
            'name'         => $newName,
            'display_name' => $displayName,
        ]);
        $role->syncPermissions($permissionNames);

        return $role;
    }

    /**
     * Xoá role. Throw nếu là role hệ thống hoặc đang có người dùng.
     */
    public function delete(Role $role): void
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            throw new BusinessRuleException("Không thể xoá vai trò hệ thống '{$role->name}'.", 403);
        }
        if ($role->users()->exists()) {
            throw new BusinessRuleException(
                "Vai trò '{$role->display_name}' đang được gán cho người dùng, không thể xoá."
            );
        }
        $role->delete();
    }

    /** Sinh slug snake_case từ chuỗi có dấu tiếng Việt. */
    public function makeSlug(string $text): string
    {
        $slug = Str::of($text)->ascii()->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
        return $slug !== '' ? $slug : 'role_' . time();
    }

    /** Thêm hậu tố số nếu slug đã tồn tại. */
    public function ensureUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $i = 1;
        while (Role::where('name', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '_' . (++$i);
        }
        return $slug;
    }

    public function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }
}
