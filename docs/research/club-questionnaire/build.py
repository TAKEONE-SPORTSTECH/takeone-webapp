#!/usr/bin/env python3
"""Builds the bilingual club-discovery questionnaire as HTML, for Chromium to print."""
import html, pathlib

F = '/var/www/takeone/public/fonts/designer'
OUT = pathlib.Path(__file__).with_name('questionnaire.html')

def e(s): return html.escape(s)

YESNO = [('Yes', 'نعم'), ('No', 'لا'), ('Sometimes', 'أحياناً')]

SECTIONS = [
 ('The Club Today', 'النادي اليوم',
  'A quick picture of who they are, so every later answer has context.',
  'صورة سريعة عن النادي، حتى تكون لكل إجابة لاحقة سياقها.',
  ('Ask to see their member list or register — how they keep it tells you more than any answer.',
   'اطلب رؤية سجل الأعضاء — طريقة حفظه تخبرك أكثر من أي إجابة.'),
  [
   ('num', 'The club in numbers', 'النادي بالأرقام',
    [('Active members', 'الأعضاء النشطون'), ('Coaches', 'المدربون'), ('Admin staff', 'الإداريون'),
     ('Branches', 'الفروع'), ('Years open', 'سنوات العمل'), ('Classes / week', 'الحصص أسبوعياً')]),
   ('choice', 'Which sports or activities do you offer?', 'ما الرياضات أو الأنشطة التي تقدمونها؟',
    [('Taekwondo', 'تايكوندو'), ('Karate', 'كاراتيه'), ('Jiu-Jitsu', 'جوجيتسو'), ('Judo', 'جودو'),
     ('Boxing / Muay Thai', 'ملاكمة / مواي تاي'), ('Football', 'كرة القدم'), ('Swimming', 'سباحة'),
     ('Fitness / Gym', 'لياقة / صالة'), ('Gymnastics', 'جمباز')], True),
   ('choice', 'Who are your members mostly?', 'من هم غالبية أعضائكم؟',
    [('Kids under 12', 'أطفال دون 12'), ('Teens 12–17', 'مراهقون 12–17'), ('Adults', 'بالغون'),
     ('Women-only classes', 'حصص للنساء فقط'), ('Competitive athletes', 'رياضيون تنافسيون'),
     ('Families / siblings', 'عائلات / إخوة')], False),
   ('choice', 'What do you use today to run the club?', 'ماذا تستخدمون اليوم لإدارة النادي؟',
    [('Paper / notebook', 'ورق / دفتر'), ('Excel / Google Sheets', 'إكسل / جداول جوجل'),
     ('WhatsApp groups', 'مجموعات واتساب'), ('Instagram', 'إنستغرام'),
     ('Other club software', 'برنامج إدارة آخر'), ('Accounting software', 'برنامج محاسبة')], True),
   ('text', 'If you use other software: what is it, what do you like, and why might you leave it?',
    'إن كنتم تستخدمون برنامجاً آخر: ما هو، وما الذي يعجبكم فيه، ولماذا قد تتركونه؟', 3),
   ('text', 'Who actually does the daily admin work — the owner, a coach, a receptionist?',
    'من يقوم فعلياً بالعمل الإداري اليومي — المالك، مدرب، أم موظف استقبال؟', 2),
  ]),

 ('Joining & Registration', 'الانضمام والتسجيل',
  'From the first phone call to the first class — where do people get lost?',
  'من أول اتصال إلى أول حصة — أين يتعثر الناس؟',
  ('Ask them to walk you through the last new member who joined, step by step.',
   'اطلب منهم أن يشرحوا خطوة بخطوة كيف انضم آخر عضو جديد.'),
  [
   ('choice', 'How do new members usually find and contact you?', 'كيف يجدكم الأعضاء الجدد عادةً ويتواصلون معكم؟',
    [('Walk in', 'زيارة مباشرة'), ('Phone call', 'اتصال هاتفي'), ('WhatsApp', 'واتساب'),
     ('Instagram / TikTok', 'إنستغرام / تيك توك'), ('Friend referral', 'توصية من صديق'),
     ('School / company', 'مدرسة / شركة'), ('Google Maps', 'خرائط جوجل')], True),
   ('choice', 'What do you collect when someone joins?', 'ما الذي تجمعونه عند انضمام عضو؟',
    [('Name & phone', 'الاسم والهاتف'), ('Date of birth', 'تاريخ الميلاد'), ('CPR / ID copy', 'نسخة البطاقة الشخصية'),
     ('Photo', 'صورة'), ('Medical info', 'معلومات طبية'), ('Guardian details', 'بيانات ولي الأمر'),
     ('Signed waiver', 'إقرار موقّع'), ('Emergency contact', 'جهة اتصال للطوارئ')], True),
   ('one', 'Do you offer a free or paid trial class?', 'هل تقدمون حصة تجريبية مجانية أو مدفوعة؟',
    [('Free trial', 'تجربة مجانية'), ('Paid trial', 'تجربة مدفوعة'), ('No trial', 'لا يوجد')]),
   ('scale', 'How painful is registering a new member today?', 'ما مدى صعوبة تسجيل عضو جديد اليوم؟',
    ('Easy', 'سهل'), ('Very painful', 'مرهق جداً')),
   ('one', 'Who usually registers a child — the parent, or your staff on their behalf?',
    'من يسجّل الطفل عادةً — ولي الأمر أم موظفوكم نيابةً عنه؟',
    [('Parent', 'ولي الأمر'), ('Our staff', 'موظفونا'), ('Both', 'كلاهما')]),
   ('text', 'Would you let parents register themselves online or by QR code? What worries you about it?',
    'هل تسمحون لأولياء الأمور بالتسجيل بأنفسهم عبر الإنترنت أو رمز QR؟ ما الذي يقلقكم في ذلك؟', 3),
  ]),

 ('Classes, Schedule & Attendance', 'الحصص والجدول والحضور',
  'The heartbeat of the club: who trains, when, with whom — and who stopped coming.',
  'نبض النادي: من يتدرب، ومتى، ومع من — ومن توقف عن الحضور.',
  ('Watch a class start if you can. How is attendance actually taken?',
   'شاهد بداية حصة إن أمكن. كيف يُسجَّل الحضور فعلياً؟'),
  [
   ('choice', 'How do you take attendance?', 'كيف تسجّلون الحضور؟',
    [("We don't", 'لا نسجّله'), ('Paper list', 'قائمة ورقية'), ('Spreadsheet', 'جدول بيانات'),
     ('App / card scan', 'تطبيق / مسح بطاقة'), ('Coach remembers', 'المدرب يتذكر')], True),
   ('one', 'How do you sell training?', 'كيف تبيعون التدريب؟',
    [('Monthly fee', 'اشتراك شهري'), ('Term / season', 'فصل / موسم'), ('Class packs', 'باقة حصص'),
     ('Per session', 'بالحصة'), ('Mixed', 'مزيج')]),
   ('one', 'Do classes fill up? Do you keep a waiting list?', 'هل تمتلئ الحصص؟ هل لديكم قائمة انتظار؟', YESNO),
   ('text', 'When a member misses classes for weeks, how do you notice — and what do you do?',
    'عندما يغيب عضو لأسابيع، كيف تلاحظون ذلك — وماذا تفعلون؟', 3),
   ('one', 'Do members ask to freeze or pause (travel, injury, Ramadan, exams)?',
    'هل يطلب الأعضاء تجميد الاشتراك (سفر، إصابة، رمضان، امتحانات)؟', YESNO),
   ('text', 'What happens when a coach is absent or a class is cancelled? How are members told?',
    'ماذا يحدث عند غياب المدرب أو إلغاء حصة؟ كيف يُبلَّغ الأعضاء؟', 2),
  ]),

 ('Fees, Payments & Money', 'الرسوم والمدفوعات والمال',
  'Where the club lives or dies. Listen for chasing, forgetting and arguing.',
  'هنا يعيش النادي أو يتعثر. انتبه لكلمات المطاردة والنسيان والخلاف.',
  ('Ask: "How many members owe you money right now?" — and note how long it took to answer.',
   'اسأل: «كم عضواً عليه مبالغ لكم الآن؟» — ولاحظ كم استغرقهم الجواب.'),
  [
   ('choice', 'How do members pay you?', 'كيف يدفع الأعضاء لكم؟',
    [('Cash', 'نقداً'), ('BenefitPay', 'بنفت باي'), ('Bank transfer', 'تحويل بنكي'), ('Card machine', 'جهاز البطاقات'),
     ('Cheque', 'شيك'), ('Online link', 'رابط دفع')], True),
   ('one', 'Do you send invoices or receipts?', 'هل ترسلون فواتير أو إيصالات؟',
    [('Always', 'دائماً'), ('On request', 'عند الطلب'), ('Never', 'أبداً')]),
   ('scale', 'How much time goes into chasing late payments?', 'كم من الوقت يذهب في مطاردة المتأخرين عن الدفع؟',
    ('Almost none', 'لا يُذكر'), ('Hours every week', 'ساعات أسبوعياً')),
   ('choice', 'Which of these do you offer?', 'أيٌّ مما يلي تقدمونه؟',
    [('Sibling discount', 'خصم الإخوة'), ('Installments', 'دفع بالتقسيط'), ('Registration fee', 'رسوم تسجيل'),
     ('Uniform fee', 'رسوم الزي'), ('Refunds', 'استرداد'), ('Scholarships', 'منح / إعفاءات'),
     ('Corporate / school deals', 'عروض للشركات / المدارس')], True),
   ('choice', 'Which expenses would you like to track?', 'ما المصاريف التي تودون متابعتها؟',
    [('Rent', 'الإيجار'), ('Coach salaries', 'رواتب المدربين'), ('Equipment', 'المعدات'), ('Utilities', 'الخدمات'),
     ('Competition fees', 'رسوم البطولات'), ('Marketing', 'التسويق')], True),
   ('one', 'How are coaches paid?', 'كيف يُدفع للمدربين؟',
    [('Fixed salary', 'راتب ثابت'), ('Per class', 'بالحصة'), ('% of members', 'نسبة من الأعضاء'), ('Volunteer', 'تطوع'), ('Mixed', 'مزيج')]),
   ('text', 'At the end of the month, what number do you most want to see — and how do you get it today?',
    'في نهاية الشهر، ما الرقم الذي تريدون رؤيته أكثر — وكيف تحصلون عليه اليوم؟', 3),
  ]),

 ('Communication with Members & Parents', 'التواصل مع الأعضاء وأولياء الأمور',
  'Most clubs run on WhatsApp. Find out what that costs them.',
  'معظم الأندية تعمل عبر واتساب. اكتشف كم يكلّفهم ذلك.',
  ('Ask how many WhatsApp groups they run, and how many messages a day the owner answers.',
   'اسأل كم مجموعة واتساب لديهم، وكم رسالة يرد عليها المالك يومياً.'),
  [
   ('choice', 'What do you most often need to tell members?', 'ما أكثر ما تحتاجون لإبلاغ الأعضاء به؟',
    [('Payment reminders', 'تذكير بالدفع'), ('Schedule changes', 'تغيير الجدول'), ('Cancelled class', 'إلغاء حصة'),
     ('Events / competitions', 'فعاليات / بطولات'), ('Belt tests', 'اختبارات الأحزمة'), ('Holidays', 'العطل'),
     ('Child progress', 'تقدّم الطفل')], True),
   ('one', 'Preferred channel for the club to reach members:', 'القناة المفضلة لتواصل النادي مع الأعضاء:',
    [('WhatsApp', 'واتساب'), ('App notification', 'إشعار التطبيق'), ('SMS', 'رسالة نصية'), ('Email', 'بريد إلكتروني')]),
   ('one', 'Language you need for messages and screens:', 'اللغة المطلوبة للرسائل والشاشات:',
    [('Arabic', 'العربية'), ('English', 'الإنجليزية'), ('Both', 'كلتاهما'), ('Other', 'أخرى')]),
   ('scale', 'How often do parents ask "how is my child doing?"', 'كم مرة يسأل أولياء الأمور «كيف حال ابني؟»',
    ('Rarely', 'نادراً'), ('Constantly', 'باستمرار')),
   ('text', 'What would you never want the system to send automatically?',
    'ما الذي لا تريدون أبداً أن يرسله النظام تلقائياً؟', 2),
  ]),

 ('Progress, Belts & Achievements', 'التقدّم والأحزمة والإنجازات',
  'What keeps a member coming back is seeing themselves get better.',
  'ما يُبقي العضو مستمراً هو أن يرى نفسه يتحسن.',
  ('If they hold belt tests, ask to see a grading sheet or certificate.',
   'إن كانوا يقيمون اختبارات أحزمة، اطلب رؤية ورقة التقييم أو الشهادة.'),
  [
   ('one', 'Do you run belt tests or gradings?', 'هل تقيمون اختبارات أحزمة أو ترفيع؟', YESNO),
   ('text', 'How does a grading work, start to finish? (who decides, fees, scoring, certificates)',
    'كيف يجري الاختبار من البداية للنهاية؟ (من يقرر، الرسوم، التقييم، الشهادات)', 3),
   ('choice', 'What would you like to track for each member?', 'ما الذي تودون متابعته لكل عضو؟',
    [('Belt / level', 'الحزام / المستوى'), ('Attendance history', 'سجل الحضور'), ('Fitness tests', 'اختبارات لياقة'),
     ('Weight', 'الوزن'), ('Medals & results', 'الميداليات والنتائج'), ('Coach notes', 'ملاحظات المدرب'),
     ('Goals', 'الأهداف'), ('Videos', 'مقاطع فيديو')], True),
   ('scale', 'How valuable would a progress report for parents be?', 'ما قيمة تقرير تقدّم يُرسل لأولياء الأمور؟',
    ('Not useful', 'غير مفيد'), ('Extremely', 'مفيد جداً')),
  ]),

 ('Competitions & Events', 'البطولات والفعاليات',
  'Entering tournaments, hosting them, and everything in between.',
  'المشاركة في البطولات واستضافتها، وكل ما بينهما.',
  ('Ask about the last tournament they entered: how long did the entry list take?',
   'اسأل عن آخر بطولة شاركوا فيها: كم استغرق إعداد قائمة المشاركين؟'),
  [
   ('one', 'Do you take members to competitions?', 'هل تشاركون بأعضائكم في البطولات؟',
    [('Often', 'كثيراً'), ('Sometimes', 'أحياناً'), ('Never', 'أبداً')]),
   ('one', 'Do you host your own events (tournaments, open days, camps)?', 'هل تستضيفون فعالياتكم الخاصة (بطولات، أيام مفتوحة، معسكرات)؟', YESNO),
   ('choice', 'What is hardest about competition days?', 'ما الأصعب في أيام البطولات؟',
    [('Collecting entries & fees', 'جمع التسجيلات والرسوم'), ('Weights & categories', 'الأوزان والفئات'),
     ('The draw / brackets', 'القرعة / الجداول'), ('Scoring', 'التحكيم والنقاط'), ('Running order', 'ترتيب النزالات'),
     ('Results & certificates', 'النتائج والشهادات'), ('Filming bouts', 'تصوير النزالات')], True),
   ('scale', 'How much would members value videos of their own bouts?', 'ما مدى تقدير الأعضاء لمقاطع فيديو لنزالاتهم؟',
    ('Not at all', 'لا يهم'), ('A lot', 'كثيراً')),
   ('text', 'Which federation or body do you deal with, and what do they ask you for?',
    'مع أي اتحاد أو جهة تتعاملون، وماذا يطلبون منكم؟', 2),
  ]),

 ('Staff, Roles & Ownership', 'الموظفون والصلاحيات والملكية',
  'Who may see what, who may change what — and who signs off on money.',
  'من يرى ماذا، ومن يغيّر ماذا — ومن يعتمد الأمور المالية.',
  ('Ask: "Is there anything a coach should NEVER be able to see?"',
   'اسأل: «هل هناك ما لا ينبغي للمدرب أن يراه أبداً؟»'),
  [
   ('choice', 'Who should be able to use the system?', 'من يجب أن يستخدم النظام؟',
    [('Owner', 'المالك'), ('Partners', 'الشركاء'), ('Manager', 'المدير'), ('Coaches', 'المدربون'),
     ('Receptionist', 'الاستقبال'), ('Accountant', 'المحاسب'), ('Parents', 'أولياء الأمور'), ('Members', 'الأعضاء')], True),
   ('text', 'What should coaches be allowed to do — and NOT do? (e.g. see fees, edit members, take payments)',
    'ما المسموح للمدربين — وما غير المسموح؟ (مثل رؤية الرسوم، تعديل الأعضاء، استلام المدفوعات)', 3),
   ('one', 'More than one owner or branch?', 'أكثر من مالك أو فرع؟',
    [('Single owner', 'مالك واحد'), ('Partners', 'شركاء'), ('Several branches', 'عدة فروع'), ('Both', 'كلاهما')]),
   ('one', 'Do you sell uniforms, equipment or merchandise?', 'هل تبيعون الزي أو المعدات أو منتجات النادي؟', YESNO),
  ]),

 ('Devices, Trust & Priorities', 'الأجهزة والثقة والأولويات',
  'What they will actually use, what would stop them, and what matters most.',
  'ما سيستخدمونه فعلاً، وما قد يمنعهم، وما الأهم لديهم.',
  ('Notice which phone the owner carries and whether they use apps in Arabic or English.',
   'لاحظ أي هاتف يستخدمه المالك، وهل يستخدم التطبيقات بالعربية أم بالإنجليزية.'),
  [
   ('choice', 'Which devices would you use to run the club?', 'ما الأجهزة التي ستستخدمونها لإدارة النادي؟',
    [('iPhone', 'آيفون'), ('Android phone', 'هاتف أندرويد'), ('Tablet at reception', 'جهاز لوحي في الاستقبال'),
     ('Laptop / PC', 'حاسوب'), ('TV screen in the hall', 'شاشة في القاعة')], False),
   ('choice', 'What would make you hesitate to move to a new system?', 'ما الذي قد يجعلكم تترددون في الانتقال لنظام جديد؟',
    [('Price', 'السعر'), ('Time to set up', 'وقت الإعداد'), ('Moving old data', 'نقل البيانات القديمة'),
     ('Staff training', 'تدريب الموظفين'), ('Data privacy', 'خصوصية البيانات'), ('Parents won’t use it', 'أولياء الأمور لن يستخدموه'),
     ('Internet at the club', 'الإنترنت في النادي')], True),
   ('one', 'How would you prefer to pay for a system like this?', 'كيف تفضّلون الدفع مقابل نظام كهذا؟',
    [('Monthly', 'شهرياً'), ('Yearly', 'سنوياً'), ('Per member', 'لكل عضو'), ('One-time', 'مرة واحدة')]),
   ('text', 'Roughly what monthly amount would feel fair — if it saved you real time?',
    'ما المبلغ الشهري الذي ترونه عادلاً تقريباً — إن وفّر عليكم وقتاً حقيقياً؟', 1),
  ]),
]

