<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property string $type
 * @property string $status
 * @property array<string, mixed> $payload
 * @property string|null $result
 * @property-read ChatSession|null $chatSession
 */
class AgentAction extends Model
{
    public const TYPE_WRITE = 'write';

    public const TYPE_COMMAND = 'command';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'chat_session_id',
        'type',
        'status',
        'payload',
        'result',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
