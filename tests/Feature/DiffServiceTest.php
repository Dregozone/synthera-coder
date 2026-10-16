<?php

use App\Services\DiffService;

test('diff rows number each line in the old and new file and count the changes', function (): void {
    $diffs = new DiffService;

    $rows = $diffs->rows($diffs->unified("one\ntwo\nthree", "one\n2\nthree\nfour", 'numbers.txt'));

    expect($rows['added'])->toBe(2)
        ->and($rows['removed'])->toBe(1)
        ->and($rows['segments'])->toHaveCount(1)
        ->and($rows['segments'][0]['rows'])->toBe([
            ['type' => 'context', 'old' => 1, 'new' => 1, 'text' => 'one'],
            ['type' => 'remove', 'old' => 2, 'new' => null, 'text' => 'two'],
            ['type' => 'add', 'old' => null, 'new' => 2, 'text' => '2'],
            ['type' => 'context', 'old' => 3, 'new' => 3, 'text' => 'three'],
            ['type' => 'add', 'old' => null, 'new' => 4, 'text' => 'four'],
        ]);
});

test('diff rows fold long runs of unchanged lines away from any change', function (): void {
    $diffs = new DiffService;
    $old = implode("\n", range(1, 20));
    $new = str_replace("\n10\n", "\nten\n", $old);

    $segments = $diffs->rows($diffs->unified($old, $new, 'numbers.txt'))['segments'];

    expect(array_column($segments, 'folded'))->toBe([true, false, true])
        ->and($segments[0]['rows'])->toHaveCount(6)
        ->and($segments[1]['rows'])->toHaveCount(8)
        ->and($segments[2]['rows'])->toHaveCount(7)
        ->and($segments[2]['rows'][0])->toBe(['type' => 'context', 'old' => 14, 'new' => 14, 'text' => '14']);
});

test('a new file is all additions and an empty new file has no rows', function (): void {
    $diffs = new DiffService;

    $created = $diffs->rows($diffs->unified('', "<?php\n\necho 'hi';", 'hello.php'));
    $empty = $diffs->rows($diffs->unified('', '', 'empty.txt'));

    expect($created['added'])->toBe(3)
        ->and($created['removed'])->toBe(0)
        ->and(array_column($created['segments'][0]['rows'], 'new'))->toBe([1, 2, 3])
        ->and($empty['segments'])->toBe([]);
});

test('files are highlighted in the language their name suggests', function (string $path, string $language): void {
    expect((new DiffService)->language($path))->toBe($language);
})->with([
    ['app/Models/User.php', 'php'],
    ['resources/views/welcome.blade.php', 'php-template'],
    ['resources/js/app.js', 'javascript'],
    ['package.json', 'json'],
    ['.github/workflows/tests.yml', 'yaml'],
    ['.env', 'ini'],
    ['README', 'plaintext'],
]);