FEATURES = [
 ('Online & QR self-registration', 'التسجيل الذاتي عبر الإنترنت ورمز QR'),
 ('Family accounts (one parent, many children)', 'حسابات عائلية (ولي أمر واحد لعدة أبناء)'),
 ('Packages, class schedule & capacity', 'الباقات وجدول الحصص والسعة'),
 ('Attendance check-in (QR / tablet)', 'تسجيل الحضور (QR / جهاز لوحي)'),
 ('Automatic payment reminders', 'تذكير تلقائي بالدفع'),
 ('Upload proof of payment, admin approves', 'رفع إثبات الدفع واعتماد الإدارة'),
 ('Invoices & receipts', 'الفواتير والإيصالات'),
 ('Freeze / pause a subscription', 'تجميد / إيقاف الاشتراك مؤقتاً'),
 ('Sibling & special discounts', 'خصومات الإخوة والعروض الخاصة'),
 ('Installment plans', 'خطط الدفع بالتقسيط'),
 ('Income, expenses & monthly report', 'الإيرادات والمصاريف والتقرير الشهري'),
 ('Coach pay / per-class payroll', 'رواتب المدربين / الدفع بالحصة'),
 ('WhatsApp notifications', 'إشعارات واتساب'),
 ('Belt tests & grading certificates', 'اختبارات الأحزمة وشهادات الترفيع'),
 ('Progress reports for parents', 'تقارير تقدّم لأولياء الأمور'),
 ('Tournament entry & brackets', 'التسجيل في البطولات والقرعة'),
 ('Bout video & highlights', 'فيديو النزالات وأبرز اللقطات'),
 ('Club shop (uniforms, equipment)', 'متجر النادي (زي، معدات)'),
 ('Staff roles & permissions', 'أدوار الموظفين وصلاحياتهم'),
 ('Member app in Arabic', 'تطبيق الأعضاء بالعربية'),
 ('Public club page & gallery', 'صفحة النادي العامة والمعرض'),
 ('Several branches in one account', 'عدة فروع في حساب واحد'),
]

