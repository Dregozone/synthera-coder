<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $fillable = [
        // Basic session info to help user identify and organize their chat sessions
        'title',
        'number_of_messages',

        // Pull the current setup so we can pick up where we left off between sessions
        'current_model',
        'current_chat_type',
    ];
}
