<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'ghi_chu')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->text('ghi_chu')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'ghi_chu')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('ghi_chu');
            });
        }
    }
};
