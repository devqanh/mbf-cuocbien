<?php

namespace App\Services;

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Tạo user mới + gán roles.
     *
     * @param array{name:string,email:string,password:string} $data
     * @param array<int,string> $roleNames
     */
    public function create(array $data, array $roleNames = []): User
    {
        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role'              => $roleNames[0] ?? 'user',
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($roleNames);

        return $user;
    }

    /**
     * Update user + sync roles. Đổi password chỉ khi truyền vào (không rỗng).
     */
    public function update(User $user, array $data, array $roleNames = []): User
    {
        $user->name  = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->role = $roleNames[0] ?? $user->role;
        $user->save();

        $user->syncRoles($roleNames);

        return $user;
    }

    /**
     * Xoá user. Block các trường hợp nguy hiểm:
     * - Không cho tự xoá chính mình
     * - Chỉ super_admin mới được xoá super_admin khác
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new BusinessRuleException('Không thể tự xoá chính mình.', 403);
        }
        if ($user->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
            throw new BusinessRuleException('Bạn không có quyền xoá super admin.', 403);
        }
        $user->delete();
    }
}
