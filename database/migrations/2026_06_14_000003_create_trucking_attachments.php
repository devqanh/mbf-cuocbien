<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng FILE TẬP TRUNG (polymorphic) — gom mọi file upload (tài liệu lái xe/xe, ảnh phiếu chi)
 * về 1 chỗ để dễ quản lý + migrate S3 (mỗi file lưu kèm 'disk').
 * Backfill dữ liệu cũ từ các cột JSON: trucking_drivers.documents, trucking_vehicles.documents,
 * trucking_vehicle_costs.photos. Sau backfill: cost.photos được thay bằng MẢNG ID attachment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');                 // App\Models\TruckingDriver | TruckingVehicle
            $table->unsignedBigInteger('owner_id');
            $table->string('group', 32)->default('doc');  // 'doc' | 'costPhoto'
            $table->string('disk', 32)->default('local'); // local | s3 …
            $table->string('path');                       // đường dẫn trên disk
            $table->string('name')->nullable();           // tên file gốc
            $table->string('type')->nullable();           // nhãn loại (CCCD mặt trước, Bằng lái…)
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'group']);
        });

        $now = now();
        $insert = function ($ownerType, $ownerId, $group, $disk, $path, $name, $type, $mime, $size, $sort) use ($now) {
            return DB::table('trucking_attachments')->insertGetId([
                'owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'disk' => $disk,
                'path' => $path, 'name' => $name, 'type' => $type, 'mime' => $mime, 'size' => (int) $size, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        // 1) Tài liệu lái xe + xe (documents JSON: {type,name,path,mime,size})
        foreach ([['trucking_drivers', 'App\\Models\\TruckingDriver'], ['trucking_vehicles', 'App\\Models\\TruckingVehicle']] as [$tbl, $cls]) {
            foreach (DB::table($tbl)->whereNotNull('documents')->get(['id', 'documents']) as $row) {
                $docs = json_decode($row->documents ?? '[]', true) ?: [];
                foreach (array_values($docs) as $i => $d) {
                    if (empty($d['path'])) continue;
                    $insert($cls, $row->id, 'doc', 'local', $d['path'], $d['name'] ?? null, $d['type'] ?? null, $d['mime'] ?? null, $d['size'] ?? 0, $i);
                }
            }
        }

        // 2) Ảnh phiếu chi (photos JSON: {file(basename),name,mime,size}) — owner = XE; cost.photos → mảng id
        foreach (DB::table('trucking_vehicle_costs')->whereNotNull('photos')->get(['id', 'vehicle_id', 'photos']) as $cost) {
            $photos = json_decode($cost->photos ?? '[]', true) ?: [];
            $ids = [];
            foreach (array_values($photos) as $i => $p) {
                $file = basename((string) ($p['file'] ?? ''));
                if ($file === '') continue;
                $ids[] = $insert('App\\Models\\TruckingVehicle', $cost->vehicle_id, 'costPhoto', 'local',
                    "trucking/cost-photos/{$cost->vehicle_id}/{$file}", $p['name'] ?? $file, null, $p['mime'] ?? null, $p['size'] ?? 0, $i);
            }
            DB::table('trucking_vehicle_costs')->where('id', $cost->id)->update(['photos' => json_encode($ids)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_attachments');
    }
};
