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
