<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Notifications\BroadcastTestNotification;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request)
    {
        $q = $request->get('q');

        $users = User::with('roles')
            ->when($q, fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%");
            }))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        // Tổng quan 2FA toàn hệ thống (2 con số nhẹ, phục vụ nhắc nhở bảo mật).
        $twoFactorEnabled = User::whereNotNull('two_factor_confirmed_at')->count();
        $totalUsers       = User::count();

        return view('users.index', compact('users', 'roles', 'q', 'twoFactorEnabled', 'totalUsers'));
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

        $user = $this->users->create($data, $data['roles'] ?? []);

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

        $user = $this->users->update($user, $data, $data['roles'] ?? []);

        return back()->with('success', "Đã cập nhật thành viên: {$user->name}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->users->delete($user, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã xoá: {$user->name}");
    }

    /**
     * Reset (tắt) 2FA của một thành viên — dùng khi họ mất thiết bị
     * authenticator. Sau đó họ đăng nhập bằng mật khẩu và tự bật lại ở /profile.
     */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        if (! $user->hasTwoFactorEnabled()) {
            return back()->with('error', "{$user->name} chưa bật 2FA.");
        }

        $user->disableTwoFactor();

        return back()->with('success', "Đã reset 2FA cho {$user->name}. Họ có thể đăng nhập và thiết lập lại.");
    }

    /**
     * Debug: gửi 1 notification TEST cho TẤT CẢ users để kiểm tra Reverb hoạt động.
     * Chỉ admin (có quyền users.view) được dùng.
     */
    public function broadcastTest(Request $request): RedirectResponse
    {
        $sender = $request->user();
        $users  = User::all();

        Notification::send($users, new BroadcastTestNotification($sender));

        return back()->with('success', sprintf(
            'Đã đẩy thông báo TEST cho %d user. Mở các tab khác để kiểm tra toast realtime.',
            $users->count()
        ));
    }
}
