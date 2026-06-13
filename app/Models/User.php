<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

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