SPEAK = [
 ('If you could fix ONE thing about running your club tomorrow, what would it be?',
  'لو استطعت إصلاح شيء واحد في إدارة ناديك غداً، ماذا سيكون؟'),
 ('Tell me about the worst week you had running the club. What went wrong?',
  'حدّثني عن أسوأ أسبوع مرّ عليك في إدارة النادي. ما الذي حدث؟'),
 ('What do parents and members complain about most?',
  'ما أكثر ما يشتكي منه أولياء الأمور والأعضاء؟'),
 ('What would a “dream system” do for you that nothing does today?',
  'ماذا سيفعل لك «النظام المثالي» مما لا يفعله شيء اليوم؟'),
 ('Is there anything we did not ask that we should have?',
  'هل هناك شيء لم نسأل عنه وكان ينبغي أن نسأل؟'),
]

# ---------------------------------------------------------------- renderers
def boxes(opts):
    return '<div class="opts">' + ''.join(
        f'<span class="opt"><i class="box"></i><span class="en">{e(a)}</span><span class="ar" dir="rtl">{e(b)}</span></span>'
        for a, b in opts) + '</div>'

def other():
    return ('<div class="other"><i class="box"></i><span class="en">Other</span>'
            '<span class="rule"></span><span class="ar" dir="rtl">أخرى</span></div>')

