@props([
    'name',                    // the email input's name — what the form keeps
    'value' => '',
    'nameInput' => null,       // optional: a person's-name input posted beside the email (officers)
    'nameValue' => '',
    'searchUrl',               // GET ?q= → {people: [{name, email, photo}]}
    'required' => false,
    'id' => null,
    'placeholder' => '',
    'namePlaceholder' => '',
    'inputClass' => 'w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent',
])
{{--
    A person, given either way: type their EMAIL, or FIND A MEMBER on TakeOne
    and use theirs. Both post the same input(s) — `name` (the email) and, when
    `nameInput` is set, the person's name — so the endpoint behind the form
    does not know or care which way it was filled in.

    Standalone: all behaviour is inline x-data (no registration script, so it
    survives the admin shell's content swaps), the member side is <x-fields.member-search>
    (name, phone, email or TakeOne ID) against `searchUrl`. Every server value is drawn
    with x-text / :src, never as HTML. The endpoint decides who may be found;
    this only draws what it answers.
--}}
@php
    $uid = $id ?: 'pp-'.substr(md5($name), 0, 8);
    $t = [
        'email' => __('shared.person_picker_email'),
        'member' => __('shared.person_picker_member'),
        'search' => __('shared.person_picker_search'),
        'none' => __('shared.person_picker_none'),
        'change' => __('shared.person_picker_change'),
        'searching' => __('shared.person_picker_searching'),
    ];
@endphp
<div x-data="{
        mode: 'email',
        email: @js((string) $value),
        pname: @js((string) $nameValue),
        typed: null,
        picked: null,
        setMode(m) {
            if (m === this.mode) return;
            if (m === 'member') { this.typed = { email: this.email, pname: this.pname }; if (!this.picked) { this.email = ''; this.pname = ''; } this.$nextTick(() => this.$el.querySelector('input[type=search]')?.focus()); }
            else { if (this.typed) { this.email = this.typed.email; this.pname = this.typed.pname; } this.picked = null; }
            this.mode = m;
        },
        pick(p) { this.picked = p; this.email = p.email; this.pname = p.name; },
        unpick() { this.picked = null; this.email = ''; this.pname = ''; this.$nextTick(() => this.$el.querySelector('input[type=search]')?.focus()); },
     }" @member-picked="pick($event.detail)" {{ $attributes->merge(['class' => 'space-y-2']) }}>

    {{-- Which way: two segments, the chosen one lifted --}}
    <div class="inline-flex p-1 rounded-full bg-muted text-xs font-semibold" role="tablist">
        <button type="button" role="tab" :aria-selected="mode === 'email'" @click="setMode('email')"
                class="px-3 py-1.5 rounded-full inline-flex items-center gap-1.5 transition-all"
                :class="mode === 'email' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-gray-700'">
            <i class="bi bi-envelope"></i>{{ $t['email'] }}
        </button>
        <button type="button" role="tab" :aria-selected="mode === 'member'" @click="setMode('member')"
                class="px-3 py-1.5 rounded-full inline-flex items-center gap-1.5 transition-all"
                :class="mode === 'member' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-gray-700'">
            <i class="bi bi-search"></i>{{ $t['member'] }}
        </button>
    </div>

    {{-- The inputs the form posts. Typed in "Email"; filled by the pick in "Find a member". --}}
    <div x-show="mode === 'email'" class="{{ $nameInput ? 'grid grid-cols-1 md:grid-cols-2 gap-2' : '' }}">
        @if ($nameInput)
            <input name="{{ $nameInput }}" x-model="pname" maxlength="150" class="{{ $inputClass }}" placeholder="{{ $namePlaceholder }}" autocomplete="off">
        @endif
        <div class="relative">
            <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input id="{{ $uid }}" name="{{ $name }}" type="email" x-model="email" maxlength="191" class="{{ $inputClass }} pl-10"
                   placeholder="{{ $placeholder }}" autocomplete="off" :required="mode === 'email' && {{ $required ? 'true' : 'false' }}">
        </div>
    </div>

    {{-- Find a member --}}
    <div x-show="mode === 'member'" x-cloak>
        <template x-if="picked">
            <div class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 p-2.5">
                <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted"><img :src="picked.photo" alt="" class="w-full h-full object-cover"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 mb-0 break-words" x-text="picked.name"></p>
                    <p class="text-xs text-muted-foreground mb-0 break-all" x-text="picked.email"></p>
                </div>
                <button type="button" @click="unpick()" class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors flex-shrink-0">
                    {{ $t['change'] }}
                </button>
            </div>
        </template>

        <template x-if="!picked">
            <x-fields.member-search :search-url="$searchUrl" :input-class="$inputClass" />
        </template>
    </div>
</div>
