{{--
    Plain textarea prose, rendered as the structure its author typed.

    A field like an event's "About" is written in a <textarea>: paragraphs are
    blank lines and lists are lines beginning "- ". Echoing that into a single
    <p> collapses all of it, and `whitespace-pre-line` only restores the
    newlines — never the paragraph rhythm or the list indent, because there are
    no elements to style. This turns it into real markup.

    The author's text is UNTRUSTED and is escaped in
    `App\Support\PlainProse::toHtml()` BEFORE any tag is added, which is why the
    result is rendered unescaped here. Nothing typed into the field can become
    markup.

        <x-prose-text :text="$e['about']" />
        <x-prose-text :text="$notes" text-class="text-sm text-muted-foreground" />

    Bullets: a line starting with "-", "*", "•" or "·" followed by a space.
--}}
@props([
    'text' => '',
    // Applied to every <p> and <li>, so the block type never changes the voice.
    'textClass' => 'text-[15px] leading-relaxed text-foreground',
    // The space BETWEEN blocks. The last block never carries it.
    'gapClass' => 'mb-3',
])

@php $prose = \App\Support\PlainProse::toHtml($text, $textClass, $gapClass); @endphp

@if ((string) $prose !== '')
    <div {{ $attributes }}>{!! $prose !!}</div>
@endif
