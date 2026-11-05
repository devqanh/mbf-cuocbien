<?php

namespace App\Models;

use App\Http\Controllers\Auth\LoginController;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /** Số phiên vừa bị đăng xuất ở lần đổi mật khẩu gần nhất (controller đọc để báo lại). */
    public int $revokedSessionCount = 0;

    protected static function booted(): void
    {
        // Đổi mật khẩu (admin ở /users hay chính chủ ở /profile) → đăng xuất mọi phiên cũ.
        static::updated(function (User $user) {
            if ($user->wasChanged('password')) $user->logoutSessionsAfterPasswordChange();
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'shipment_column_prefs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'password'                  => 'hashed',
            'shipment_column_prefs'     => 'array',
            'two_factor_secret'         => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at'   => 'datetime',
        ];
    }

    // ===================================================================
    // Xác thực 2 lớp (2FA / TOTP)
    // ===================================================================

    /** Đã bật 2FA = có secret VÀ đã xác nhận được 1 mã hợp lệ. */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /** @return array<int,string> */
    public function recoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    /**
     * Dùng 1 mã khôi phục để đăng nhập: nếu khớp thì xoá khỏi danh sách
     * (mỗi mã chỉ dùng được 1 lần) và lưu lại.
     */
    public function useRecoveryCode(string $code): bool
    {
        $code  = strtoupper(trim($code));
        $codes = $this->recoveryCodes();

        foreach ($codes as $i => $existing) {
            if (hash_equals(strtoupper($existing), $code)) {
                unset($codes[$i]);
                $this->two_factor_recovery_codes = array_values($codes);
                $this->save();
                return true;
            }
        }

        return false;
    }

    /** Tắt/xoá hoàn toàn 2FA (dùng khi user tự tắt hoặc admin reset). */
    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();
    }

    /**
     * Chốt chặn thật là middleware AuthenticateSession (nhóm web): phiên hay cookie "ghi nhớ" mang mã băm
     * mật khẩu cũ bị đá ở request kế tiếp, với mọi session driver. Ở đây: xoá ngay dòng phiên DB (danh sách
     * thiết bị ở /profile cập nhật liền) và giữ phiên hiện tại khi chính chủ tự đổi.
     */
    public function logoutSessionsAfterPasswordChange(): void
    {
        $request = request();
        $isSelf  = $request->hasSession() && Auth::id() === $this->id;
        $current = $isSelf ? $request->session()->getId() : null;

        if (config('session.driver') === 'database') {
            $this->revokedSessionCount = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->when($current, fn ($q) => $q->where('id', '!=', $current))
                ->delete();
        }

        if (! $isSelf) return;

        // Phiên đang thao tác: ghi mã băm mới để AuthenticateSession không đá chính mình (setUser vì middleware
        // ghi lại mã băm từ user của guard sau response), và cấp lại cookie "ghi nhớ" vì cookie cũ chứa mã băm cũ.
        $guard = Auth::guard();
        $hash  = $guard->hashPasswordForCookie($this->getAuthPassword());
        $guard->setUser($this);
        $request->session()->put('password_hash_' . Auth::getDefaultDriver(), $hash);
        if ($this->getRememberToken() && $request->cookies->has($guard->getRecallerName())) {
            Cookie::queue(
                $guard->getRecallerName(),
                $this->id . '|' . $this->getRememberToken() . '|' . $hash,
                LoginController::REMEMBER_DURATION_MINUTES,
            );
        }
    }

    /**
     * Trang chủ theo quyền: trang ĐẦU TIÊN user được vào (thứ tự = thứ tự menu). Tránh đăng nhập
     * xong bị 403 khi vai trò không có quyền Lô hàng (vd Kế toán).
     */
    public function homeUrl(): string
    {
        $pages = [
            'shipments.view'     => 'trucking2.shipments',
            'statements.view'    => 'trucking2.statements',
            'extStatements.view' => 'trucking2.extStatements',
            'driverPay.manage'   => 'trucking2.loTrinh',
            'tripCost.view'      => 'trucking2.tripCost',
            'tracking.view'      => 'trucking2.tracking',
            'fleet.view'         => 'trucking2.fleet',
            'prices.view'        => 'trucking2.prices',
            'settings.view'      => 'trucking2.settings',
            'tasks.view'         => 'tasks.index',
            'reports.view'       => 'trucking2.report',
            'users.view'         => 'users.index',
            'roles.view'         => 'roles.index',
            'system.settings'    => 'system.settings',
            'spend.request'      => 'trucking2.spendRequest',
        ];
        foreach ($pages as $perm => $route) {
            if ($this->can($perm)) return route($route);
        }
        return route('profile.show');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' || $this->hasRole('super_admin');
    }

    /**
     * Trả về nhãn vai trò để hiển thị cho user (header, profile…).
     * Ưu tiên: roles.display_name (DB) → config('permissions.roles.{name}.label') → tên role.
     * Nếu user không có Spatie role → fallback cột legacy users.role.
     */
    public function roleLabel(): string
    {
        $role = $this->roles->first();

        if ($role) {
            return $role->display_name
                ?: (config("permissions.roles.{$role->name}.label")
                    ?: ucwords(str_replace('_', ' ', $role->name)));
        }

        return $this->role
            ? ucwords(str_replace('_', ' ', $this->role))
            : '—';
    }

    // ===================================================================
    // Phân quyền cột (Luckysheet) ĐÃ BỎ — hệ thống dùng phân quyền theo vai trò (/roles).
    // Giữ các shim dưới đây để code Follow-Up/Trucking cũ (đang tắt qua feature flag)
    // không vỡ nếu được bật lại: mọi cột mặc định XEM & SỬA được, không còn giới hạn.
    // ===================================================================
    public const PERM_HIDDEN = 'hidden';
    public const PERM_VIEW   = 'view';
    public const PERM_EDIT   = 'edit';

    public function columnPermission(string $key): string { return self::PERM_EDIT; }
    public function canViewColumn(string $key): bool { return true; }
    public function canEditColumn(string $key): bool { return true; }
    public function truckingColumnPermission(string $key): string { return self::PERM_EDIT; }
    public function canViewTruckingColumn(string $key): bool { return true; }
    public function canEditTruckingColumn(string $key): bool { return true; }
}
