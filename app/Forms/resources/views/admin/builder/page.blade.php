{{-- The current page's own wording, and whether only the visitor sees it. --}}
<div>
    <label class="fb-lbl">{{ __('forms::builder.page_title') }}</label>
    <div class="fb-pair two">
        <input class="fb-in" maxlength="150" x-model="cur.title">
        <input class="fb-in ar" dir="rtl" maxlength="150" x-model="cur.title_ar">
    </div>
    <label class="fb-lbl">{{ __('forms::builder.page_intro') }}</label>
    <div class="fb-pair two">
        <input class="fb-in" maxlength="500" x-model="cur.intro" placeholder="{{ __('forms::builder.optional') }}">
        <input class="fb-in ar" dir="rtl" maxlength="500" x-model="cur.intro_ar" placeholder="اختياري">
    </div>
    <label class="fb-sw" style="margin-top:8px" @click.prevent="cur.private = !cur.private">
        <span>{{ __('forms::builder.page_private') }}<small>{{ __('forms::builder.private_hint') }}</small></span><span class="fb-tog" :class="cur.private && 'on'"></span></label>
</div>
