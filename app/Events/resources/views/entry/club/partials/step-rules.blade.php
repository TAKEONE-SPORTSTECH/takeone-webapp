{{-- Step 1 — the rules and conditions, then the two statements a club accepts. --}}
<div x-show="step === 1" class="space-y-3">
    <div class="inv-card overflow-hidden inv-in">
        <x-event-section-band :color="$e['color']" icon="bi-journal-text" :title="$t['rulesCardTitle'] ?? ''" />
        <div class="p-5">
            <p class="inv-sub" style="margin:0 0 14px;" x-text="t.rulesSub"></p>
            @if(trim((string) $e['about']) !== '' || ! empty($e['requirements']))
                @if(trim((string) $e['about']) !== '')
                    <x-prose-text :text="$e['about']" style="overflow-wrap:anywhere;" />
                @endif
                @if(! empty($e['requirements']))
                    <ol class="space-y-2 {{ trim((string) $e['about']) !== '' ? 'mt-4' : '' }}" style="padding:0; margin-block:0; list-style:none;">
                        @foreach($e['requirements'] as $i => $req)
                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <span class="flex-none grid place-items-center text-[11px] font-black text-white"
                                      style="width:20px; height:20px; border-radius:999px; background: {{ $ev }}; margin-top:1px;">{{ $i + 1 }}</span>
                                <span>{{ $req }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            @else
                <p class="text-[13px] text-muted-foreground leading-relaxed m-0" x-text="t.rulesNone"></p>
            @endif
        </div>
    </div>

    @if(! empty($e['documents']))
        <div class="inv-card overflow-hidden">
            <x-event-section-band :color="$e['color']" icon="bi-paperclip" :title="$t['docsTitle'] ?? ''" />
            <div class="p-5">
                <p class="inv-sub" style="margin:0 0 12px;" x-text="t.docsSub"></p>
                <x-event-documents :event="$e['key']" :documents="$e['documents']" :can-manage="false" :color="$e['color']" />
            </div>
        </div>
    @endif

    <div class="space-y-2.5">
        <template x-for="(txt, i) in [t.accept1, t.accept2]" :key="i">
            <button type="button" @click="accepted = accepted.map((v, j) => j === i ? ! v : v); error = ''"
                    :class="accepted[i] ? 'is-on' : ''" :aria-pressed="accepted[i] ? 'true' : 'false'"
                    class="m-press e-pick w-full rounded-2xl px-4 py-3.5 flex items-start gap-3 text-start">
                <span class="w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0 transition-colors"
                      :class="accepted[i] ? 'text-white' : 'border-gray-300'"
                      :style="accepted[i] ? 'background: {{ $ev }}; border-color: {{ $ev }}' : ''">
                    <i class="bi bi-check-lg text-[13px]" x-show="accepted[i]"></i>
                </span>
                <span class="min-w-0 flex-1 text-[13.5px] font-bold text-foreground leading-snug" x-text="txt"></span>
            </button>
        </template>
    </div>
</div>
