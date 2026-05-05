<?php

use App\Ai\Tools\ReadFile;
use App\Models\ChatSession;
use App\Services\ToolService;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

test('tool service lists tool classes from the tools directory', function (): void {
    $tools = app(ToolService::class)->listTools();

    foreach ($tools as $tool) {
        expect($tool)->toBeInstanceOf(Tool::class);
    }

    $toolClasses = array_map(static fn (Tool $tool): string => $tool::class, $tools);

    expect($toolClasses)
        ->toContain(ReadFile::class)
        ->toEqual(array_values(array_unique($toolClasses)));
});

test('tool service exposes tool definitions for agent prompts', function (): void {
    $toolDefinitions = app(ToolService::class)->listToolDefinitions();

    $readFileDefinition = collect($toolDefinitions)
        ->firstWhere('class', ReadFile::class);

    expect($readFileDefinition)->not->toBeNull();
    expect($readFileDefinition['name'])->toBe('ReadFile');
    expect($readFileDefinition['description'])->toBe('This tool can be used to check if a file exists or read the contents of a file.');
    expect($readFileDefinition['tool'])->toBeInstanceOf(ReadFile::class);
});

test('read file tool reads relative paths from the latest session working directory', function (): void {
    ChatSession::create([
        'current_working_directory' => base_path(),
    ]);

    $contents = (new ReadFile)->handle(new Request([
        'value' => 'composer.json',
    ]));

    expect($contents)->toBe(File::get(base_path('composer.json')));
});

test('read file tool preserves absolute paths', function (): void {
    ChatSession::create([
        'current_working_directory' => app_path(),
    ]);

    $absolutePath = base_path('composer.json');

    $contents = (new ReadFile)->handle(new Request([
        'value' => $absolutePath,
    ]));

    expect($contents)->toBe(File::get($absolutePath));
});
