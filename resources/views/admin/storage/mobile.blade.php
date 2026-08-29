@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Storage')

@php
    $driverCards = [
        ['value' => 'mount', 'label' => 'Mounted share', 'sub' => 'Streams straight off it', 'icon' => 'bi-hdd-rack'],
        ['value' => 'smb',   'label' => 'SMB connection', 'sub' => 'Copied here to play',   'icon' => 'bi-hdd-network'],
    ];
@endphp

@section('content')
<div class="min-h-screen bg-background pb-24" x-data="storageSettingsM(@js($vaults), @js($local), @js($writeTarget), @js($pipeline))">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="Back">
                <i class="bi bi-arrow-left text-xl rtl:rotate-180"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">Storage</p>
            <button type="button" @click="openCreate()"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="Attach storage">
                <i class="bi bi-plus-circle-fill text-xl"></i>
            </button>
        </div>
    </header>

    {{-- ===== Hero — answers "where is video going?" before anything else ===== --}}
    <header class="m-hero mx-4 mt-4 rounded-3xl px-5 py-5 text-white relative overflow-hidden">
        <div class="absolute -end-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                <i class="bi bi-hdd-stack text-2xl m-float"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">New video is written to</p>
                <h1 class="text-xl font-black leading-tight truncate" x-text="targetName()"></h1>
            </div>
            <div class="ms-auto text-end flex-shrink-0">
                <p class="text-2xl font-black leading-none" x-text="vaults.length"></p>
                <p class="text-[10px] uppercase tracking-wide text-white/70">attached</p>
            </div>
        </div>
        <p class="relative z-10 text-[12px] text-white/80 leading-snug mt-3" x-text="targetNote()"></p>
    </header>

    <div class="px-4 pt-5 space-y-6 mobile-stagger">

        {{-- ===== The machinery ===== --}}
        <section>
            <div class="flex items-center gap-2.5 mb-2.5">
                <span class="w-8 h-8 rounded-xl grid place-items-center flex-shrink-0 bg-indigo-100 text-indigo-600"><i class="bi bi-cpu text-sm"></i></span>
                <h3 class="text-sm font-bold text-foreground">Pipeline</h3>
            </div>
            <div class="m-card rounded-2xl p-4">
                <div class="min-w-0">
                    <span class="block text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Match video</span>
                    <span class="block text-sm font-bold text-foreground mt-0.5">Stored and streamed here</span>
                    <span class="block text-[11px] text-muted-foreground leading-snug mt-0.5">Camera clips are kept on the storage below and served from this server.</span>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-100">
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Watchable</p><p class="text-sm font-bold tabular-nums" x-text="pipeline.ready"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Working</p><p class="text-sm font-bold tabular-nums" x-text="pipeline.processing"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Attention</p><p class="text-sm font-bold tabular-nums" :class="pipeline.failed > 0 ? 'text-red-600' : ''" x-text="pipeline.failed"></p></div>
                </div>

                <p class="text-[11px] mt-2.5 flex items-start gap-1.5"
                   :class="pipeline.has_ffmpeg ? (pipeline.gpu ? 'text-green-600' : 'text-amber-600') : 'text-red-600'">
                    <i class="bi bi-cpu-fill mt-0.5"></i>
                    <span x-text="pipeline.has_ffmpeg
                        ? (pipeline.gpu ? 'Encoding on the GPU (' + pipeline.gpu_encoder + ')'
                                        : 'Encoding on the CPU — the GPU encode libraries are not installed on this server')
                        : 'No ffmpeg installed — video is stored and downloadable, but cannot be streamed'"></span>
                </p>
            </div>
        </section>

        {{-- ===== This server ===== --}}
        <section>
            <div class="flex items-center gap-2.5 mb-2.5">
                <span class="w-8 h-8 rounded-xl grid place-items-center flex-shrink-0 bg-slate-100 text-slate-600"><i class="bi bi-server text-sm"></i></span>
                <h3 class="text-sm font-bold text-foreground">This server</h3>
            </div>
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 bg-slate-100 text-slate-600"><i class="bi bi-hdd"></i></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-foreground">Local disk</p>
                            <span x-show="!writeTarget" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">RECEIVING</span>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Always available. Cannot be detached.</p>
                    </div>
                    <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0 mt-2"></span>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-100">
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Files</p><p class="text-sm font-bold tabular-nums" x-text="local.file_count"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Media</p><p class="text-sm font-bold tabular-nums" x-text="size(local.stored_bytes)"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Free</p><p class="text-sm font-bold tabular-nums" x-text="size(local.free_bytes)"></p></div>
                </div>
            </div>
        </section>

        {{-- ===== Attached ===== --}}
        <section>
            <div class="flex items-center gap-2.5 mb-2.5">
                <span class="w-8 h-8 rounded-xl grid place-items-center flex-shrink-0 bg-teal-100 text-teal-700"><i class="bi bi-hdd-network text-sm"></i></span>
                <h3 class="text-sm font-bold text-foreground">Attached storage</h3>
                <span class="ms-auto text-xs font-bold text-muted-foreground tabular-nums" x-text="vaults.length"></span>
            </div>

            <template x-if="vaults.length === 0">
                <button type="button" @click="openCreate()"
                        class="w-full m-card border border-dashed border-gray-200 rounded-2xl p-5 text-center flex flex-col items-center gap-1.5">
                    <i class="bi bi-plus-circle text-xl text-gray-300"></i>
                    <span class="text-sm font-semibold text-foreground">Attach a NAS</span>
                    <span class="text-[11px] text-muted-foreground leading-snug">None attached — video is kept on this server, which is a working setup.</span>
                </button>
            </template>

            <div class="space-y-3">
                <template x-for="v in vaults" :key="v.uuid">
                    <div class="m-card rounded-2xl p-4">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="v.serves_in_place ? 'bg-teal-100 text-teal-700' : 'bg-amber-100 text-amber-700'">
                                <i class="bi" :class="v.serves_in_place ? 'bi-hdd-rack' : 'bi-hdd-network'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-bold text-foreground truncate" x-text="v.name"></p>
                                    <span x-show="v.uuid === writeTarget" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">RECEIVING</span>
                                    <span x-show="v.read_only" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">READ-ONLY</span>
                                    <span x-show="!v.enabled" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground">OFF</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-0.5 font-mono truncate" x-text="v.location"></p>
                                <p class="text-[11px] mt-1" :class="v.serves_in_place ? 'text-green-600' : 'text-amber-600'"
                                   x-text="v.serves_in_place ? 'Streams directly — nothing kept here' : 'Copied here before playback'"></p>
                            </div>
                            <span class="w-2 h-2 rounded-full flex-shrink-0 mt-2"
                                  :class="v.last_status === 'online' ? 'bg-green-500' : (v.last_status === 'offline' ? 'bg-red-500' : 'bg-gray-300')"></span>
                        </div>

                        <p x-show="v.last_error" class="text-[11px] text-red-600 mt-2 bg-red-50 rounded-xl px-2.5 py-1.5 leading-snug" x-text="v.last_error"></p>

                        <template x-if="v.migration">
                            <div class="mt-2 rounded-xl px-2.5 py-2"
                                 :class="v.migration.state === 'running' ? 'bg-primary/5' : (v.migration.state === 'done' ? 'bg-green-50' : 'bg-amber-50')">
                                <p class="text-[11px] font-bold flex items-center gap-1.5"
                                   :class="v.migration.state === 'running' ? 'text-primary' : (v.migration.state === 'done' ? 'text-green-700' : 'text-amber-700')">
                                    <i class="bi" :class="v.migration.state === 'running' ? 'bi-arrow-repeat' : (v.migration.state === 'done' ? 'bi-check2-circle' : 'bi-exclamation-triangle')"></i>
                                    <span x-text="v.migration.state === 'running' ? 'Moving video onto this storage…'
                                                : (v.migration.state === 'done' ? 'Migration complete' : 'Migration stopped')"></span>
                                </p>
                                <p class="text-[10px] text-muted-foreground mt-0.5 tabular-nums">
                                    <span x-text="v.migration.moved"></span> moved<span x-show="v.migration.remaining"> · <span x-text="v.migration.remaining"></span> to go</span><span x-show="v.migration.failed"> · <span x-text="v.migration.failed"></span> failed</span>
                                </p>
                            </div>
                        </template>

                        <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-100">
                            <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Files</p><p class="text-sm font-bold tabular-nums" x-text="v.file_count"></p></div>
                            <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Stored</p><p class="text-sm font-bold tabular-nums" x-text="size(v.stored_bytes)"></p></div>
                            <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Free</p><p class="text-sm font-bold tabular-nums" x-text="size(v.free_bytes)"></p></div>
                        </div>

                        <div class="flex items-center gap-2 mt-3">
                            <button type="button" @click="test(v)" class="m-press flex-1 py-2 rounded-xl border border-gray-200 bg-white text-xs font-bold text-foreground">
                                <i class="bi bi-plug"></i> Test
                            </button>
                            <button type="button" @click="openEdit(v)" class="m-press flex-1 py-2 rounded-xl border border-gray-200 bg-white text-xs font-bold text-foreground">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button type="button" @click="drain(v)" x-show="v.file_count > 0" class="m-press flex-1 py-2 rounded-xl border border-gray-200 bg-white text-xs font-bold text-foreground">
                                <i class="bi bi-box-arrow-up"></i> Drain
                            </button>
                            <button type="button" @click="detach(v)" class="m-press w-10 py-2 rounded-xl border border-red-200 text-red-600" aria-label="Detach">
                                <i class="bi bi-eject"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    {{-- ===== Attach / edit sheet (teleported past the mobile-shell transform) ===== --}}
    <template x-teleport="body">
        <div x-show="showForm" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="showForm = false">
            <div x-show="showForm" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="showForm = false"></div>
            <div class="fixed inset-x-0 bottom-0 flex justify-center">
                <div x-show="showForm"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="relative bg-background w-full sm:max-w-lg rounded-t-3xl shadow-2xl flex flex-col"
                     style="max-height: 92vh; max-height: 92dvh;" @click.stop>

                    <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                         style="background: linear-gradient(150deg, #0e6e63, #0e6e63b0);">
                        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                        <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                        <div class="relative flex items-start gap-3">
                            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                <i class="bi bi-hdd-network text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-lg font-black leading-tight" x-text="editing ? 'Edit storage' : 'Attach storage'"></h4>
                                <p class="text-[12px] text-white/85 mt-0.5" x-text="form.name || 'Somewhere to keep match video'"></p>
                            </div>
                            <button type="button" @click="showForm = false" aria-label="{{ __('shared.close') }}"
                                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pt-4 pb-5 space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Name</label>
                            <input type="text" x-model="form.name" placeholder="e.g. Hall NAS"
                                   class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                        </div>

                        {{-- Driver — selection cards, never a native select --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">How we reach it</label>
                            <div class="space-y-2">
                                @foreach($driverCards as $card)
                                    <button type="button" @click="form.driver = '{{ $card['value'] }}'"
                                            class="m-press w-full flex items-center gap-3 px-3.5 py-3 rounded-xl border text-start transition-colors"
                                            :class="form.driver === '{{ $card['value'] }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                              :class="form.driver === '{{ $card['value'] }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                            <i class="bi {{ $card['icon'] }}"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ $card['label'] }}</span>
                                            <span class="block text-[11px] text-muted-foreground">{{ $card['sub'] }}</span>
                                        </span>
                                        <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                              :class="form.driver === '{{ $card['value'] }}' ? 'border-primary' : 'border-gray-300'">
                                            <span x-show="form.driver === '{{ $card['value'] }}'" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <template x-if="form.driver === 'mount'">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Mount path</label>
                                <input type="text" x-model="form.mount_path" placeholder="/mnt/nas/takeone" inputmode="url"
                                       class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                <p class="text-[11px] text-muted-foreground mt-1.5 leading-snug">Mount the share on the server first. A path that is not really a mount is refused — writing to one would quietly fill the local disk.</p>
                            </div>
                        </template>

                        <template x-if="form.driver === 'smb'">
                            <div class="space-y-4">
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Host</label>
                                        <input type="text" x-model="form.host" placeholder="10.1.40.11" inputmode="url"
                                               class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Port</label>
                                        <input type="number" x-model="form.port" inputmode="numeric" placeholder="445"
                                               class="w-full px-3 py-3 bg-white border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Share</label>
                                    <input type="text" x-model="form.share" placeholder="Media"
                                           class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Username</label>
                                        <input type="text" x-model="form.username" autocomplete="off"
                                               class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Password</label>
                                        <input type="password" x-model="form.password" autocomplete="new-password"
                                               :placeholder="editing && editing.has_password ? '••••••••' : ''"
                                               class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                                    </div>
                                </div>
                                <p class="text-[11px] text-muted-foreground flex items-center gap-1"><i class="bi bi-shield-lock"></i> Stored encrypted. Never shown again.</p>
                            </div>
                        </template>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Folder inside</label>
                                <input type="text" x-model="form.root_path" placeholder="takeone/media"
                                       class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Priority</label>
                                <input type="number" min="0" x-model="form.priority" inputmode="numeric" placeholder="0"
                                       class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <button type="button" @click="form.enabled = !form.enabled"
                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl bg-white border border-gray-200">
                                <span class="min-w-0 text-start">
                                    <span class="block text-sm font-semibold text-foreground">Attached</span>
                                    <span class="block text-[11px] text-muted-foreground">Off keeps the settings without using it</span>
                                </span>
                                <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors" :class="form.enabled ? 'bg-primary' : 'bg-gray-200'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                                </span>
                            </button>
                            <button type="button" @click="form.read_only = !form.read_only"
                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl bg-white border border-gray-200">
                                <span class="min-w-0 text-start">
                                    <span class="block text-sm font-semibold text-foreground">Read-only</span>
                                    <span class="block text-[11px] text-muted-foreground">Keep serving it, send new video elsewhere</span>
                                </span>
                                <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors" :class="form.read_only ? 'bg-primary' : 'bg-gray-200'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.read_only ? 'translate-x-6' : 'translate-x-1'"></span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background flex items-center gap-2"
                         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="showForm = false" class="m-press flex-1 py-3 rounded-xl border border-gray-200 bg-white text-sm font-bold text-muted-foreground">Cancel</button>
                        <button type="button" @click="save()" :disabled="saving"
                                class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-bold disabled:opacity-60">
                            <span x-show="!saving">Save &amp; test</span><span x-show="saving">Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection

{{-- Inline, not @push — the mobile shell re-runs inline scripts on every AJAX
     nav, and a pushed script would not be re-registered after a swap. --}}
<script>
function storageSettingsM(vaults, local, writeTarget, pipeline) {
    return {
        vaults: vaults || [],
        local: local || {},
        writeTarget: writeTarget || null,
        pipeline: pipeline || {},
        showForm: false,
        editing: null,
        saving: false,
        form: {},

        init() { this.form = this.blank(); },
        blank() {
            return {
                uuid: null, name: '', driver: 'mount', mount_path: '', host: '', port: 445,
                share: '', username: '', password: '', domain: '', root_path: '',
                priority: 0, enabled: true, read_only: false,
            };
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },

        size(bytes) {
            if (bytes === null || bytes === undefined || bytes === '') return '—';
            let b = Number(bytes);
            if (!b) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            let i = 0;
            while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
            return (i === 0 ? b : b.toFixed(1)) + ' ' + units[i];
        },

        targetName() {
            const v = this.vaults.find(v => v.uuid === this.writeTarget);
            return v ? v.name : 'Local disk';
        },
        targetNote() {
            const v = this.vaults.find(v => v.uuid === this.writeTarget);
            if (!v) {
                return this.vaults.length
                    ? 'No attached storage is reachable, so video is being kept here for now.'
                    : 'No storage attached — video is kept on this server.';
            }
            return v.serves_in_place
                ? 'Streamed straight off it. Nothing is kept on this server.'
                : 'Copied here for playback, then served from the local cache.';
        },

        openCreate() { this.editing = null; this.form = this.blank(); this.showForm = true; },
        openEdit(v) {
            this.editing = v;
            this.form = {
                uuid: v.uuid, name: v.name, driver: v.driver,
                mount_path: v.mount_path || '', host: v.host || '', port: v.port || 445,
                share: v.share || '', username: v.username || '', password: '',
                domain: v.domain || '', root_path: v.root_path || '',
                priority: v.priority || 0, enabled: !!v.enabled, read_only: !!v.read_only,
            };
            this.showForm = true;
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            const editing = !!this.form.uuid;
            const url = editing ? `/admin/storage/vaults/${this.form.uuid}` : '/admin/storage/vaults';
            const payload = { ...this.form };
            if (editing) payload._method = 'PUT';
            if (payload.password === '') delete payload.password;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    const v = data.vault;
                    window.showToast && window.showToast(
                        v.last_status === 'online' ? 'success' : 'warning',
                        v.last_status === 'online' ? `${v.name} attached and reachable.` : (v.last_error || 'Saved, but could not be reached.')
                    );
                    this.showForm = false;
                    window.location.reload();
                } else {
                    const first = data.errors ? Object.values(data.errors)[0][0] : null;
                    window.showToast && window.showToast('error', first || data.message || 'Could not save.');
                }
            } catch (e) {
                window.showToast && window.showToast('error', 'Could not save.');
            } finally { this.saving = false; }
        },


        async test(v) {
            window.showToast && window.showToast('info', `Testing ${v.name}…`);
            try {
                const res = await fetch(`/admin/storage/vaults/${v.uuid}/test`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: '{}',
                });
                const data = await res.json();
                window.showToast && window.showToast(data.success ? 'success' : 'error', data.message);
                if (data.vault) {
                    const i = this.vaults.findIndex(x => x.uuid === data.vault.uuid);
                    if (i !== -1) this.vaults[i] = data.vault;
                }
            } catch (e) { window.showToast && window.showToast('error', 'Test failed.'); }
        },

        async drain(v) {
            const ok = window.confirmAction
                ? await window.confirmAction({ title: 'Drain this storage', message: `Move video off "${v.name}"? It goes read-only while draining.`, confirmText: 'Start' })
                : true;
            if (!ok) return;
            window.showToast && window.showToast('info', 'Draining…');
            try {
                const res = await fetch(`/admin/storage/vaults/${v.uuid}/drain`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ limit: 5 }),
                });
                const data = await res.json();
                window.showToast && window.showToast(data.remaining === 0 ? 'success' : 'info', data.message);
                window.location.reload();
            } catch (e) { window.showToast && window.showToast('error', 'Drain failed.'); }
        },

        async detach(v, force = false) {
            if (!force) {
                const ok = window.confirmAction
                    ? await window.confirmAction({ title: 'Detach storage', message: `Detach "${v.name}"? New video will go elsewhere.`, type: 'danger', confirmText: 'Detach' })
                    : true;
                if (!ok) return;
            }
            try {
                const res = await fetch(`/admin/storage/vaults/${v.uuid}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ _method: 'DELETE', force }),
                });
                const data = await res.json();
                if (res.status === 409 && data.needs_confirmation) {
                    const ok = window.confirmAction
                        ? await window.confirmAction({ title: 'Videos are stored here', message: data.message, type: 'danger', confirmText: 'Detach anyway' })
                        : false;
                    if (ok) return this.detach(v, true);
                    return;
                }
                if (data.success) {
                    window.showToast && window.showToast('success', data.message);
                    window.location.reload();
                } else {
                    window.showToast && window.showToast('error', data.message || 'Could not detach.');
                }
            } catch (e) { window.showToast && window.showToast('error', 'Could not detach.'); }
        },
    };
}
</script>
