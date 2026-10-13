<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->existingSessionContextDirectories = sessionContextDirectories();
    })
    ->afterEach(function (): void {
        foreach (array_diff(sessionContextDirectories(), $this->existingSessionContextDirectories) as $directory) {
            File::deleteDirectory($directory);
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The per-session context directories that currently exist under app/Ai/Sessions.
 *
 * Feature tests snapshot these before each test and remove only the ones the test created,
 * so session context written during tests never leaks into the working tree or wipes real data.
 *
 * @return list<string>
 */
function sessionContextDirectories(): array
{
    return File::isDirectory(app_path('Ai/Sessions')) ? File::directories(app_path('Ai/Sessions')) : [];
}
