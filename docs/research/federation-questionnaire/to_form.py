"""Turn the paper questionnaire (content.py) into a Forms schema JSON."""
import json, re, sys
from content import SECTIONS, FEATURES, SPEAK, YESNO, STATUS

def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')[:40]
def opts(pairs): return [{'value': slug(a) or f'o{i}', 'label': a, 'label_ar': b} for i, (a, b) in enumerate(pairs)]

pages = [{
  'id': 'visit', 'title': 'The visit', 'title_ar': 'الزيارة', 'private': True,
  'intro': 'Who you met, and where. Only the TAKEONE team sees this page.',
  'intro_ar': 'من قابلت وأين. هذه الصفحة يراها فريق تيك ون فقط.',
  'fields': [
    {'key': 'cover_club', 'type': 'text', 'label': 'Federation', 'label_ar': 'الاتحاد', 'required': True},
    {'key': 'cover_person', 'type': 'text', 'label': 'Person interviewed · role', 'label_ar': 'الشخص · الصفة'},
    {'key': 'cover_phone', 'type': 'phone', 'label': 'Phone / WhatsApp', 'label_ar': 'الهاتف / واتساب'},
    {'key': 'cover_area', 'type': 'text', 'label': 'Department / committee', 'label_ar': 'الإدارة / اللجنة'},
    {'key': 'cover_interviewer', 'type': 'text', 'label': 'Interviewer', 'label_ar': 'المحاور'},
    {'key': 'consent', 'type': 'checkbox', 'required': True,
     'label': 'The federation agrees that TAKEONE may keep these notes to design its product and may contact them again. Nothing is published under the federation’s name.',
     'label_ar': 'يوافق الاتحاد على احتفاظ تيك ون بهذه الملاحظات لتصميم منتجها، وعلى التواصل معه لاحقاً. لا يُنشر شيء باسم الاتحاد.'},
  ]}]

n = 1
for i, (en, ar, ien, iar, probe, qs) in enumerate(SECTIONS, 1):
    fields = []
    for q in qs:
        k, key = q[0], f'q{n}'
        f = {'key': key, 'label': q[1], 'label_ar': q[2]}
        if k == 'text':
            f.update(type='textarea', rows=q[3])
        elif k == 'choice':
            f.update(type='checkboxes', options=opts(q[3]), other=bool(q[4]))
        elif k == 'one':
            f.update(type='radio', options=opts(q[3]), note=True)
        elif k == 'scale':
            f.update(type='scale', min=1, max=5, low=q[3][0], low_ar=q[3][1], high=q[4][0], high_ar=q[4][1], note=True)
        elif k == 'num':
            f.update(type='numbers', rows=opts(q[3]))
        fields.append(f); n += 1
    fields.append({'key': f'obs{i}', 'type': 'note', 'private': True,
                   'label': 'Interviewer observations', 'label_ar': 'ملاحظات المحاور',
                   'help': probe[0], 'help_ar': probe[1]})
    pages.append({'id': f's{i}', 'title': en, 'title_ar': ar, 'intro': ien, 'intro_ar': iar, 'fields': fields})

feat = opts(FEATURES)
for j, o in enumerate(feat, 1): o['value'] = f'f{j:02d}'
pages.append({'id': 'priorities', 'title': 'What matters most', 'title_ar': 'ما الأهم لديكم',
  'intro': 'One answer per line, then pick the five that matter most.', 'intro_ar': 'إجابة واحدة لكل سطر، ثم اختر الخمسة الأهم.',
  'fields': [
    {'key': 'grid', 'type': 'grid', 'required': True, 'label': 'For each one: must have, nice to have, or not needed?',
     'label_ar': 'لكل ميزة: ضرورية، جيدة، أم غير مطلوبة؟', 'rows': feat,
     'columns': [{'value': 'must', 'label': 'Must', 'label_ar': 'ضرورية'}, {'value': 'nice', 'label': 'Nice', 'label_ar': 'جيدة'},
                 {'value': 'no', 'label': 'No need', 'label_ar': 'غير لازمة'}]},
    {'key': 'top5', 'type': 'rank', 'max': 5, 'from': 'grid', 'fromValue': 'must', 'rows': feat,
     'label': 'Your top 5, most important first', 'label_ar': 'أهم خمسة لديكم، الأهم أولاً',
     'help': 'Tap in order. Tap again to remove.', 'help_ar': 'اضغط بالترتيب. اضغط مرة أخرى للإزالة.'},
    {'key': 'grid_other', 'type': 'text', 'label': 'Something missing from this list?', 'label_ar': 'شيء غير موجود في القائمة؟'},
  ]})
