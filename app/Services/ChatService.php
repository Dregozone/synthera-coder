<?php

namespace App\Services;

use App\Ai\Agents\Qwen3_8b_8k;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\DB;

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
        set_time_limit(300);

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
            Please delimit the tasks with a "|" pipe character. 
            Ensure minimal number of tasks to be able to complete the users request effectively, no more than 5 total tasks, ideally 3 or less, 
            For example, if the request includes reading the contents of a file and doing something with it, do not first check existence, then read it, then check 
            for errors, then parse the file, just try to read and gain the details from it that are needed. If it fails then we know it was an issue with the file. 
            User message: 
        ';

        $response = $agent
            ->prompt($instructions.$message);

        $tasksArr = array_values(array_filter(
            array_map(trim(...), explode('|', $response->text)),
            static fn (string $task): bool => $task !== '',
        ));

        // Refined tasks list
        $refiningInstructions = '
            Here is a list of tasks that have been derived from the users message: '.implode('; ', $tasksArr).'. 
            Refine this list to ensure it is as concise as possible, with minimal overlap between tasks and no unnecessary tasks. 
            Ensure the tasks are clearly defined and actionable. 
            Return the refined list of tasks delimited with a "|" pipe character. 
            If there are no tasks return "No tasks".
            For example, if the initial list of tasks includes "Check if file exists" and "Read file contents", 
            these can be combined into a single task "Read file contents and handle if file does not exist".
        ';

        $refinedResponse = $agent
            ->prompt($refiningInstructions);

        $refinedTasksArr = array_values(array_filter(
            array_map(trim(...), explode('|', $refinedResponse->text)),
            static fn (string $task): bool => $task !== '',
        ));

        // Log the tasks that have been derived from the users message
        $this->addMessage(
            sessionId: $sessionId,
            type: 'info',
            by: 'assistant',
            content: 'Tasks list updated|'.$refinedResponse->text,
        );

        return $refinedTasksArr;
    }

    public function workOnTask(
        int $sessionId,
        string $type,
        string $model,
        string $message,
        array $tasks,
        int $task,
        array $toolResults,
        array $taskResults,
    ): array {
        set_time_limit(300);

        // Adjust task index since the UI is 1 indexed but arrays are 0 indexed
        $taskIndex = $task - 1;

        if (! isset($tasks[$taskIndex])) {
            return [
                'response' => '',
                'summary' => '',
            ];
        }

        $agent = $this->findAgent($model, $sessionId);

        if ($agent == '') {
            return [
                'response' => '',
                'summary' => '',
            ];
        }

        // TODO: Also provide the details already found by completing previous tasks, this context will be valuable in assisting with the continued task work...
        $instructions = '
            Based on the following task, the results from any tools that have already been run, and the results from any previous tasks, work on this task and give me your response. 
            Only return the solution for this particular task do not try to solve other tasks. 
            If there are no tasks return "No tasks".
            If the task is unclear, return "Reframe your question".
            Task: '.$tasks[$taskIndex].'.
            Previous tasks results: '.(count($taskResults) > 0 ? json_encode($taskResults) : 'No previous tasks have been completed yet').'.
            Current tool results: '.(count($toolResults) > 0 ? json_encode($toolResults) : 'No tools have been run yet').'.
        ';

        $response = $agent
            ->prompt($instructions);

        // Find a short summary of what this task has achieved
        $summaryInstructions = '
            Based on the results from working on the task, generate a concise summary of what has been achieved for this task. 
            The summary should be no more than 5-8 words. 
            Task results:
        ';

        $summaryResponse = $agent
            ->prompt($summaryInstructions.$response->text);

        // Log the response from the agent after working on the task
        $this->addMessage(
            sessionId: $sessionId,
            type: $type,
            by: 'assistant',
            content: "### Worked on task #{$task}\n\n{$summaryResponse->text}",
        );

        return [
            'response' => $response->text,
            'summary' => $summaryResponse->text,
        ];
    }

    public function assignSessionTitle(
        int $sessionId,
        string $type,
        string $model,
        string $message,
        array $tasks
    ): string {
        set_time_limit(300);

        $agent = $this->findAgent($model, $sessionId);

        if ($agent == '') {
            return 'Unknown agent';
        }

        // Process the users message
        $instructions = '
            Based on the following user message, generate a concise and descriptive title for this chat session. 
            The title should be no more than 5 words. 
            User message: 
        ';

        $response = $agent
            ->prompt($instructions.$message);

        $title = $response->text;

        return $title;
    }

    public function findAssistantResponse(
        int $sessionId,
        string $type,
        string $model,
        string $message,
        string $originalPrompt,
        array $taskResults,
    ): string {
        set_time_limit(300);

        $agent = $this->findAgent($model, $sessionId);

        if ($agent == '') {
            return '';
        }

        $instructions = '
            Based on the results from processing each of the individual tasks, and the original user message, generate a final response to the user.
            Ensure this is a valuable and accurate response that effectively addresses the users original message in a coherent and comprehensive manner.
            Results from tasks: '.(count($taskResults) > 0 ? json_encode($taskResults) : 'No tasks have been completed yet').'. 
            Original message: 
        ';

        // Process the users message
        $response = $agent
            ->prompt($instructions.$originalPrompt);

        $this->addMessage(
            sessionId: $sessionId,
            type: $type,
            by: 'assistant',
            content: $response->text,
        );

        return $response->text;
    }

    public function addMessage(
        int $sessionId,
        string $type,
        string $by,
        string $content,
    ): void {
        $chatMessage = ChatMessage::create([
            'chat_session_id' => $sessionId,
            'type' => $type,
            'by' => $by,
            'content' => $content,
        ]);

        ChatSession::query()
            ->whereKey($sessionId)
            ->update([
                'number_of_messages' => DB::raw('COALESCE(number_of_messages, 0) + 1'),
                'updated_at' => now(),
            ]);

        $contextService = new ContextService($sessionId);
        $contextService->push('chat_history', [
            'id' => $chatMessage->id,
            'type' => $type,
            'by' => $by,
            'content' => $content,
            'created_at' => $chatMessage->created_at?->toIso8601String(),
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

    public function findAgent(string $model, int $sessionId): Qwen3_8b_8k|string
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
