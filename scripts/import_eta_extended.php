<?php
/**
 * Script: import_eta_extended.php
 *
 * Second-pass enrichment: for every ETA member already in the DB,
 * fills in the data that the first import skipped:
 *
 *  - documents        : CPR number + downloaded CPR photo (col H + col N)
 *  - emergency_contacts: up to 2 phone numbers (col G + col T)
 *  - health_conditions : free-text condition from col J (skips "no condition")
 *  - HealthRecord      : height + weight at registration date (col K + col L)
 *  - profile_picture   : downloaded personal photo (col P)
 *
 * Column map (after gender column insertion at E):
 *  A=Timestamp  B=First  C=Middle  D=Last  E=Gender
 *  F=Phone      G=Emergency1  H=CPR/ID  I=Email
 *  J=Health     K=Height      L=Weight
 *  N=CPR photo (Drive)   P=Personal photo (Drive)
 *  T=Emergency2  U=DOB
 *
 * Usage:
 *   php scripts/import_eta_extended.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\HealthRecord;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

$CLUB_ID       = 1;
$SOURCE        = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses) - With Gender.xlsx';
$DRY_RUN       = in_array('--dry-run', $argv ?? []);
$SKIP_DRIVE    = in_array('--skip-drive', $argv ?? []); // skip Drive downloads (files are private)

echo "Dry-run: " . ($DRY_RUN ? 'YES' : 'NO') . "\n\n";

// ── Build phone → user_id index (primary dedup key) ──────────────────────
echo "Building member index...\n";
$memberIds  = Membership::where('tenant_id', $CLUB_ID)->pluck('user_id');
$users      = User::whereIn('id', $memberIds)->get(['id', 'full_name', 'mobile', 'email']);

$phoneIndex = []; // digits → user_id
$nameIndex  = []; // normalised-name → user_id
$emailIndex = []; // lower-email → user_id

foreach ($users as $u) {
    $digits = preg_replace('/\D/', '', $u->mobile['number'] ?? '');
    if ($digits) $phoneIndex[$digits] = $u->id;
    $nameIndex[mb_strtolower(trim($u->full_name ?? ''))] = $u->id;
    if ($u->email) $emailIndex[strtolower($u->email)] = $u->id;
}
echo "Members indexed: " . count($memberIds) . "\n\n";

// ── Helpers ───────────────────────────────────────────────────────────────
function cellVal($sheet, string $col, int $row): string
{
    return trim((string) $sheet->getCell($col . $row)->getValue());
}

function parsePhone(string $raw, string $defaultCode = '+973'): ?array
{
    $raw = preg_replace('/[\s\-\(\)\.]+/', '', $raw);
    if ($raw === '' || $raw === '0') return null;
    if (preg_match('/^(\+\d{1,4})(\d{6,})$/', $raw, $m))
        return ['phone_code' => $m[1], 'phone' => $m[2], 'name' => '', 'relationship' => ''];
    if (preg_match('/^(00\d{1,4})(\d{6,})$/', $raw, $m))
        return ['phone_code' => '+' . ltrim($m[1], '0'), 'phone' => $m[2], 'name' => '', 'relationship' => ''];
    $digits = preg_replace('/\D/', '', $raw);
    return $digits ? ['phone_code' => $defaultCode, 'phone' => $digits, 'name' => '', 'relationship' => ''] : null;
}

function isNoCondition(string $raw): bool
{
    $lower = mb_strtolower($raw);
    foreach (['no condition', 'لايوجد', 'لا يوجد', 'none', 'n/a', 'na', 'nil', 'nothing', 'no'] as $p) {
        if (str_contains($lower, $p)) return true;
    }
    return strlen($raw) < 3;
}

/**
 * Extract Google Drive file ID from various Drive URL formats.
 */
function driveFileId(string $url): ?string
{
    if (preg_match('/[?&]id=([a-zA-Z0-9_\-]+)/', $url, $m)) return $m[1];
    if (preg_match('/\/d\/([a-zA-Z0-9_\-]+)/', $url, $m))    return $m[1];
    return null;
}

/**
 * Download a Google Drive file (shared with "anyone with link") and store it.
 * Returns the local storage path, or null on failure.
 */
