<?php

namespace Database\Seeders;

use App\Models\Shipment;
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
            'users.view',      'users.create',      'users.update',      'users.delete',
            'roles.view',      'roles.create',      'roles.update',      'roles.delete',
            'shipments.view',  'shipments.create',  'shipments.update',  'shipments.delete',
            'prices.view',     'prices.update',
            'statements.view', 'statements.create', 'statements.update', 'statements.delete',
            'settings.view',   'settings.update',
            'tasks.view',      'tasks.create',      'tasks.assign_others', 'tasks.manage_all',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // --- Roles ---
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'], ['display_name' => 'Quản trị tối cao']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web'], ['display_name' => 'Quản trị viên']);
        $editor     = Role::firstOrCreate(['name' => 'editor',      'guard_name' => 'web'], ['display_name' => 'Biên tập viên']);
        $user       = Role::firstOrCreate(['name' => 'user',        'guard_name' => 'web'], ['display_name' => 'Nhân viên xem']);

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions([
            'dashboard.view',
            'users.view', 'users.create', 'users.update',
            'roles.view',
            'shipments.view', 'shipments.create', 'shipments.update', 'shipments.delete',
            'prices.view', 'prices.update',
            'statements.view', 'statements.create', 'statements.update', 'statements.delete',
            'settings.view', 'settings.update',
            'tasks.view', 'tasks.create', 'tasks.assign_others', 'tasks.manage_all',
        ]);
        $editor->syncPermissions([
            'dashboard.view',
            'shipments.view', 'shipments.create', 'shipments.update',
            'prices.view',
            'statements.view', 'statements.create', 'statements.update',
            'settings.view',
            'tasks.view', 'tasks.create', 'tasks.assign_others',
        ]);
        $user->syncPermissions([
            'dashboard.view',
            'shipments.view', 'prices.view', 'statements.view', 'settings.view',
            'tasks.view', 'tasks.create',
        ]);

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

        // --- Users mẫu ---
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

        // --- Shipments mẫu (chỉ nếu chưa có) ---
        if (Shipment::count() === 0) {
            $thisMonth = now()->format('Y-m');
            // SCENARIO TEST CHO BÁO CÁO PHẢI TRẢ
            // 3 NCC: MSC, COSCO, EVERGREEN. Dates chốt báo cáo phân bố để có 2 đợt chốt rõ rệt.
            //
            // ĐỢT 1 (chốt: tăng=2026-05-15, giảm=2026-05-25)
            //   MSC      : tăng=(50M+30M)=80M, giảm=15M  → cuối kỳ = 100M+80-15 = 165M
            //   COSCO    : tăng=80M,           giảm=25M  → cuối kỳ =  50M+80-25 = 105M
            //   EVERGREEN: tăng=60M,           giảm=35M  → cuối kỳ = 200M+60-35 = 225M
            //
            // ĐỢT 2 (chốt: tăng=2026-05-20, giảm=2026-05-30)
            //   MSC      : tăng=20M, giảm=0   → cuối kỳ = 165M+20-0 = 185M
            //   COSCO    : tăng=40M, giảm=10M → cuối kỳ = 105M+40-10 = 135M
            //   EVERGREEN: tăng=0,   giảm=0   → cuối kỳ = 225M+0-0   = 225M
            $samples = [
                // ===== MSC Việt Nam =====
                ['client' => 'CANON QUẾ VÕ',  'hbl' => 'MBF202604006', 'mbl_no' => 'MEDUR7398312', 'pol' => 'HAIPHONG', 'pod' => 'JEBEL ALI', 'vol' => '8', 'container_type' => '40RF', 'etd' => '2026-05-01', 'eta' => '2026-05-28', 'vessel_name' => 'MSC TIGER III HD617A', 'line' => 'MSC',
                 'supplier' => 'MSC Việt Nam', 'payment_amount' => 50_000_000,
                 'report_close_date_increase' => '2026-05-15',  // → đợt 1 tăng
                 'vgm' => 'x', 'si' => 'x', 'bl_draft' => 'x',
                 'customer' => 'Canon Việt Nam', 'receivable_amount' => 70_000_000, 'revenue_recognized' => 70_000_000,
                ],
                ['client' => 'WOLONG',         'hbl' => 'MBF214513', 'mbl_no' => 'OERT175702P02130', 'pol' => 'HAIPHONG', 'pod' => 'DALLAS', 'vol' => '1', 'container_type' => '20DC', 'etd' => '2026-05-02', 'eta' => '2026-05-25', 'vessel_name' => 'MSC ILARIA FV616N', 'line' => 'MSC',
                 'supplier' => 'MSC Việt Nam', 'payment_amount' => 30_000_000,
                 'report_close_date_increase' => '2026-05-15',  // → đợt 1 tăng
                 'vgm' => 'x', 'si' => 'x', 'bl_draft' => 'x',
                 'customer' => 'Wolong Electric VN', 'receivable_amount' => 42_000_000,
                ],
                ['client' => 'SAMSUNG',        'hbl' => 'MBF214600', 'mbl_no' => 'MSCU2407001', 'pol' => 'HAIPHONG', 'pod' => 'BUSAN', 'vol' => '1', 'container_type' => '40HC', 'etd' => '2026-05-10', 'eta' => '2026-05-18', 'vessel_name' => 'MSC OSCAR V.020E', 'line' => 'MSC',
                 'supplier' => 'MSC Việt Nam', 'payment_amount' => 20_000_000,
                 'report_close_date_increase' => '2026-05-20',  // → đợt 2 tăng
                 'customer' => 'Samsung VN', 'receivable_amount' => 28_000_000,
                ],
                ['client' => 'CANON QUẾ VÕ',   'hbl' => 'MBF202604010', 'mbl_no' => 'MEDUR7400000', 'pol' => 'NINGBO', 'pod' => 'HAIPHONG', 'vol' => '1', 'container_type' => '40HC', 'etd' => '2026-05-08', 'eta' => '2026-05-14', 'vessel_name' => 'MSC ARIES V.045W', 'line' => 'MSC',
                 'direction' => 'import',
                 'supplier' => 'MSC Việt Nam', 'payment_amount' => 15_000_000,
                 'report_close_date_decrease' => '2026-05-25',  // → đợt 1 giảm (vd: giảm trừ, refund)
                 'note' => 'Refund DEM/DET',
                ],

                // ===== COSCO Shipping =====
                ['client' => 'NAKAWA STAR',    'hbl' => 'COSU260501', 'mbl_no' => 'COAU7268805970', 'pol' => 'NINGBO', 'pod' => 'HAIPHONG', 'vol' => '2', 'container_type' => '40HC', 'etd' => '2026-05-04', 'eta' => '2026-05-07', 'vessel_name' => 'COSCO BOSTON 203E', 'line' => 'COSCO',
                 'direction' => 'import',
                 'supplier' => 'COSCO Shipping', 'payment_amount' => 80_000_000,
                 'report_close_date_increase' => '2026-05-15',  // → đợt 1 tăng
                 'vgm' => 'x',
                 'customer' => 'Nakawa Star JSC', 'receivable_amount' => 105_000_000,
                ],
                ['client' => 'TOYOTA',         'hbl' => 'COSU260502', 'mbl_no' => 'COAU7268810000', 'pol' => 'OSAKA', 'pod' => 'HAIPHONG', 'vol' => '1', 'container_type' => '40HC', 'etd' => '2026-05-12', 'eta' => '2026-05-17', 'vessel_name' => 'COSCO INDIA 088E', 'line' => 'COSCO',
                 'direction' => 'import',
                 'supplier' => 'COSCO Shipping', 'payment_amount' => 40_000_000,
                 'report_close_date_increase' => '2026-05-20',  // → đợt 2 tăng
                 'customer' => 'Toyota VN',
                ],
                ['client' => 'BRIDGESTONE',    'hbl' => 'COSU260503', 'mbl_no' => 'COAU7268820000', 'pol' => 'SHANGHAI', 'pod' => 'HAIPHONG', 'vol' => '1', 'container_type' => '20DC', 'etd' => '2026-05-09', 'eta' => '2026-05-14', 'vessel_name' => 'COSCO ASIA 105W', 'line' => 'COSCO',
                 'direction' => 'import',
                 'supplier' => 'COSCO Shipping', 'payment_amount' => 25_000_000,
                 'report_close_date_decrease' => '2026-05-25',  // → đợt 1 giảm
                 'note' => 'Cancel BKG',
                ],
                ['client' => 'YAMAHA',         'hbl' => 'COSU260504', 'mbl_no' => 'COAU7268830000', 'pol' => 'KOBE', 'pod' => 'HAIPHONG', 'vol' => '1', 'container_type' => '20DC', 'etd' => '2026-05-15', 'eta' => '2026-05-20', 'vessel_name' => 'COSCO PRIDE 200E', 'line' => 'COSCO',
                 'direction' => 'import',
                 'supplier' => 'COSCO Shipping', 'payment_amount' => 10_000_000,
                 'report_close_date_decrease' => '2026-05-30',  // → đợt 2 giảm
                ],

                // ===== EVERGREEN Line =====
                ['client' => 'AUTEL',          'hbl' => 'EGLV260501', 'mbl_no' => 'EGLV237600250957', 'pol' => 'HAIPHONG', 'pod' => 'ROTTERDAM', 'vol' => '1', 'container_type' => '40HC', 'etd' => '2026-05-02', 'eta' => '2026-06-10', 'vessel_name' => 'EVER WISE 0471-029S', 'line' => 'EMC',
                 'supplier' => 'EVERGREEN Line', 'payment_amount' => 60_000_000,
                 'report_close_date_increase' => '2026-05-15',  // → đợt 1 tăng
                 'vgm' => 'x', 'si' => 'x',
                 'customer' => 'Autel VN', 'receivable_amount' => 78_000_000,
                ],
                ['client' => 'XIAOMI',         'hbl' => 'EGLV260502', 'mbl_no' => 'EGLV237600251000', 'pol' => 'SHANGHAI', 'pod' => 'HAIPHONG', 'vol' => '1', 'container_type' => '20DC', 'etd' => '2026-05-11', 'eta' => '2026-05-16', 'vessel_name' => 'EVER GIVEN 0123E', 'line' => 'EMC',
                 'direction' => 'import',
                 'supplier' => 'EVERGREEN Line', 'payment_amount' => 35_000_000,
                 'report_close_date_decrease' => '2026-05-25',  // → đợt 1 giảm
                 'note' => 'Hoàn phí DEM',
                ],
            ];

            foreach ($samples as $s) {
                Shipment::create($s + [
                    'period'    => $thisMonth,
                    'direction' => $s['direction'] ?? 'export',
                ]);
            }
        }
    }
}
