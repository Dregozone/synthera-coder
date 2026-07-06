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
    expect($readFileDefinition['description'])->toBe('Read a file (path relative to the project root).');
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

test('read file tool preserves absolute paths inside the working directory', function (): void {
    ChatSession::create([
        'current_working_directory' => base_path(),
    ]);

    $absolutePath = base_path('composer.json');

    $contents = (new ReadFile)->handle(new Request([
        'value' => $absolutePath,
    ]));

    expect($contents)->toBe(File::get($absolutePath));
});

test('read file tool rejects paths that escape the working directory', function (): void {
    ChatSession::create([
        'current_working_directory' => app_path(),
    ]);

    // Absolute path pointing outside the working directory (its parent).
    $escapingAbsolute = (new ReadFile)->handle(new Request([
        'value' => base_path('composer.json'),
    ]));

    // Relative traversal attempting to climb out of the working directory.
    $escapingRelative = (new ReadFile)->handle(new Request([
        'value' => '../composer.json',
    ]));

    expect((string) $escapingAbsolute)->toBe('Path is outside the working directory, or no working directory is set.')
        ->and((string) $escapingRelative)->toBe('Path is outside the working directory, or no working directory is set.');
});
