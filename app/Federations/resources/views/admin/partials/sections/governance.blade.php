<div class="space-y-3">
    @foreach ($officerRoles as $role)
        @php $o = $values['officers'][$role] ?? []; @endphp
        <div class="rounded-xl border border-gray-100 p-3 bg-white">
            <p class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
                <i class="bi {{ in_array($role, ['president', 'secretary_general'], true) ? 'bi-star-fill text-primary' : 'bi-person text-muted-foreground' }}"></i>
                {{ __('federations::messages.officer_'.$role) }}
            </p>
            {{-- Name + email typed, or a TakeOne member found — both post the same officer fields. --}}
            <x-fields.person-picker name="officers[{{ $role }}][email]" :value="$o['email'] ?? ''"
                                    name-input="officers[{{ $role }}][name]" :name-value="$o['name'] ?? ''"
                                    :search-url="route('admin.federations.people')" :input-class="$in"
                                    :placeholder="__('federations::messages.n_email')" :name-placeholder="__('federations::messages.n_name')" />
            <input name="officers[{{ $role }}][phone]" value="{{ $o['phone'] ?? '' }}" maxlength="40" inputmode="tel" class="{{ $in }} mt-2" placeholder="{{ __('federations::messages.n_phone') }}">
            <p x-show="hasErr('officers.{{ $role }}.email')" x-text="err('officers.{{ $role }}.email')" class="text-xs text-red-600 mt-1"></p>
        </div>
    @endforeach

    <div class="pt-2">
        <div class="flex items-center justify-between mb-2">
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-0">{{ __('federations::messages.f_board') }} <span class="text-xs font-medium text-muted-foreground" x-text="'· ' + board.length"></span></p>
                <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.f_board_help') }}</p>
            </div>
            <button type="button" @click="addMember()" :disabled="board.length >= 12"
                    class="px-3 py-2 rounded-lg text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-40">
                <i class="bi bi-plus-lg me-1"></i>{{ __('federations::messages.f_add_member') }}
            </button>
        </div>
        <div class="space-y-2">
            <template x-for="(m, i) in board" :key="i">
                <div class="flex items-center gap-2">
                    <input :name="'board[' + i + '][name]'" x-model="m.name" maxlength="150" class="{{ $in }}" placeholder="{{ __('federations::messages.f_member_name') }}">
                    <input :name="'board[' + i + '][role]'" x-model="m.role" maxlength="80" class="{{ $in }}" placeholder="{{ __('federations::messages.f_member_role') }}">
                    <button type="button" @click="removeMember(i)" class="w-10 h-10 rounded-lg flex items-center justify-center text-red-600 hover:bg-red-50 flex-shrink-0" aria-label="{{ __('federations::messages.doc_remove') }}"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
        </div>
    </div>
</div>
