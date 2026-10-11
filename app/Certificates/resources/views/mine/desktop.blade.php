@extends('layouts.app')

@section('title', __('cert::messages.mine_title'))

@section('content')
@php
    $strings = ['copied' => __('cert::messages.link_copied')];
@endphp
@include('cert::mine.partials.script')
<x-media-lightbox event-name="open-my-certificate" />

<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="myCertificates(@js($certificates), @js(route('me.certificates.list')), @js($strings))"
     @realtime:certificates.window="if ($event.detail && $event.detail.action === 'refresh') reload()">

    {{-- A top-level destination with no subject colour: the shared mesh, no back control (Design Rule #6). --}}
    <div class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-patch-check-fill"></i> {{ __('cert::messages.console_title') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur" x-text="@js(__('cert::messages.count_issued')).replace(':count', rows.filter(r => r.status === 'approved').length)"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('cert::messages.mine_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-person"></i>{{ auth()->user()->certificateName() }}</p>
        </div>
    </div>

    <div class="border-b border-gray-200 mb-5">
        <nav class="nav -mb-px flex gap-8">
            @foreach(['all' => 'cert::messages.filter_all', 'participation' => 'cert::messages.types.participation', 'podium' => 'cert::messages.types.podium'] as $f => $label)
                <button type="button" @click="filter = '{{ $f }}'" class="border-b-2 pb-3 font-medium text-sm transition-colors"
                        :class="filter === '{{ $f }}' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">{{ __($label) }}</button>
            @endforeach
        </nav>
    </div>

    <template x-if="! shown.length">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center"><i class="bi bi-patch-check text-3xl"></i></span>
            <p class="font-bold text-foreground mt-4">{{ __('cert::messages.mine_empty_title') }}</p>
            <p class="text-sm text-muted-foreground mt-1">{{ __('cert::messages.mine_empty') }}</p>
        </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <template x-for="row in shown" :key="row.uuid">
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                <button type="button" @click="view(row)" class="block relative text-start" style="background:#fbf8f2;">
                    <img :src="row.image_url" alt="" loading="lazy" class="w-full object-cover" :style="{ aspectRatio: row.portrait ? '210 / 297' : '297 / 210' }" style="aspect-ratio: 297 / 210;">
                    <span x-show="row.status === 'revoked'" class="absolute inset-0 bg-white/60 grid place-items-center">
                        <span class="px-3 py-1 rounded-full bg-red-600 text-white text-xs font-bold" x-text="row.status_label"></span>
                    </span>
                </button>
                <div class="p-4 flex-1">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide"
                          :class="row.status === 'revoked' ? 'bg-rose-50 text-rose-700' : (row.place ? 'bg-amber-50 text-amber-800' : 'bg-accent text-primary')"
                          x-text="row.status === 'revoked' ? row.status_label : row.type_label"></span>
                    <p class="text-base font-bold text-foreground mt-2 break-words" x-text="row.what"></p>
                    <p class="text-xs text-muted-foreground mt-1 break-words" x-text="(row.context ? row.context.title + ' · ' : '') + (row.issued_on || '')"></p>
                    <p class="text-xs text-muted-foreground/80 mt-1" x-text="row.number"></p>
                    <p x-show="row.status === 'revoked'" class="text-xs text-rose-700 mt-2" x-text="@js(__('cert::messages.revoked_on')).replace(':date', row.revoked_on || '')"></p>
                </div>
                <div class="flex border-t border-gray-100" x-show="row.status !== 'revoked'">
                    <a :href="row.download_url" class="flex-1 h-11 text-sm font-medium text-primary inline-flex items-center justify-center gap-1.5 hover:bg-muted/60 transition-colors"><i class="bi bi-download"></i>{{ __('cert::messages.pdf') }}</a>
                    <button type="button" @click="share(row)" class="flex-1 h-11 border-s border-gray-100 text-sm font-medium text-primary inline-flex items-center justify-center gap-1.5 hover:bg-muted/60 transition-colors"><i class="bi bi-share"></i>{{ __('cert::messages.share') }}</button>
                    <a :href="row.verify_url" target="_blank" rel="noopener" class="flex-1 h-11 border-s border-gray-100 text-sm font-medium text-primary inline-flex items-center justify-center gap-1.5 hover:bg-muted/60 transition-colors"><i class="bi bi-qr-code-scan"></i>{{ __('cert::messages.verify') }}</a>
                </div>
            </article>
        </template>
    </div>
</div>
@endsection
