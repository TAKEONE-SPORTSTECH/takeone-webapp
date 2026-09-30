<?php

// The generic competition engine (App\Events\Championship), 2026-09-27.
// Official roles named in sport recipes (app/Events/Sports/*/recipe.php).

return [
    'official_referee' => 'حكم',
    'official_umpire' => 'حكم الكرسي',
    'official_line_judge' => 'حكم خط',
    'official_service_judge' => 'حكم الإرسال',
    'official_marker' => 'المسجّل',
    'official_scorer' => 'مسجّل النقاط',
    'official_assistant_referee' => 'حكم مساعد',
    'official_fourth_official' => 'الحكم الرابع',
    'official_timekeeper' => 'ميقاتي',
    'official_technical_officer' => 'مسؤول فني',
    'official_goal_judge' => 'حكم المرمى',
    'official_admin' => 'مشرف المباراة',
    'official_judge' => 'حكم',
    'official_mat_chairman' => 'رئيس البساط',
    'official_arbiter' => 'حكم الشطرنج',
    'official_starter' => 'مُطلق السباق',
    'official_commissaire' => 'مفوّض',
    'official_technical_official' => 'مسؤول فني',
    'official_stroke_judge' => 'حكم أسلوب السباحة',

    // Result rules (ResultRules, Heats).
    'result_sets_required' => 'أدخل نتيجة كل شوط.',
    'result_too_many_sets' => 'تُلعب هذه المباراة من أفضل :n أشواط.',
    'result_set_invalid' => 'الشوط :n غير مكتمل حسب قواعد هذه الرياضة.',
    'result_after_decided' => 'حُسمت المباراة قبل الشوط الأخير.',
    'result_not_decided' => 'لم يفز أحد بعد بعدد الأشواط المطلوب (:n).',
    'result_score_required' => 'أدخل النتيجتين.',
    'result_needs_decider' => 'التعادل هنا يحتاج إلى حسم — أدخل ركلات الترجيح (أو الفائز في الحسم).',
    'result_value_required' => 'أدخل النتيجة.',
];
