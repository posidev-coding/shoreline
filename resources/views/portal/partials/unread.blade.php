@if ($count > 0)
    <span class="inline-flex items-center gap-1 rounded-full bg-signal-500 px-2.5 py-0.5 text-xs font-bold text-navy-900">
        <x-heroicon-s-chat-bubble-left-right class="size-3.5" aria-hidden="true" />
        {{ $count === 1 ? 'New message' : $count.' new messages' }}
    </span>
@endif
