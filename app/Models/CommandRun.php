<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One run of a sidebar command in a project, in a background process: its
 * output as it comes, then how it ended.
 *
 * @property int $id
 * @property int $chat_session_id
 * @property string $command_key
 * @property string $command
 * @property string $cwd
 * @property string $status
 * @property int|null $exit_code
 * @property string|null $output
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ChatSession|null $chatSession
 */
class CommandRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /**
     * A run still "running" with no word from its process for this long is
     * taken to have died (the PC slept, the process was killed).
     */
    public const LOST_AFTER_MINUTES = 35;

    protected $fillable = [
        'chat_session_id',
        'command_key',
        'command',
        'cwd',
        'status',
        'exit_code',
        'output',
        'finished_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING
            && $this->updated_at->greaterThan(now()->subMinutes(self::LOST_AFTER_MINUTES));
    }
}