def lines(n): return '<div class="lines">' + '<span></span>' * n + '</div>'

def qhead(n, en, ar, hint_en='', hint_ar=''):
    he = f'<span class="hint">{e(hint_en)}</span>' if hint_en else ''
    ha = f'<span class="hint">{e(hint_ar)}</span>' if hint_ar else ''
    return (f'<div class="qh"><span class="qn">{n:02d}</span>'
            f'<div class="qen">{e(en)}{he}</div><div class="qar" dir="rtl">{e(ar)}{ha}</div></div>')

def render_q(n, q):
    k = q[0]
    if k == 'text':
        _, en, ar, ln = q
        return f'<div class="q">{qhead(n, en, ar)}{lines(ln)}</div>'
    if k == 'choice':
        _, en, ar, opts, oth = q
        return (f'<div class="q">{qhead(n, en, ar, "Tick all that apply", "اختر كل ما ينطبق")}'
                f'{boxes(opts)}{other() if oth else ""}</div>')
    if k == 'one':
        _, en, ar, opts = q
        return (f'<div class="q">{qhead(n, en, ar, "Tick one", "اختر واحداً")}{boxes(opts)}'
                f'<div class="note"><span class="en">Note</span><span class="rule"></span><span class="ar" dir="rtl">ملاحظة</span></div></div>')
    if k == 'scale':
        _, en, ar, lo, hi = q
        dots = ''.join(f'<span class="dot">{i}</span>' for i in range(1, 6))
        return (f'<div class="q">{qhead(n, en, ar, "Circle one", "ضع دائرة")}'
                f'<div class="scale"><span class="lo"><b>{e(lo[0])}</b><span dir="rtl" style="text-align:left">{e(lo[1])}</span></span>'
                f'<span class="dots">{dots}</span>'
                f'<span class="hi"><b>{e(hi[0])}</b><span dir="rtl">{e(hi[1])}</span></span></div></div>')
    if k == 'num':
        _, en, ar, cells = q
        c = ''.join(f'<div class="cell"><span class="v"></span><span class="en">{e(a)}</span><span class="ar" dir="rtl">{e(b)}</span></div>' for a, b in cells)
        return f'<div class="q">{qhead(n, en, ar)}<div class="nums">{c}</div></div>'
    raise ValueError(k)

