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
        Schema::create('agent_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();

            // 'write' (propose a file change) or 'command' (propose a shell command).
            $table->string('type');

            // pending -> approved|rejected ; approved -> executed|failed.
            $table->string('status')->default('pending');

            // Structured proposal: for writes {path, contents, diff}; for
            // commands {command, cwd}.
            $table->json('payload');

            // Captured output once the action has been executed.
            $table->longText('result')->nullable();

            $table->timestamps();

            $table->index(['chat_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_actions');
    }
};
