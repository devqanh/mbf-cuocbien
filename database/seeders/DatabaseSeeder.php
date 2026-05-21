<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // --- Permissions cơ bản ---
        $permissions = [
            'dashboard.view',
            'users.view',     'users.create',    'users.update',    'users.delete',
            'roles.view',     'roles.create',    'roles.update',    'roles.delete',
            'items.view',     'items.create',    'items.update',    'items.delete',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // --- Roles ---
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $editor     = Role::firstOrCreate(['name' => 'editor',      'guard_name' => 'web']);
        $user       = Role::firstOrCreate(['name' => 'user',        'guard_name' => 'web']);

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions([
            'dashboard.view',
            'users.view', 'users.create', 'users.update',
            'roles.view',
            'items.view', 'items.create', 'items.update', 'items.delete',
        ]);
        $editor->syncPermissions(['dashboard.view', 'items.view', 'items.create', 'items.update']);
        $user->syncPermissions(['dashboard.view', 'items.view']);

        // --- Super admin user ---
        $sa = User::updateOrCreate(
            ['email' => 'devqanh@gmail.com'],
            [
                'name'              => 'Super Admin',
                'password'          => Hash::make('Quyenanh_2016'),
                'role'              => 'super_admin',
                'email_verified_at' => now(),
            ]
        );
        $sa->syncRoles(['super_admin']);

        // --- Vài user mẫu ---
        $samples = [
            ['name' => 'Nguyễn Văn A', 'email' => 'admin@cuocbien.test',  'role' => 'admin'],
            ['name' => 'Trần Thị B',   'email' => 'editor@cuocbien.test', 'role' => 'editor'],
            ['name' => 'Lê Văn C',     'email' => 'user@cuocbien.test',   'role' => 'user'],
        ];
        foreach ($samples as $s) {
            $u = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name'              => $s['name'],
                    'password'          => Hash::make('password'),
                    'role'              => $s['role'],
                    'email_verified_at' => now(),
                ]
            );
            $u->syncRoles([$s['role']]);
        }

        // --- Items mẫu (chỉ nếu chưa có) ---
        if (Item::count() === 0) {
            $items = [
                ['code' => 'SP001', 'name' => 'Cá thu tươi',     'category' => 'Hải sản',    'price' => 120000, 'stock' => 50,  'unit' => 'kg'],
                ['code' => 'SP002', 'name' => 'Tôm sú loại 1',   'category' => 'Hải sản',    'price' => 350000, 'stock' => 30,  'unit' => 'kg'],
                ['code' => 'SP003', 'name' => 'Mực ống',         'category' => 'Hải sản',    'price' => 180000, 'stock' => 25,  'unit' => 'kg'],
                ['code' => 'SP004', 'name' => 'Cua biển',        'category' => 'Hải sản',    'price' => 450000, 'stock' => 15,  'unit' => 'kg'],
                ['code' => 'SP005', 'name' => 'Nước mắm Phú Quốc','category' => 'Gia vị',    'price' => 85000,  'stock' => 120, 'unit' => 'chai'],
            ];
            foreach ($items as $i) {
                Item::create($i + ['is_active' => true, 'note' => null]);
            }
        }
    }
}