def band(num, en, ien, ar, iar):
    return f'''<header class="band"><span class="c1"></span><span class="c2"></span>
    <div class="bandrow"><div class="num">{num}</div>
      <div class="t"><h2>{e(en)}</h2><p>{e(ien)}</p></div>
      <div class="t ar" dir="rtl"><h2>{e(ar)}</h2><p>{e(iar)}</p></div>
    </div></header>'''

def section(i, s, start_n):
    en, ar, ien, iar, probe, qs = s
    body = ''.join(render_q(start_n + j, q) for j, q in enumerate(qs))
    return f'''
<section class="sec">
  {band(f"{i:02d}", en, ien, ar, iar)}
  <div class="probe"><span class="pi">◆</span><div><b>Interviewer</b> {e(probe[0])}</div><div dir="rtl"><b>للمحاور</b> {e(probe[1])}</div></div>
  {body}
  <div class="obs"><span class="ar" dir="rtl">ملاحظات المحاور</span><span class="en">Interviewer observations</span>{lines(3)}</div>
</section>''', start_n + len(qs)

# ---------------------------------------------------------------- styles
css = f'''
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Regular.ttf);font-weight:400}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-SemiBold.ttf);font-weight:600}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Bold.ttf);font-weight:700}}
@font-face{{font-family:Bricolage;src:url(file://{F}/bricolage-grotesque/BricolageGrotesque-VF.ttf);font-weight:200 800}}
@font-face{{font-family:Serif;src:url(file://{F}/instrument-serif/InstrumentSerif-Italic.ttf);font-style:italic}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf);font-weight:400}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.ttf);font-weight:700}}
@font-face{{font-family:Alexandria;src:url(file://{F}/alexandria/Alexandria-VF.ttf);font-weight:100 900}}

:root{{--p:#7b68ee;--p2:#b3a8f6;--ink:#17133a;--mute:#6b6a80;--line:#d9d6ea;--soft:#f3f1fc;--amber:#d97706}}
@page{{size:A4;margin:15mm 14mm 17mm;
  @bottom-left{{content:"TAKEONE · Club Discovery Visit";font:600 7.5pt Inter;color:#9a97b3;letter-spacing:.06em}}
  @bottom-center{{content:counter(page);font:700 8pt Inter;color:#7b68ee}}
  @bottom-right{{content:"زيارة استكشاف احتياجات الأندية";font:400 7.5pt Plex;color:#9a97b3}}}}
@page cover{{margin:0;@bottom-left{{content:none}}@bottom-right{{content:none}}@bottom-center{{content:none}}}}
*{{box-sizing:border-box;margin:0;padding:0}}
html{{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
body{{font:400 9.6pt/1.45 Inter,Plex,sans-serif;color:var(--ink)}}
[dir=rtl]{{font-family:Plex,Inter,sans-serif;text-align:right}}
h1,h2{{font-family:Bricolage,Inter;font-weight:800;letter-spacing:-.02em}}
[dir=rtl] h2{{font-family:Alexandria,Plex;letter-spacing:0}}

/* cover */
.cover{{page:cover;height:297mm;position:relative;overflow:hidden;color:#fff;
  background:radial-gradient(120% 80% at 100% 0%,#9d8cf7 0%,transparent 55%),
             radial-gradient(90% 70% at 0% 100%,#3b2a9a 0%,transparent 60%),
             linear-gradient(160deg,#6a55e6,#2a1f78);break-after:page}}
.cover .ring{{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.18)}}
.cover .r1{{width:150mm;height:150mm;right:-50mm;top:-40mm}}
.cover .r2{{width:95mm;height:95mm;right:-18mm;top:-8mm;background:rgba(255,255,255,.07);border:none}}
.cover .r3{{width:60mm;height:60mm;left:-20mm;bottom:70mm;background:rgba(255,255,255,.06);border:none}}
.cover .in{{position:absolute;inset:22mm 20mm 20mm}}
.brand{{font:800 11pt Bricolage;letter-spacing:.32em}}
.brand small{{display:block;font:600 7pt Inter;letter-spacing:.24em;opacity:.7;margin-top:1.5mm}}
.cover h1{{font-size:46pt;line-height:.98;margin-top:52mm}}
.cover h1 em{{font:italic 400 54pt Serif;letter-spacing:0;color:#e3dcff}}
.cover .arT{{font:800 30pt/1.3 Alexandria;margin-top:7mm;text-align:right}}
.cover .lead{{margin-top:8mm;max-width:120mm;font-size:10.5pt;opacity:.92}}
.cover .leadar{{max-width:120mm;margin-left:auto;font-size:10.5pt;opacity:.92;margin-top:4mm}}
.card{{position:absolute;left:0;right:0;bottom:0;background:#fff;color:var(--ink);border-radius:5mm;padding:5mm 7mm 6mm;
  display:grid;grid-template-columns:1fr 1fr;gap:3mm 8mm;box-shadow:0 10px 40px rgba(20,10,80,.35)}}
.card .f{{border-bottom:1px solid var(--line);padding-bottom:1.6mm;display:flex;justify-content:space-between;gap:3mm;font-size:8pt;color:var(--mute);height:12mm;align-items:flex-end}}
.card .f b{{color:var(--ink);font-weight:600}}

/* guide */
.guide{{break-after:page}}
.kicker{{font:700 7.5pt Inter;letter-spacing:.2em;text-transform:uppercase;color:var(--p)}}
.guide h2{{font-size:24pt;margin:2mm 0 0}}
.guide .arh{{font:800 18pt Alexandria;text-align:right;margin-top:-9mm}}
.two{{display:grid;grid-template-columns:1fr 1fr;gap:9mm;margin-top:7mm}}
.steps li{{list-style:none;display:flex;gap:3mm;margin-bottom:3.8mm;font-size:9.2pt}}
.steps li i{{flex:0 0 6.5mm;height:6.5mm;border-radius:50%;background:var(--soft);color:var(--p);font:700 8pt Inter;font-style:normal;display:grid;place-items:center}}
.steps b{{display:block;font-weight:700}}
.tip{{margin-top:5mm;border-radius:4mm;background:var(--ink);color:#fff;padding:5mm 6mm;display:grid;grid-template-columns:1fr 1fr;gap:8mm;font-size:9pt}}
.tip b{{color:#c9bfff}}
.legend{{margin-top:5mm;display:grid;grid-template-columns:repeat(4,1fr);gap:3mm}}
.legend div{{border:1px solid var(--line);border-radius:3mm;padding:3mm;font-size:8pt;color:var(--mute);display:flex;flex-direction:column;gap:1mm}}
.legend b{{color:var(--ink);font-size:8.6pt;display:flex;align-items:center;gap:1.6mm}}
.legend .dot{{width:5mm;height:5mm;font-size:6.5pt}}
.consent{{margin-top:5mm;border:1.5px dashed var(--p2);border-radius:4mm;padding:4mm 5mm;display:grid;grid-template-columns:1fr 1fr;gap:8mm;font-size:8.6pt}}

/* sections */
.sec{{break-before:page}}
.band{{position:relative;overflow:hidden;border-radius:5mm;color:#fff;padding:6mm 7mm;
  background:linear-gradient(150deg,#7b68ee,#7b68eeb0),#4b3bc4}}
.band .c1,.band .c2{{position:absolute;border-radius:50%;background:rgba(255,255,255,.12)}}
.band .c1{{width:44mm;height:44mm;right:-12mm;top:-16mm}}
.band .c2{{width:22mm;height:22mm;left:44%;bottom:-11mm}}
.bandrow{{position:relative;display:grid;grid-template-columns:auto 1fr 1fr;gap:6mm;align-items:center}}
.band .num{{font:300 34pt/1 Bricolage;opacity:.85;min-width:14mm}}
.band h2{{font-size:16pt;line-height:1.12}}
.band .ar h2{{font-size:15pt;font-weight:700;line-height:1.35}}
.band p{{font-size:8.5pt;opacity:.92;margin-top:1.5mm}}
.probe{{display:grid;grid-template-columns:auto 1fr 1fr;gap:4mm;align-items:start;margin:4mm 0 1mm;padding:3mm 4mm;border-radius:3mm;
  background:#fff7e8;border:1px solid #f5d9a6;font-size:8.3pt;color:#7a4a05}}
.probe b{{color:var(--amber);font-weight:700;margin-inline-end:1.2mm}}
.probe .pi{{color:var(--amber);font-size:9pt}}

.q{{break-inside:avoid;padding:3.6mm 0 3.2mm;border-bottom:1px solid #ebe9f4}}
.qh{{display:grid;grid-template-columns:9mm 1fr 1fr;gap:5mm;align-items:start}}
.qn{{font:700 8.5pt Inter;color:var(--p);background:var(--soft);border-radius:2mm;padding:.8mm 0;text-align:center}}
.qen{{font-weight:600;font-size:9.8pt}}
.qar{{font-weight:700;font-size:10pt;line-height:1.55}}
.hint{{display:block;font-weight:400;font-size:7.4pt;color:var(--mute);margin-top:.6mm}}
.opts,.other,.note,.scale,.nums,.q>.lines{{margin-left:14mm}}
.opts{{display:flex;flex-wrap:wrap;gap:2mm;margin-top:2.6mm}}
.opt{{display:inline-flex;align-items:center;gap:1.6mm;border:1px solid var(--line);border-radius:10mm;padding:1.1mm 3mm 1.1mm 2mm;font-size:8.3pt;background:#fff}}
.opt .ar{{font-size:8.4pt;color:var(--mute);padding-left:1.6mm;border-left:1px solid var(--line)}}
.box{{display:inline-block;width:3.2mm;height:3.2mm;border:1.3px solid var(--p);border-radius:.8mm;flex:none}}
.other,.note{{display:flex;align-items:flex-end;gap:2mm;margin-top:2.4mm;font-size:8.3pt;color:var(--mute)}}
.rule{{flex:1;border-bottom:1px solid var(--line);height:4mm}}
.lines span{{display:block;height:7.4mm;border-bottom:1px solid var(--line)}}
.scale{{display:flex;align-items:center;gap:5mm;margin-top:3mm}}
.scale .lo,.scale .hi{{display:flex;flex-direction:column;font-size:7.8pt;color:var(--mute);width:30mm}}
.scale .hi{{text-align:right}}
.scale b{{color:var(--ink);font-weight:600}}
.dots{{display:flex;gap:4.5mm;flex:1;justify-content:center}}
.dot{{width:8.5mm;height:8.5mm;border-radius:50%;border:1.3px solid var(--p2);display:grid;place-items:center;font:700 8.5pt Inter;color:var(--p);background:#fff}}
.nums{{display:grid;grid-template-columns:repeat(6,1fr);gap:2.5mm;margin-top:3mm}}
.cell{{border:1px solid var(--line);border-radius:2.5mm;padding:2mm;background:#fff;display:flex;flex-direction:column}}
.cell .v{{height:9mm;border-bottom:1px dashed var(--line);margin-bottom:1.5mm}}
.cell .en{{font-size:7.4pt;font-weight:600}}
.cell .ar{{font-size:7.6pt;color:var(--mute)}}
.obs{{break-inside:avoid;margin-top:4mm;padding:3.5mm 4mm 2mm;background:var(--soft);border-radius:3mm}}
.obs>.en{{font:700 7.6pt Inter;letter-spacing:.14em;text-transform:uppercase;color:var(--p)}}
.obs>.ar{{float:right;font:700 8.4pt Plex;color:var(--p)}}
.obs .lines span{{border-color:#d6d0f3}}

/* priority grid */
table.pri{{width:100%;border-collapse:separate;border-spacing:0;margin-top:4mm;font-size:8.6pt}}
.pri th{{font:700 7.2pt Inter;letter-spacing:.08em;text-transform:uppercase;color:var(--mute);padding:2mm 1.5mm;border-bottom:1.5px solid var(--ink);text-align:center}}
.pri th small{{display:block;font:700 7.8pt Plex;letter-spacing:0;text-transform:none}}
.pri td{{padding:1.75mm 1.5mm;border-bottom:1px solid #ebe9f4;vertical-align:middle}}
.pri tr:nth-child(even) td{{background:#faf9fe}}
.pri .n{{color:var(--p);font-weight:700;width:7mm}}
.pri .ar{{font-family:Plex;text-align:right}}
.pri .c{{text-align:center;width:15mm}}
.pri .c .box{{width:3.6mm;height:3.6mm}}
.pri .rk{{width:13mm;text-align:center}}
.pri .rk span{{display:inline-block;width:8mm;border-bottom:1px solid var(--line);height:4mm}}

/* speak your mind */
.speak .band{{background:linear-gradient(150deg,#17133a,#2c2470),#17133a}}
.speak .band .num{{font-size:26pt}}
.prompt{{break-inside:avoid;margin-top:4mm}}
.prompt .qn{{background:var(--ink);color:#fff}}
.prompt .lines{{margin-left:14mm}}
.free{{break-before:page}}
.free .ttl{{display:flex;justify-content:space-between;align-items:baseline;margin-top:1.5mm}}
.free .ttl h2{{font-size:20pt}}
.free .ttl span{{font:800 15pt Alexandria}}
.free>.lines{{margin-top:4mm}}
.free>.lines span{{height:8.6mm}}
.free .q{{border:none;padding-bottom:1mm}}
.score{{display:grid;grid-template-columns:repeat(5,1fr);gap:3mm;margin-top:4mm}}
.score div{{border:1px solid var(--line);border-radius:3mm;padding:3mm 2mm;text-align:center;font-size:8pt;line-height:1.6}}
.score div span{{display:block;color:var(--mute);font-family:Plex}}
.thanks{{margin-top:7mm;text-align:center;font:italic 400 20pt Serif;color:var(--p)}}
.thanks span{{display:block;text-align:center;font:700 14pt Alexandria;color:var(--ink);margin-top:1mm}}
'''

