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
        Schema::table('chat_sessions', function (Blueprint $table): void {
            // The folder the user confirmed (seeing its full path, file count and size) before any change is applied in it.
            $table->string('confirmed_working_directory')->nullable()->after('current_working_directory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->dropColumn('confirmed_working_directory');
        });
    }
};
