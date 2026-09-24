{{-- THE JOB — which mat role this person is being appointed to.

     A dropdown rather than a stack of selection cards: a sport declares as many
     mat jobs as it has (referee, mat organiser, scorekeeper, timekeeper, and
     more in some rule books), and four tall cards with their explanations
     pushed the pay question and the Add button off the bottom of the sheet —
     the one thing a mobile form may never do.

     The panel is ANCHORED (partials/float-panel): fixed, positioned under the
     trigger, so it neither pushes the fields below it down the sheet nor gets
     clipped by the sheet's own scrolling body. Same control as the club picker
     beside it, so one question looks like one thing.

     `roleGroups` says WHICH jobs this instance offers. The NEW door lists mat
     jobs only — a person the platform has never met may not be handed a role
     that grants access, and storeOfficialPerson() refuses it. The FIND door
     lists both, headed by group, because a permission role is a real
     appointment for somebody the club already knows.

     Included inside the officials panel's Alpine scope, which owns `roles`,
     `role`, `roleOpen`, `roleMeta` and `setRole()`. --}}
@php
    $roleGroups = $roleGroups ?? ['mat'];
    /* Two instances live in ONE Alpine scope (one per door); a shared x-ref
       name would mean the second silently wins. */
    $ref = $ref ?? 'role';
@endphp
@include('components.fields.support.float-panel')
<div @click.outside="roleOpen = false; window.tkFloat(null, $refs['{{ $ref }}Panel'], false)"
     @keydown.escape.stop="roleOpen = false; window.tkFloat(null, $refs['{{ $ref }}Panel'], false)">
    <button type="button" x-ref="{{ $ref }}Trigger"
            @click="roleOpen = ! roleOpen; $nextTick(() => window.tkFloat($refs['{{ $ref }}Trigger'], $refs['{{ $ref }}Panel'], roleOpen))"
            :aria-expanded="roleOpen"
            :class="roleOpen ? 'ring-2 ring-primary border-transparent' : 'border-gray-200'"
            class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-white border rounded-xl text-start transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        {{-- The same two marks the cards carried: a shield for a job that
             grants ACCESS to this event, an athlete for one that runs the mat.
             What a role gives away is the reason to look twice at it. --}}
        <i class="bi flex-shrink-0"
           :class="roleMeta.group === 'platform' ? 'bi-shield-lock text-amber-600' : 'bi-person-arms-up text-primary'"></i>
        <span class="min-w-0 flex-1">
            <span class="block text-[13px] font-bold text-foreground truncate" x-text="roleLabel(role)"></span>
            <span x-show="roleMeta.hint" class="block text-[10px] text-muted-foreground leading-snug truncate" x-text="roleMeta.hint"></span>
        </span>
        <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform flex-shrink-0"
           :class="roleOpen && 'rotate-180'"></i>
    </button>

    <div x-show="roleOpen" x-cloak x-ref="{{ $ref }}Panel"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         {{-- Above the sheet (65/70), below the cropper's editor (80). Inline,
              because the Tailwind bundle is prebuilt. --}}
         style="z-index:78"
         class="rounded-xl border border-gray-100 bg-white shadow-lg overflow-hidden">
        <template x-for="g in @js(array_values($roleGroups))" :key="g">
            <div x-show="roles.some(r => r.group === g)">
                {{-- A heading only when there is more than one kind of job to
                     tell apart: a single-group list headed by its own name is
                     the label printed twice. --}}
                @if(count($roleGroups) > 1)
                    <p class="px-3 pt-2.5 pb-1 text-[9.5px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground/80"
                       x-text="g === 'platform'
                            ? @js(__('personal.personal_event_officials_group_platform'))
                            : @js(__('personal.personal_event_officials_group_mat'))"></p>
                @endif

                <template x-for="(r, i) in roles.filter(x => x.group === g)" :key="r.value">
                    <button type="button" @click="setRole(r.value); roleOpen = false"
                            :class="[role === r.value ? 'bg-primary/5' : 'hover:bg-muted/60', i ? 'border-t border-gray-100' : '']"
                            class="w-full flex items-start gap-2.5 px-3 py-2.5 text-start transition-colors">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-bold leading-tight"
                                  :class="role === r.value ? 'text-primary' : 'text-foreground'" x-text="r.label"></span>
                            {{-- One line, like every other description in this
                                 sheet: the job's NAME is what is being chosen,
                                 and the sentence under it is a reminder, not
                                 reading. --}}
                            <span x-show="r.hint" class="block text-[10px] text-muted-foreground mt-0.5 truncate"
                                  :title="r.hint" x-text="r.hint"></span>
                        </span>
                        <i class="bi text-[11px] flex-shrink-0 mt-0.5"
                           :class="g === 'platform' ? 'bi-shield-lock text-amber-600' : 'bi-person-arms-up text-primary'"></i>
                        <i class="bi bi-check-lg flex-shrink-0 text-primary mt-0.5" x-show="role === r.value"></i>
                    </button>
                </template>
            </div>
        </template>
    </div>
</div>
