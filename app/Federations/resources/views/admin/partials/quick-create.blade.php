{{--
    The quick create: a federation from four facts — name, sport, country,
    owner. Everything else is asked for afterwards, on the record, and never
    demanded (FederationProfiles::rules). Shared by both layouts; needs $in,
    $lb, $values, $sports and the fedAdminForm scope (v, errors, saving,
    quickCreate, full). Its own <form>, so it posts only these fields.
--}}
@php $me = auth()->user(); @endphp
<div x-show="!full" x-cloak class="fedx-card-in">
    <form x-ref="quick" @submit.prevent="quickCreate()" novalidate
          class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12">

            {{-- The four facts --}}
            <div class="lg:col-span-8 p-6 space-y-6">
                <div>
                    <p class="fedx-chapter-no mb-1">{{ __('federations::messages.quick_eyebrow') }}</p>
                    <h2 class="text-xl font-black text-gray-900 leading-tight mb-1">{{ __('federations::messages.quick_title') }}</h2>
                    <p class="text-sm text-muted-foreground mb-0">{{ __('federations::messages.quick_sub') }}</p>
                </div>

                {{-- 1 · Name --}}
                <div class="flex gap-3">
                    <span class="fedx-q-step" :class="(v.name || '').trim() && 'is-done'"><span>1</span><i class="bi bi-check-lg"></i></span>
                    <div class="flex-1 min-w-0">
                        <label class="{{ $lb }}" for="q-name">{{ __('federations::messages.f_name') }}</label>
                        <input id="q-name" name="name" x-model="v.name" maxlength="150" class="{{ $in }}" :class="hasErr('name') && 'border-red-400'"
                               placeholder="Bahrain Karate Federation" autocomplete="off">
                        <p x-show="hasErr('name')" x-text="err('name')" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                {{-- 2 · Sport --}}
                <div class="flex gap-3">
                    <span class="fedx-q-step" :class="v.sports.length && 'is-done'"><span>2</span><i class="bi bi-check-lg"></i></span>
                    <div class="flex-1 min-w-0">
                        <label class="{{ $lb }}">{{ __('federations::messages.f_sports') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($sports as $sport)
                                <button type="button" @click="toggleSport(@js($sport))"
                                        class="fedx-chip-toggle px-3 py-1.5 rounded-full text-xs font-medium border"
                                        :class="v.sports.includes(@js($sport)) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200 hover:border-primary'">
                                    <i class="bi me-1" :class="v.sports.includes(@js($sport)) ? 'bi-check-lg' : 'bi-plus'"></i>{{ $sport }}
                                </button>
                            @endforeach
                        </div>
                        <template x-for="s in v.sports" :key="s"><input type="hidden" name="sports[]" :value="s"></template>
                        <p x-show="hasErr('sports')" x-text="err('sports')" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                {{-- 3 · Country --}}
                <div class="flex gap-3">
                    <span class="fedx-q-step is-done"><span>3</span><i class="bi bi-check-lg"></i></span>
                    <div class="flex-1 min-w-0">
                        <x-fields.country name="country" id="q_country" :value="$values['country']" :label="__('federations::messages.f_country')" />
                        <p x-show="hasErr('country')" x-text="err('country')" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                {{-- 4 · Owner — you, unless you pick someone --}}
                <div class="flex gap-3">
                    <span class="fedx-q-step is-done"><span>4</span><i class="bi bi-check-lg"></i></span>
                    <div class="flex-1 min-w-0">
                        <label class="{{ $lb }}" for="q-owner">{{ __('federations::messages.f_owner_email') }}</label>
                        <x-fields.person-picker id="q-owner" name="owner_email" :value="$me?->email"
                                                :search-url="route('admin.federations.people')" :input-class="$in" placeholder="president@federation.bh" />
                        <p x-show="hasErr('owner_email')" x-text="err('owner_email')" class="text-xs text-red-600 mt-1"></p>
                        <p class="text-xs text-muted-foreground mt-2 mb-0">{{ __('federations::messages.quick_owner_help') }}</p>
                    </div>
                </div>
            </div>

            {{-- The federation, taking shape as it is typed --}}
            <div class="lg:col-span-4 hidden lg:block relative overflow-hidden text-white p-6" :style="gradient()">
                <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
                <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
                <div class="relative h-full flex flex-col">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-white/70 mb-4">{{ __('federations::messages.preview_hint') }}</p>
                    <span class="fedx-crest-fallback fedx-q-crest" x-text="(v.name || '').trim() ? initials() : '—'"></span>
                    <p class="text-lg font-black leading-tight mt-4 mb-0 break-words" x-text="v.name || @js(__('federations::messages.f_name'))"></p>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <template x-for="s in v.sports" :key="s">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20" x-text="s"></span>
                        </template>
                    </div>
                    <p class="text-xs text-white/80 mt-auto pt-6 mb-0"><i class="bi bi-magic me-1"></i>{{ __('federations::messages.quick_later') }}</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-muted/40 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <button type="button" @click="full = true; $nextTick(() => window.scrollTo({top: 0, behavior: 'smooth'}))"
                    class="text-sm font-medium text-primary hover:underline text-start">
                <i class="bi bi-list-check me-1"></i>{{ __('federations::messages.quick_full') }}
            </button>
            <button type="submit" :disabled="saving || !(v.name || '').trim() || !v.sports.length"
                    class="bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-50 flex items-center justify-center gap-2">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-lightning-charge-fill'"></i>
                <span x-text="saving ? @js(__('federations::messages.admin_saving')) : @js(__('federations::messages.quick_create'))"></span>
            </button>
        </div>
    </form>
</div>
