<?php

use App\Models\AgentAction;
use App\Models\ChatSession;
use App\Services\AgentActionExecutor;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * A temporary project with two files, and a chat working in it with a
 * proposed write pending.
 *
 * @return array{0: ChatSession, 1: string, 2: AgentAction}
 */
function unconfirmedProject(?string $confirmed = null): array
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'synthera-confirm-'.uniqid();
    File::makeDirectory($dir.'/app', 0755, true);
    File::put($dir.'/composer.json', '{"name":"acme/shop"}');
    File::put($dir.'/app/Post.php', '<?php // 2');
    File::makeDirectory($dir.'/vendor/pkg', 0755, true);
    File::put($dir.'/vendor/pkg/big.php', str_repeat('x', 5000));
    temporaryProjects($dir);

    $session = ChatSession::create([
        'current_working_directory' => $dir,
        'confirmed_working_directory' => $confirmed,
        'current_chat_type' => 'agent',
    ]);

    $action = AgentAction::create([
        'chat_session_id' => $session->id,
        'type' => AgentAction::TYPE_WRITE,
        'status' => AgentAction::STATUS_PENDING,
        'payload' => ['path' => 'notes.txt', 'contents' => 'hello'],
    ]);

    return [$session, $dir, $action];
}

afterEach(function (): void {
    foreach (temporaryProjects() as $dir) {
        File::deleteDirectory($dir);
    }
});

test('a proposed change is not applied until the project folder is confirmed', function (string $method): void {
    [$session, $dir, $action] = unconfirmedProject();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call($method, ...($method === 'approveAction' ? [$action->id] : []))
        ->assertNotDispatched('process-agent-turn');

    expect(File::exists($dir.'/notes.txt'))->toBeFalse()
        ->and($action->fresh()->status)->toBe(AgentAction::STATUS_PENDING);
})->with(['approveAction', 'approveAllActions']);

test('the changes panel shows the project folder to confirm: name, full path, files and size', function (): void {
    [$session, $dir] = unconfirmedProject();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->assertSeeHtml('data-test="confirm-project"')
        ->assertSee(basename($dir))
        ->assertSee($dir)
        // vendor is left out of the count: composer.json and app/Post.php.
        ->assertSee('2 files')
        ->assertSee('30 B');
});

test('confirming the project folder lets the change be applied', function (): void {
    [$session, $dir, $action] = unconfirmedProject();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->call('confirmWorkingDirectory')
        ->assertDontSeeHtml('data-test="confirm-project"')
        ->call('approveAction', $action->id);

    expect($session->fresh()->confirmed_working_directory)->toBe($dir)
        ->and(File::get($dir.'/notes.txt'))->toBe('hello')
        ->and($action->fresh()->status)->toBe(AgentAction::STATUS_EXECUTED);
});

test('switching project in the dialog shows the folder first, and confirms it', function (): void {
    [$session, $dir] = unconfirmedProject();

    Livewire::test('pages::chat', ['sessionId' => $session->id])
        ->set('tempSelectedProject', $dir)
        ->assertSeeHtml('data-test="switch-project-details"')
        ->call('changeWorkingDirectory');

    expect($session->fresh()->confirmed_working_directory)->toBe($dir);
});

test('a confirmation is for one folder: moving the chat to another needs a new one', function (): void {
    [$session, $dir] = unconfirmedProject();

    $session->update(['confirmed_working_directory' => $dir]);

    expect($session->fresh()->hasConfirmedWorkingDirectory())->toBeTrue();

    $session->update(['current_working_directory' => sys_get_temp_dir()]);

    expect($session->fresh()->hasConfirmedWorkingDirectory())->toBeFalse();
});

test('the executor itself will not apply a change in an unconfirmed folder', function (): void {
    [, $dir, $action] = unconfirmedProject();

    $result = app(AgentActionExecutor::class)->execute($action);

    expect($result)->toContain('Confirm the project folder')
        ->and($action->fresh()->status)->toBe(AgentAction::STATUS_PENDING)
        ->and(File::exists($dir.'/notes.txt'))->toBeFalse();
});
