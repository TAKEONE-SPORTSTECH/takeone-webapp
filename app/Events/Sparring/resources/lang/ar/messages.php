<?php

/*
| المبارزة — لوحة نتائج النادي للتدريب.
|
| ما يظهر على الشاشة أثناء النزال يأتي من لوحة الرياضة نفسها، لأن الجلسة
| تستعير تلك الطاولة كما هي ولا يجوز أن تُقرأ بشكل مختلف عن المنافسة.
*/

return [

    'label' => 'مبارزة',
    'session_title' => 'مبارزة · :date',
    'mat_n' => 'بساط :n',

    'launch_title' => 'مبارزة',
    'launch_eyebrow' => 'لوحة نتائج التدريب',
    'launch_lead' => 'ضع لاعبين على البساط وترى القاعة النتيجة. بلا قرعة وبلا تسجيل — افتحها الآن وأغلقها بعد التدريب.',
    'launch_sport' => 'أي رياضة',
    'launch_mats' => 'كم بساط',
    'launch_minutes' => 'مدة النزال',
    'launch_minutes_n' => ':n دقيقة',
    'launch_start' => 'ابدأ المبارزة',
    'launch_resume' => 'افتح جلسة اليوم',
    'launch_running' => 'جارية الآن',
    'launch_open_since' => 'مفتوحة منذ :time',
    'launch_no_sport' => 'لا توجد في هذا النادي رياضة لها لوحة نتائج على البساط.',
    'launch_past' => 'جلسات سابقة',
    'launch_past_none' => 'لا توجد جلسات بعد.',
    'launch_bouts_n' => ':n نزال',

    'console_mats' => 'البُسط',
    'console_mat_count' => 'قيد التشغيل :n',
    'console_floor' => 'الحاضرون',
    'console_floor_hint' => 'اختر اثنين لمواجهتهما',
    'console_add_people' => 'إضافة أشخاص',
    'console_queue' => 'قائمة الانتظار',
    'console_queue_empty' => 'لا شيء في انتظار هذا البساط.',
    'console_fought' => 'ما تم خوضه',
    'console_fought_empty' => 'لا نزالات بعد.',
    'console_open_table' => 'طاولة التحكيم',
    'console_table_needs_bout' => 'أضف نزالًا على هذا البساط لفتح طاولة التحكيم.',
    'console_screens' => 'شاشات القاعة',
    'console_pick_red' => 'الزاوية الحمراء',
    'console_pick_blue' => 'الزاوية الزرقاء',
    'console_pair_hint' => 'اختر شخصين من الحاضرين',
    'console_queue_it' => 'أضف هذا النزال',
    'console_clear_pick' => 'مسح',
    'console_bouts_today' => ':n اليوم',
    'console_end_session' => 'إنهاء الجلسة',
    'console_end_confirm' => 'إنهاء جلسة المبارزة؟ تتوقف لوحة النتائج وتُؤرشف الجلسة.',
    'console_closed' => 'انتهت هذه الجلسة.',
    'console_search_members' => 'ابحث عن الأعضاء',
    'console_no_members' => 'لا يوجد أعضاء.',
    'console_add_selected' => 'أضف :n إلى الحاضرين',
    'console_mats_label' => 'البُسط العاملة',

    'entrants_added' => 'تمت الإضافة.',
    'entrant_removed' => 'تم الاستبعاد.',
    'entrant_queued' => 'لديه نزال لم يُخض بعد.',
    'bout_queued' => 'أُضيف إلى القائمة.',
    'bout_removed' => 'أُزيل من القائمة.',
    'bout_fought' => 'هذا النزال خُضْ بالفعل.',
    'mats_saved' => 'تم تحديث البُسط.',
    'session_ended' => 'انتهت الجلسة.',
    'session_closed' => 'انتهت هذه الجلسة.',
    'same_person' => 'اختر شخصين مختلفين.',
    'unknown_mat' => 'هذا ليس بساطًا في هذه الجلسة.',
    'unknown_entrant' => 'يجب أن يكون الطرفان من الحاضرين.',

    'action_add_entrants' => 'إضافة أشخاص',
    'action_remove_entrant' => 'استبعاد شخص',
    'action_queue_bout' => 'إضافة نزال',
    'action_unqueue_bout' => 'إزالة نزال',
    'action_set_mats' => 'تحديد البُسط',
    'action_close' => 'إنهاء الجلسة',
];
