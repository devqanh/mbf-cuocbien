<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles       = Role::with('permissions')->orderBy('id')->get();
        $permissions = Permission::orderBy('name')->get();

        // Gom nhóm theo prefix (vd: users.*, items.*)
        $grouped = $permissions->groupBy(fn ($p) => explode('.', $p->name)[0] ?? 'other');

        return view('roles.index', compact('roles', 'permissions', 'grouped'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:64', 'unique:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', "Đã tạo vai trò: {$role->name}");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:64', 'unique:roles,name,' . $role->id],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name !== $data['name'] && $role->name === 'super_admin') {
            return back()->with('error', 'Không thể đổi tên vai trò super_admin.');
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', "Đã cập nhật vai trò: {$role->name}");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return back()->with('error', 'Không thể xoá vai trò super_admin.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', "Vai trò '{$role->name}' đang được gán cho người dùng, không thể xoá.");
        }
        $name = $role->name;
        $role->delete();
        return back()->with('success', "Đã xoá vai trò: {$name}");
    }
}
