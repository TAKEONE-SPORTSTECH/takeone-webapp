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
];
