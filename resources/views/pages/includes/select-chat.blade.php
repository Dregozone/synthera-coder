@php
    $chatSessions = \App\Models\ChatSession::query()
        ->orderByDesc(
            \App\Models\ChatMessage::select('created_at')
                ->whereColumn('chat_session_id', 'chat_sessions.id')
                ->latest()
                ->limit(1)
        )
        ->take(10)
        ->get();
@endphp

<flux:modal name="select-chat" class="md:w-[25%]">
    <div class="space-y-6">        
        @foreach ($chatSessions as $session)
            <a 
                class="block p-4 border border-zinc-300 dark:border-zinc-700 shadow-md rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                href="{{ route('home', ['sessionId' => $session->id]) }}"
            >
                <div class="flex items-center justify-between mb-2">
                    <flux:badge>#{{ $session->id }}</flux:badge>
                    <div class="font-semibold">{!! $session->title ?? '<span class="italic text-zinc-500">No title</span>' !!}</div>
                    <flux:text>({{ $session->number_of_messages }} messages)</flux:text>
                    <flux:badge>{{ $session->created_at->diffForHumans() }}</flux:badge>
                </div>
            </a>
        @endforeach
    </div>
</flux:modal>
