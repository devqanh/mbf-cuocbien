<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng hồ sơ lái xe: nhiều SĐT, ngày sinh, ngày vào công ty (để tự tính thâm niên),
 * nhiều số tài khoản ngân hàng, và tài liệu đính kèm (CCCD/bằng lái — file/ảnh).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_drivers', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('name');         // ["09xx", "09yy"]
            $table->date('birthday')->nullable()->after('phones');     // ngày sinh
            $table->date('joined_date')->nullable()->after('birthday');// ngày vào công ty → tự tính thâm niên
            $table->json('bank_accounts')->nullable()->after('joined_date'); // [{bank,number,holder}]
            $table->json('documents')->nullable()->after('bank_accounts');   // [{type,name,path,mime,size}]
        });
    }

    public function down(): void
    {
        Schema::table('trucking_drivers', function (Blueprint $table) {
            $table->dropColumn(['phones', 'birthday', 'joined_date', 'bank_accounts', 'documents']);
        });
    }
};
