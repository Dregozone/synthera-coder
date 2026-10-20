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
            // The soul chosen for this chat (null: the default soul; ':none': no soul).
            $table->string('soul')->nullable()->after('current_working_directory');
            // The guidelines chosen for this chat (null: those of the chat mode's profile).
            $table->json('guidelines')->nullable()->after('soul');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->dropColumn(['soul', 'guidelines']);
        });
    }
};
