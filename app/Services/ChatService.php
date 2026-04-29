<?php

namespace App\Services;

use App\Ai\Agents\Qwen3_8b_8k;
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
        // //
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
    }

    public function updateTasks(
        int $sessionId,
        string $type,
        string $model,
        string $message,
    ): array {
        set_time_limit(180);

        $agent = $this->findAgent($model, $sessionId);

        if ($agent == '') {
            return ['No agent found'];
        }

        // Process the users message
        $instructions = '
            Based on the following user message, give me a list of tasks that would need to be carried out to provide a working solution for the user. 
            Only return the list of tasks, do not include any other commentary. 
            If there are no tasks return "No tasks".
            If the message is unclear, return "Reframe your question".
            Please delimit the tasks with a "|" pipe character. User message: 
        ';
        
        $response = $agent
            ->prompt($instructions . $message);

        dd($response->text);

        // $this->addMessage(
        //     sessionId: $sessionId,
        //     type: $type,
        //     by: 'assistant',
        //     content: $response,
        // );

        // dd(
        //     "ChatService->sendMessage called with type: $type, model: $model, message: $message",
        // );

        return ['Task 1', 'Task 2',];
    }

    public function findAssistantResponse(
        int $sessionId,
        string $type,
        string $model,
        string $message,
        string $originalPrompt,
    ): void {
        set_time_limit(180);

        $agent = $this->findAgent($model, $sessionId);

        if ($agent == '') {
            return;
        }

        // Process the users message
        $response = $agent
            ->prompt($originalPrompt);

        $this->addMessage(
            sessionId: $sessionId,
            type: $type,
            by: 'assistant',
            content: $response,
        );

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

    private function findAgent(string $model, int $sessionId): Qwen3_8b_8k|string
    {
        if ($model === 'qwen3:8b-8k') {
            return new Qwen3_8b_8k;

        } elseif ($model === 'qwen3:14b-16k') {
            $this->addMessage(
                sessionId: $sessionId,
                type: 'info',
                by: 'assistant',
                content: '14b model still needs implementing...',
            );

            return '';

        } else {
            $this->addMessage(
                sessionId: $sessionId,
                type: 'info',
                by: 'assistant',
                content: 'The specified model is not recognized...',
            );

            return '';
        }
    }
}
