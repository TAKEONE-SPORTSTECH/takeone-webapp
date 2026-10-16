<?php

/*
 * The registration form (App\Events\Support\RegistrationFormPdf) — the blank
 * entry form an organiser prints and hands out.
 *
 * The form is BILINGUAL by design: every label is printed in English AND
 * Arabic side by side, whatever language the organiser reads the site in. The
 * view reads this file with an explicit locale (`__('…', [], 'en')` and
 * `'ar'`), so only en and ar exist; the menu row that offers it lives in
 * events.php with the rest of the page's strings.
 */
return [
    'eyebrow' => 'Tournament Application · Entry Form',
    'title' => 'Tournament',
    'terms_title' => 'Terms & Declaration',

    'photo' => 'Photo',
    'photo_sub' => '3 × 4 · recent',

    's_applicant' => 'Applicant information',
    'name_en' => 'Full name — English',
    'name_en_hint' => 'as it will be printed on your certificate',
    'name_ar' => 'Full name — Arabic',
    'name_ar_hint' => '',
    'given' => 'Given name',
    'middle' => 'Middle name',
    'surname' => 'Surname',
    'dob' => 'Date of birth',
    'gender' => 'Gender',
    'male' => 'Male',
    'female' => 'Female',
    'nationality' => 'Nationality',
    'id_no' => 'CPR / Passport no.',
    'app_date' => 'Application date',
    'style' => 'Style',
    'rank' => 'Rank',
    'rank_hint' => 'Dan · Kyu',
    'club' => 'Club / Team',
    'height' => 'Height',
    'weight' => 'Weight',
    'mobile' => 'Mobile',
    'phone' => 'Phone',
    'emergency' => 'Emergency contact',
    'relation' => 'Relation',
    'their_mobile' => 'Their mobile',

    's_entry' => 'Entry',
    'tick_all' => 'tick all that apply',
    'division' => 'Division',
    'division_hint' => 'filled by the organiser',
    'age_group' => 'Age group',
    'weight_class' => 'Weight category',

    's_requirements' => 'Participants must',
    'req_1' => 'Wear <b>full personal protective equipment</b>.',
    'req_2' => 'Attach <b>one recent photo</b> for media and advertising.',
    'req_3' => 'Be <b>free of any disease</b> that could infect others.',
    'req_4' => 'Write the name clearly, <b>exactly as on the certificate</b> — given, middle, surname.',
    'req_5' => 'Tournament fees are <b>non-refundable</b>.',

    's_terms' => 'General terms · Disclaimer',
    'term_1' => 'Members must comply with the tournament <b>rules and regulations</b> and respect the referees\' decisions.',
    'term_2' => 'Members must always respect the <b>morals of the sport</b>.',
    'term_3' => 'Participants <b>under 18</b> must attach a passport copy.',
    'term_4' => 'Participants must pay the <b>participation fee in advance</b>.',
    'term_5' => 'Participants must bring the <b>full protective equipment</b> required.',
    'term_6' => 'The member and their parents are <b>solely responsible</b> for not disclosing any health condition that might endanger the member.',
    'term_7' => 'The member and/or their parents bear the <b>cost of treating any injury</b> resulting from the tournament.',

    's_declaration' => 'Participant declaration',
    'declaration' => 'I, the undersigned, declare that <b>I have read and agree to all the terms and conditions</b> of the tournament.',
    'name' => 'Name',
    'signature' => 'Signature',
    'guardian' => 'Parent / guardian',

    's_office' => 'Tournament management',
    'office_only' => 'Office use only',
    'received' => 'Received',
    'fee_paid' => 'Fee paid',
    'passport' => 'Passport copy',
    'equipment' => 'Equipment',
    'receipt' => 'Receipt no.',
    'weigh_in' => 'Weigh-in',
    'received_by' => 'Received by',
    'date' => 'Date',
    'stamp' => 'Stamp',
    'stamp_approval' => 'Stamp & approval',

    'fee' => 'Entry fee :amount',
    'fees_advance' => 'Fees are paid in advance',
    'fees_nonrefundable' => 'Fees are non-refundable',
    'entry_no' => 'Entry no.',
];
