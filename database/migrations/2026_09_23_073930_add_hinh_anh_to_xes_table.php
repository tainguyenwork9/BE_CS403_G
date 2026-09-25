<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xes', function (Blueprint $table) {
            $table->string('hinhAnh')->nullable()->after('tinhTrang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xes', function (Blueprint $table) {
            $table->dropColumn('hinhAnh');
        });
    }
};