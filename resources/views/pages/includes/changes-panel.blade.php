{{--
    The proposed changes, one open at a time: each file's diff (line numbers,
    highlighted code, unchanged runs folded) or command, with what to do with it.
    Keyed by the actions shown, so resolving one opens the next.
--}}
@php($writeCount = $actions->where('type', \App\Models\AgentAction::TYPE_WRITE)->count())
@php($commandCount = $actions->count() - $writeCount)
@php($diffService = app(\App\Services\DiffService::class))

<div
    wire:key="changes-{{ $actions->pluck('id')->implode('-') }}"
    x-data="{ open: @js($actions->first()?->id) }"
    class="h-full flex flex-col border border-zinc-300 dark:border-zinc-700 rounded-2xl shadow-lg overflow-hidden"
>
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
        <div class="min-w-0">
            <flux:heading size="lg">Changes to review</flux:heading>
            <flux:text size="sm">
                {{ collect([
                    $writeCount ? $writeCount.' '.Str::plural('file', $writeCount) : null,
                    $commandCount ? $commandCount.' '.Str::plural('command', $commandCount) : null,
                ])->filter()->implode(' and ') }}
                awaiting your approval
            </flux:text>
        </div>

        <flux:button size="sm" variant="primary" icon="check" wire:click="approveAllActions" wire:loading.attr="disabled">
            Accept all
        </flux:button>
    </div>

    <div class="grow overflow-y-auto p-3 flex flex-col gap-3">
        @foreach ($actions as $action)
            @php($isWrite = $action->type === \App\Models\AgentAction::TYPE_WRITE)
            @php($diff = $diffs[$action->id] ?? null)

            <div
                wire:key="change-{{ $action->id }}"
                x-data="{ requesting: false, note: '' }"
                class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden"
                data-test="change-{{ $action->id }}"
            >
                <button
                    type="button"
                    x-on:click="open = open === {{ $action->id }} ? null : {{ $action->id }}"
                    class="w-full flex items-center gap-2 px-3 py-2 text-left bg-zinc-50 dark:bg-zinc-800/60 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                    <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-500 transition-transform" x-bind:class="open === {{ $action->id }} && 'rotate-90'" />

                    @if ($isWrite)
                        <flux:icon.document-text class="size-4 shrink-0 text-violet-500" />
                        <span class="font-mono text-sm truncate text-zinc-800 dark:text-zinc-200">{{ $action->payload['path'] ?? '' }}</span>
                        <flux:badge size="sm" :color="($action->payload['is_new'] ?? false) ? 'emerald' : 'violet'" class="shrink-0">
                            {{ ($action->payload['is_new'] ?? false) ? 'New' : 'Edited' }}
                        </flux:badge>
                        <span class="ml-auto shrink-0 font-mono text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">+{{ $diff['added'] ?? 0 }}</span>
                            <span class="text-rose-600 dark:text-rose-400">−{{ $diff['removed'] ?? 0 }}</span>
                        </span>
                    @else
                        <flux:icon.command-line class="size-4 shrink-0 text-amber-500" />
                        <span class="font-mono text-sm truncate text-zinc-800 dark:text-zinc-200">{{ $action->payload['command'] ?? '' }}</span>
                        <flux:badge size="sm" color="amber" class="ml-auto shrink-0">Command</flux:badge>
                    @endif
                </button>

                <div x-show="open === {{ $action->id }}" @if (! $loop->first) x-cloak @endif>
                    @if ($isWrite)
                        @php($language = $diffService->language((string) ($action->payload['path'] ?? '')))

                        <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                            @if (empty($diff['segments']))
                                <flux:text size="sm" class="p-3">An empty file.</flux:text>
                            @else
                                <table class="w-full border-collapse font-mono text-xs leading-5" data-test="diff">
                                    @foreach ($diff['segments'] as $segment)
                                        <tbody @if ($segment['folded']) x-data="{ shown: false }" @endif>
                                            @if ($segment['folded'])
                                                <tr x-show="! shown">
                                                    <td colspan="4" class="p-0">
                                                        <button type="button" x-on:click="shown = true" class="w-full py-1 text-center text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-900/50">
                                                            Show {{ count($segment['rows']) }} unchanged lines
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endif

                                            @foreach ($segment['rows'] as $row)
                                                <tr
                                                    @if ($segment['folded']) x-show="shown" x-cloak @endif
                                                    @class([
                                                        'bg-emerald-50 dark:bg-emerald-950/40' => $row['type'] === 'add',
                                                        'bg-rose-50 dark:bg-rose-950/40' => $row['type'] === 'remove',
                                                    ])
                                                >
                                                    <td class="w-px select-none px-2 text-right text-zinc-400 dark:text-zinc-500">{{ $row['old'] }}</td>
                                                    <td class="w-px select-none px-2 text-right text-zinc-400 dark:text-zinc-500 border-r border-zinc-200 dark:border-zinc-700">{{ $row['new'] }}</td>
                                                    <td @class([
                                                        'w-px select-none px-1 text-center',
                                                        'text-emerald-600 dark:text-emerald-400' => $row['type'] === 'add',
                                                        'text-rose-600 dark:text-rose-400' => $row['type'] === 'remove',
                                                    ])>{{ ['add' => '+', 'remove' => '−', 'context' => ''][$row['type']] }}</td>
                                                    <td class="whitespace-pre pr-4 text-zinc-800 dark:text-zinc-200"><code wire:ignore x-highlight data-language="{{ $language }}">{{ $row['text'] }}</code></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    @endforeach
                                </table>
                            @endif
                        </div>
                    @else
                        <div class="border-t border-zinc-200 dark:border-zinc-700 p-3">
                            <pre class="text-xs bg-zinc-50 dark:bg-zinc-950 rounded-lg p-2 whitespace-pre-wrap break-words"><code wire:ignore x-highlight data-language="bash">{{ $action->payload['command'] ?? '' }}</code></pre>
                            @if (filled($action->payload['cwd'] ?? null))
                                <flux:text size="sm" class="mt-2">Runs in {{ basename((string) $action->payload['cwd']) }}</flux:text>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-2 px-3 py-2 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-900/40">
                        <flux:button size="sm" variant="primary" :icon="$isWrite ? 'check' : 'play'" wire:click="approveAction({{ $action->id }})" wire:loading.attr="disabled">
                            {{ $isWrite ? 'Accept' : 'Run' }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" icon="chat-bubble-left-ellipsis" x-on:click="requesting = ! requesting">
                            Request changes
                        </flux:button>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="rejectAction({{ $action->id }})" wire:loading.attr="disabled" class="ml-auto">
                            Reject
                        </flux:button>
                    </div>

                    <div x-show="requesting" x-cloak class="px-3 py-3 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:textarea x-model="note" rows="3" placeholder="What should change? This goes back to the model, which proposes it again." />
                        <div class="flex justify-end gap-2 mt-2">
                            <flux:button size="sm" variant="ghost" x-on:click="requesting = false">Cancel</flux:button>
                            <flux:button size="sm" variant="primary" icon="paper-airplane" x-on:click="$wire.requestChanges({{ $action->id }}, note)" x-bind:disabled="note.trim() === ''">
                                Send back
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