function downloadDriveFile(string $url, string $storagePath, bool $dryRun): ?string
{
    $fileId = driveFileId($url);
    if (!$fileId) return null;

    if ($dryRun) return "DRYRUN:{$storagePath}";

    $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";

    $ctx = stream_context_create([
        'http' => [
            'timeout'          => 20,
            'follow_location'  => true,
            'user_agent'       => 'Mozilla/5.0',
        ],
        'ssl' => ['verify_peer' => false],
    ]);

    $content = @file_get_contents($downloadUrl, false, $ctx);
    if (!$content) return null;

    // Detect MIME — reject HTML (Google login/warning page)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->buffer($content);
    if (str_starts_with($mime, 'text/')) return null;

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        default      => 'bin',
    };

    $finalPath = $storagePath . '.' . $ext;
    Storage::disk('local')->put($finalPath, $content);
    return $finalPath;
}

// ── Load spreadsheet ──────────────────────────────────────────────────────
echo "Loading spreadsheet...\n";
$spreadsheet = IOFactory::load($SOURCE);
$sheet       = $spreadsheet->getActiveSheet();
$highestRow  = $sheet->getHighestDataRow();
echo "Rows: $highestRow\n\n";

// ── Stats ─────────────────────────────────────────────────────────────────
$stats = [
    'documents_cpr_number'  => 0,
    'documents_cpr_photo'   => 0,
    'documents_cpr_failed'  => 0,
    'profile_photos'        => 0,
    'profile_failed'        => 0,
    'emergency_contacts'    => 0,
    'health_conditions'     => 0,
    'health_records'        => 0,
    'not_matched'           => 0,
    'already_enriched'      => 0,
];

