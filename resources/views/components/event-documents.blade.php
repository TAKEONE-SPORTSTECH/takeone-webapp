{{--
    Event documents — download list, plus an uploader for the organiser.

    Standalone: all state, requests and DOM updates live in this file's Alpine
    component. Drop it into any view that can supply the four props; it needs no
    page glue, no shared script and no surrounding markup.

    Writes patch the list in place (No Page Reload rule) — an upload prepends its
    row from the JSON response, a delete removes it. It also dispatches
    `event-documents-changed` on window with {action, document|uuid} so anything
    else on the page (a count badge, say) can follow along.

    Security notes that belong with the component, not the caller:
      • Files are served only through the download route, which re-checks that
        the viewer may see the event. This list never holds a storage path.
      • Every value rendered from the server goes through x-text / :href, never
        innerHTML, so a filename can't inject markup.
      • The uploader is shown only when $canManage — but that is cosmetic; the
        endpoint enforces it again server-side.

    Props:
      event      ClubEvent uuid (public key)
      documents  array of presented docs (uuid,title,size_label,icon,url)
      canManage  bool — show the uploader + delete buttons
      color      event colour, for the accents
--}}
@props([
    'event',
    'documents' => [],
    'canManage' => false,
    'color' => '#7c3aed',
])
@php
    // Reaches a style attribute — whitelist it like the other event surfaces do.
    $docColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="eventDocuments({
        event: @js($event),
        items: @js(array_values($documents)),
        canManage: @js((bool) $canManage),
        storeUrl: @js(route('me.events.documents.store', $event)),
     })"
     class="space-y-2">

    {{-- The list. Empty state only shows for organisers: a competitor with no
         documents to download should see nothing at all, not an empty box. --}}
    <template x-for="doc in items" :key="doc.uuid">
        <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-3 hover:bg-muted/40 transition-colors">
            <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white"
                 style="background: {{ $docColor }};">
                <i class="bi text-lg" :class="doc.icon"></i>
            </div>

            <a :href="doc.url"
               class="min-w-0 flex-1 group"
               :aria-label="doc.title">
                <p class="text-sm font-bold text-foreground truncate group-hover:text-primary transition-colors" x-text="doc.title"></p>
                <p class="text-[11px] text-muted-foreground">
                    <span class="uppercase" x-text="doc.extension"></span>
                    · <span x-text="doc.size_label"></span>
                </p>
            </a>

            <a :href="doc.url"
               class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0 text-muted-foreground hover:text-primary hover:bg-accent transition-colors"
               :aria-label="'{{ __('personal.event_docs_download') }} ' + doc.title">
                <i class="bi bi-download"></i>
            </a>

            <template x-if="canManage">
                <button type="button" @click="remove(doc)" :disabled="busy"
                        class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0 text-muted-foreground hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50"
                        :aria-label="'{{ __('shared.delete') }} ' + doc.title">
                    <i class="bi bi-trash"></i>
                </button>
            </template>
        </div>
    </template>

    <template x-if="canManage && items.length === 0">
        <p class="text-[12px] text-muted-foreground text-center py-3">{{ __('personal.event_docs_empty') }}</p>
    </template>

    {{-- Uploader — organiser only. Title is its own field: the original
         filename is never used as the display name (nor as the stored one). --}}
    @if($canManage)
        <div class="rounded-xl border-2 border-dashed border-gray-200 p-3 space-y-2">
            <input type="text" x-model="title" maxlength="120"
                   placeholder="{{ __('personal.event_docs_title_ph') }}"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">

            <div class="flex items-center gap-2">
                <label class="flex-1 cursor-pointer">
                    <input type="file" class="hidden"
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,application/pdf,image/jpeg,image/png,image/webp"
                           x-ref="file" @change="pick($event)">
                    <span class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-foreground hover:bg-muted transition-colors">
                        <i class="bi bi-paperclip"></i>
                        <span x-text="file ? file.name : '{{ __('personal.event_docs_choose') }}'" class="truncate"></span>
                    </span>
                </label>

                <button type="button" @click="upload()" :disabled="busy || !file || !title.trim()"
                        class="px-4 py-2.5 rounded-lg text-white text-sm font-bold flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 transition-opacity"
                        style="background: {{ $docColor }};">
                    <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-upload'"></i>
                    <span x-text="busy ? '{{ __('personal.event_docs_uploading') }}' : '{{ __('personal.event_docs_upload') }}'"></span>
                </button>
            </div>

            <p class="text-[11px] text-muted-foreground">{{ __('personal.event_docs_hint') }}</p>
        </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
        // Registered once per page; the component is instantiated per instance.
        function eventDocuments(config) {
            return {
                event: config.event,
                items: config.items || [],
                canManage: !!config.canManage,
                storeUrl: config.storeUrl,
                title: '',
                file: null,
                busy: false,

                _csrf() {
                    const m = document.querySelector('meta[name="csrf-token"]');
                    return m ? m.content : '';
                },

                pick(e) {
                    this.file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                    // Offer the filename (minus extension) as a starting title —
                    // a convenience only; the server never reads it.
                    if (this.file && !this.title.trim()) {
                        this.title = this.file.name.replace(/\.[^.]+$/, '').slice(0, 120);
                    }
                },

                async upload() {
                    if (this.busy || !this.file || !this.title.trim()) return;
                    this.busy = true;

                    const body = new FormData();
                    body.append('document', this.file);
                    body.append('title', this.title.trim());

                    try {
                        const res = await fetch(this.storeUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body,
                        });
                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.success) {
                            window.showToast('error', data.message || '{{ __('personal.event_docs_failed') }}');
                            return;
                        }

                        this.items.push(data.document);
                        this.title = '';
                        this.file = null;
                        if (this.$refs.file) this.$refs.file.value = '';
                        window.showToast('success', data.message);
                        window.dispatchEvent(new CustomEvent('event-documents-changed', {
                            detail: { action: 'created', document: data.document },
                        }));
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_docs_failed') }}');
                    } finally {
                        this.busy = false;
                    }
                },

                async remove(doc) {
                    if (this.busy) return;

                    const ok = await window.confirmAction({
                        title: '{{ __('personal.event_docs_delete_title') }}',
                        message: '{{ __('personal.event_docs_delete_msg') }}'.replace(':title', doc.title),
                        type: 'danger',
                        confirmText: '{{ __('shared.delete') }}',
                    });
                    if (!ok) return;

                    this.busy = true;
                    try {
                        const res = await fetch(doc.url, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.success) {
                            window.showToast('error', data.message || '{{ __('personal.event_docs_failed') }}');
                            return;
                        }

                        this.items = this.items.filter(d => d.uuid !== doc.uuid);
                        window.showToast('success', data.message);
                        window.dispatchEvent(new CustomEvent('event-documents-changed', {
                            detail: { action: 'deleted', uuid: doc.uuid },
                        }));
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_docs_failed') }}');
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
