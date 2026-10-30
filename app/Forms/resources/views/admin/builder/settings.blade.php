{{-- Form-level details: artwork, names, description, and how it closes. --}}
<div>
    {{-- Cover artwork — the first thing a club sees. Uses the one platform
         cropper (inline sheet on a phone, modal on desktop); the endpoint
         checks the real bytes and decides the folder and name itself. --}}
    <label class="fb-lbl">{{ __('forms::builder.cover') }}</label>
    <div class="fb-cover" :class="form.cover_url ? 'has' : ''">
        <template x-if="form.cover_url"><img :src="form.cover_url" alt=""></template>
        <div class="fb-cover-empty" x-show="!form.cover_url">
            <i class="bi bi-image"></i>
            <span>{{ __('forms::builder.cover_hint') }}</span>
        </div>
    </div>
    @if($form)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
            @if($cropInline ?? false)
                <button type="button" class="fb-ghost" @click="coverOpen = !coverOpen"><i class="bi bi-camera"></i><span x-text="form.cover_url ? @js(__('forms::builder.cover_change')) : @js(__('forms::builder.cover_add'))"></span></button>
            @else
                <x-takeone-cropper id="formCover" mode="ajax" :width="1920" :height="1080" shape="rectangle" :canvasHeight="460"
                    folder="cover" filename="cover" :uploadUrl="route('admin.forms.cover', $form->uuid)"
                    buttonText="{{ __('forms::builder.cover_upload') }}" buttonClass="fb-ghost"
                    saveText="{{ __('forms::builder.save') }}" :uploadAsIs="true" uploadAsIsText="{{ __('forms::builder.cover_as_is') }}" />
            @endif
            <button type="button" class="fb-ghost" style="color:#dc2626" x-show="form.cover_url" @click="removeCover()"><i class="bi bi-trash3"></i>{{ __('forms::builder.remove') }}</button>
            <span style="font-size:11.5px;color:var(--ink3)">{{ __('forms::builder.cover_size') }}</span>
        </div>
        @if($cropInline ?? false)
            <div x-show="coverOpen" x-cloak style="margin-top:10px">
                <x-takeone-cropper id="formCoverM" mode="ajax" :inline="true" :width="1920" :height="1080" shape="rectangle" :canvasHeight="260"
                    folder="cover" filename="cover" :uploadUrl="route('admin.forms.cover', $form->uuid)"
                    sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background" :showControls="false" :showCancel="false"
                    saveText="{{ __('forms::builder.save') }}" :uploadAsIs="true" uploadAsIsText="{{ __('forms::builder.cover_as_is') }}" />
            </div>
        @endif
    @else
        <p style="font-size:12px;color:var(--ink3);margin-top:6px">{{ __('forms::builder.cover_after_save') }}</p>
    @endif

    <label class="fb-lbl">{{ __('forms::builder.title') }}</label>
    <div class="fb-pair two">
        <div><span class="fb-flag">EN</span><input class="fb-in" maxlength="150" x-model="form.title" placeholder="{{ __('forms::builder.title_ph') }}"></div>
        <div><span class="fb-flag">عربي</span><input class="fb-in ar" dir="rtl" maxlength="150" x-model="form.title_ar"></div>
    </div>
    <p class="fb-err" x-show="errors.title" x-text="(errors.title||[])[0]"></p>
    <label class="fb-lbl">{{ __('forms::builder.description') }}</label>
    <div class="fb-pair two">
        <textarea class="fb-in" rows="3" maxlength="2000" x-model="form.description"></textarea>
        <textarea class="fb-in ar" dir="rtl" rows="3" maxlength="2000" x-model="form.description_ar"></textarea>
    </div>
    <label class="fb-lbl">{{ __('forms::builder.thanks') }}</label>
    <div class="fb-pair two">
        <input class="fb-in" maxlength="300" x-model="form.successMessage" placeholder="Thank you for your time.">
        <input class="fb-in ar" dir="rtl" maxlength="300" x-model="form.successMessage_ar" placeholder="شكراً لوقتكم.">
    </div>
    <div style="margin-top:14px">
        <label class="fb-sw" @click.prevent="form.active = !form.active">
            <span>{{ __('forms::builder.open') }}<small>{{ __('forms::builder.open_hint') }}</small></span><span class="fb-tog" :class="form.active && 'on'"></span></label>
        <label class="fb-sw" @click.prevent="form.interviewer = !form.interviewer">
            <span>{{ __('forms::builder.interviewer') }}<small>{{ __('forms::builder.interviewer_hint') }}</small></span><span class="fb-tog" :class="form.interviewer && 'on'"></span></label>
    </div>
</div>