// Track which user IDs have been processed (each user only enriched once)
$processed = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $firstName = cellVal($sheet, 'B', $row);
    $midName   = cellVal($sheet, 'C', $row);
    $lastName  = cellVal($sheet, 'D', $row);
    if ($firstName === '' && $lastName === '') continue;

    $fullName  = trim(implode(' ', array_filter([$firstName, $midName, $lastName])));
    $normName  = mb_strtolower($fullName);
    $rawPhone  = cellVal($sheet, 'F', $row);
    $email     = strtolower(cellVal($sheet, 'I', $row));

    // Match user
    $phoneDigits = preg_replace('/\D/', '', $rawPhone);
    $userId = null;
    if ($phoneDigits && isset($phoneIndex[$phoneDigits]))      $userId = $phoneIndex[$phoneDigits];
    elseif ($email && isset($emailIndex[$email]))              $userId = $emailIndex[$email];
    elseif (isset($nameIndex[$normName]))                      $userId = $nameIndex[$normName];

    if (!$userId) { $stats['not_matched']++; continue; }

    // Each user processed only once — use earliest timestamp per user
    if (isset($processed[$userId])) { continue; }
    $processed[$userId] = true;

    // ── Read form fields ──────────────────────────────────────────────────
    $tsRaw    = $sheet->getCell('A' . $row)->getValue();
    $regDate  = $tsRaw ? XlDate::excelToDateTimeObject((float)$tsRaw)->format('Y-m-d') : null;

    $rawCpr   = cellVal($sheet, 'H', $row);
    $cprNum   = $rawCpr !== '' ? (string)(int)$rawCpr : null;

    $cprPhotoUrl  = cellVal($sheet, 'N', $row);
    $photoUrl     = cellVal($sheet, 'P', $row);

    $rawHealth = cellVal($sheet, 'J', $row);
    $rawEmerg1 = cellVal($sheet, 'G', $row);
    $rawEmerg2 = cellVal($sheet, 'T', $row);

    $heightRaw = cellVal($sheet, 'K', $row);
    $weightRaw = cellVal($sheet, 'L', $row);
    $height    = is_numeric($heightRaw) && $heightRaw > 0 ? (float)$heightRaw : null;
    $weight    = is_numeric($weightRaw) && $weightRaw > 0 ? (float)$weightRaw : null;

    // ── Build documents array ─────────────────────────────────────────────
    $documents = [];
    if ($cprNum || $cprPhotoUrl) {
        $doc = ['type' => 'CPR', 'number' => $cprNum ?? '', 'file_path' => null, 'uploaded_at' => $regDate];

        if ($cprPhotoUrl && !$SKIP_DRIVE) {
            $path = downloadDriveFile($cprPhotoUrl, "documents/users/{$userId}/cpr", $DRY_RUN);
            if ($path) { $doc['file_path'] = $path; $stats['documents_cpr_photo']++; }
            else        { $stats['documents_cpr_failed']++; }
        }
        $documents[] = $doc;
        if ($cprNum) $stats['documents_cpr_number']++;
    }

    // ── Emergency contacts ────────────────────────────────────────────────
    $emergencyContacts = array_filter([
        parsePhone($rawEmerg1),
        parsePhone($rawEmerg2),
    ]);

    // ── Health conditions ─────────────────────────────────────────────────
    $healthConditions = [];
    if ($rawHealth && !isNoCondition($rawHealth)) {
        $healthConditions[] = ['condition' => $rawHealth, 'noted_at' => $regDate, 'notes' => ''];
    }

    // ── Download profile picture ──────────────────────────────────────────
    $profilePicturePath = null;
    if ($photoUrl && !$SKIP_DRIVE) {
        $path = downloadDriveFile($photoUrl, "images/profiles/user_{$userId}", $DRY_RUN);
        if ($path) { $profilePicturePath = $path; $stats['profile_photos']++; }
        else        { $stats['profile_failed']++; }
    }

    // ── Persist ───────────────────────────────────────────────────────────
    if (!$DRY_RUN) {
        $update = [];

        if (!empty($documents))        { $update['documents']          = json_encode($documents); }
        if (!empty($emergencyContacts)) { $update['emergency_contacts'] = json_encode(array_values($emergencyContacts)); }
        if (!empty($healthConditions)) { $update['health_conditions']  = json_encode($healthConditions); }
        if ($profilePicturePath)       { $update['profile_picture']    = $profilePicturePath; }

        if (!empty($update)) {
            DB::table('users')->where('id', $userId)->update($update);
        }

        // Insert HealthRecord if we have height or weight
        if ($height || $weight) {
            $recordDate = $regDate ?? now()->toDateString();

            // Normalise height: if entered in metres (1.0–2.5), convert to cm
            if ($height !== null && $height >= 1.0 && $height <= 2.5) {
                $height = round($height * 100, 1);
            }

            // Calculate BMI if both valid values are present
            $bmi = null;
            if ($height !== null && $weight !== null && $height >= 50 && $weight >= 5) {
                $bmi = round($weight / pow($height / 100, 2), 2);
            }

            $recordData = array_filter([
                'height' => $height,
                'weight' => $weight,
                'bmi'    => $bmi,
            ], fn($v) => $v !== null);

            $exists = HealthRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $recordDate)->exists();
            if (!$exists) {
                HealthRecord::create(['user_id' => $userId, 'recorded_at' => $recordDate] + $recordData);
            } else {
                HealthRecord::where('user_id', $userId)
                    ->whereDate('recorded_at', $recordDate)->update($recordData);
            }
            $stats['health_records']++;
        }
    } else {
        // Dry-run counters
        if (!empty($emergencyContacts)) $stats['emergency_contacts']++;
        if (!empty($healthConditions))  $stats['health_conditions']++;
        if ($height || $weight)         $stats['health_records']++;
    }

    if (!$DRY_RUN) {
        if (!empty($emergencyContacts)) $stats['emergency_contacts']++;
        if (!empty($healthConditions))  $stats['health_conditions']++;
    }

    // Progress
    $done = count($processed);
    if ($done <= 5 || $done % 100 === 0) {
        echo "  [$done] $fullName | CPR:" . ($cprNum ? '✓' : '–')
            . " | Photo:" . ($photoUrl ? '↓' : '–')
            . " | H/W:" . ($height ? $height : '–') . "/" . ($weight ? $weight : '–')
            . " | Emrg:" . count($emergencyContacts)
            . "\n";
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════\n";
echo ($DRY_RUN ? "DRY-RUN " : "") . "RESULTS\n";
echo "══════════════════════════════════════\n";
foreach ($stats as $k => $v) echo str_pad($k, 26) . ": $v\n";
