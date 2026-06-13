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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'shipment_column_prefs' => 'array',
        ];
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
