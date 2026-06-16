<?php
/**
 * Script: add_gender.php
 * Reads the Emperor Taekwondo Academy responses xlsx,
 * infers gender from the first name (Arabic + English),
 * inserts a Gender column after the last name column (D),
 * and saves a new file.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ──────────────────────────────────────────────
// Normalize helpers
// ──────────────────────────────────────────────
function normalizeArabic(string $name): string
{
    $name = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $name); // diacritics
    $name = str_replace('ـ', '', $name);                                // tatweel
    $name = preg_replace('/[أإآٱ]/u', 'ا', $name);                    // alef variants
    $name = str_replace('ة', 'ه', $name);                              // ta marbuta → ha
    $name = str_replace(['ى', 'ی'], 'ي', $name);                       // alef maqsura + Farsi ya → ya
    $name = str_replace('ّ', '', $name);                                // shadda
    // Strip leading non-letter chars (e.g. underscores, commas)
    $name = preg_replace('/^[^ء-ي\w]+/u', '', $name);
    return mb_strtolower(trim($name), 'UTF-8');
}

function normEn(string $s): string
{
    return mb_strtolower(trim($s), 'UTF-8');
}

// ──────────────────────────────────────────────
// Gender lookup tables — Arabic names
// ──────────────────────────────────────────────
$arabicMale = [
    // A
    'عيسى','احمد','أحمد','ادم','آدم','ابراهيم','إبراهيم','إسرافيل','إيليا',
    'البراء','الحسن','السيد','السيدمحمد','امير','أمير','امين','أمين','أيمن','ايمن',
    'أسامه','اسامة','انس','أنس','اكبر',
    // ب
    'بدر','بلال','بسام','بدر',
    // ت
    'تركي','تميم',
    // ج
    'جاسم','جابر','جمال','جعفر','حمود',
    // ح
    'حسن','حسين','حسان','حمد','حمزه','حمزة','حيدر','حبيب',
    // خ
    'خالد','خليل','خليفة','خليفه','خميس',
    // ر
    'راشد','رضا',
    // س
    'سلطان','سلمان','سعيد','سعود','سجاد','سيف','سيد',
    // ص
    'صالح','صهيب',
    // ط
    'طارق','طلال','طه',
    // ع
    'عادل','عبدالله','عبد الله','عبدالرحمن','عبد الرحمن','عبدالعزيز','عبد العزيز',
    'عبدالنبي','عبد النبي','عبدالسلام','عبد السلام','عبدالكريم','عبد الكريم',
    'عبدالحميد','عبد الحميد','عبدالمجيد','عبد المجيد','عبدالوهاب','عبد الوهاب',
    'عبدالباري','عبدالغفور','عبدالودود','عبدالجليل','عبدالملك','عباس','عثمان',
    'عدنان','عزيز','علاء','علي','عمار','عمر','عقيل','عيسى',
    // غ
    'غازي',
    // ف
    'فيصل','فهد','فياض',
    // ق
    'قاسم',
    // ك
    'كريم','كرم',
    // ل
    'لؤي','ليث',
    // م
    'ماجد','مبارك','محمد','محمود','مروان','مشاري','مشعل','معاذ','مناف','منتظر',
    'مهدي','مهند','موسى','مصطفى','مصطفا',
    // ن
    'ناصر','نايف','نجيب','نواف','نوح',
    // و
    'وليد',
    // ه
    'هادي','هشام','هود',
    // ي
    'ياسر','يعقوب','يوسف',
    // extra Arabic-only
    'زين','سالم','متعب','دعيج','حسين',
];

$arabicFemale = [
    // آ
    'آمنة','آمنه',
    // ا
    'ابرار','أبرار','اروى','اسيل','الجازي','الجوري','الدانة','الريم','المها',
    'الهنوف','امنه','امينه','أمينة','ايثار','ايلاف','ايمان','إيمان',
    // ب
    'بنين','بدريه',
    // ث
    'ثاجبة',
    // ج
    'جمانه','جنى','جواهر','جود','جيلان',
    // ح
    'حليمة','حليمه','حنان','حصه','حصة','حلا',
    // خ
    'خديجه','خديجة',
    // د
    'دانه','دانية','دلال',
    // ر
    'رتاج','ريناد','رباب','رقية','رقيه','رنيم','روان','ريم','ريان',
    // ز
    'زهراء','زهور','زينب',
    // س
    'سارة','ساره','سلمى','سلما','سلوى','سمية',
    // ش
    'شهد','شموخ','شيخة','شيخه',
    // ض
    'ضحى',
    // ع
    'عائشة','عائشه','عايشة','عايشه','عالية','عبير','أسيل','اسيل',
    // غ
    'غزلان','غلا',
    // ف
    'فاطمة','فاطمه','فجر','فرح','فوزيه',
    // ج
    'جور','جوري',
    // ك
    'كلثم','كلثوم',
    // ل
    'لجين','لطيفة','لطيفه','لمار','لمياء','لميا','لولوة','لولو','ليا','ليلى','ليلا',
    // م
    'ماريا','ماريه','مريم','مروة','مروه','مزنه','ملاك','منال','منيره','منى','مها',
    'مهرة',
    // ن
    'نجود','نسيبة','نور','نورة','نوره','نوف',
    // ه
    'هبه','هيا','هنوف','الهنوف',
    // و
    'وسن','وفاء','وفا',
    // ي
    'ياسمين',
    // others
    'النور','الجازي','سحاب','نبأ','جنه','جنة','إلهام','الهام','بشرى','بشرا',
    'ختام','خلود','رهف','سما','رانية','رانيه','خيال','حور',
    // common Gulf female names
    'شيماء','شيما','سهيلة','سهيله','حنان','منيرة','بتول','كوثر','زهرا',
    'فيروز','هاجر','حفصة','حفصه','لبنى','لبنا','إسراء','اسراء','ابتسام',
    'أسماء','اسماء','نهى','سناء','بسمة','بسمه',
    // extra
    'ليان',
];

// ──────────────────────────────────────────────
// Gender lookup tables — English / transliterated
// ──────────────────────────────────────────────
$englishMale = [
    // A
    'affan','adam','adnan','ahmad','ahmed','ahad','ali','alaa','ameer','ammar',
    'anas','aser','abdulla','abdullah','abdulaziz','abdulrahman','abdulnabi',
    'abdulsalam','abdulkarim','abdulhamid','abdulmajid','abdulwahab','abdulbari',
    'abdulgafoor','abduljalil','abdulmalik','abdullatif','ayman','abu',
    // B
    'bader','badr','bader','bilal','bisam','basil','baha',
    // D
    'daraab','dilmun','duaij','dueij',
    // E
    'ebrahim','eyad','esa','isa',
    // F
    'fahad','faisal','fares','faraj','fuad','fouad','fawzi',
    // G
    'ghassan','georgios','ghazi',
    // H
    'habib','haider','hamad','hamza','hamdan','hasan','hassan','hashim','husain',
    'hussain','hussam','hisham','hadi','hud',
    // I
    'ibrahim','isa','israfeel','ilya',
    // J
    'jaber','jarrah','jassim','jasim',
    // K
    'khalid','khaled','khalifa','khalil','khamis','karim','karam',
    // L
    'louai','laith',
    // M
    'mahmood','mahmoud','majed','mubarak','muhanna','murtadha','mutaib',
    'muammad','muhammad','muslim','mohamad','mohammad','mohammed','mohamed',
    'marwan','mishari','meshal','munir','mansour','mustafa','mazen','murtada',
    'mohd',
    // N
    'najeeb','naser','nasser','nawaf','nayef','nayaf','nooh','noah',
    // O
    'omar',
    // R
    'rashid','rashed','rayyan','redha','riyad','raed','rayan',
    // S
    'saad','saif','saleh','salem','salman','saoud','saqer','saud','sayed',
    'shaheer','sultan','suleiman','sami','saqer','sajjad',
    // T
    'tariq','talal','taha','tom','turki','tamim',
    // W
    'waleed','walid',
    // Y
    'yaqoob','yousef','yousif','yousuf','yusuf','yasser','yazid','zaid','zayed',
    // Z
    'zain','zayn','zayed',
    // extra
    'hajer','hajar','hajer','mutaib','duaij','salem',
    'abdul aziz','abdulrahman',
];

$englishFemale = [
    // A
    'aisha','aishah','ameena','amina','amna','aseel','asel','ayesha','alghala',
    'aljawhara','alyah','amal','arwa','afrah','abrar',
    // B
    'budoor',
    // D
    'deema','dhuwa','daniha',
    // E
    'eman',
    // F
    'fajer','farah','fatema','fatima','fatimah','fawzia',
    // G
    'ghala',
    // H
    'hafsa','hanan','haya','hessa','hessa',
    // J
    'jameela','jawaher','joanna','joury','jood','jouri','juri',
    // K
    'kaltham',
    // L
    'latifa','lulwa','lina',
    // M
    'maria','mariam','marwa','maryam','mashael','mayar','mona','muna','muneera',
    'myar',
    // N
    'noof','noora','nouf','nada',
    // R
    'rania','reem','reema','reham','rose','ruqaya','rawan',
    // S
    'sahar','sara','sarah','salwa','shaima','sharifa','sharıfa','suhaila',
    // T
    'talia',
    // W
    'wafa',
    // Y
    'yasmina','yasmin',
    // Z
    'zainab',
    // extra
    'hajer','hajar',
];

// ──────────────────────────────────────────────
// Build fast lookup sets
// ──────────────────────────────────────────────
$maleLookup   = [];
$femaleLookup = [];

foreach ($arabicMale as $n) {
    $maleLookup[normalizeArabic($n)] = true;
}
foreach ($arabicFemale as $n) {
    $femaleLookup[normalizeArabic($n)] = true;
}
foreach ($englishMale as $n) {
    $maleLookup[normEn($n)] = true;
}
foreach ($englishFemale as $n) {
    $femaleLookup[normEn($n)] = true;
}

// ──────────────────────────────────────────────
// Detect gender from raw first-name cell value
// Returns 'Male', 'Female', or 'Unknown'
// ──────────────────────────────────────────────
function detectGender(string $raw, array &$maleLookup, array &$femaleLookup): string
{
    // Strip possessive 's and leading non-letter chars
    $raw = preg_replace("/['\u{2019}]s$/u", '', $raw);
    $raw = preg_replace('/^[^a-zA-Z\x{0600}-\x{06FF}]+/u', '', $raw);
    $tokens = preg_split('/[\s\/\-،,]+/u', trim($raw));

    foreach ($tokens as $token) {
        $token = trim($token);
        if (mb_strlen($token, 'UTF-8') < 2) continue;

        $normAr = normalizeArabic($token);
        $normEn = normEn($token);

        if (isset($maleLookup[$normAr]) || isset($maleLookup[$normEn])) {
            return 'Male';
        }
        if (isset($femaleLookup[$normAr]) || isset($femaleLookup[$normEn])) {
            return 'Female';
        }
    }

    // Try whole normalized value
    $full = normalizeArabic($raw);
    if (isset($maleLookup[$full]))   return 'Male';
    if (isset($femaleLookup[$full])) return 'Female';

    return 'Unknown';
}

// ──────────────────────────────────────────────
// Read source xlsx
// ──────────────────────────────────────────────
$srcPath = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses).xlsx';
$dstPath = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses) - With Gender.xlsx';

echo "Reading source file...\n";
$spreadsheet = IOFactory::load($srcPath);
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestDataRow();

echo "Rows: $highestRow\n";

// Insert Gender column as E (shifts old E onward)
echo "Inserting Gender column...\n";
$sheet->insertNewColumnBefore('E', 1);
$sheet->setCellValue('E1', 'Gender الجنس');
$sheet->getStyle('E1')->applyFromArray(
    $sheet->getStyle('B1')->exportArray()
);

// ──────────────────────────────────────────────
// Fill gender per row
// ──────────────────────────────────────────────
$stats = ['Male' => 0, 'Female' => 0, 'Unknown' => 0];
$unknownList = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $firstName = trim((string) $sheet->getCell('B' . $row)->getValue());
    if ($firstName === '') continue;

    $gender = detectGender($firstName, $maleLookup, $femaleLookup);
    $sheet->setCellValue('E' . $row, $gender);
    $stats[$gender]++;

    if ($gender === 'Unknown') {
        $unknownList[] = $firstName;
    }
}

$sheet->getColumnDimension('E')->setAutoSize(true);

// ──────────────────────────────────────────────
// Save
// ──────────────────────────────────────────────
echo "Saving...\n";
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($dstPath);

echo "\nResults:\n";
echo "Male:    {$stats['Male']}\n";
echo "Female:  {$stats['Female']}\n";
echo "Unknown: {$stats['Unknown']}\n";
echo "Total:   " . array_sum($stats) . "\n";

if (count($unknownList)) {
    echo "\nUnknown names (" . count($unknownList) . "):\n";
    $unique = array_unique($unknownList);
    sort($unique);
    foreach ($unique as $n) echo "  - $n\n";
}
