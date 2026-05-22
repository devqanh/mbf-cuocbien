<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function index()
    {
        $roles       = Role::with('permissions')->orderBy('id')->get();
        $permissions = Permission::orderBy('name')->get();

        $grouped = $permissions->groupBy(fn ($p) => explode('.', $p->name)[0] ?? 'other');

        return view('roles.index', [
            'roles'       => $roles,
            'permissions' => $permissions,
            'grouped'     => $grouped,
            'labels'      => config('permissions.permissions', []),
            'modules'     => config('permissions.modules', []),
            'roleMeta'    => config('permissions.roles', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $role = $this->roles->create(
            displayName:     $data['display_name'],
            name:            $data['name'] ?? null,
            permissionNames: $data['permissions'] ?? [],
        );

        return back()->with('success', "Đã tạo vai trò: {$role->display_name}");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateData($request, $role->id);
        $role = $this->roles->update(
            role:            $role,
            displayName:     $data['display_name'],
            name:            $data['name'] ?? null,
            permissionNames: $data['permissions'] ?? [],
        );

        return back()->with('success', "Đã cập nhật vai trò: {$role->display_name}");
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roles->delete($role);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã xoá vai trò: {$role->display_name}");
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'display_name'  => ['required', 'string', 'max:64'],
            'name'          => [
                'nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                'unique:roles,name' . ($ignoreId ? ",$ignoreId" : ''),
            ],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'display_name.required' => 'Vui lòng nhập tên hiển thị (vd: Kế toán).',
            'name.regex'            => 'Mã vai trò chỉ chứa chữ thường, số và dấu gạch dưới.',
        ]);
    }
}
