@extends('members::layouts.personal-mobile')

@section('title', __('cert::messages.mine_title'))

@section('personal-content')
@php
    $strings = ['copied' => __('cert::messages.link_copied')];
    $medalInk = ['gold' => '#b8892b', 'silver' => '#8a939c', 'bronze' => '#a0612e'];
@endphp
@include('cert::mine.partials.script')
<x-media-lightbox event-name="open-my-certificate" />

<div class="-mx-4 -mt-4" x-data="myCertificates(@js($certificates), @js(route('me.certificates.list')), @js($strings))"
     @realtime:certificates.window="if ($event.detail && $event.detail.action === 'refresh') reload()">

    {{-- A top-level destination with no subject colour: the shared mesh, no back control (Design Rule #6). --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative z-10 mt-2">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-patch-check-fill"></i> {{ __('cert::messages.console_title') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur" x-text="@js(__('cert::messages.count_issued')).replace(':count', rows.filter(r => r.status === 'approved').length)"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('cert::messages.mine_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-person"></i>{{ auth()->user()->certificateName() }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 px-4 pb-8 space-y-3 mobile-stagger">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 flex gap-1">
            @foreach(['all' => 'cert::messages.filter_all', 'participation' => 'cert::messages.types.participation', 'podium' => 'cert::messages.types.podium'] as $f => $label)
                <button type="button" @click="filter = '{{ $f }}'" class="m-press flex-1 h-10 rounded-xl text-[13px] font-bold transition-colors"
                        :class="filter === '{{ $f }}' ? 'bg-primary text-white' : 'text-muted-foreground'">{{ __($label) }}</button>
            @endforeach
        </div>

        <template x-if="! shown.length">
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center m-float"><i class="bi bi-patch-check text-3xl"></i></span>
                <p class="font-bold text-foreground mt-4">{{ __('cert::messages.mine_empty_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('cert::messages.mine_empty') }}</p>
            </div>
        </template>

        <template x-for="row in shown" :key="row.uuid">
            <article class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <button type="button" @click="view(row)" class="w-full text-start flex gap-3 p-3">
                    {{-- a miniature of the paper, in its medal's metal --}}
                    <span class="flex-shrink-0 rounded-sm border-2" style="width: 100px; height: 71px; padding: 3px; background: #fbf8f2;"
                          :style="'border-color:' + (@js($medalInk)[medal(row.place)] || '#7c6bf5')">
                        <span class="w-full h-full border grid place-items-center" :style="'border-color:' + (@js($medalInk)[medal(row.place)] || '#7c6bf5')">
                            <span class="text-center">
                                <i class="bi text-base" :class="row.place ? 'bi-award-fill' : 'bi-patch-check-fill'" :style="'color:' + (@js($medalInk)[medal(row.place)] || '#7c6bf5')"></i>
                                <span class="block w-12 h-0.5 mx-auto mt-1 rounded-full bg-gray-300"></span>
                                <span class="block w-8 h-0.5 mx-auto mt-1 rounded-full bg-gray-200"></span>
                            </span>
                        </span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide"
                              :class="row.status === 'revoked' ? 'bg-rose-50 text-rose-700' : (row.place ? 'bg-amber-50 text-amber-800' : 'bg-accent text-primary')"
                              x-text="row.status === 'revoked' ? row.status_label : row.type_label"></span>
                        <span class="block text-[15px] font-extrabold text-foreground mt-1 leading-snug break-words" x-text="row.what"></span>
                        <span class="block text-[12px] text-muted-foreground mt-0.5 leading-snug break-words" x-text="(row.context ? row.context.title + ' · ' : '') + (row.issued_on || '')"></span>
                    </span>
                </button>
                <div class="flex border-t border-gray-100" x-show="row.status !== 'revoked'">
                    <a :href="row.download_url" class="m-press flex-1 h-11 text-[13px] font-bold text-primary inline-flex items-center justify-center gap-1.5"><i class="bi bi-download"></i>{{ __('cert::messages.pdf') }}</a>
                    <button type="button" @click="share(row)" class="m-press flex-1 h-11 border-s border-gray-100 text-[13px] font-bold text-primary inline-flex items-center justify-center gap-1.5"><i class="bi bi-share"></i>{{ __('cert::messages.share') }}</button>
                    <a :href="row.verify_url" class="m-press flex-1 h-11 border-s border-gray-100 text-[13px] font-bold text-primary inline-flex items-center justify-center gap-1.5"><i class="bi bi-qr-code-scan"></i>{{ __('cert::messages.verify') }}</a>
                </div>
                <p x-show="row.status === 'revoked'" class="border-t border-red-100 bg-rose-50/60 px-3 py-2 text-[12px] text-rose-700"
                   x-text="@js(__('cert::messages.revoked_on')).replace(':date', row.revoked_on || '')"></p>
            </article>
        </template>
    </div>
</div>
@endsection
