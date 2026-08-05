<?php

/*
|--------------------------------------------------------------------------
| حزم أنواع الفعاليات
|--------------------------------------------------------------------------
| النصوص المشتركة لنظام الفعاليات نفسه. أما نصوص كل نوع فتوجد داخل مجلد
| حزمته (app/Events/Types/<Name>/resources/lang) تحت النطاق
| event-<key>::messages لتنتقل مع الحزمة.
*/

return [

    // نصوص عامة لنظام الفعاليات
    'action_unsupported' => 'هذا الإجراء غير متاح لهذا النوع من الفعاليات.',
    'action_unavailable' => 'هذا الإجراء غير متاح حالياً.',
    'outcome_saved' => 'تم تسجيل النتيجة',
    'results_derived_from_engine' => 'نتائج هذه الفعالية تُستخرج من المباريات المسجّلة — لا يمكن إدخالها يدوياً.',
    'banned_by_organiser' => 'تمت إزالتك من هذه الفعالية من قِبل المنظّم.',

    // التسجيل
    'reg_confirmed' => 'تم تسجيلك! نراك هناك 🎉',
    'reg_pay_at_club' => 'تم حجز مقعدك · :fee — أكمل الدفع في النادي',
    'reg_proof_sent' => 'تم حجز مقعدك · تم إرسال إثبات الدفع للمراجعة',

    // إشعارات المراحل
    'notify_created_title' => 'فعالية جديدة: :title',
    'notify_created_body' => ':club · :date — التسجيل مفتوح',
    'notify_enrolment_open_title' => 'فُتح التسجيل — :title',
    'notify_enrolment_open_body' => 'التسجيل مفتوح الآن. احجز مقعدك.',
    'notify_enrolment_closing_title' => 'آخر فرصة — :title',
    'notify_enrolment_closing_body' => 'يُغلق التسجيل في :date. سجّل قبل الإغلاق.',
    'notify_enrolment_closed_title' => 'أُغلق التسجيل — :title',
    'notify_enrolment_closed_body' => 'قائمة المشاركين نهائية. نراك هناك.',
    'notify_event_day_title' => 'اليوم: :title',
    'notify_event_day_body' => 'تبدأ :time في :place. بالتوفيق!',

    // تسجيل النادي / المدرب
    'entry_result' => 'تم تسجيل :entered · تعذّر :rejected',
    'entry_not_your_athlete' => ':name ليس عضواً في نادٍ تديره.',
    'entry_out_of_scope' => 'هذه الفعالية غير متاحة لنادي :name.',
    'entry_banned' => 'تم منع :name من هذه الفعالية.',
    'entry_event_ended' => 'انتهت هذه الفعالية.',
    'entry_not_open' => 'يفتح التسجيل في :date.',
    'entry_closed' => 'أُغلق التسجيل في :date.',
    'entry_full' => 'اكتمل العدد في هذه الفعالية.',
    'entry_not_eligible' => ':name غير مؤهل لهذه الفعالية.',
    'entry_notify_title' => 'تم تسجيلك — :title',
    'entry_notify_body' => 'قام :club بتسجيلك في هذه الفعالية.',
    'entry_notify_body_division' => 'قام :club بتسجيلك في هذه الفعالية · :division',

    // القرعة والمخطط — مشتركة بين كل أنواع الفعاليات التي تُقام بنظام الإقصاء
    'athlete' => 'لاعب',
    'division_not_found' => 'هذه الفئة ليست ضمن هذه الفعالية.',
    'bracket_not_drawn' => 'لم تُسحب القرعة لهذه الفئة بعد.',
    'bracket_slot_invalid' => 'لا يمكن تغيير هذا الموضع.',
    'bracket_competitor_invalid' => 'هذا المتسابق غير مسجّل في هذه الفئة.',
    'bracket_tbd' => 'يُحدد لاحقاً',
    'bracket_bye' => 'عبور',
    'bracket_bench' => 'المشاركون',
    'bracket_bench_empty' => 'جميع المشاركين ضمن القرعة.',
    'bracket_no_draw' => 'لا توجد قرعة لهذه الفئة بعد.',
    'bracket_load_failed' => 'تعذّر تحميل المخطط.',
    'bracket_move_failed' => 'تعذّر نقل هذا المتسابق.',
    'bracket_locked' => 'القرعة نهائية',
    'bracket_arrange' => 'ترتيب القرعة',
    'bracket_done_arranging' => 'إنهاء الترتيب',
    'bracket_arrange_hint' => 'اسحب متسابقاً إلى الموضع — أو اضغط عليه ثم اضغط على الموضع.',
    'bracket_holding' => 'مُمسك بـ',
    'bracket_tap_to_place' => 'اضغط على الموضع لوضعه',
    'bracket_clear' => 'إفراغ القرعة',
    'bracket_clear_title' => 'إفراغ هذه القرعة؟',
    'bracket_clear_message' => 'سيعود جميع المتسابقين إلى قائمة المشاركين لتبني القرعة يدوياً. لن يُفقد شيء — يمكنك وضعهم من جديد أو سحب قرعة جديدة.',
    'bracket_clear_confirm' => 'إفراغها',
    'bracket_zoom_in' => 'تكبير',
    'bracket_zoom_out' => 'تصغير',
    'bracket_fit' => 'ملاءمة الشاشة',
    'bracket_legend_provisional' => 'غير مؤكد بعد',
    'bracket_legend_done' => 'محسوم',
    'bracket_legend_gestures' => 'اسحب للتحريك · قرّص أو مرّر للتكبير',

    // نموذج الإنشاء/التعديل — أسباب رفض الحفظ
    'validate_enrolment_ends_after_event' => 'يجب أن يُغلق التسجيل في تاريخ الفعالية أو قبله.',
    'validate_enrolment_ends_before_start' => 'لا يمكن أن يُغلق التسجيل قبل أن يفتح.',
    'validate_end_date_before_start' => 'لا يمكن أن يسبق تاريخ الانتهاء تاريخ البداية.',
    'validate_start_time_format' => 'اختر وقت البداية.',
    'validate_end_time_format' => 'اختر وقت الانتهاء أو اتركه فارغاً.',
    'validate_break_before_start' => 'لا يمكن أن تبدأ الاستراحة قبل بداية الفعالية.',
    'validate_break_end_before_break_start' => 'يجب أن تنتهي الاستراحة بعد بدايتها.',
    'validate_break_after_end' => 'يجب أن تنتهي الاستراحة قبل انتهاء الفعالية.',
];
