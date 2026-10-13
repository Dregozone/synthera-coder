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
        Schema::create('command_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();

            // The key of the command in config('synthera-coder.commands'), and the
            // command line and folder it ran with.
            $table->string('command_key');
            $table->string('command');
            $table->string('cwd');

            // running -> done | failed (a non-zero exit, a timeout, or never started).
            $table->string('status')->default('running');
            $table->integer('exit_code')->nullable();

            // What it has printed so far (stdout and stderr, colours stripped).
            $table->longText('output')->nullable();

            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
