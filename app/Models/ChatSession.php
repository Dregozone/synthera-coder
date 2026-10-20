<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $title
 * @property int|null $number_of_messages
 * @property string|null $current_model
 * @property string|null $current_chat_type
 * @property string|null $current_working_directory
 * @property string|null $soul
 * @property array<int, string>|null $guidelines
 */
class ChatSession extends Model
{
    protected $fillable = [
        // Basic session info to help user identify and organize their chat sessions
        'title',
        'number_of_messages',

        // Pull the current setup so we can pick up where we left off between sessions
        'current_model',
        'current_chat_type',

        'current_working_directory',

        // The agent setup chosen for this chat (null: the defaults); see AgentSetup::selection()
        'soul',
        'guidelines',
    ];

    protected function casts(): array
    {
        return [
            'guidelines' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
