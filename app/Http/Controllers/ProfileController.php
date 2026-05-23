<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show', [
            'user' => Auth::user(),
        ]);
    }

    public function updateInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], [
            'name'  => 'Họ tên',
            'email' => 'Email',
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Đã cập nhật thông tin cá nhân.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(6)],
        ], [
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ], [
            'current_password' => 'Mật khẩu hiện tại',
            'password'         => 'Mật khẩu mới',
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('success', 'Đã đổi mật khẩu. Lần đăng nhập tới hãy dùng mật khẩu mới.');
    }
}
