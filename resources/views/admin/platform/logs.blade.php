@extends('layouts.admin')

@section('title', __('nav.layouts_admin_nav_error_log'))

{{--
    The error log.

    Exists so a failure can be REPORTED rather than described: the message, when
    it happened, and the stack trace behind a fold. Read-only by design — there is
    no clear button, because a log a viewer can prune is not an audit trail.

    Every line is rendered with {{ }}, never unescaped: a log entry contains
    whatever an attacker managed to get logged, markup included.
--}}

@php
    $levelTone = [
        'EMERGENCY' => ['bg-red-100', 'text-red-700'],
        'ALERT'     => ['bg-red-100', 'text-red-700'],
        'CRITICAL'  => ['bg-red-100', 'text-red-700'],
        'ERROR'     => ['bg-red-100', 'text-red-700'],
        'WARNING'   => ['bg-amber-100', 'text-amber-700'],
        'INFO'      => ['bg-blue-100', 'text-blue-700'],
        'DEBUG'     => ['bg-gray-100', 'text-gray-600'],
    ];
@endphp

@section('content')
<div class="space-y-6" x-data="{ open: null }">

    <x-admin-hero
        :title="__('nav.layouts_admin_nav_error_log')"
        eyebrow="System"
        :subtitle="__('admin.error_log_subtitle')"
        icon="bi-bug-fill"
        :count="count($entries)"
        :countLabel="__('admin.error_log_entries')">
        <x-slot:actions>
            <a href="{{ route('admin.platform.logs', request()->only('filter', 'level', 'limit')) }}"
               data-shell-link data-route="admin.platform.logs"
               class="px-4 py-2 rounded-full text-sm font-semibold bg-white/15 border border-white/25 backdrop-blur flex items-center gap-2 hover:bg-white/25 transition-colors">
                <i class="bi bi-arrow-clockwise"></i>{{ __('admin.error_log_refresh') }}
            </a>
            <button type="button" onclick="copyErrorLog(this)"
                    class="px-4 py-2 rounded-full text-sm font-semibold bg-white text-primary flex items-center gap-2 hover:bg-white/90 transition-colors">
                <i class="bi bi-clipboard"></i>{{ __('admin.error_log_copy') }}
            </button>
        </x-slot:actions>
    </x-admin-hero>

    {{-- Filters. A GET form, so a filtered view is a shareable URL. --}}
    <form method="GET" action="{{ route('admin.platform.logs') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1 min-w-0">
            <label for="log-filter" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.error_log_search') }}</label>
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="filter" id="log-filter" value="{{ $filter }}"
                       placeholder="{{ __('admin.error_log_search_hint') }}"
                       class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>

        <div class="sm:w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.error_log_level') }}</label>
            <x-select-menu
                name="level"
                :value="$level"
                :options="[
                    ['value' => '', 'label' => __('admin.error_log_all_levels')],
                    ['value' => 'ERROR', 'label' => 'ERROR'],
                    ['value' => 'CRITICAL', 'label' => 'CRITICAL'],
                    ['value' => 'WARNING', 'label' => 'WARNING'],
                    ['value' => 'INFO', 'label' => 'INFO'],
                    ['value' => 'DEBUG', 'label' => 'DEBUG'],
                ]" />
        </div>

        <div class="sm:w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.error_log_show') }}</label>
            <x-select-menu
                name="limit"
                :value="(string) $limit"
                :options="collect([50, 100, 200, 500, 1000])->map(fn ($n) => ['value' => (string) $n, 'label' => trans_choice('admin.error_log_n_entries', $n, ['count' => $n])])->all()" />
        </div>

        <button type="submit"
                class="bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-medium flex items-center gap-2 flex-shrink-0">
            <i class="bi bi-funnel"></i>{{ __('admin.error_log_apply') }}
        </button>
    </form>

    {{-- The log itself --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
            <p class="text-xs text-muted-foreground font-mono truncate">{{ $logPath }}</p>
            <p class="text-xs text-muted-foreground">
                {{ __('admin.error_log_file_size', ['size' => number_format($logSize / 1024, 0)]) }}
                @if($truncated)
                    · <span class="text-amber-600">{{ __('admin.error_log_tail_only') }}</span>
                @endif
            </p>
        </div>

        @if($logMissing)
            <div class="p-10 text-center">
                <i class="bi bi-file-earmark-x text-3xl text-muted-foreground/50"></i>
                <p class="text-sm font-semibold text-foreground mt-3">{{ __('admin.error_log_no_file') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ $logPath }}</p>
            </div>
        @elseif(! count($entries))
            <div class="p-10 text-center">
                <i class="bi bi-check2-circle text-3xl text-green-500/60"></i>
                <p class="text-sm font-semibold text-foreground mt-3">{{ __('admin.error_log_none') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ __('admin.error_log_none_hint') }}</p>
            </div>
        @else
            <div class="divide-y divide-gray-100" id="error-log-body">
                @foreach($entries as $i => $entry)
                    @php $tone = $levelTone[$entry['level']] ?? ['bg-gray-100', 'text-gray-600']; @endphp
                    <div class="px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex-shrink-0 {{ $tone[0] }} {{ $tone[1] }}">
                                {{ $entry['level'] }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-foreground font-mono break-words">{{ $entry['message'] }}</p>
                                <p class="text-[11px] text-muted-foreground mt-1 tabular-nums">{{ $entry['stamp'] }}</p>
                            </div>
                            @if($entry['trace'] !== '')
                                {{-- The trace is folded away: it is the reason the page
                                     exists, and also the reason it would be unreadable
                                     if every entry printed forty lines. --}}
                                <button type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                        class="text-xs font-semibold text-primary flex-shrink-0 flex items-center gap-1">
                                    <i class="bi" :class="open === {{ $i }} ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                    {{ __('admin.error_log_trace') }}
                                </button>
                            @endif
                        </div>

                        @if($entry['trace'] !== '')
                            <pre x-show="open === {{ $i }}" x-cloak
                                 class="mt-3 p-3 rounded-lg bg-muted text-[11px] leading-relaxed text-gray-700 overflow-x-auto whitespace-pre">{{ $entry['trace'] }}</pre>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    /**
     * Copy the whole visible log, so it can be pasted into a bug report.
     *
     * Reads the rendered text rather than re-fetching: what gets pasted is exactly
     * what was on screen, filters and all.
     */
    function copyErrorLog(btn) {
        const body = document.getElementById('error-log-body');
        const text = body ? body.innerText.trim() : '';

        if (! text) {
            window.showToast && window.showToast('info', @js(__('admin.error_log_nothing_to_copy')));
            return;
        }

        navigator.clipboard.writeText(text)
            .then(() => window.showToast && window.showToast('success', @js(__('admin.error_log_copied'))))
            .catch(() => window.showToast && window.showToast('error', @js(__('shared.something_went_wrong'))));
    }
</script>
@endpush
