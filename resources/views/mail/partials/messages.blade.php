{{-- Messages as escaped HTML, not markdown, so what someone typed shows as they typed it. No blank lines: one would end the HTML block. --}}
@foreach ($messages as $message)
<p style="margin: 0 0 4px; font-size: 13px; color: #6b7280;">{{ $message['from'] }} · {{ $message['at'] }}</p>
<div style="margin: 0 0 16px; padding: 12px 16px; border-left: 3px solid #ff6b1a; background-color: #f6f3ec; color: #0b1f33; font-size: 15px; line-height: 1.5;">{!! nl2br(e($message['body']), false) !!}</div>
@endforeach
