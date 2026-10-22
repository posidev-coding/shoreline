@props(['html'])
{{-- Owner-edited HTML, passed through Filament's sanitizer (Symfony HtmlSanitizer) before output. --}}
<div {{ $attributes->class('prose-site') }}>
    {!! \Filament\Forms\Components\RichEditor\RichContentRenderer::make($html)->toHtml() !!}
</div>
