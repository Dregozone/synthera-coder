<?php

use App\Models\ChatSession;
use App\Services\ProjectDirectories;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
    // A projects folder of its own: two projects and a worktree of one.
    $this->root = storage_path('framework/testing/projects-'.Str::uuid());
    foreach (['wifone', 'orbit', 'wifone.worktrees/issue-12-dark-mode'] as $dir) {
        File::ensureDirectoryExists($this->root.'/'.$dir);
    }
    app()->instance(ProjectDirectories::class, new ProjectDirectories($this->root));
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

test('the projects beside it are listed, with their worktrees', function (): void {
    $all = app(ProjectDirectories::class)->all();

    expect(array_keys($all))->toBe(['orbit', 'wifone', 'wifone.worktrees/issue-12-dark-mode'])
        ->and($all['wifone.worktrees/issue-12-dark-mode']['label'])->toBe('wifone › issue-12-dark-mode')
        ->and(app(ProjectDirectories::class)->find('wifone.worktrees/issue-12-dark-mode'))->toEndWith('issue-12-dark-mode')
        ->and(app(ProjectDirectories::class)->find('../elsewhere'))->toBeNull();
});

test('a link opens a project, carrying on with its latest chat', function (): void {
    $older = ChatSession::create(['current_working_directory' => $this->root.'/wifone', 'number_of_messages' => 3]);
    $latest = ChatSession::create(['current_working_directory' => $this->root.DIRECTORY_SEPARATOR.'wifone', 'number_of_messages' => 5]);
    ChatSession::create(['current_working_directory' => $this->root.'/orbit', 'number_of_messages' => 1]);
    $older->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

    get(route('home', ['project' => 'wifone', 'embed' => 1]))
        ->assertRedirectContains('project=wifone&embed=1&sessionId='.$latest->id);
});

test('a project with no chat yet gets a new one, working in it', function (): void {
    $response = get(route('home', ['project' => 'orbit']));
    $session = ChatSession::query()->latest('id')->first();
    $response->assertRedirectContains('project=orbit&sessionId='.$session->id);

    Livewire::withQueryParams(['project' => 'orbit'])
        ->test('pages::chat', ['sessionId' => $session->id])
        ->assertSet('selectedProject', $this->root.DIRECTORY_SEPARATOR.'orbit');

    expect(ProjectDirectories::same($session->fresh()->current_working_directory, $this->root.'/orbit'))->toBeTrue();
});

test('an unknown project in the link is ignored', function (): void {
    $session = ChatSession::create(['current_working_directory' => $this->root.'/wifone']);

    Livewire::withQueryParams(['project' => 'not-here'])
        ->test('pages::chat', ['sessionId' => $session->id])
        ->assertSet('selectedProject', $this->root.'/wifone');
});

test('choosing a project on the page drops the one from the link', function (): void {
    $session = ChatSession::create(['current_working_directory' => $this->root.'/wifone']);

    Livewire::withQueryParams(['project' => 'wifone'])
        ->test('pages::chat', ['sessionId' => $session->id])
        ->set('tempSelectedProject', $this->root.'/orbit')
        ->call('changeWorkingDirectory')
        ->assertSet('project', '')
        ->assertSet('selectedProject', $this->root.'/orbit');
});

test('embedded (as on a Life OS desk) it leaves out the footer; on its own it shows it', function (): void {
    $session = ChatSession::create(['current_working_directory' => $this->root.'/wifone']);

    get(route('home', ['sessionId' => $session->id]))->assertSee('Glacial Studio');
    get(route('home', ['sessionId' => $session->id, 'embed' => 1]))->assertDontSee('Glacial Studio');
});
