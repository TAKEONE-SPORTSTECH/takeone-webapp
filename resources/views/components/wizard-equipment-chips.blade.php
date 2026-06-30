@props(['person' => 'self'])

{{--
  Equipment chips for one package card in the self-registration wizard.
  Rendered inside an Alpine `x-for="pkg ..."` loop, so `pkg` and the equipment
  helpers are in scope. `person` is an Alpine expression (`self` or `child`).

  Each item carries an "I already have it" toggle: ticking it removes the item
  from the bill (overriding required) and records it as owned on submit.

  Two shapes per item:
    • plain gear  → a single on/off checkbox row.
    • variant gear (size/colour/brand) → name + a row of selectable variant
      chips; picking one ticks the item and sets its price.
--}}
<div x-show="{{ $person }}.packages.includes(pkg.id) && (pkg.equipment || []).length > 0"
     class="mt-2 ml-7 mb-1 p-3 rounded-xl bg-primary/5 border border-primary/10">
    <p class="text-[11px] font-semibold text-gray-500 mb-2 flex items-center gap-1.5">
        <i class="bi bi-box-seam text-primary"></i> <span x-text="t.equipmentLabel"></span>
    </p>
    <div class="space-y-2.5">
        <template x-for="eq in (pkg.equipment || [])" :key="eq.id">
            <div class="rounded-xl" :class="isEquipmentOwned({{ $person }}, eq.id) ? 'opacity-60' : ''">
                {{-- Plain gear: simple checkbox --}}
                <label x-show="!eq.has_variants" class="flex items-center gap-2.5 cursor-pointer select-none py-1" @click.stop
                       :class="isEquipmentOwned({{ $person }}, eq.id) ? 'pointer-events-none' : ''">
                    <input type="checkbox" :checked="({{ $person }}.equipment || []).includes(eq.id)"
                           :disabled="isEquipmentOwned({{ $person }}, eq.id)"
                           @change="toggleEquipment({{ $person }}, eq.id)"
                           class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                    <span class="flex-1 text-sm text-gray-700" :class="isEquipmentOwned({{ $person }}, eq.id) ? 'line-through' : ''" x-text="eq.name"></span>
                    <span x-show="eq.is_required && !isEquipmentOwned({{ $person }}, eq.id)" class="text-[10px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary font-medium" x-text="t.requiredBadge"></span>
                    <span class="text-sm font-medium text-gray-600" x-text="parseFloat(eq.price).toFixed(2) + ' ' + t.currency"></span>
                </label>

                {{-- Variant gear: name + selectable chips --}}
                <div x-show="eq.has_variants" @click.stop>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="flex-1 text-sm font-medium text-gray-700" :class="isEquipmentOwned({{ $person }}, eq.id) ? 'line-through' : ''" x-text="eq.name"></span>
                        <span x-show="eq.is_required && !isEquipmentOwned({{ $person }}, eq.id)" class="text-[10px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary font-medium" x-text="t.requiredBadge"></span>
                    </div>
                    <div class="flex flex-wrap gap-1.5" x-show="!isEquipmentOwned({{ $person }}, eq.id)">
                        <template x-for="v in (eq.variants || [])" :key="v.id">
                            <button type="button" @click="selectEquipmentVariant({{ $person }}, eq, v.id)"
                                    :disabled="!v.in_stock || v.owned"
                                    class="px-2.5 py-1.5 rounded-lg border text-xs font-medium transition-colors inline-flex items-center gap-1.5"
                                    :class="equipmentVariantId({{ $person }}, eq.id) === v.id
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-gray-200 bg-white text-gray-700 hover:border-primary/40'"
                                    :style="(!v.in_stock || v.owned) ? 'opacity:.45;cursor:not-allowed;text-decoration:line-through' : ''">
                                <span x-show="v.color_hex" class="w-2.5 h-2.5 rounded-full border border-gray-200" :style="`background:${v.color_hex}`"></span>
                                <span x-text="v.label"></span>
                                <span class="text-gray-400 font-normal" x-text="'· ' + parseFloat(v.price).toFixed(2)"></span>
                                <span x-show="v.owned" class="text-[9px] px-1 py-0.5 rounded-full bg-green-100 text-green-700" x-text="t.alreadyOwned"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- "I already have it" — removes the item from the bill --}}
                <label class="flex items-center gap-2 cursor-pointer select-none mt-1.5 pl-0.5" @click.stop>
                    <input type="checkbox" :checked="isEquipmentOwned({{ $person }}, eq.id)"
                           @change="toggleOwned({{ $person }}, eq)"
                           class="w-3.5 h-3.5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                    <span class="text-[11px] font-medium" :class="isEquipmentOwned({{ $person }}, eq.id) ? 'text-green-700' : 'text-gray-500'" x-text="t.alreadyHaveIt"></span>
                </label>
            </div>
        </template>
    </div>
    <p class="text-[11px] text-gray-500 mt-2.5 flex items-start gap-1.5">
        <i class="bi bi-info-circle text-primary mt-0.5"></i>
        <span x-text="t.equipmentOwnHint"></span>
    </p>
</div>
