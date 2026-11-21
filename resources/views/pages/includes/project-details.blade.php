{{--
    A project folder as the user confirms it: its name, full path, and how many
    files it holds and their size (vendor, node_modules and hidden folders left out).
--}}
<dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm" data-test="{{ $test }}">
    <dt class="text-zinc-500 dark:text-zinc-400">Folder</dt>
    <dd class="font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $details['name'] }}</dd>

    <dt class="text-zinc-500 dark:text-zinc-400">Path</dt>
    <dd class="font-mono text-xs break-all text-zinc-700 dark:text-zinc-200">{{ $details['path'] }}</dd>

    <dt class="text-zinc-500 dark:text-zinc-400">Contents</dt>
    <dd class="text-zinc-700 dark:text-zinc-200">
        {{ number_format($details['files']) }} {{ Str::plural('file', $details['files']) }}, {{ $details['size'] }}
        <span class="text-zinc-500 dark:text-zinc-400">(without vendor, node_modules and hidden folders)</span>
    </dd>
</dl>
