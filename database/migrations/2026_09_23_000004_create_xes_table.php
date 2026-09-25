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
        Schema::create('xes', function (Blueprint $table) {
            $table->id('maXe');
            $table->string('tenXe');
            $table->string('hangXe');
            $table->string('bienSo')->unique();
            $table->string('mauSac');
            $table->year('namSanXuat');
            $table->double('giaThue');
            $table->string('loaiXe');
            $table->string('tinhTrang')->default('san_sang');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xes');
    }
};