# ---------------------------------------------------------------- page
parts = ['''
<div class="cover">
  <span class="ring r1"></span><span class="ring r2"></span><span class="ring r3"></span>
  <div class="in">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div class="brand">TAKEONE<small>SPORTSTECH</small></div>
      <div class="brand" dir="rtl" style="font-family:Alexandria;letter-spacing:0;font-size:11pt">تيك ون<small style="font-family:Plex;letter-spacing:0">للتقنيات الرياضية</small></div>
    </div>
    <h1>What does your<br>club <em>really</em> need?</h1>
    <div class="arT" dir="rtl">ما الذي يحتاجه ناديك فعلاً؟</div>
    <p class="lead">A conversation with club owners, managers and coaches — about how the club runs today, what hurts, and what would make the work easier.</p>
    <p class="leadar" dir="rtl">حوار مع ملاك الأندية ومديريها ومدربيها — عن طريقة إدارة النادي اليوم، وما يُتعبهم، وما الذي يجعل العمل أسهل.</p>
    <div class="card">
      <div class="f"><b>Club name</b><span dir="rtl">اسم النادي</span></div>
      <div class="f"><b>Date</b><span dir="rtl">التاريخ</span></div>
      <div class="f"><b>Person interviewed · role</b><span dir="rtl">الشخص · الصفة</span></div>
      <div class="f"><b>Phone / WhatsApp</b><span dir="rtl">الهاتف / واتساب</span></div>
      <div class="f"><b>Area / branch</b><span dir="rtl">المنطقة / الفرع</span></div>
      <div class="f"><b>Interviewer</b><span dir="rtl">المحاور</span></div>
    </div>
  </div>
</div>''', '''
<div class="guide">
  <div class="kicker">Before you start · قبل أن تبدأ</div>
  <h2>A guide for the visit</h2>
  <div class="arh" dir="rtl">دليل الزيارة</div>
  <div class="two">
    <ol class="steps">
      <li><i>1</i><div><b>Listen, don’t sell.</b>We are here to learn how they work — not to demo or promise anything.</div></li>
      <li><i>2</i><div><b>30–45 minutes.</b>Skip sections that don’t apply. Depth beats coverage.</div></li>
      <li><i>3</i><div><b>Ask for stories.</b>“Tell me about the last time…” gives truer answers than “Do you…?”</div></li>
      <li><i>4</i><div><b>Write their words.</b>Exact phrases and complaints are gold. Use the observation boxes.</div></li>
      <li><i>5</i><div><b>Ask to see things.</b>Their register, a receipt, the WhatsApp group, a grading sheet.</div></li>
      <li><i>6</i><div><b>End open.</b>The “Speak your mind” pages matter most. Don’t rush them.</div></li>
    </ol>
    <ol class="steps" dir="rtl">
      <li><i>١</i><div><b>استمع ولا تبع.</b>نحن هنا لنتعلم طريقة عملهم — لا لنعرض أو نعد بشيء.</div></li>
      <li><i>٢</i><div><b>٣٠–٤٥ دقيقة.</b>تجاوز الأقسام غير المناسبة. العمق أهم من الشمول.</div></li>
      <li><i>٣</i><div><b>اطلب قصصاً.</b>«حدّثني عن آخر مرة…» يعطي إجابات أصدق من «هل تفعلون…؟»</div></li>
      <li><i>٤</i><div><b>اكتب كلماتهم.</b>العبارات والشكاوى كما قيلت كنز. استخدم مربعات الملاحظات.</div></li>
      <li><i>٥</i><div><b>اطلب أن ترى.</b>سجل الأعضاء، إيصال، مجموعة الواتساب، ورقة تقييم.</div></li>
      <li><i>٦</i><div><b>اختم بحوار مفتوح.</b>صفحات «قل رأيك بحرية» هي الأهم. لا تستعجلها.</div></li>
    </ol>
  </div>
  <div class="tip">
    <div><b>Opening line.</b> “We’re building a system for clubs in Bahrain and we want it shaped by the people who actually run them. There are no right answers — tell us how it really is.”</div>
    <div dir="rtl"><b>جملة البداية.</b> «نحن نبني نظاماً للأندية في البحرين، ونريده أن يُصمَّم بآراء من يديرونها فعلاً. لا توجد إجابات صحيحة — أخبرونا كيف هو الواقع.»</div>
  </div>
  <div class="legend">
    <div><b><i class="box"></i> Tick all</b><span dir="rtl">اختر كل ما ينطبق</span></div>
    <div><b><i class="box"></i> Tick one</b><span dir="rtl">اختر واحداً</span></div>
    <div><b><span class="dot">3</span> Circle</b><span dir="rtl">ضع دائرة على رقم</span></div>
    <div><b><span style="color:#d97706">◆</span> Interviewer</b><span dir="rtl">إرشاد للمحاور فقط</span></div>
  </div>
  <div class="consent">
    <div><b>Consent.</b> The club agrees that TAKEONE may keep these notes to design its product and may contact them again. Nothing is published under the club’s name.<br><br>Signature ____________________</div>
    <div dir="rtl"><b>الموافقة.</b> يوافق النادي على احتفاظ تيك ون بهذه الملاحظات لتصميم منتجها، وعلى التواصل معه لاحقاً. لا يُنشر شيء باسم النادي.<br><br>التوقيع ____________________</div>
  </div>
</div>''']

