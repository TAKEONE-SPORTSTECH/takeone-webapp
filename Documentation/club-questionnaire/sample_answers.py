"""One FICTIONAL club's answers — illustration only. No real club, person or number."""

SAMPLE = {
 'cover': {'club': 'Falcon Kicks Taekwondo (sample)', 'date': '3 Nov 2026', 'person': 'Owner & head coach',
           'phone': '+973 3x xx xx xx', 'area': 'Riffa', 'interviewer': 'Visitor A'},
 'signature': 'F. K.',

 # 01 The club today
 'q1': {'values': ['140', '4', '1', '1', '7', '18']},
 'q2': {'pick': ['Taekwondo', 'Fitness / Gym'], 'other': 'Kids self-defence'},
 'q3': {'pick': ['Kids under 12', 'Teens 12–17', 'Families / siblings']},
 'q4': {'pick': ['Paper / notebook', 'Excel / Google Sheets', 'WhatsApp groups', 'Instagram']},
 'q5': {'text': 'Tried a gym app last year — too complicated for parents,\nno Arabic, charged per member. Stopped after 2 months.'},
 'q6': {'text': 'The owner, at night after classes. Wife helps with payments.'},
 'obs1': 'Register = blue notebook + Excel on his phone. Two lists never match.',

 # 02 Joining
 'q7': {'pick': ['Walk in', 'WhatsApp', 'Instagram / TikTok', 'Friend referral']},
 'q8': {'pick': ['Name & phone', 'Date of birth', 'CPR / ID copy', 'Guardian details', 'Emergency contact']},
 'q9': {'pick': ['Free trial'], 'note': 'one class, then they must pay'},
 'q10': {'value': 4},
 'q11': {'pick': ['Our staff']},
 'q12': {'text': 'Yes if he can approve each one first. Worried about fake\nnames and parents who never pay after registering.'},
 'obs2': 'Showed a paper form — half the fields empty. Copies CPR by photo on WhatsApp.',

 # 03 Classes
 'q13': {'pick': ['Paper list', 'Coach remembers']},
 'q14': {'pick': ['Monthly fee']},
 'q15': {'pick': ['Sometimes'], 'note': 'Kids 5–7 class is full, no list kept'},
 'q16': {'text': 'He doesn’t notice until the parent stops paying.\nBy then the child already left. "We lose them quietly."'},
 'q17': {'pick': ['Yes'], 'note': 'every summer + Ramadan — no way to record it'},
 'q18': {'text': 'Voice note in the WhatsApp group. Half the parents miss it.'},
 'obs3': 'Attendance sheet for October had 3 classes missing. Coach "forgot".',

 # 04 Money
 'q19': {'pick': ['Cash', 'BenefitPay', 'Bank transfer']},
 'q20': {'pick': ['On request']},
 'q21': {'value': 5, 'note': '"2–3 hours every week"'},
 'q22': {'pick': ['Sibling discount', 'Registration fee', 'Uniform fee']},
 'q23': {'pick': ['Rent', 'Coach salaries', 'Equipment', 'Competition fees']},
 'q24': {'pick': ['Per class']},
 'q25': {'text': 'Who has NOT paid this month. Today: scrolls BenefitPay\nscreenshots in WhatsApp and ticks the notebook.'},
 'obs4': 'Asked "how many owe you now?" — took 6 minutes, answer "around 20".',

 # 05 Communication
 'q26': {'pick': ['Payment reminders', 'Schedule changes', 'Cancelled class', 'Belt tests', 'Holidays']},
 'q27': {'pick': ['WhatsApp']},
 'q28': {'pick': ['Both']},
 'q29': {'value': 4},
 'q30': {'text': 'Payment reminders to parents who already paid in cash.'},
 'obs5': '5 WhatsApp groups. Owner answers ~40 messages a day.',

 # 06 Progress
 'q31': {'pick': ['Yes'], 'note': 'every 3 months'},
 'q32': {'text': 'Head coach decides who is ready, 10 BD fee, certificates\nprinted in a shop in Riffa, belts ordered from Dubai.'},
 'q33': {'pick': ['Belt / level', 'Attendance history', 'Medals & results', 'Coach notes']},
 'q34': {'value': 5},
 'obs6': 'Grading sheet is a Word file. No history of who passed when.',

 # 07 Competitions
 'q35': {'pick': ['Sometimes']},
 'q36': {'pick': ['No']},
 'q37': {'pick': ['Collecting entries & fees', 'Weights & categories']},
 'q38': {'value': 3},
 'q39': {'text': 'Taekwondo federation — entry list in Excel + copies of CPR.'},
 'obs7': '',

 # 08 Staff
 'q40': {'pick': ['Owner', 'Coaches', 'Parents']},
 'q41': {'text': 'Coaches: attendance + belt notes only.\nNever see fees or who paid. Cannot delete members.'},
 'q42': {'pick': ['Single owner']},
 'q43': {'pick': ['Yes'], 'note': 'doboks + belts only'},
 'obs8': 'Strong on privacy: "the money is between me and the parent".',

 # 09 Devices & trust
 'q44': {'pick': ['iPhone', 'Tablet at reception']},
 'q45': {'pick': ['Moving old data', 'Parents won’t use it']},
 'q46': {'pick': ['Monthly']},
 'q47': {'text': '15–25 BD a month if it really stops the chasing.'},
 'obs9': 'Uses his phone in Arabic. Wife uses English.',

 # 10 Priority grid
 'grid': {1: 'must', 2: 'must', 3: 'nice', 4: 'must', 5: 'must', 6: 'must', 7: 'nice', 8: 'must', 9: 'must',
          10: 'nice', 11: 'must', 12: 'nice', 13: 'must', 14: 'must', 15: 'nice', 16: 'nice', 17: 'no',
          18: 'nice', 19: 'must', 20: 'must', 21: 'nice', 22: 'no'},
 'top5': {5: 1, 8: 2, 4: 3, 9: 4, 13: 5},
 'grid_other': 'Reminder when a belt test is due',

 # Speak your mind
 'speak1': 'Knowing who paid without opening WhatsApp.\n"If I could see one red list of who owes me, I would sleep."',
 'speak2': 'Last Ramadan: 30 kids paused, nobody wrote it down.\nAfter Eid he charged 6 families who had paused — they were angry,\none family left. He paid back by hand.',
 'speak3': 'Parents: "why did I get the reminder, I paid".\n"When is my son’s belt test?" — asked again and again.',
 'speak4': 'Parent opens the app, sees: paid ✓, next class, belt, attendance.\nHe only gets a message when something needs HIM.',
 'speak5': 'Ask about the coaches’ pay — calculating per class at month end\ntakes him a full evening.',

 'free': ('— Lost about 1 in 10 kids last year "quietly" — no follow-up.\n'
          '— Will NOT use anything that sends parents messages he can’t control.\n'
          '— Sibling discount is done in his head — every month a different number.\n'
          '— Wants the coach to mark attendance on the tablet at the door.\n'
          '— Federation entry list took 2 evenings in Excel.\n'
          '— Would pay yearly if the first month proves it works.\n'
          '«أبي شي بسيط، الوالدين ما يبون تطبيق معقد»\n'
          '— Likes that Arabic + English sit side by side.\n'
          '— Asked if he can import his Excel list — 140 names.'),
 'summary': {
     'pains': '1. Chasing payments (2–3 h / week, never sure who paid)\n2. Pauses / freezes not recorded → wrong charges\n3. Attendance on paper → kids drop out unnoticed',
     'quote': '"If I could see one red list of who owes me, I would sleep."',
     'missing': 'Freeze / pause · sibling discount · coach pay per class ·\nbelt-test reminders',
     'next': 'Show him a demo with his own Excel list — week of 10 Nov',
 },
 'scores': {'interest': 5, 'tech': 3, 'urgency': 4, 'pilot': True, 'followup': '10 / 11'},
}
