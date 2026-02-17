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
        Schema::table('moonshine_users', function (Blueprint $table) {
            $table->foreignId('bus_id')
                ->nullable()
                ->after('moonshine_user_role_id')
                ->constrained('buses')
                ->nullOnDelete()
                ->comment('Привязанный автобус (для водителей)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moonshine_users', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropColumn('bus_id');
        });
    }
};
