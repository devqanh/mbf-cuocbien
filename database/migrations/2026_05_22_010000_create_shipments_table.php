<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop bảng items cũ
        Schema::dropIfExists('items');

        // 2) Tạo bảng shipments theo schema mới (Follow Up Shipment)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('client');                          // Tên khách hàng
            $table->string('hbl', 64)->nullable();             // House B/L
            $table->string('mbl_no', 64)->nullable();          // Master B/L No
            $table->string('bkg_no', 64)->nullable();          // Booking No
            $table->string('pol', 64)->nullable();             // Port of Loading
            $table->string('pod', 64)->nullable();             // Port of Discharge
            $table->string('vol', 64)->nullable();             // Volume (vd: 1x20'GP)
            $table->string('type', 32)->nullable();            // EXPORT / IMPORT / TRANSIT
            $table->date('etd')->nullable();                   // Estimated Time of Departure
            $table->date('eta')->nullable();                   // Estimated Time of Arrival
            $table->string('vessel_name')->nullable();         // Tên tàu
            $table->text('note')->nullable();                  // Ghi chú
            $table->timestamps();

            $table->index(['hbl', 'mbl_no']);
            $table->index('client');
            $table->index('etd');
        });

        // 3) Rename các permission items.* → shipments.* để giữ phân quyền đã gán cho roles
        DB::table('permissions')
            ->where('name', 'like', 'items.%')
            ->get()
            ->each(function ($perm) {
                $newName = str_replace('items.', 'shipments.', $perm->name);
                DB::table('permissions')->where('id', $perm->id)->update(['name' => $newName]);
            });

        // 4) Xoá snapshot cũ của items_grid (không còn dùng)
        DB::table('sheet_snapshots')->where('key', 'items_grid')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('category', 64)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->string('unit', 32)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('permissions')
            ->where('name', 'like', 'shipments.%')
            ->get()
            ->each(function ($perm) {
                $newName = str_replace('shipments.', 'items.', $perm->name);
                DB::table('permissions')->where('id', $perm->id)->update(['name' => $newName]);
            });
    }
};
