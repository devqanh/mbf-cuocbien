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

    public const PERM_HIDDEN = 'hidden';
    public const PERM_VIEW   = 'view';
    public const PERM_EDIT   = 'edit';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'column_permissions',
        'shipment_column_prefs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'column_permissions'     => 'array',
            'shipment_column_prefs'  => 'array',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' || $this->hasRole('super_admin');
    }

    /**
     * Trả về permission cho 1 cột.
     * Super admin luôn 'edit'. Nếu user không có config → 'edit' (mặc định mở).
     */
    public function columnPermission(string $columnKey): string
    {
        if ($this->isSuperAdmin()) return self::PERM_EDIT;

        $perms = $this->column_permissions ?? [];
        return $perms[$columnKey] ?? self::PERM_EDIT;
    }

    public function canViewColumn(string $key): bool
    {
        return $this->columnPermission($key) !== self::PERM_HIDDEN;
    }

    public function canEditColumn(string $key): bool
    {
        return $this->columnPermission($key) === self::PERM_EDIT;
    }
}
