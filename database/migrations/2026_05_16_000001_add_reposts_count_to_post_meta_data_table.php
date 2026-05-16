<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_meta_data', function (Blueprint $table) {
            $table->integer('reposts_count')->default(0)->after('shares_count');
        });
    }

    public function down(): void
    {
        Schema::table('post_meta_data', function (Blueprint $table) {
            $table->dropColumn('reposts_count');
        });
    }
};
