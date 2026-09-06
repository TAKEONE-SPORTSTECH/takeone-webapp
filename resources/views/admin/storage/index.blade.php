@extends('layouts.admin')

@section('title', 'Storage')

@php
    $driverOptions = [
        ['value' => 'mount', 'label' => 'Mounted share (NFS / CIFS)'],
        ['value' => 'smb', 'label' => 'SMB — we connect ourselves'],
    ];
@endphp

@section('admin-content')
<div class="space-y-6" x-data="storageSettings(@js($vaults), @js($local), @js($writeTarget), @js($pipeline))">

    <x-admin-hero title="Storage" eyebrow="System" icon="bi-hdd-stack"
        subtitle="Where match video is kept. Nothing attached means it lives on this server's own disk. Attach a NAS and new video goes there instead — as many as you like, highest priority first.">
        <x-slot:actions>
            <button type="button" @click="openCreate()"
                class="bg-white text-primary px-4 py-2 rounded-lg font-medium hover:bg-white/90 transition-colors inline-flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> Attach storage
            </button>
        </x-slot:actions>
    </x-admin-hero>

    {{-- Where new video is going right now. The one question this page exists to
         answer, so it is answered before anything has to be read. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-start gap-4">
        <span class="w-11 h-11 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
            <i class="bi bi-box-arrow-in-down text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-xs text-muted-foreground uppercase tracking-wide font-semibold">New video is written to</p>
            <p class="text-lg font-bold text-foreground mt-0.5" x-text="targetName()"></p>
            <p class="text-sm text-muted-foreground mt-0.5" x-text="targetNote()"></p>
        </div>
    </div>

    {{-- The machinery: whether this server can make new video watchable.
         There is no longer a choice of destination — match video is kept here,
         full stop, since the external video platform was disconnected. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="min-w-0">
            <p class="text-xs text-muted-foreground uppercase tracking-wide font-semibold">Match video</p>
            <p class="text-lg font-bold text-foreground mt-0.5">Stored and streamed here</p>
            <p class="text-sm text-muted-foreground mt-0.5">Clips filed by a mat camera are kept on the storage below and served from this server.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 pt-3 border-t border-gray-100">
            <div>
                <p class="text-xs text-muted-foreground">Encoder</p>
                <p class="text-sm font-semibold" :class="pipeline.has_ffmpeg ? 'text-foreground' : 'text-red-600'"
                   x-text="pipeline.has_ffmpeg ? (pipeline.gpu ? pipeline.gpu_encoder + ' (GPU)' : 'libx264 (CPU)') : 'not installed'"></p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Watchable</p>
                <p class="text-sm font-semibold text-foreground tabular-nums" x-text="pipeline.ready"></p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">In progress</p>
                <p class="text-sm font-semibold text-foreground tabular-nums" x-text="pipeline.processing"></p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Needs attention</p>
                <p class="text-sm font-semibold tabular-nums" :class="pipeline.failed > 0 ? 'text-red-600' : 'text-foreground'" x-text="pipeline.failed"></p>
            </div>
        </div>

        <p x-show="pipeline.has_ffmpeg && !pipeline.gpu" class="text-xs text-amber-700 bg-amber-50 rounded-lg px-2.5 py-1.5 mt-3">
            Encoding on the CPU. The GPUs are visible to this server but its NVIDIA encode libraries are not installed, so <span class="font-mono">h264_nvenc</span> cannot be used yet.
        </p>
        <p x-show="!pipeline.has_ffmpeg" class="text-xs text-red-700 bg-red-50 rounded-lg px-2.5 py-1.5 mt-3">
            No ffmpeg on this server. Video is still stored and can be downloaded, but it cannot be streamed until ffmpeg is installed.
        </p>
    </div>

    {{-- Local disk. Always present, never detachable — the floor everything
         stands on when no vault is attached or a vault is unreachable. --}}
    <div>
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">This server</h3>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold text-foreground">Local disk</h4>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground">ALWAYS ON</span>
                        <span x-show="!writeTarget" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary">RECEIVING</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1 font-mono truncate" x-text="local.root"></p>
                </div>
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0 mt-1.5" title="Available"></span>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-4 pt-3 border-t border-gray-100">
                <div>
                    <p class="text-xs text-muted-foreground">Files here</p>
                    <p class="text-sm font-semibold text-foreground" x-text="local.file_count"></p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Media size</p>
                    <p class="text-sm font-semibold text-foreground" x-text="size(local.stored_bytes)"></p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Disk free</p>
                    <p class="text-sm font-semibold text-foreground" x-text="size(local.free_bytes)"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- The attached vaults, in the order writes prefer them. --}}
    <div>
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">Attached storage</h3>

        <template x-if="vaults.length === 0">
            <div class="bg-white rounded-xl border border-dashed border-gray-200 p-8 text-center">
                <span class="w-12 h-12 rounded-2xl bg-muted grid place-items-center mx-auto text-muted-foreground">
                    <i class="bi bi-hdd-network text-xl"></i>
                </span>
                <p class="font-semibold text-foreground mt-3">No storage attached</p>
                <p class="text-sm text-muted-foreground mt-1 max-w-md mx-auto">
                    That is a working setup — video is kept on this server. Attach a NAS when you want it kept somewhere else.
                </p>
                <button type="button" @click="openCreate()"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white font-medium hover:bg-primary/90 transition-colors">
                    <i class="bi bi-plus-lg"></i> Attach storage
                </button>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <template x-for="v in vaults" :key="v.uuid">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="font-semibold text-foreground truncate" x-text="v.name"></h4>
                                <span x-show="v.uuid === writeTarget" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary">RECEIVING</span>
                                <span x-show="v.read_only" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">READ-ONLY</span>
                                <span x-show="!v.enabled" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground">DISABLED</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1 font-mono truncate" x-text="v.location"></p>
                            <p class="text-xs mt-1.5" :class="v.serves_in_place ? 'text-green-600' : 'text-amber-600'">
                                <i class="bi" :class="v.serves_in_place ? 'bi-lightning-charge-fill' : 'bi-arrow-down-circle'"></i>
                                <span x-text="v.serves_in_place ? 'Streams directly off this storage' : 'Copied to this server before playback'"></span>
                            </p>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5"
                              :class="v.last_status === 'online' ? 'bg-green-500' : (v.last_status === 'offline' ? 'bg-red-500' : 'bg-gray-300')"
                              :title="v.last_status"></span>
                    </div>

                    <p x-show="v.last_error" class="text-xs text-red-600 mt-2 bg-red-50 rounded-lg px-2.5 py-1.5" x-text="v.last_error"></p>

                    {{-- What the automatic migration is doing. Attaching storage
                         moves what is already here onto it, so this is the first
                         thing an operator wants to see after pressing Save. --}}
                    <template x-if="v.migration">
                        <div class="mt-3 rounded-lg px-3 py-2"
                             :class="v.migration.state === 'running' ? 'bg-primary/5 border border-primary/20'
                                   : (v.migration.state === 'done' ? 'bg-green-50 border border-green-100' : 'bg-amber-50 border border-amber-100')">
                            <div class="flex items-center gap-2 text-xs font-semibold"
                                 :class="v.migration.state === 'running' ? 'text-primary' : (v.migration.state === 'done' ? 'text-green-700' : 'text-amber-700')">
                                <i class="bi" :class="v.migration.state === 'running' ? 'bi-arrow-repeat' : (v.migration.state === 'done' ? 'bi-check2-circle' : 'bi-exclamation-triangle')"></i>
                                <span x-text="v.migration.state === 'running' ? 'Moving existing video onto this storage…'
                                            : (v.migration.state === 'done' ? 'Migration complete — nothing left on local disk'
                                            : 'Migration stopped')"></span>
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-1 tabular-nums">
                                <span x-text="v.migration.moved"></span> moved<span x-show="v.migration.remaining"> · <span x-text="v.migration.remaining"></span> to go</span><span x-show="v.migration.failed"> · <span x-text="v.migration.failed"></span> failed, sources kept</span>
                            </p>
                        </div>
                    </template>

                    <div class="grid grid-cols-4 gap-3 mt-4 pt-3 border-t border-gray-100">
                        <div>
                            <p class="text-xs text-muted-foreground">Files</p>
                            <p class="text-sm font-semibold text-foreground" x-text="v.file_count"></p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Stored</p>
                            <p class="text-sm font-semibold text-foreground" x-text="size(v.stored_bytes)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Free</p>
                            <p class="text-sm font-semibold text-foreground" x-text="size(v.free_bytes)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Priority</p>
                            <p class="text-sm font-semibold text-foreground" x-text="v.priority"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-3 flex-wrap">
                        <button type="button" @click="test(v)"
                            class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-plug"></i> Test
                        </button>
                        <button type="button" @click="openEdit(v)"
                            class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button type="button" @click="drain(v)" x-show="v.file_count > 0"
                            class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-box-arrow-up"></i> Drain
                        </button>
                        <button type="button" @click="detach(v)"
                            class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors ml-auto">
                            <i class="bi bi-eject"></i> Detach
                        </button>
                    </div>
                    <p x-show="v.last_checked_at" class="text-[11px] text-muted-foreground mt-2">Checked <span x-text="v.last_checked_at"></span></p>
                </div>
            </template>
        </div>
    </div>

    {{-- Attach / edit sheet --}}
    <div x-show="showForm" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="showForm = false"></div>
        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #0e6e63, #0e6e63b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-hdd-network text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h4 class="text-lg font-black leading-tight" x-text="editing ? 'Edit storage' : 'Attach storage'"></h4>
                        <p class="text-[12px] text-white/85 mt-0.5" x-text="form.name || 'A NAS to keep match video on'"></p>
                    </div>
                    <button type="button" @click="showForm = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="px-5 py-4 overflow-y-auto space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" x-model="form.name" placeholder="e.g. Hall NAS, Archive"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">How we reach it</label>
                    <x-select-menu model="form.driver" :options="$driverOptions" />
                    <p class="text-xs mt-1.5" :class="form.driver === 'mount' ? 'text-green-600' : 'text-amber-600'"
                       x-text="form.driver === 'mount'
                            ? 'Best: the share is mounted on this server, so video streams straight off it and never lands on our disk.'
                            : 'Works, but each video is copied to this server before it can be played. Prefer a mounted share when you can.'"></p>
                </div>

                {{-- Mounted share --}}
                <template x-if="form.driver === 'mount'">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mount path on this server</label>
                        <input type="text" x-model="form.mount_path" placeholder="/mnt/nas/takeone"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm">
                        <p class="text-xs text-muted-foreground mt-1">Mount it in the server's fstab first. Test will refuse a path that is not actually a mount — writing to an unmounted folder would quietly fill the local disk.</p>
                    </div>
                </template>

                {{-- SMB --}}
                <template x-if="form.driver === 'smb'">
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                                <input type="text" x-model="form.host" placeholder="10.1.40.11"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                                <input type="number" x-model="form.port" placeholder="445"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Share</label>
                            <input type="text" x-model="form.share" placeholder="Media"
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input type="text" x-model="form.username" autocomplete="off"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" x-model="form.password" autocomplete="new-password"
                                    :placeholder="editing && editing.has_password ? '•••••••• (leave blank to keep)' : ''"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Domain <span class="text-muted-foreground font-normal">(optional)</span></label>
                            <input type="text" x-model="form.domain"
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Folder inside it <span class="text-muted-foreground font-normal">(optional)</span></label>
                        <input type="text" x-model="form.root_path" placeholder="takeone/media"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <input type="number" min="0" x-model="form.priority" placeholder="0"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="text-xs text-muted-foreground mt-1">Highest wins for new video.</p>
                    </div>
                </div>

                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.enabled" class="rounded border-gray-300 text-primary focus:ring-primary"> Attached
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.read_only" class="rounded border-gray-300 text-primary focus:ring-primary"> Read-only
                    </label>
                </div>
                <p class="text-xs text-muted-foreground">Read-only keeps serving what is already there and sends new video elsewhere — the state to use while retiring a NAS.</p>
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0"
                 style="padding-bottom: max(1rem, calc(0.75rem + env(safe-area-inset-bottom)));">
                <button type="button" @click="showForm = false" class="px-4 py-2 rounded-lg border border-gray-200 text-muted-foreground hover:bg-muted transition-colors">Cancel</button>
                <button type="button" @click="save()" :disabled="saving"
                    class="px-4 py-2 rounded-lg bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-60">
                    <span x-show="!saving">Save &amp; test</span><span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function storageSettings(vaults, local, writeTarget, pipeline) {
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
                : 'Copied to this server for playback, then served from the local cache.';
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
                    // Say what the probe found, not just "saved" — an attached
                    // vault that cannot be reached is the thing worth knowing.
                    const v = data.vault;
                    window.showToast && window.showToast(
                        v.last_status === 'online' ? 'success' : 'warning',
                        v.last_status === 'online' ? `${v.name} attached and reachable.` : (v.last_error || 'Saved, but could not be reached.')
                    );
                    this.showForm = false;
                    window.__adminShellRefresh && window.__adminShellRefresh();
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
                if (data.vault) this.patch(data.vault);
            } catch (e) { window.showToast && window.showToast('error', 'Test failed.'); }
        },

        async drain(v) {
            const ok = window.confirmAction
                ? await window.confirmAction({
                    title: 'Drain this storage',
                    message: `Move video off "${v.name}" so it can be detached? It is set read-only while draining. Large libraries are better drained from a terminal with: php artisan media:drain "${v.name}"`,
                    confirmText: 'Start draining',
                })
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
                window.__adminShellRefresh && window.__adminShellRefresh();
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
                    window.__adminShellRefresh && window.__adminShellRefresh();
                } else {
                    window.showToast && window.showToast('error', data.message || 'Could not detach.');
                }
            } catch (e) { window.showToast && window.showToast('error', 'Could not detach.'); }
        },

        patch(v) {
            const i = this.vaults.findIndex(x => x.uuid === v.uuid);
            if (i !== -1) this.vaults[i] = v;
        },
    };
}
</script>
@endpush
