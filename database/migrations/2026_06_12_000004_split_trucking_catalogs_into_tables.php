<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách `trucking_catalogs` (gộp) thành các bảng danh mục RIÊNG.
 * Lý do: sau này mỗi danh mục sẽ có quan hệ link phức tạp riêng (FK, thuộc
 * tính bổ sung…), nên mỗi danh mục một bảng độc lập dễ mở rộng hơn.
 */
return new class extends Migration
{
    /** Bảng chỉ có name (+sort). */
    private array $simple = [
        'trucking_locations',   // (có thêm cột code)
        'trucking_payers',
        'trucking_drivers',
        'trucking_cont_types',
        'trucking_warehouses',
    ];

    /** Bảng có name + default_price (+sort). */
    private array $priced = [
        'trucking_cost_items',
        'trucking_choho_items',
        'trucking_revenue_items',
        'trucking_veh_items',
    ];

    public function up(): void
    {
        Schema::dropIfExists('trucking_catalogs');

        foreach ($this->simple as $t) {
            Schema::create($t, function (Blueprint $table) use ($t) {
                $table->id();
                $table->string('name')->unique();
                if ($t === 'trucking_locations') {
                    $table->string('code', 32)->nullable(); // ký hiệu viết tắt
                }
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        foreach ($this->priced as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->decimal('default_price', 15, 2)->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([...$this->simple, ...$this->priced] as $t) {
            Schema::dropIfExists($t);
        }

        // tái tạo bảng gộp cũ (rollback)
        Schema::create('trucking_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('value');
            $table->string('code', 32)->nullable();
            $table->decimal('default_price', 15, 2)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['type', 'value']);
        });
    }
};
