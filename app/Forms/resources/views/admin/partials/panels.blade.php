{{-- The three panels that change when an answer arrives. Re-fetched as one
     piece (?fragment=panels) and swapped in; the tab state lives outside. --}}
<div id="fa-panels">
    <div x-show="tab === 'results'">@include('forms::admin.partials.results')</div>
    <div x-show="tab === 'decisions'" x-cloak>@include('forms::admin.partials.decisions')</div>
    <div x-show="tab === 'responses'" x-cloak>@include('forms::admin.partials.responses')</div>
</div>
