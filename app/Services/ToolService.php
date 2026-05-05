<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use ReflectionClass;

class ToolService
{
    /**
     * @return array<int, Tool>
     */
    public function listTools(): array
    {
        $toolsPath = app_path('Ai/Tools');

        if (! File::isDirectory($toolsPath)) {
            return [];
        }

        return collect(File::allFiles($toolsPath))
            ->map(function ($file) {
                $relativePath = str_replace(
                    ['/', '\\', '.php'],
                    ['\\', '\\', ''],
                    $file->getRelativePathname(),
                );

                return 'App\\Ai\\Tools\\'.$relativePath;
            })
            ->filter(function ($class) {
                if (! class_exists($class) || ! is_subclass_of($class, Tool::class)) {
                    return false;
                }

                return (new ReflectionClass($class))->isInstantiable();
            })
            ->sort()
            ->map(function ($class) {
                return new $class;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, class: class-string<Tool>, description: string, tool: Tool}>
     */
    public function listToolDefinitions(): array
    {
        return collect($this->listTools())
            ->map(function (Tool $tool): array {
                return [
                    'name' => class_basename($tool),
                    'class' => $tool::class,
                    'description' => trim((string) $tool->description()),
                    'tool' => $tool,
                ];
            })
            ->values()
            ->all();
    }

    public function runToolsForTask(
        int $sessionId,
        string $type,
        string $model,
        string $message,
        array $tasks,
        int $task
    ): array {
        set_time_limit(300);

        // Adjust task index since the UI is 1 indexed but arrays are 0 indexed
        $taskIndex = $task - 1;

        if (! isset($tasks[$taskIndex])) {
            return [];
        }

        $chatService = app(ChatService::class);

        $agent = $chatService->findAgent($model, $sessionId);

        if ($agent == '') {
            return [];
        }

        $toolDefinitions = $this->listToolDefinitions();

        $availableTools = collect($toolDefinitions)
            ->map(static fn (array $toolDefinition): string => $toolDefinition['name'].': '.$toolDefinition['description'])
            ->implode('; ');

        // Process the users message
        $instructions = '
            Based on the following task and the list of available tools, determine if any tools need to be run in order to complete this task effectively. 
            If you determine that a tool needs to be run, please respond with the name of the tool and the input for the tool in the following format: 
            tool_name: input for the tool
            If no tools need to be run, respond with "No tools needed". 
            Use the tool name exactly as written in the available tools list.
            Available tools: '.($availableTools !== '' ? $availableTools : 'No application tools available').'. 
            Task: 
        ';

        $response = $agent
            ->prompt($instructions.$tasks[$taskIndex]);

        $toolResults = [];
        foreach ($toolDefinitions as $toolDefinition) {
            $toolName = $toolDefinition['name'];
            $tool = $toolDefinition['tool'];

            if (str_starts_with($response->text, $toolName.':')) {
                $input = trim(Str::after($response->text, $toolName.':'));

                $toolResponse = $tool->handle(new Request([
                    'value' => $input,
                ]));

                $toolResults[] = [
                    'tool' => $toolName,
                    'input' => $input,
                    'output' => $toolResponse,
                ];

                // Log the response from the tool
                $chatService->addMessage(
                    sessionId: $sessionId,
                    type: 'info',
                    by: 'assistant',
                    content: "### Ran tool while working on task #{$task}\n\nTool: {$toolName}\n\nInput: {$input}\n\nOutput: ".Str::limit($toolResponse, 200),
                );
            }
        }

        return $toolResults;
    }
}