n = 1
for i, s in enumerate(SECTIONS, 1):
    h, n = section(i, s, n)
    parts.append(h)

rows = ''.join(
    f'<tr><td class="n">{j:02d}</td><td>{e(a)}</td><td class="ar" dir="rtl">{e(b)}</td>'
    f'<td class="c"><i class="box"></i></td><td class="c"><i class="box"></i></td><td class="c"><i class="box"></i></td><td class="rk"><span></span></td></tr>'
    for j, (a, b) in enumerate(FEATURES, 1))
parts.append(f'''
<section class="sec">
  {band(f"{len(SECTIONS)+1:02d}", "What matters most", "Read each line aloud. One tick per row — then rank their top 5 (1 = most important).",
        "ما الأهم لديكم", "اقرأ كل سطر بصوت عالٍ. علامة واحدة لكل سطر — ثم رتّب أهم ٥ (١ = الأهم).")}
  <table class="pri">
    <thead><tr><th></th><th style="text-align:left">Feature</th><th style="text-align:right"><small>الميزة</small></th>
      <th>Must<small>ضروري</small></th><th>Nice<small>جيد</small></th><th>No need<small>غير لازم</small></th><th>Top 5<small>الأهم</small></th></tr></thead>
    <tbody>{rows}</tbody>
  </table>
  <div class="other" style="margin:4mm 0 0"><i class="box"></i><span class="en">Something missing from this list</span><span class="rule"></span><span class="ar" dir="rtl">شيء غير موجود في القائمة</span></div>
</section>''')

