{{-- The page's sheets — one teleported overlay, four faces (Design Rule #8:
     every sheet opens with the gradient band). Rendered inside the page's
     x-data, so the template sees its scope. --}}
<template x-teleport="body">
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="sheet = null">
        <div class="absolute inset-0 bg-black/45" @click="sheet = null" x-show="sheet" x-transition.opacity></div>

        <div class="absolute inset-x-0 bottom-0 sm:bottom-6 sm:mx-auto sm:max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden"
             x-show="sheet" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             role="dialog" aria-modal="true">

            {{-- Band --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi text-xl" :class="{ 'bi-receipt': sheet === 'item', 'bi-bookmark-plus': sheet === 'scenario', 'bi-check2-circle': sheet === 'approve', 'bi-copy': sheet === 'start', 'bi-stars': sheet === 'advisor' }"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight"
                            x-text="({ item: item && item.uuid ? @js(__('events::events.budget_edit_cost')) : @js(__('events::events.budget_add_cost')), scenario: @js(__('events::events.budget_save_scenario')), approve: @js(__('events::events.budget_approve_title')), start: @js(__('events::events.budget_start_from')), advisor: @js(__('events::events.budget_advisor_title')) })[sheet] || ''"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ $event->title }}</p>
                    </div>
                    <button type="button" @click="sheet = null" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- ===== Add / edit a cost ===== --}}
            <template x-if="sheet === 'item' && item">
                <div class="flex-1 min-h-0 flex flex-col">
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <div>
                            <label for="bud-item-label" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_item_label') }}</label>
                            <input id="bud-item-label" type="text" maxlength="120" x-model="item.label" placeholder="{{ __('events::events.budget_item_label_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>

                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.budget_item_category') }}</p>
                            <div class="flex flex-wrap gap-1.5" role="radiogroup">
                                <template x-for="(label, key) in categories" :key="key">
                                    <button type="button" role="radio" :aria-checked="item.category === key" @click="item.category = key"
                                            class="m-press h-9 px-3.5 rounded-full border text-[12px] font-bold transition-colors"
                                            :class="item.category === key ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-gray-700'" x-text="label"></button>
                                </template>
                            </div>
                        </div>

                        {{-- People: a role, how many, and what each one costs. A volunteer
                             may cost nothing, or their meal / T-shirt. --}}
                        <template x-if="item.category === 'people'">
                            <div class="space-y-4">
                                <div>
                                    <p class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.budget_item_role') }}</p>
                                    <div class="flex flex-wrap gap-1.5" role="radiogroup">
                                        @foreach (\App\Events\Models\EventBudgetItem::ROLES as $role)
                                            <button type="button" role="radio" :aria-checked="item.role === '{{ $role }}'" @click="pickRole('{{ $role }}')"
                                                    class="m-press h-9 px-3.5 rounded-full border text-[12px] font-bold transition-colors inline-flex items-center gap-1.5"
                                                    :class="item.role === '{{ $role }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-gray-700'">
                                                <i class="bi {{ ['referee' => 'bi-flag', 'judge' => 'bi-clipboard-check', 'organiser' => 'bi-person-badge', 'volunteer' => 'bi-heart', 'medical' => 'bi-heart-pulse', 'other' => 'bi-person'][$role] }}"></i>{{ __('events::events.budget_role_'.$role) }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="bud-item-qty" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_item_quantity') }}</label>
                                        <input id="bud-item-qty" type="number" min="0" step="1" inputmode="numeric" x-model="item.quantity" placeholder="0"
                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                    </div>
                                    <div>
                                        <label for="bud-item-unit" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_item_unit_cost') }}</label>
                                        <div class="relative">
                                            <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
                                            <input id="bud-item-unit" type="number" min="0" step="0.001" inputmode="decimal" x-model="item.unit_cost" placeholder="0"
                                                   class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-muted-foreground -mt-2" x-show="item.role === 'volunteer'">{{ __('events::events.budget_volunteer_hint') }}</p>
                                <div class="rounded-xl bg-muted/50 px-3.5 py-2.5 flex items-center justify-between">
                                    <span class="text-[12px] font-bold text-muted-foreground" x-text="(parseInt(item.quantity || 0, 10)) + ' × ' + money(parseFloat(item.unit_cost || 0))"></span>
                                    <span class="text-sm font-black" x-text="money((parseInt(item.quantity || 0, 10)) * parseFloat(item.unit_cost || 0)) + ' ' + b.currency"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="item.category !== 'people'">
                            <div class="space-y-4">
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.budget_item_basis') }}</p>
                            <div class="grid grid-cols-3 gap-2" role="radiogroup">
                                @foreach (['fixed' => 'bi-cash', 'per_entrant' => 'bi-people', 'per_division' => 'bi-diagram-3'] as $basis => $icon)
                                    <button type="button" role="radio" :aria-checked="item.basis === '{{ $basis }}'" @click="item.basis = '{{ $basis }}'"
                                            class="m-press text-start rounded-xl border p-3 transition-colors"
                                            :class="item.basis === '{{ $basis }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                        <span class="flex items-center gap-2"><i class="bi {{ $icon }}" :class="item.basis === '{{ $basis }}' ? 'text-primary' : 'text-muted-foreground'"></i>
                                            <span class="text-sm font-bold text-foreground">{{ __('events::events.budget_basis_'.$basis) }}</span></span>
                                        <span class="block text-[11px] text-muted-foreground mt-1 leading-snug">{{ __('events::events.budget_basis_'.$basis.'_desc') }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Priced per unit (3 trophies × 15, 50 chairs × 0.6) or as one sum. --}}
                        <button type="button" role="switch" :aria-checked="item.units" @click="item.units = ! item.units"
                                class="w-full flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 transition-colors"
                                :class="item.units ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                            <span class="text-start">
                                <span class="block text-sm font-bold text-foreground">{{ __('events::events.budget_item_units') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('events::events.budget_item_units_hint') }}</span>
                            </span>
                            <span class="shrink-0 w-11 h-6 rounded-full relative transition-colors" :class="item.units ? 'bg-primary' : 'bg-gray-300'">
                                <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="item.units ? 'translate-x-5' : 'translate-x-0'"></span>
                            </span>
                        </button>
                        <template x-if="item.units">
                            <div class="space-y-2">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="bud-item-uqty" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_item_quantity') }}</label>
                                        <input id="bud-item-uqty" type="number" min="0" step="1" inputmode="numeric" x-model="item.quantity" placeholder="0"
                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                    </div>
                                    <div>
                                        <label for="bud-item-uprice" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_item_unit_price') }}</label>
                                        <div class="relative">
                                            <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
                                            <input id="bud-item-uprice" type="number" min="0" step="0.001" inputmode="decimal" x-model="item.unit_cost" placeholder="0"
                                                   class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl bg-muted/50 px-3.5 py-2.5 flex items-center justify-between">
                                    <span class="text-[12px] font-bold text-muted-foreground"
                                          x-text="(parseInt(item.quantity || 0, 10)) + ' × ' + money(parseFloat(item.unit_cost || 0)) + (item.basis === 'per_division' ? ' ' + @js(__('events::events.budget_per_division_word')) : (item.basis === 'per_entrant' ? ' ' + @js(__('events::events.budget_each_entrant')) : ''))"></span>
                                    <span class="text-sm font-black" x-text="money((parseInt(item.quantity || 0, 10)) * parseFloat(item.unit_cost || 0)) + ' ' + b.currency"></span>
                                </div>
                            </div>
                        </template>
                        <template x-if="! item.units">
                        <div>
                            <label for="bud-item-amount" class="block text-sm font-medium text-gray-700 mb-1"
                                   x-text="item.basis === 'per_entrant' ? @js(__('events::events.budget_item_amount_each')) : @js(__('events::events.budget_item_amount'))"></label>
                            <div class="relative w-44">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
                                <input id="bud-item-amount" type="number" min="0" step="0.001" inputmode="decimal" x-model="item.amount" placeholder="0"
                                       class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                        </div>
                        </template>

                            </div>
                        </template>

                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.budget_item_status') }}</p>
                            <div class="grid grid-cols-3 gap-1.5" role="radiogroup">
                                @foreach (['planned', 'quoted', 'paid'] as $st)
                                    <button type="button" role="radio" :aria-checked="item.status === '{{ $st }}'" @click="item.status = '{{ $st }}'"
                                            class="m-press h-10 rounded-xl border text-[12px] font-bold transition-colors"
                                            :class="item.status === '{{ $st }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-gray-700'">{{ __('events::events.budget_status_item_'.$st) }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex-shrink-0 bg-white border-t border-gray-100 px-4 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" x-show="item.uuid" @click="removeItem()" :disabled="busy"
                                class="m-press h-12 px-4 rounded-xl border border-red-200 text-red-600 text-sm font-bold" aria-label="{{ __('events::events.budget_remove') }}"><i class="bi bi-trash"></i></button>
                        <button type="button" @click="saveItem()" :disabled="busy || ! (item.label || '').trim()"
                                class="m-press flex-1 h-12 rounded-xl text-white text-sm font-extrabold disabled:opacity-50" style="background: {{ $color }}">{{ __('events::events.budget_save') }}</button>
                    </div>
                </div>
            </template>

            {{-- ===== Save the tuned numbers as a scenario ===== --}}
            <template x-if="sheet === 'scenario'">
                <div class="flex-1 min-h-0 flex flex-col">
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div class="rounded-xl bg-white border border-gray-100 p-3 flex items-center justify-between">
                            <span class="text-[12px] text-muted-foreground" x-text="money(tune.fee) + ' ' + b.currency + ' × ' + tune.entrants"></span>
                            <span class="text-sm font-black" :class="live.net < 0 ? 'text-red-700' : 'text-green-700'" x-text="signed(live.net)"></span>
                        </div>
                        <div>
                            <label for="bud-sc-name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.budget_scenario_name') }}</label>
                            <input id="bud-sc-name" type="text" maxlength="80" x-model="scenarioName" placeholder="{{ __('events::events.budget_scenario_name_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>
                    </div>
                    <div class="flex-shrink-0 bg-white border-t border-gray-100 px-4 pt-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="saveScenario()" :disabled="busy || ! scenarioName.trim()"
                                class="m-press w-full h-12 rounded-xl text-white text-sm font-extrabold disabled:opacity-50" style="background: {{ $color }}">{{ __('events::events.budget_save') }}</button>
                    </div>
                </div>
            </template>

            {{-- ===== Approve a scenario (and, if chosen, set the real fee) ===== --}}
            <template x-if="sheet === 'approve' && approving">
                <div class="flex-1 min-h-0 flex flex-col">
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div class="rounded-xl bg-white border border-gray-100 p-3.5">
                            <p class="text-[13px] font-extrabold text-foreground" x-text="approving.name"></p>
                            <p class="text-[12px] text-muted-foreground mt-0.5" x-text="money(approving.fee) + ' ' + b.currency + ' × ' + approving.entrants + ' → ' + signed(approving.now) + ' ' + b.currency"></p>
                        </div>
                        <button type="button" role="switch" :aria-checked="applyFee" @click="applyFee = ! applyFee"
                                class="w-full text-start rounded-xl border p-3.5 flex items-center gap-3 transition-colors"
                                :class="applyFee ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-bold text-foreground"
                                      x-text="@js(__('events::events.budget_apply_fee', ['fee' => ':f'])).replace(':f', money(approving.fee) + ' ' + b.currency)"></span>
                                <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.budget_apply_fee_hint') }}</span>
                            </span>
                            <span class="shrink-0 w-12 h-7 rounded-full relative transition-colors" :class="applyFee ? 'bg-green-500' : 'bg-gray-300'">
                                <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow transition-transform" :class="applyFee ? 'translate-x-5' : 'translate-x-0'"></span>
                            </span>
                        </button>
                        <p class="text-[11px] text-muted-foreground leading-snug">{{ __('events::events.budget_approve_hint') }}</p>
                    </div>
                    <div class="flex-shrink-0 bg-white border-t border-gray-100 px-4 pt-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="approve()" :disabled="busy"
                                class="m-press w-full h-12 rounded-xl text-white text-sm font-extrabold bg-green-700 disabled:opacity-50">{{ __('events::events.budget_approve') }}</button>
                    </div>
                </div>
            </template>

            {{-- ===== The advisor =====
                 Every reply is shown with x-text (never as HTML). A proposal's
                 result is the server's own figure (BudgetAdvisor::proposal),
                 and saving it goes through the ordinary scenario endpoint. --}}
            <template x-if="sheet === 'advisor'">
                <div class="flex flex-col min-h-0" style="height: 72vh;">
                    <div id="bud-chat" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div class="rounded-2xl bg-white border border-gray-100 p-3.5 text-[13.5px] leading-relaxed text-foreground" style="border-start-start-radius:6px;max-width:92%">
                            {{ __('events::events.budget_advisor_hello') }}
                        </div>

                        <template x-for="(m, i) in chat" :key="i">
                            <div>
                                <template x-if="m.role === 'user'">
                                    <div class="ms-auto w-fit rounded-2xl text-white px-3.5 py-2.5 text-[13.5px] leading-relaxed whitespace-pre-line" style="background:#111827;max-width:85%;border-start-end-radius:6px" x-text="m.text"></div>
                                </template>
                                <template x-if="m.role === 'error'">
                                    <div class="rounded-2xl bg-red-50 border border-red-200 px-3.5 py-2.5 text-[13px] text-red-800" style="max-width:92%" x-text="m.text"></div>
                                </template>
                                <template x-if="m.role === 'assistant'">
                                    <div class="space-y-2">
                                        <div class="rounded-2xl bg-white border border-gray-100 p-3.5 text-[13.5px] leading-relaxed text-foreground whitespace-pre-line" style="max-width:92%;border-start-start-radius:6px" x-text="m.text"></div>
                                        <p x-show="m.source === 'facts'" class="text-[11px] text-muted-foreground px-1">{{ __('events::events.budget_advisor_offline') }}</p>
                                        <p x-show="toolsLine(m)" class="text-[11px] text-muted-foreground px-1 flex items-center gap-1.5"><i class="bi bi-search"></i><span x-text="toolsLine(m)"></span></p>

                                        {{-- What the agent SAVED — the one thing that changed real data. --}}
                                        <template x-if="m.actions && m.actions.length">
                                            <div class="space-y-1 px-1" style="max-width:92%">
                                                <template x-for="(a, ai) in m.actions" :key="ai">
                                                    <p class="text-[11.5px] font-bold text-green-700 flex items-start gap-1.5"><i class="bi bi-check2-circle mt-0.5"></i><span x-text="a"></span></p>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- The limits it kept, as chips — so the organiser can see they were heard. --}}
                                        <template x-if="m.kept && m.kept.length">
                                            <div class="flex flex-wrap gap-1 px-1">
                                                <template x-for="(k, ki) in m.kept" :key="ki">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold" style="background:#efedfe;color:#5b4bd6"><i class="bi bi-lock-fill"></i><span x-text="k"></span></span>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Why — two or three short facts the answer stands on. --}}
                                        <template x-if="m.reasoning && m.reasoning.length">
                                            <ul class="space-y-1 px-1" style="max-width:92%">
                                                <template x-for="(r, ri) in m.reasoning" :key="ri">
                                                    <li class="flex items-start gap-2 text-[12px] text-muted-foreground leading-snug">
                                                        <i class="bi bi-dot text-base leading-none mt-0.5" style="color:#7c6bf5"></i><span x-text="r"></span>
                                                    </li>
                                                </template>
                                            </ul>
                                        </template>

                                        {{-- The proposal card: what would change, and the platform's own result. --}}
                                        <template x-if="m.proposal">
                                            <div class="rounded-2xl bg-white border-2 p-3.5" style="border-color:#c9c3f7">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-[10px] font-extrabold uppercase tracking-wide px-2 py-1 rounded-full" style="background:#efedfe;color:#5b4bd6">{{ __('events::events.budget_advisor_proposal') }}</span>
                                                    <span class="text-[12px] text-muted-foreground truncate" x-text="m.proposal.name"></span>
                                                </div>
                                                <div class="mt-2 divide-y divide-gray-100">
                                                    <template x-for="(c, ci) in m.proposal.changes.slice(0, 4)" :key="ci">
                                                        <div class="flex items-center gap-2 py-2 text-[12.5px]">
                                                            <span class="flex-1 font-bold text-foreground" x-text="changeLabel(c).name"></span>
                                                            <span class="text-muted-foreground line-through" x-text="changeLabel(c).from"></span>
                                                            <i class="bi bi-arrow-right text-muted-foreground text-[11px] rtl:rotate-180"></i>
                                                            <span class="font-black text-foreground" x-text="changeLabel(c).to"></span>
                                                        </div>
                                                    </template>
                                                    <p x-show="m.proposal.changes.length > 4" class="py-2 text-[11.5px] text-muted-foreground"
                                                       x-text="@js(__('events::events.budget_advisor_more_changes', ['n' => ':n'])).replace(':n', m.proposal.changes.length - 4)"></p>
                                                    <div class="flex items-center gap-2 py-2 text-[12.5px]">
                                                        <span class="flex-1 font-bold text-foreground">{{ __('events::events.budget_net_result') }}</span>
                                                        <span class="text-muted-foreground line-through" x-text="signed(m.proposal.net_before)"></span>
                                                        <i class="bi bi-arrow-right text-muted-foreground text-[11px] rtl:rotate-180"></i>
                                                        <span class="font-black" :class="m.proposal.net < 0 ? 'text-red-700' : 'text-green-700'" x-text="signed(m.proposal.net) + ' ' + b.currency"></span>
                                                    </div>
                                                </div>
                                                <p class="mt-1 text-[11.5px] font-bold flex items-center gap-1.5" :class="m.proposal.meets_target ? 'text-green-700' : 'text-amber-700'">
                                                    <i class="bi" :class="m.proposal.meets_target ? 'bi-bullseye' : 'bi-exclamation-circle'"></i>
                                                    <span x-text="m.proposal.meets_target ? @js(__('events::events.budget_advisor_meets')) : @js(__('events::events.budget_advisor_short'))"></span>
                                                </p>

                                                {{-- Applied straight to the sliders; the organiser keeps or undoes it. --}}
                                                <template x-if="m.applied">
                                                    <p class="mt-2 text-[11.5px] font-bold flex items-center gap-1.5" style="color:#5b4bd6"><i class="bi bi-sliders"></i>{{ __('events::events.budget_advisor_applied') }}</p>
                                                </template>
                                                <template x-if="! m.done">
                                                    <div class="flex flex-wrap gap-1.5 mt-2.5">
                                                        <button type="button" x-show="! hasLineChanges(m)" @click="saveProposal(m)" :disabled="busy" class="m-press flex-1 h-10 px-3 rounded-xl text-white text-[12.5px] font-extrabold" style="background:#7c6bf5">{{ __('events::events.budget_advisor_create') }}</button>
                                                        <button type="button" x-show="hasLineChanges(m)" @click="keepChanges(m)" :disabled="busy" class="m-press flex-1 h-10 px-3 rounded-xl text-white text-[12.5px] font-extrabold" style="background:#7c6bf5">{{ __('events::events.budget_advisor_keep_changes') }}</button>
                                                        <button type="button" @click="tryProposal(m)" class="m-press h-10 px-3 rounded-xl border border-gray-200 bg-white text-[12.5px] font-bold text-gray-700"><i class="bi bi-eye"></i> {{ __('events::events.budget_advisor_show') }}</button>
                                                        <button type="button" x-show="m.applied" @click="undoProposal(m)" :disabled="busy" class="m-press h-10 px-3 rounded-xl border border-gray-200 bg-white text-[12.5px] font-bold text-gray-700"><i class="bi bi-arrow-counterclockwise"></i> {{ __('events::events.budget_advisor_undo') }}</button>
                                                        <button type="button" x-show="! m.applied" @click="applyProposal(m)" :disabled="busy" class="m-press h-10 px-3 rounded-xl border border-gray-200 bg-white text-[12.5px] font-bold text-gray-700"><i class="bi bi-sliders"></i> {{ __('events::events.budget_advisor_apply') }}</button>
                                                    </div>
                                                </template>
                                                <p x-show="m.done" class="mt-2.5 text-[12px] font-bold text-green-700 flex items-center gap-1.5"><i class="bi bi-check2-circle"></i>{{ __('events::events.budget_advisor_saved') }}</p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div x-show="asking" class="rounded-2xl bg-white border border-gray-100 px-3.5 py-3 text-[13px] text-muted-foreground flex items-center gap-2" style="max-width:60%;border-start-start-radius:6px">
                            <i class="bi bi-three-dots animate-pulse"></i>{{ __('events::events.budget_advisor_thinking') }}
                        </div>
                    </div>

                    <div class="flex-shrink-0 bg-white border-t border-gray-100 px-3 pt-2.5" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <div class="flex gap-1.5 overflow-x-auto pb-2.5">
                            <button type="button" x-show="chat.length" @click="newChat()" :disabled="asking"
                                    class="m-press flex-shrink-0 h-8 px-3 rounded-full border border-gray-200 bg-white text-[12px] font-bold text-muted-foreground whitespace-nowrap"><i class="bi bi-plus-lg"></i> {{ __('events::events.budget_advisor_new_chat') }}</button>
                            @foreach (['budget_advisor_q_target', 'budget_advisor_q_fee', 'budget_advisor_q_fewer', 'budget_advisor_q_sponsor'] as $q)
                                <button type="button" @click="ask(@js(__('events::events.'.$q)))" :disabled="asking"
                                        class="m-press flex-shrink-0 h-8 px-3 rounded-full border border-gray-200 bg-white text-[12px] font-bold text-gray-700 whitespace-nowrap disabled:opacity-50">{{ __('events::events.'.$q) }}</button>
                            @endforeach
                        </div>
                        <form class="flex items-end gap-2" @submit.prevent="ask()">
                            <label for="bud-ask" class="sr-only">{{ __('events::events.budget_advisor_title') }}</label>
                            <textarea id="bud-ask" rows="1" maxlength="500" x-model="question" @keydown.enter.prevent="if (! $event.shiftKey) ask()"
                                      placeholder="{{ __('events::events.budget_advisor_placeholder') }}"
                                      class="flex-1 resize-none px-3.5 py-3 rounded-xl border border-gray-200 bg-muted/40 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none" style="min-height:44px"></textarea>
                            <button type="submit" :disabled="asking || ! question.trim()" aria-label="{{ __('events::events.budget_advisor_send') }}"
                                    class="m-press w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 disabled:opacity-50" style="background:#7c6bf5">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </template>

            {{-- ===== Start from another event's budget ===== --}}
            <template x-if="sheet === 'start'">
                <div class="flex-1 overflow-y-auto p-4 space-y-2" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <p class="text-[12px] text-muted-foreground">{{ __('events::events.budget_start_hint') }}</p>
                    <template x-if="sources === null"><p class="text-sm text-muted-foreground py-6 text-center"><i class="bi bi-arrow-repeat animate-spin"></i></p></template>
                    <template x-if="sources !== null && ! sources.length">
                        <p class="text-sm text-muted-foreground py-6 text-center">{{ __('events::events.budget_no_sources') }}</p>
                    </template>
                    <template x-for="src in (sources || [])" :key="src.uuid">
                        <button type="button" @click="copyFrom(src)" :disabled="busy"
                                class="m-press w-full text-start bg-white rounded-xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 bg-muted text-muted-foreground"><i class="bi bi-calendar-event"></i></span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-bold text-foreground truncate" x-text="src.title"></span>
                                <span class="block text-[11px] text-muted-foreground mt-0.5"
                                      x-text="[src.date, @js(__('events::events.budget_source_items', ['n' => ':n'])).replace(':n', src.items), money(src.expenses) + ' ' + b.currency].filter(Boolean).join(' · ')"></span>
                            </span>
                            <i class="bi bi-chevron-right text-muted-foreground/50 text-xs rtl:rotate-180"></i>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>
