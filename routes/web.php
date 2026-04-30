<?php

use App\Ai\Agents\Qwen3_8b_8k;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::chat')->name('home');

// Route::view('/', 'welcome')->name('home');

// Route::middleware(['auth', 'verified'])->group(function (): void {
//     Route::view('dashboard', 'dashboard')->name('dashboard');
// });

// require __DIR__.'/settings.php';

Route::get('/test', function () {
    $response = (new Qwen3_8b_8k)
        ->prompt('Hi there');

    return (string) $response;
});

Route::get('/test-tasks', function (): void {
    set_time_limit(300);

    $agent = new Qwen3_8b_8k;

    $message = "
        how would you go about adding a new livewire v4 single file component (SFC) page to a laravel app? the livewire page should be available at 
        '/new-page' and be called 'new-page', then on that page it should output the text 'Hello, world!'.
    ";

    $instructions = '
        Based on the following user message, give me a list of tasks that would need to be carried out to provide a working solution for the user. 
        Only return the list of tasks, do not include any other commentary. 
        If there are no tasks return "No tasks".
        If the message is unclear, return "Reframe your question".
        Please delimit the tasks with a "|" pipe character. User message: 
    ';

    $response = $agent
        ->prompt($instructions.$message);

    $tasksArr = explode('|', $response->text);

    dd($tasksArr);
});
