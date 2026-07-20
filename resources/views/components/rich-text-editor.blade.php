@props([
    'name',
    'id' => null,
    'value' => '',
    'dir' => 'ltr',
    'placeholder' => '',
    'minHeight' => '150px',
    // AI helper (magic wand): when enabled, a wand button generates professional
    // content for this field. The system prompt is built from aiLabel + aiPurpose.
    'aiEnabled' => true,
    'aiLabel' => '',
    'aiPurpose' => '',
    // Editors sharing an aiGroup are language pairs: generating one also fills
    // the other language (e.g. EN requirements ↔ AR requirements).
    'aiGroup' => null,
])

@php $id = $id ?? 'rte_' . \Illuminate\Support\Str::random(6); @endphp

@once
<style>
    .rte-wrap { border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #fff; overflow: hidden; }
    .rte-wrap:focus-within { border-color: hsl(250 65% 65%); box-shadow: 0 0 0 3px hsl(250 65% 65% / 0.15); }
    .rte-toolbar { display: flex; flex-wrap: wrap; gap: 2px; padding: 6px 8px; border-bottom: 1px solid #f1f1f4; background: #fafafb; }
    .rte-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
               color: #4b5563; background: transparent; border: none; cursor: pointer; transition: background .12s, color .12s; font-size: 14px; }
    .rte-btn:hover { background: #ede9fb; color: hsl(250 65% 55%); }
    .rte-sep { width: 1px; align-self: stretch; background: #e5e7eb; margin: 4px 4px; }
    .rte-btn.is-active { background: #ede9fb; color: hsl(250 65% 55%); }
    .rte-color { position: relative; flex: 0 0 32px; width: 32px; height: 32px; box-sizing: border-box; padding: 0; margin: 0; border: none; border-radius: 8px;
                 cursor: pointer; overflow: hidden; background: transparent; display: inline-flex; align-items: center; justify-content: center;
                 color: #4b5563; font-size: 14px; transition: background .12s, color .12s; }
    .rte-color:hover { background: #ede9fb; color: hsl(250 65% 55%); }
    /* AI magic-wand button — constant gentle pulse to signal "AI here" */
    .rte-ai { color: hsl(250 65% 55%); }
    .rte-ai:hover { background: #ede9fb; color: hsl(250 60% 48%); }
    .rte-ai i { animation: rteWand 1.8s ease-in-out infinite; transform-origin: center; }
    @keyframes rteWand { 0%, 100% { transform: scale(1) rotate(0deg); opacity: .8; } 50% { transform: scale(1.2) rotate(-8deg); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .rte-ai i { animation: none; } }
    .rte-aibar { display: flex; gap: 6px; align-items: center; padding: 6px 8px; border-bottom: 1px solid #f1f1f4; background: #f6f5ff; }
    .rte-aibar input { flex: 1; min-width: 0; font-size: 13px; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }
    .rte-aibar input:focus { border-color: hsl(250 65% 65%); box-shadow: 0 0 0 3px hsl(250 65% 65% / 0.15); }
    .rte-ai-generate { font-size: 13px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; background: hsl(250 65% 65%); color: #fff; white-space: nowrap; }
    .rte-ai-generate:disabled { opacity: .6; }
    .rte-color input[type="color"] { position: absolute; inset: 0; width: 100%; height: 100%; min-width: 0; min-height: 0; opacity: 0; cursor: pointer; border: none; padding: 0; margin: 0; }
    .rte-color i { pointer-events: none; }
    .rte-linkbar { display: flex; gap: 6px; align-items: center; padding: 6px 8px; border-bottom: 1px solid #f1f1f4; background: #f6f5ff; }
    .rte-linkbar input { flex: 1; min-width: 0; font-size: 13px; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }
    .rte-linkbar input:focus { border-color: hsl(250 65% 65%); box-shadow: 0 0 0 3px hsl(250 65% 65% / 0.15); }
    .rte-linkbar button { font-size: 13px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; }
    .rte-link-apply { background: hsl(250 65% 65%); color: #fff; }
    .rte-link-cancel { background: #fff; color: #4b5563; border: 1px solid #e5e7eb !important; }
    .rte-editor { padding: 12px 14px; font-size: 14px; line-height: 1.7; color: #1f2937; outline: none; overflow-y: auto; }
    .rte-editor:empty:before { content: attr(data-placeholder); color: #9ca3af; }
    .rte-editor h1 { font-size: 1.35rem; font-weight: 700; margin: .45em 0; }
    .rte-editor h2 { font-size: 1.15rem; font-weight: 700; margin: .4em 0; }
    .rte-editor h3 { font-size: 1.02rem; font-weight: 700; margin: .4em 0; }
    .rte-editor ul { list-style: disc; padding-inline-start: 1.5em; margin: .4em 0; }
    .rte-editor ol { list-style: decimal; padding-inline-start: 1.5em; margin: .4em 0; }
    .rte-editor a { color: hsl(250 65% 55%); text-decoration: underline; }
    .rte-editor p { margin: .35em 0; }
    .rte-editor blockquote { border-inline-start: 3px solid hsl(250 65% 75%); padding-inline-start: 12px; margin: .5em 0; color: #4b5563; font-style: italic; }
    .rte-editor hr { border: none; border-top: 1px solid #e5e7eb; margin: .8em 0; }
    /* Arabic glyphs render visually smaller at the same px — bump size/leading in RTL. */
    [dir="rtl"] .rte-editor { text-align: right; font-size: 16px; line-height: 2; }
</style>
<script>
    function richTextEditor(opts) {
        return {
            linkOpen: false,
            linkUrl: '',
            savedRange: null,
            active: { bold: false, italic: false, underline: false, strikeThrough: false,
                      insertUnorderedList: false, insertOrderedList: false,
                      justifyLeft: false, justifyCenter: false, justifyRight: false },
            init() {
                this.$refs.editor.innerHTML = opts.initial || '';
                this.sync();
            },
            sync() {
                // contenteditable HTML → hidden field that actually submits.
                this.$refs.hidden.value = this.$refs.editor.innerHTML.trim();
            },
            refreshState() {
                // Reflect cursor formatting on the toolbar buttons.
                try {
                    for (const cmd in this.active) this.active[cmd] = document.queryCommandState(cmd);
                } catch (e) {}
            },
            exec(cmd, val = null) {
                this.$refs.editor.focus();
                document.execCommand(cmd, false, val);
                this.sync();
                this.refreshState();
            },
            block(tag) {
                this.$refs.editor.focus();
                // Toggle a block back to <p> if it's already that heading.
                document.execCommand('formatBlock', false, tag);
                this.sync();
            },
            color(val) {
                this.exec('foreColor', val);
            },
            // Link popover — avoids native prompt(), uses an inline bar.
            openLink() {
                const sel = window.getSelection();
                if (sel && sel.rangeCount) this.savedRange = sel.getRangeAt(0).cloneRange();
                // Pre-fill if cursor is inside an existing link.
                let node = sel && sel.anchorNode;
                let a = node && (node.nodeType === 1 ? node : node.parentElement);
                while (a && a.tagName !== 'A' && a !== this.$refs.editor) a = a.parentElement;
                this.linkUrl = (a && a.tagName === 'A') ? a.getAttribute('href') : '';
                this.linkOpen = true;
                this.$nextTick(() => this.$refs.linkInput && this.$refs.linkInput.focus());
            },
            applyLink() {
                let url = this.linkUrl.trim();
                if (!url) { this.linkOpen = false; return; }
                if (!/^(https?:|mailto:|tel:|\/|#)/i.test(url)) url = 'https://' + url;
                this.$refs.editor.focus();
                if (this.savedRange) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(this.savedRange);
                }
                document.execCommand('createLink', false, url);
                this.sync();
                this.linkOpen = false;
                this.linkUrl = '';
            },
            unlink() {
                this.exec('unlink');
            },
            // ── AI helper ─────────────────────────────────────────────
            aiOpen: false,
            aiPrompt: '',
            aiLoading: false,
            openAi() {
                this.aiOpen = true;
                this.$nextTick(() => this.$refs.aiInput && this.$refs.aiInput.focus());
            },
            async generateAi() {
                if (this.aiLoading) return;
                this.aiLoading = true;
                try {
                    const res = await fetch(opts.composeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify({
                            label: opts.aiLabel || '',
                            purpose: opts.aiPurpose || '',
                            instructions: this.aiPrompt || '',
                            current: this.$refs.editor.innerHTML.trim(),
                            dir: opts.dir || 'ltr',
                            // Paired editor → also generate the other language.
                            both: !!opts.aiGroup,
                        }),
                    });
                    const data = await res.json();
                    if (data.success && data.html) {
                        this.$refs.editor.innerHTML = data.html;
                        this.sync();
                        // Fill the paired-language editor too, if any.
                        if (opts.aiGroup && data.other_html) {
                            window.dispatchEvent(new CustomEvent('rte:ai-fill', {
                                detail: { group: opts.aiGroup, lang: data.other_lang, html: data.other_html },
                            }));
                        }
                        this.aiOpen = false;
                        this.aiPrompt = '';
                        window.showToast && window.showToast('success', opts.aiGroup && data.other_html ? 'Content generated (English + Arabic)' : 'Content generated');
                    } else {
                        window.showToast && window.showToast('error', data.message || 'Could not generate content.');
                    }
                } catch (e) {
                    window.showToast && window.showToast('error', 'Could not generate content.');
                } finally {
                    this.aiLoading = false;
                }
            },
            // Allow external JS to set this editor's content by id (e.g. a modal
            // prefilling on edit/duplicate). Matches the component's id prop.
            onSetContent(detail) {
                if (detail && detail.id === opts.rteId) {
                    this.$refs.editor.innerHTML = detail.html || '';
                    this.sync();
                }
            },
            // Receive the paired-language content generated by the sibling editor.
            onAiFill(detail) {
                if (! detail || ! opts.aiGroup) return;
                const myLang = (opts.dir === 'rtl') ? 'ar' : 'en';
                if (detail.group === opts.aiGroup && detail.lang === myLang && detail.html) {
                    this.$refs.editor.innerHTML = detail.html;
                    this.sync();
                }
            },
        };
    }
</script>
@endonce

<div x-data="richTextEditor({ initial: @js($value), dir: @js($dir), aiLabel: @js($aiLabel ?: $name), aiPurpose: @js($aiPurpose), aiGroup: @js($aiGroup), rteId: @js($id), composeUrl: @js(url('/ai/compose')) })"
     @rte:ai-fill.window="onAiFill($event.detail)"
     @rte:set-content.window="onSetContent($event.detail)"
     class="rte-wrap" {{ $attributes }}>
    <div class="rte-toolbar">
        @if($aiEnabled)
        <button type="button" class="rte-btn rte-ai" title="Write with AI" @mousedown.prevent="openAi()"><i class="bi bi-magic"></i></button>
        <span class="rte-sep"></span>
        @endif
        <button type="button" class="rte-btn" :class="{ 'is-active': active.bold }" title="Bold" @mousedown.prevent="exec('bold')"><i class="bi bi-type-bold"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.italic }" title="Italic" @mousedown.prevent="exec('italic')"><i class="bi bi-type-italic"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.underline }" title="Underline" @mousedown.prevent="exec('underline')"><i class="bi bi-type-underline"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.strikeThrough }" title="Strikethrough" @mousedown.prevent="exec('strikeThrough')"><i class="bi bi-type-strikethrough"></i></button>
        <label class="rte-color" title="Text color">
            <i class="bi bi-palette"></i>
            <input type="color" value="#1f2937" @input="color($event.target.value)" @mousedown.stop>
        </label>
        <span class="rte-sep"></span>
        <button type="button" class="rte-btn" title="Title" @mousedown.prevent="block('h1')"><i class="bi bi-type-h1"></i></button>
        <button type="button" class="rte-btn" title="Heading" @mousedown.prevent="block('h2')"><i class="bi bi-type-h2"></i></button>
        <button type="button" class="rte-btn" title="Subheading" @mousedown.prevent="block('h3')"><i class="bi bi-type-h3"></i></button>
        <button type="button" class="rte-btn" title="Normal text" @mousedown.prevent="block('p')"><i class="bi bi-text-paragraph"></i></button>
        <button type="button" class="rte-btn" title="Quote" @mousedown.prevent="block('blockquote')"><i class="bi bi-quote"></i></button>
        <span class="rte-sep"></span>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.insertUnorderedList }" title="Bullet list" @mousedown.prevent="exec('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.insertOrderedList }" title="Numbered list" @mousedown.prevent="exec('insertOrderedList')"><i class="bi bi-list-ol"></i></button>
        <button type="button" class="rte-btn" title="Decrease indent" @mousedown.prevent="exec('outdent')"><i class="bi bi-text-indent-right"></i></button>
        <button type="button" class="rte-btn" title="Increase indent" @mousedown.prevent="exec('indent')"><i class="bi bi-text-indent-left"></i></button>
        <span class="rte-sep"></span>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.justifyLeft }" title="Align left" @mousedown.prevent="exec('justifyLeft')"><i class="bi bi-text-left"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.justifyCenter }" title="Align center" @mousedown.prevent="exec('justifyCenter')"><i class="bi bi-text-center"></i></button>
        <button type="button" class="rte-btn" :class="{ 'is-active': active.justifyRight }" title="Align right" @mousedown.prevent="exec('justifyRight')"><i class="bi bi-text-right"></i></button>
        <span class="rte-sep"></span>
        <button type="button" class="rte-btn" title="Insert link" @mousedown.prevent="openLink()"><i class="bi bi-link-45deg"></i></button>
        <button type="button" class="rte-btn" title="Remove link" @mousedown.prevent="unlink()"><i class="bi bi-link"></i></button>
        <button type="button" class="rte-btn" title="Horizontal line" @mousedown.prevent="exec('insertHorizontalRule')"><i class="bi bi-dash-lg"></i></button>
        <span class="rte-sep"></span>
        <button type="button" class="rte-btn" title="Undo" @mousedown.prevent="exec('undo')"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button type="button" class="rte-btn" title="Redo" @mousedown.prevent="exec('redo')"><i class="bi bi-arrow-clockwise"></i></button>
        <button type="button" class="rte-btn" title="Clear formatting" @mousedown.prevent="exec('removeFormat')"><i class="bi bi-eraser"></i></button>
    </div>

    @if($aiEnabled)
    <div class="rte-aibar" x-show="aiOpen" x-cloak x-transition>
        <input type="text" x-ref="aiInput" x-model="aiPrompt"
               placeholder="Optional — tell the AI what you want, or leave blank to auto-write"
               @keydown.enter.prevent="generateAi()" @keydown.escape.prevent="aiOpen = false">
        <button type="button" class="rte-ai-generate" @mousedown.prevent="generateAi()" :disabled="aiLoading">
            <span x-show="!aiLoading"><i class="bi bi-stars"></i> Generate</span>
            <span x-show="aiLoading"><i class="bi bi-arrow-repeat"></i> Writing…</span>
        </button>
        <button type="button" class="rte-link-cancel" @mousedown.prevent="aiOpen = false">Cancel</button>
    </div>
    @endif

    <div class="rte-linkbar" x-show="linkOpen" x-cloak x-transition>
        <input type="text" x-ref="linkInput" x-model="linkUrl" placeholder="https://example.com"
               @keydown.enter.prevent="applyLink()" @keydown.escape.prevent="linkOpen = false">
        <button type="button" class="rte-link-apply" @mousedown.prevent="applyLink()">Apply</button>
        <button type="button" class="rte-link-cancel" @mousedown.prevent="linkOpen = false">Cancel</button>
    </div>

    <div x-ref="editor"
         class="rte-editor"
         contenteditable="true"
         dir="{{ $dir }}"
         data-placeholder="{{ $placeholder }}"
         style="min-height: {{ $minHeight }};"
         @input="sync()"
         @blur="sync()"
         @keyup="refreshState()"
         @mouseup="refreshState()"></div>

    <textarea x-ref="hidden" name="{{ $name }}" id="{{ $id }}" class="hidden" aria-hidden="true">{{ $value }}</textarea>
</div>
