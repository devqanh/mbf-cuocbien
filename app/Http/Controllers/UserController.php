<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q     = $request->get('q');
        $users = User::with('roles')
            ->when($q, fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%");
            }))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'q'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role'              => $data['roles'][0] ?? 'user',
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', "Đã thêm thành viên: {$user->name}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->role = $data['roles'][0] ?? $user->role;
        $user->save();

        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', "Đã cập nhật thành viên: {$user->name}");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể tự xoá chính mình.');
        }
        if ($user->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin')) {
            return back()->with('error', 'Bạn không có quyền xoá super admin.');
        }
        $name = $user->name;
        $user->delete();
        return back()->with('success', "Đã xoá: {$name}");
    }
}