pages.append({'id': 'speak', 'title': 'Speak your mind', 'title_ar': 'قل رأيك بحرية',
  'intro': 'In your own words. These only start the conversation.', 'intro_ar': 'بكلماتكم. هذه الأسئلة لبدء الحديث فقط.',
  'fields': [{'key': f'speak{k}', 'type': 'textarea', 'rows': 4, 'label': a, 'label_ar': b} for k, (a, b) in enumerate(SPEAK, 1)]
          + [{'key': 'free', 'type': 'textarea', 'rows': 8, 'label': 'Anything else, in their own words', 'label_ar': 'أي شيء آخر، بكلماتهم'}]})
pages.append({'id': 'summary', 'title': 'Visit summary', 'title_ar': 'ملخص الزيارة', 'private': True,
  'intro': 'After the visit. Only the TAKEONE team sees this page.', 'intro_ar': 'بعد الزيارة. هذه الصفحة يراها فريق تيك ون فقط.',
  'fields': [
    {'key': 'sum_pains', 'type': 'textarea', 'rows': 4, 'label': 'The three biggest pains you heard', 'label_ar': 'أكبر ثلاث مشكلات سمعتها'},
    {'key': 'sum_quote', 'type': 'textarea', 'rows': 2, 'label': 'Their best quote, word for word', 'label_ar': 'أفضل عبارة قالوها، حرفياً'},
    {'key': 'sum_missing', 'type': 'textarea', 'rows': 3, 'label': 'Features they asked for that we don’t have', 'label_ar': 'ميزات طلبوها وليست لدينا'},
    {'key': 'sum_next', 'type': 'text', 'label': 'Next step agreed', 'label_ar': 'الخطوة التالية المتفق عليها'},
    {'key': 'score_interest', 'type': 'scale', 'min': 1, 'max': 5, 'label': 'Interest', 'label_ar': 'الاهتمام', 'low': 'Low', 'low_ar': 'منخفض', 'high': 'High', 'high_ar': 'مرتفع'},
    {'key': 'score_tech', 'type': 'scale', 'min': 1, 'max': 5, 'label': 'Comfort with technology', 'label_ar': 'الراحة مع التقنية', 'low': 'Low', 'low_ar': 'منخفض', 'high': 'High', 'high_ar': 'مرتفع'},
    {'key': 'score_urgency', 'type': 'scale', 'min': 1, 'max': 5, 'label': 'Urgency', 'label_ar': 'مدى الإلحاح', 'low': 'Low', 'low_ar': 'منخفض', 'high': 'High', 'high_ar': 'مرتفع'},
    {'key': 'pilot', 'type': 'radio', 'label': 'Pilot federation?', 'label_ar': 'اتحاد تجريبي؟', 'options': opts(YESNO[:2])},
    {'key': 'followup', 'type': 'date', 'label': 'Follow-up date', 'label_ar': 'موعد المتابعة'},
  ]})
for p in pages:
    for f in p['fields']: f['id'] = f['key']

form = {
  'title': 'What does your federation really need?', 'title_ar': 'ما الذي يحتاجه اتحادكم فعلاً؟',
  'description': 'A conversation with federation presidents, secretaries general, technical directors, referees and staff about how the federation runs today, what slows it down, and what would make the work easier.',
  'description_ar': 'حوار مع رؤساء الاتحادات وأمنائها العامين ومديريها الفنيين والحكام والموظفين عن طريقة إدارة الاتحاد اليوم، وما يُبطئ العمل، وما الذي يجعله أسهل.',
  'type': 'survey',
  'settings': {'layout': 'paged', 'bilingual': True, 'interviewer': True,
               'successMessage': 'Thank you for your time.', 'successMessage_ar': 'شكراً لوقتكم.'},
  'schema': {'pages': pages},
}
json.dump(form, open(sys.argv[1], 'w'), ensure_ascii=False, indent=1)
print(len(pages), 'pages', sum(len(p['fields']) for p in pages), 'fields')
