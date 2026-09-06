<?php

/*
|--------------------------------------------------------------------------
| حزمة بطولة الكاراتيه — النصوص
|--------------------------------------------------------------------------
| مملوكة لهذه الحزمة وتحت نطاقها: event-karate_tournament::messages.*
*/

return [

    'type_label' => 'بطولة كاراتيه',

    // شروط التسجيل
    'gate_no_profile' => 'أضف الجنس وتاريخ الميلاد إلى ملفك الشخصي لنتمكن من تحديد فئتك الوزنية.',
    'gate_no_weight' => 'أضف وزنك الحالي إلى ملفك الصحي لنتمكن من تحديد فئتك الوزنية.',
    'gate_no_division' => 'فئتك الوزنية ليست من فئات هذه البطولة، لذلك لا يمكنك المشاركة فيها.',
    'gate_no_division_spectator' => 'فئتك الوزنية ليست من فئات هذه البطولة، لذلك لا يمكنك المنافسة — لكن يمكنك الحضور كمتفرّج.',

    // قائمة المشاركين
    'roster_registered' => 'مسجّل',
    'roster_unclassified' => 'غير مصنّف',

    // القرعة ويوم البطولة
    'action_generate_draw' => 'إنشاء القرعة',
    'draw_generated' => 'تم إنشاء القرعة المبدئية',
    'draw_final' => 'انطلقت البطولة — القرعة نهائية ولا يمكن إعادة إنشائها.',
    'action_arrange_draw' => 'ترتيب القرعة',
    'action_clear_draw' => 'إفراغ القرعة',
    'draw_arranged' => 'تم تحديث القرعة 🥋',
    'draw_cleared' => 'أُفرغت القرعة — عاد جميع المشاركين إلى القائمة',
    'day_mats' => 'اليوم :day: :count بساط',
    'ended_locked' => 'انتهت هذه البطولة — نتائجها نهائية.',

    // Notifications
    'notify_weigh_in_title' => 'الوزن الرسمي غداً — :title',
    'notify_weigh_in_body' => 'الوزن الرسمي الساعة :time في :place. يجب أن تحقق وزن فئتك للمشاركة.',
    'notify_draw_title' => 'صدرت القرعة — :title',
    'notify_draw_body' => 'تم نشر منافسك ورقم البساط ورقم النزال.',

    // يوم البطولة — النداء للبساط
    'call_mat_tbc' => 'بساطك',
    'call_opponent_tbc' => 'يُحدَّد لاحقاً',
    'call_warmup_title' => 'استعد للإحماء — نزالك على :mat قريباً',
    'call_warmup_body' => 'يسبقك :ahead نزال · حوالي :minutes دقيقة',
    'call_room_title' => 'توجّه إلى غرفة النداء — :mat',
    'call_room_body' => ':mat · النزال :bout · ضد :opponent',
    'result_win_title' => 'فزت 🎉',
    'result_loss_title' => 'انتهى النزال',
    'result_body_next' => 'التالي: :round على :mat، حوالي :minutes دقيقة',
    'result_body_done' => 'كان ذلك آخر نزال لك اليوم.',

    // شاشة النزال القادم
    'next_up_title' => 'نزالي القادم',
    'next_up_none' => 'لا يوجد نزال مجدول لك حالياً.',
    'next_up_done' => 'انتهت نزالاتك لليوم.',
    'next_up_ahead' => 'نزال قبلك',
    'next_up_you_are_next' => 'أنت التالي — توجّه إلى البساط',
    'next_up_estimate' => 'تقدير يتغيّر مع سير البساط',
    'next_up_squad' => 'فريقي',
    'next_up_opponent_tbc' => 'بانتظار المنافس',
    'next_up_finished' => 'انتهى',

    // شاشة الصالة
    'board_title' => 'شاشة البساط — :event',
    'board_heading' => 'ترتيب النزالات',
    'board_on_deck' => 'التالي',
    'board_idle' => 'لا توجد نزالات جارية',
    'board_live' => 'مباشر',
    'board_stale' => 'إعادة الاتصال',
    // للتجربة فقط — لا تظهر في بيئة الإنتاج
    'action_end_next_bout' => 'إنهاء النزال التالي (تجربة)',
    'end_next_bout_none' => 'لا يوجد نزال جاهز للإنهاء — كل النزالات المتبقية تنتظر نتائج سابقة.',
    'end_next_bout_done' => 'فاز :winner على :court. تم إبلاغ شاشات هذا البساط.',
];
