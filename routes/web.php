<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::chat')->name('home');
Route::livewire('/setup', 'pages::agent-setup')->name('agent-setup');
