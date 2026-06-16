<?php
/**
 * Script: import_eta_members.php
 * Imports Emperor Taekwondo Academy form responses into the TAKEONE platform.
 * Run: php scripts/import_eta_members.php [--dry-run]
 *
 * Column map (after gender column insertion):
 *  B(2)  = First name   C(3) = Middle name  D(4) = Last name
 *  E(5)  = Gender       F(6) = Phone         G(7) = Emergency 1
 *  H(8)  = CPR/ID       I(9) = Email         J(10)= Health condition
 *  K(11) = Height       L(12)= Weight        T(20)= Emergency 2
 *  U(21) = Date of Birth
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

// ── Config ────────────────────────────────────────────────────────────────
$CLUB_SLUG  = 'eta';
$SOURCE     = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses) - With Gender.xlsx';
$DRY_RUN    = in_array('--dry-run', $argv ?? []);
$DEFAULT_CC = '+973'; // Bahrain calling code

// ── Sanity checks ─────────────────────────────────────────────────────────
$club = Tenant::where('slug', $CLUB_SLUG)->first();
if (!$club) {
    die("Club '$CLUB_SLUG' not found.\n");
}
echo "Club: [{$club->id}] {$club->slug}\n";
echo "Dry-run: " . ($DRY_RUN ? 'YES' : 'NO') . "\n\n";

// ── Load xlsx ─────────────────────────────────────────────────────────────
echo "Loading spreadsheet...\n";
$spreadsheet = IOFactory::load($SOURCE);
$sheet       = $spreadsheet->getActiveSheet();
$highestRow  = $sheet->getHighestDataRow();
echo "Total rows (inc. header): $highestRow\n\n";

// ── Column index constants ─────────────────────────────────────────────────
const COL_FIRST  = 2;   // B
const COL_MIDDLE = 3;   // C
const COL_LAST   = 4;   // D
const COL_GENDER = 5;   // E  (inserted by add_gender.php)
const COL_PHONE  = 6;   // F
const COL_EMERG1 = 7;   // G
const COL_CPR    = 8;   // H
const COL_EMAIL  = 9;   // I
const COL_HEALTH = 10;  // J
const COL_HEIGHT = 11;  // K
const COL_WEIGHT = 12;  // L
const COL_EMERG2 = 20;  // T
const COL_DOB    = 21;  // U

// ── Helpers ────────────────────────────────────────────────────────────────

function cell(object $sheet, int $col, int $row): string
{
    $letter = Coordinate::stringFromColumnIndex($col);
    return trim((string) $sheet->getCell($letter . $row)->getValue());
}

function cellFormatted(object $sheet, int $col, int $row): string
{
    $letter = Coordinate::stringFromColumnIndex($col);
    return trim((string) $sheet->getCell($letter . $row)->getFormattedValue());
}

function parsePhone(string $raw, string $defaultCode): array
{
    $raw = preg_replace('/[\s\-\(\)\.]+/', '', $raw);
    if ($raw === '') return ['code' => $defaultCode, 'number' => ''];

    // Already has country code as +XXX or 00XXX
    if (preg_match('/^(\+\d{1,4})(\d{6,})$/', $raw, $m)) {
        return ['code' => $m[1], 'number' => $m[2]];
    }
    if (preg_match('/^(00\d{1,4})(\d{6,})$/', $raw, $m)) {
        return ['code' => '+' . ltrim($m[1], '0'), 'number' => $m[2]];
    }
    // 8-digit Bahrain mobile starting with 3 or 6 (no country prefix stored)
    return ['code' => $defaultCode, 'number' => ltrim($raw, '0')];
}

function parseDob(mixed $raw): ?string
{
    if ($raw === null || $raw === '') return null;
    // Excel serial date (numeric)
    if (is_numeric($raw) && $raw > 1000) {
        try {
            return XlDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        } catch (Throwable) {}
    }
    // String date
    try {
        return \Carbon\Carbon::parse((string) $raw)->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function cleanHealth(string $raw): string
{
    // Filter out "no condition" equivalents
    $lower = mb_strtolower($raw);
    $noConditionPhrases = ['no condition', 'لايوجد', 'لا يوجد', 'none', 'n/a', 'na', 'nil', 'nothing'];
    foreach ($noConditionPhrases as $phrase) {
        if (str_contains($lower, $phrase)) return '';
    }
    return $raw;
}

// ── Stats ──────────────────────────────────────────────────────────────────
$imported        = 0;
$alreadyMember   = 0;
$skippedMissing  = 0;
$skippedBadGender = 0;
$errors          = [];

// ── Import loop ────────────────────────────────────────────────────────────
for ($row = 2; $row <= $highestRow; $row++) {
    $firstName = cell($sheet, COL_FIRST,  $row);
    $midName   = cell($sheet, COL_MIDDLE, $row);
    $lastName  = cell($sheet, COL_LAST,   $row);
    $gender    = cell($sheet, COL_GENDER, $row);
    $rawPhone  = cell($sheet, COL_PHONE,  $row);
    $rawEmerg1 = cell($sheet, COL_EMERG1, $row);
    $rawEmerg2 = cell($sheet, COL_EMERG2, $row);
    $rawCpr    = cell($sheet, COL_CPR,    $row);
    $email     = strtolower(cell($sheet, COL_EMAIL,  $row));
    $health    = cleanHealth(cell($sheet, COL_HEALTH, $row));
    $heightRaw = cell($sheet, COL_HEIGHT, $row);
    $weightRaw = cell($sheet, COL_WEIGHT, $row);

    // DOB uses formatted value because it might be a date-formatted cell
    $dobLetter = Coordinate::stringFromColumnIndex(COL_DOB);
    $dobCell   = $sheet->getCell($dobLetter . $row);
    $dobRaw    = $dobCell->getValue();
    $dob       = parseDob($dobRaw);

    // Skip fully empty rows
    if ($firstName === '' && $lastName === '') continue;

    // Require first + last name
    if ($firstName === '' || $lastName === '') {
        $skippedMissing++;
        $errors[] = "Row $row: missing first or last name [{$firstName}] [{$lastName}]";
        continue;
    }

    // Validate gender
    $gender = ucfirst(strtolower($gender));
    if (!in_array($gender, ['Male', 'Female'])) {
        $skippedBadGender++;
        $errors[] = "Row $row: unknown gender [$gender] for $firstName $lastName";
        continue;
    }

    // Build full name (trim bilingual if needed — keep as-is, the DB stores it)
    $fullName = trim(implode(' ', array_filter([$firstName, $midName, $lastName])));

    // Parse phone
    $phone     = parsePhone($rawPhone, $DEFAULT_CC);
    $emerg1    = parsePhone($rawEmerg1, $DEFAULT_CC);
    $emerg2    = parsePhone($rawEmerg2, $DEFAULT_CC);

    // CPR as string
    $cpr = $rawCpr !== '' ? (string)(int)$rawCpr : null;

    // ── Duplicate detection ─────────────────────────────────────────────
    // 1. By email
    $existing = null;
    if ($email !== '') {
        $existing = User::where('email', $email)->first();
    }
    // 2. By CPR (stored in addresses JSON)
    // (No dedicated CPR field in User model — we skip CPR dedup for now)

    if ($existing) {
        // User exists — just ensure membership
        if (!$DRY_RUN) {
            Membership::firstOrCreate(
                ['tenant_id' => $club->id, 'user_id' => $existing->id],
                ['status' => 'active']
            );
        }
        $alreadyMember++;
        continue;
    }

    // ── Create new user ─────────────────────────────────────────────────
    $userData = [
        'full_name' => $fullName,
        'name'      => $fullName,
        'gender'    => $gender,
        'email'     => $email !== '' ? $email : null,
        'birthdate' => $dob,
        'mobile'    => $phone,
        'password'  => Hash::make(Str::random(16)),
    ];

    if ($health !== '') {
        // Store health as a note — no dedicated field on User, skip for now
        // (HealthRecord could be created separately)
    }

    try {
        if (!$DRY_RUN) {
            DB::beginTransaction();
            $user = User::create($userData);
            Membership::create([
                'tenant_id' => $club->id,
                'user_id'   => $user->id,
                'status'    => 'active',
            ]);
            DB::commit();
        }
        $imported++;

        if ($imported <= 5 || $imported % 100 === 0) {
            echo "  [$imported] $fullName | $gender | " . ($dob ?? 'no-dob') . " | " . ($email ?: 'no-email') . "\n";
        }
    } catch (Throwable $e) {
        if (!$DRY_RUN) DB::rollBack();
        $errors[] = "Row $row [$fullName]: " . $e->getMessage();
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════\n";
echo "IMPORT " . ($DRY_RUN ? 'DRY-RUN ' : '') . "RESULTS\n";
echo "══════════════════════════════════\n";
echo "Imported (new):     $imported\n";
echo "Already member:     $alreadyMember\n";
echo "Skipped (no name):  $skippedMissing\n";
echo "Skipped (gender?):  $skippedBadGender\n";
echo "Errors:             " . count($errors) . "\n";
if ($errors) {
    echo "\nFirst 10 errors:\n";
    foreach (array_slice($errors, 0, 10) as $e) {
        echo "  - $e\n";
    }
}
