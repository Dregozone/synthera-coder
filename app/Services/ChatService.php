<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;

class ChatService
{
    public static function nextSessionId(): int
    {
        // Make a new session that can be used
        $model = ChatSession::create([]);

        // Grab the ID of the newly created ChatSession
        return $model->id;
    }

    public function maintenanceTasks(): void
    {
        $this->tidyOldSessions();

        // Any other maintenance tasks can go here
        ////
    }

    /**
     * Over time, users may have created a number of sessions then closed out, we can implement some logic to tidy up old sessions, 
     * such as deleting sessions that were created more than 1 week ago and have 0 messages.
     */
    public function tidyOldSessions(): void
    {
        // Implement the logic to tidy up old chat sessions, such as deleting sessions that were created more than 1 week ago and have 0 messages.
        ChatSession::query()
            ->where('created_at', '<', now()->subWeek())
            ->where('number_of_messages', 0)
            ->delete();
    }

    public function sendMessage(
        int $sessionId,
        string $type,
        string $model,
        string $message
    ): void {
        // Log the users message in the database
        $this->addMessage(
            sessionId: $sessionId,
            type: $type,
            by: 'user',
            content: $message,
        );

        // Process the users message
        ////
        
        // dd(
        //     "ChatService->sendMessage called with type: $type, model: $model, message: $message",
        // );
    }

    public function addMessage(
        int $sessionId,
        string $type,
        string $by,
        string $content,
    ): void {
        // Implement the logic to add a message to the specified chat session.
        ChatMessage::create([
             'chat_session_id' => $sessionId,
             'type' => $type,
             'by' => $by,
             'content' => $content,
        ]);
    }

    public function getMessagesForSession(int $sessionId): array
    {
        // Implement the logic to retrieve messages for the specified chat session.
        return ChatMessage::query()
            ->where('chat_session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }
}
