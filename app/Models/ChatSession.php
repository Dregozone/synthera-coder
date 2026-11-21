<?php

namespace App\Models;

use App\Services\ProjectDirectories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $title
 * @property int|null $number_of_messages
 * @property string|null $current_model
 * @property string|null $current_chat_type
 * @property string|null $current_working_directory
 * @property string|null $confirmed_working_directory
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

        // The folder the user confirmed before any change is applied in it (see hasConfirmedWorkingDirectory())
        'confirmed_working_directory',

        // The agent setup chosen for this chat (null: the defaults); see AgentSetup::selection()
        'soul',
        'guidelines',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'guidelines' => 'array',
        ];
    }

    /**
     * Whether the user has confirmed the folder this chat works in now: a
     * confirmation is for one folder, so moving the chat needs a new one.
     */
    public function hasConfirmedWorkingDirectory(): bool
    {
        return ProjectDirectories::same($this->confirmed_working_directory, $this->current_working_directory);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
