<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\CommandRun;
use App\Services\BackgroundLauncher;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

/**
 * A launcher that records what it was asked to start, instead of starting it.
 */
function fakeLauncher(): BackgroundLauncher
{
    $launcher = new class extends BackgroundLauncher
    {
        /** @var list<list<string>> */
        public array $started = [];

        public function artisan(array $arguments): void
        {
            $this->started[] = $arguments;
        }
    };

    app()->instance(BackgroundLauncher::class, $launcher);

    return $launcher;
}

test('only the listed commands run: the page sends a key, never a command line', function (): void {
    $launcher = fakeLauncher();
    $session = ChatSession::create(['current_working_directory' => base_path()]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('runCommand', 'rm -rf /')
        ->call('runCommand', 'composer audit');

    expect(CommandRun::count())->toBe(0)
        ->and($launcher->started)->toBe([]);
});

test('a listed command is started in the background in the selected project', function (): void {
    $launcher = fakeLauncher();
    $session = ChatSession::create(['current_working_directory' => base_path()]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('runCommand', 'git-status')
        ->assertSee('Running')
        ->assertSee('git status');

    $run = CommandRun::sole();
    expect($run)
        ->command->toBe('git status')
        ->cwd->toBe(base_path())
        ->status->toBe(CommandRun::STATUS_RUNNING)
        ->and($launcher->started)->toBe([['synthera:run-command', (string) $run->id]]);
});

test('one command at a time, and a project folder is needed', function (): void {
    $launcher = fakeLauncher();
    $session = ChatSession::create(['current_working_directory' => base_path()]);
    CommandRun::create(['chat_session_id' => $session->id, 'command_key' => 'npm-audit', 'command' => 'npm audit', 'cwd' => base_path()]);

    Livewire::test('pages::chat', ['sessionId' => $session->id])->call('runCommand', 'git-status');
    expect(CommandRun::count())->toBe(1);

    $nowhere = ChatSession::create(['current_working_directory' => base_path('no-such-folder')]);
    Livewire::test('pages::chat', ['sessionId' => $nowhere->id])->call('runCommand', 'git-status');
    expect(CommandRun::count())->toBe(1)->and($launcher->started)->toBe([]);
});

test('the background command runs it, keeps its output, and adds the result to the chat', function (): void {
    config(['synthera-coder.commands.say' => ['group' => 'Test', 'label' => 'Say', 'icon' => 'beaker', 'command' => 'echo hello from the project']]);
    $session = ChatSession::create(['current_working_directory' => base_path()]);
    $run = CommandRun::create(['chat_session_id' => $session->id, 'command_key' => 'say', 'command' => 'echo hello from the project', 'cwd' => base_path()]);

    Artisan::call('synthera:run-command', ['run' => $run->id]);

    expect($run->fresh())
        ->status->toBe(CommandRun::STATUS_DONE)
        ->exit_code->toBe(0)
        ->output->toContain('hello from the project')
        ->finished_at->not->toBeNull();

    $message = ChatMessage::query()->where('chat_session_id', $session->id)->sole();
    expect($message->type)->toBe('info')
        ->and($message->content)->toContain('**Ran** `echo hello from the project` (exit 0)')
        ->toContain('hello from the project');

    // Run again, it does nothing: it has finished.
    Artisan::call('synthera:run-command', ['run' => $run->id]);
    expect(ChatMessage::query()->where('chat_session_id', $session->id)->count())->toBe(1);
});

test('a command that fails is marked failed with its exit code', function (): void {
    $session = ChatSession::create(['current_working_directory' => base_path()]);
    $run = CommandRun::create(['chat_session_id' => $session->id, 'command_key' => 'x', 'command' => 'git not-a-git-command', 'cwd' => base_path()]);

    Artisan::call('synthera:run-command', ['run' => $run->id]);

    expect($run->fresh())->status->toBe(CommandRun::STATUS_FAILED)->exit_code->not->toBe(0);
});

test('the page shows the output as it comes, then that it finished', function (): void {
    $session = ChatSession::create(['current_working_directory' => base_path()]);
    $run = CommandRun::create(['chat_session_id' => $session->id, 'command_key' => 'npm-audit', 'command' => 'npm audit', 'cwd' => base_path(), 'output' => 'found 0 vulnerabilities so far']);

    $page = Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertSee('found 0 vulnerabilities so far')
        ->assertSeeHtml('wire:poll.1s="pollRun"');

    $run->update(['status' => CommandRun::STATUS_DONE, 'exit_code' => 0, 'finished_at' => now()]);

    $page->set('activeRunId', $run->id)->call('pollRun')->assertSee('its output is in the chat')->call('dismissRun')->assertDontSee('its output is in the chat');
});

test('a run whose process went quiet long ago is not shown as running', function (): void {
    $session = ChatSession::create(['current_working_directory' => base_path()]);
    $run = CommandRun::create(['chat_session_id' => $session->id, 'command_key' => 'npm-audit', 'command' => 'npm audit', 'cwd' => base_path()]);
    $run->forceFill(['updated_at' => now()->subMinutes(CommandRun::LOST_AFTER_MINUTES + 1)])->saveQuietly();

    expect($run->fresh()->isRunning())->toBeFalse();
});

test('the launcher finds the PHP command line, not a web server build', function (): void {
    config(['synthera-coder.php_binary' => 'C:\\php\\php.exe']);
    expect((new BackgroundLauncher)->php())->toBe('C:\\php\\php.exe');

    config(['synthera-coder.php_binary' => null]);
    expect((new BackgroundLauncher)->php())->not->toMatch('/php-(cgi|fpm)/i');
});
