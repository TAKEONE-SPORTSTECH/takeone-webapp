@props([
    'class' => '',
])

{{--
    EN/AR (N-language) content toggle.

    The parent form/container must expose an Alpine `lang` variable, e.g.
        <form x-data="{ lang: 'en' }"> ... </form>
    Translatable fields render two sibling inputs switched by x-show:
        <input name="name" x-show="lang==='en'" ...>
        <input name="translations[name][ar]" dir="rtl" x-show="lang==='ar'" x-cloak ...>

    Buttons are generated from config/locales.php, so adding a language needs
    no change here.
--}}
@php $langs = config('locales', []); @endphp

@if(count($langs) > 1)
<div class="inline-flex rounded-lg border border-border overflow-hidden bg-white {{ $class }}" role="tablist">
    @foreach($langs as $code => $meta)
        <button type="button" @click="lang='{{ $code }}'"
                :class="lang==='{{ $code }}' ? 'bg-primary text-white' : 'bg-white text-muted-foreground hover:bg-accent'"
                class="px-3.5 py-1.5 text-sm font-medium transition-colors focus:outline-none">
            {{ $meta['native'] ?? strtoupper($code) }}
        </button>
    @endforeach
</div>
@endif