prompts = ''.join(f'<div class="prompt">{qhead(k, a, b)}{lines(4)}</div>' for k, (a, b) in enumerate(SPEAK, 1))
parts.append(f'''
<section class="sec speak">
  {band("✦", "Speak your mind", "Put the form down and let them talk — these prompts only start the conversation. Write what they say, in their words.",
        "قل رأيك بحرية", "ضع الاستمارة جانباً ودعهم يتحدثون — هذه الأسئلة لبدء الحديث فقط. اكتب ما يقولونه بكلماتهم.")}
  {prompts}
</section>
<section class="free">
  <div class="kicker">Open notes · ملاحظات مفتوحة</div>
  <div class="ttl"><h2>In their own words</h2><span dir="rtl">بكلماتهم</span></div>
  {lines(27)}
</section>
<section class="free">
  <div class="kicker">After the visit — interviewer only · بعد الزيارة — للمحاور فقط</div>
  <div class="ttl"><h2>Visit summary</h2><span dir="rtl">ملخص الزيارة</span></div>
  <div class="q">{qhead(1, "The three biggest pains you heard", "أكبر ثلاث مشكلات سمعتها")}{lines(4)}</div>
  <div class="q">{qhead(2, "Their best quote (word for word)", "أفضل عبارة قالوها (حرفياً)")}{lines(3)}</div>
  <div class="q">{qhead(3, "Features they asked for that we don’t have", "ميزات طلبوها وليست لدينا")}{lines(3)}</div>
  <div class="q">{qhead(4, "Next step agreed", "الخطوة التالية المتفق عليها")}{lines(2)}</div>
  <div class="score">
    <div><b>Interest</b><span>الاهتمام</span>① ② ③ ④ ⑤</div>
    <div><b>Tech comfort</b><span>الراحة مع التقنية</span>① ② ③ ④ ⑤</div>
    <div><b>Urgency</b><span>مدى الإلحاح</span>① ② ③ ④ ⑤</div>
    <div><b>Pilot club?</b><span>نادٍ تجريبي؟</span>Yes ☐ &nbsp; No ☐</div>
    <div><b>Follow-up date</b><span>موعد المتابعة</span>____ / ____</div>
  </div>
  <div class="thanks">Thank you for your time<span dir="rtl">شكراً لوقتكم</span></div>
</section>''')

OUT.write_text('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>TAKEONE — Club Discovery Questionnaire</title>'
               f'<style>{css}</style></head><body>{"".join(parts)}</body></html>', encoding='utf-8')
print(OUT)
