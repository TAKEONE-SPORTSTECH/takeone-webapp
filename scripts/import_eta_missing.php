<?php
/**
 * Script: import_eta_missing.php
 * Second-pass import: handles the ~181 people who were skipped in the first
 * run because they shared an email with a sibling.
 *
 * Strategy:
 *  - For each row, deduplicate by phone number (not email).
 *  - If a club member already has that exact phone → row was already imported, skip.
 *  - If email is taken by another user → create the new user without email.
 *  - If no phone either → deduplicate by full-name match in the club.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

$CLUB_SLUG  = 'eta';
$SOURCE     = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses) - With Gender.xlsx';
$DRY_RUN    = in_array('--dry-run', $argv ?? []);
$DEFAULT_CC = '+973';

$club = Tenant::where('slug', $CLUB_SLUG)->firstOrFail();
echo "Club: [{$club->id}] {$club->slug}\n";
echo "Dry-run: " . ($DRY_RUN ? 'YES' : 'NO') . "\n\n";

// ── Build lookup: all existing club members ──────────────────────────────────
echo "Building existing-member index...\n";

$existingMemberIds = Membership::where('tenant_id', $club->id)->pluck('user_id')->all();

// Composite key: normalised-name|phone-digits → true
$compositeIndex = [];

$existingUsers = User::whereIn('id', $existingMemberIds)->get(['id', 'full_name', 'mobile']);
foreach ($existingUsers as $u) {
    $normName    = mb_strtolower(trim($u->full_name ?? ''));
    $phoneDigits = preg_replace('/\D/', '', $u->mobile['number'] ?? '');
    $compositeIndex[$normName . '|' . $phoneDigits] = true;
}

echo "Existing members in club: " . count($existingMemberIds) . "\n";
echo "Composite index entries:  " . count($compositeIndex) . "\n\n";

// ── Helpers ─────────────────────────────────────────────────────────────────
function cell(object $sheet, int $col, int $row): string
{
    return trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue());
}

function parsePhone(string $raw, string $defaultCode): array
{
    $raw = preg_replace('/[\s\-\(\)\.]+/', '', $raw);
    if ($raw === '') return ['code' => $defaultCode, 'number' => ''];
    if (preg_match('/^(\+\d{1,4})(\d{6,})$/', $raw, $m))
        return ['code' => $m[1], 'number' => $m[2]];
    if (preg_match('/^(00\d{1,4})(\d{6,})$/', $raw, $m))
        return ['code' => '+' . ltrim($m[1], '0'), 'number' => $m[2]];
    return ['code' => $defaultCode, 'number' => ltrim($raw, '0')];
}

function parseDob(mixed $raw): ?string
{
    if ($raw === null || $raw === '') return null;
    if (is_numeric($raw) && $raw > 1000) {
        try { return XlDate::excelToDateTimeObject((float)$raw)->format('Y-m-d'); } catch (\Throwable) {}
    }
    try { return \Carbon\Carbon::parse((string)$raw)->format('Y-m-d'); } catch (\Throwable) { return null; }
}

function cleanHealth(string $raw): string
{
    $lower = mb_strtolower($raw);
    foreach (['no condition', 'لايوجد', 'لا يوجد', 'none', 'n/a', 'na', 'nil', 'nothing'] as $p) {
        if (str_contains($lower, $p)) return '';
    }
    return $raw;
}

// ── Load spreadsheet ─────────────────────────────────────────────────────────
$spreadsheet = IOFactory::load($SOURCE);
$sheet       = $spreadsheet->getActiveSheet();
$highestRow  = $sheet->getHighestDataRow();
echo "Spreadsheet rows: $highestRow\n\n";

// Column indices (after gender column insertion at E)
const COL_FIRST  = 2;
const COL_MIDDLE = 3;
const COL_LAST   = 4;
const COL_GENDER = 5;
const COL_PHONE  = 6;
const COL_EMAIL  = 9;
const COL_DOB    = 21;

$imported      = 0;
$alreadyThere  = 0;
$skipped       = 0;
$errors        = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $firstName = cell($sheet, COL_FIRST,  $row);
    $midName   = cell($sheet, COL_MIDDLE, $row);
    $lastName  = cell($sheet, COL_LAST,   $row);
    $gender    = ucfirst(strtolower(cell($sheet, COL_GENDER, $row)));
    $rawPhone  = cell($sheet, COL_PHONE,  $row);
    $email     = strtolower(cell($sheet, COL_EMAIL, $row));

    $dobRaw    = $sheet->getCell(Coordinate::stringFromColumnIndex(COL_DOB) . $row)->getValue();
    $dob       = parseDob($dobRaw);

    if ($firstName === '' && $lastName === '') continue;
    if ($firstName === '' || $lastName === '') { $skipped++; continue; }
    if (!in_array($gender, ['Male', 'Female']))  { $skipped++; continue; }

    $fullName  = trim(implode(' ', array_filter([$firstName, $midName, $lastName])));
    $phone     = parsePhone($rawPhone, $DEFAULT_CC);
    $phoneDigits = preg_replace('/\D/', '', $phone['number']);

    // ── Was this person already imported? ────────────────────────────────
    // Families share phone & email — only skip if BOTH name AND phone match.
    // That way same-name-different-people still get individual accounts.
    $normName    = mb_strtolower($fullName);
    $phoneDigits = preg_replace('/\D/', '', $phone['number']);
    $compositeKey = $normName . '|' . $phoneDigits;
    if (isset($compositeIndex[$compositeKey])) {
        $alreadyThere++;
        continue;
    }

    // ── Email deduplication — don't block, just clear ────────────────────
    $useEmail = $email !== '' ? $email : null;
    if ($useEmail !== null && User::where('email', $useEmail)->exists()) {
        $useEmail = null; // sibling shares parent's email — import without email
    }

    // ── Create user ──────────────────────────────────────────────────────
    try {
        if (!$DRY_RUN) {
            DB::beginTransaction();
            $user = User::create([
                'full_name' => $fullName,
                'name'      => $fullName,
                'gender'    => $gender,
                'email'     => $useEmail,
                'birthdate' => $dob,
                'mobile'    => $phone,
                'password'  => Hash::make(Str::random(16)),
            ]);
            Membership::create([
                'tenant_id' => $club->id,
                'user_id'   => $user->id,
                'status'    => 'active',
            ]);
            DB::commit();

            // Add to composite index so later rows in the same file are caught
            $compositeIndex[$compositeKey] = true;
        }
        $imported++;
        if ($imported <= 5 || $imported % 50 === 0) {
            echo "  [$imported] $fullName | $gender | " . ($dob ?? 'no-dob') . " | " . ($useEmail ?: '(no-email)') . "\n";
        }
    } catch (\Throwable $e) {
        if (!$DRY_RUN) DB::rollBack();
        $errors[] = "Row $row [$fullName]: " . $e->getMessage();
    }
}

echo "\n══════════════════════════════════\n";
echo "SECOND-PASS " . ($DRY_RUN ? 'DRY-RUN ' : '') . "RESULTS\n";
echo "══════════════════════════════════\n";
echo "New members added:     $imported\n";
echo "Already in club:       $alreadyThere\n";
echo "Skipped (bad data):    $skipped\n";
echo "Errors:                " . count($errors) . "\n";
if ($errors) {
    echo "\nErrors:\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  - $e\n";
}
