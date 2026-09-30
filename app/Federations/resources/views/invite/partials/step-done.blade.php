{{-- Step 5 — received. On the page itself, the way the confirmation always was. --}}
<div x-show="step === 5" class="space-y-3">
    <div class="inv-card p-6 text-center inv-in">
        <span class="mx-auto w-16 h-16 rounded-full grid place-items-center text-white m-float" style="background:#2f7a4f; box-shadow:0 14px 34px -14px #2f7a4f;">
            <i class="bi bi-check-lg text-3xl"></i>
        </span>
        <h2 class="text-[22px] font-black text-foreground mt-4 leading-tight" x-text="t.doneTitle"></h2>
        <p class="text-[13px] font-bold text-foreground mt-2" x-text="subClub + ' · ' + doneCountLabel()"></p>
        <p class="text-[12.5px] text-muted-foreground mt-1.5 leading-relaxed" x-text="t.doneBody"></p>

        <div class="mt-5 rounded-2xl px-4 py-3 text-start grid gap-x-4 gap-y-1.5 text-[12.5px]" style="background:#f7f8fb; grid-template-columns:auto 1fr;">
            <span class="text-muted-foreground" x-text="t.reference"></span>
            <span class="font-black text-foreground" dir="ltr" x-text="doneInfo ? doneInfo.ref : ''"></span>
        </div>
        <p class="text-[13px] font-black mt-3" style="color: {{ $ev }};" x-text="doneInfo ? doneInfo.payMsg : ''"></p>
        <p class="text-[11.5px] text-muted-foreground mt-2 leading-snug" x-text="t.weighReminder"></p>

        <button type="button" @click="go(6)" class="m-press w-full h-13 mt-5 rounded-2xl text-white font-black text-[15px] flex items-center justify-center gap-2"
                style="height:52px; background: {{ $ev }}; box-shadow:0 18px 40px -18px {{ $ev }};">
            <i class="bi bi-list-check"></i><span x-text="t.manageEntries"></span>
        </button>
    </div>
</div>
