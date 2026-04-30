<flux:modal name="select-chat" class="md:w-[25%]">
    <div class="space-y-6">
        @forelse ($chatSessions as $session)
            <a 
                class="block p-4 border border-zinc-300 dark:border-zinc-700 shadow-md rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                wire:navigate
                href="{{ route('home', ['sessionId' => $session->id]) }}"
            >
                <div class="flex items-center justify-between mb-2">
                    <flux:badge>#{{ $session->id }}</flux:badge>
                    <div class="font-semibold">
                        @if (filled($session->title))
                            {{ $session->title }}
                        @else
                            <span class="italic text-zinc-500">No title</span>
                        @endif
                    </div>
                    <flux:text>({{ $session->number_of_messages }} messages)</flux:text>
                    <flux:badge>{{ $session->updated_at->diffForHumans() }}</flux:badge>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                No recent chat sessions yet.
            </div>
        @endforelse
    </div>
</flux:modal>
