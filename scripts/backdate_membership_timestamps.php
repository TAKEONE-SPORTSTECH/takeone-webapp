<?php
/**
 * Script: backdate_membership_timestamps.php
 * Reads the original form timestamps (col A) and updates each membership's
 * created_at to the real registration date.
 *
 * Match order:
 *  1. Phone digits → user → membership
 *  2. Normalised full name → user → membership
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

$CLUB_ID  = 1;
$SOURCE   = __DIR__ . '/../sample files/Emperor Taekwondo Academy Form (Responses) - With Gender.xlsx';
$DRY_RUN  = in_array('--dry-run', $argv ?? []);

echo "Dry-run: " . ($DRY_RUN ? 'YES' : 'NO') . "\n\n";

// ── Build phone → membership_id and name → membership_id indexes ────────────
echo "Building member index...\n";

$memberIds = Membership::where('tenant_id', $CLUB_ID)->pluck('user_id', 'id'); // id => user_id
$users = User::whereIn('id', $memberIds->values())->get(['id', 'full_name', 'mobile']);

// phone-digits → [membership_id, ...]  (array because siblings share phones)
$phoneIndex = [];
// normalised-name → membership_id
$nameIndex  = [];

foreach ($users as $user) {
    $membershipId = $memberIds->search($user->id); // find membership id for this user
    if (!$membershipId) continue;

    $digits = preg_replace('/\D/', '', $user->mobile['number'] ?? '');
    if ($digits !== '') {
        $phoneIndex[$digits][] = $membershipId;
    }
    $normName = mb_strtolower(trim($user->full_name ?? ''));
    if ($normName !== '') {
        $nameIndex[$normName] = $membershipId;
    }
}

echo "Indexed: " . count($phoneIndex) . " phone entries, " . count($nameIndex) . " name entries\n\n";

// ── Load xlsx ─────────────────────────────────────────────────────────────
$spreadsheet = IOFactory::load($SOURCE);
$sheet       = $spreadsheet->getActiveSheet();
$highestRow  = $sheet->getHighestDataRow();

function cellVal($sheet, string $col, int $row): string
{
    return trim((string) $sheet->getCell($col . $row)->getValue());
}

// ── Process each row ──────────────────────────────────────────────────────
$updated       = 0;
$notFound      = 0;
$noTimestamp   = 0;

// Track which membership IDs have been assigned a timestamp
// (for shared-phone rows, assign in order of submission)
$assigned = []; // membershipId => timestamp already set

for ($row = 2; $row <= $highestRow; $row++) {
    // Parse timestamp (col A)
    $tsRaw = $sheet->getCell('A' . $row)->getValue();
    if (!$tsRaw) { $noTimestamp++; continue; }

    $registeredAt = XlDate::excelToDateTimeObject((float) $tsRaw)->format('Y-m-d H:i:s');

    // Parse name
    $firstName = cellVal($sheet, 'B', $row);
    $midName   = cellVal($sheet, 'C', $row);
    $lastName  = cellVal($sheet, 'D', $row);
    if ($firstName === '' && $lastName === '') continue;

    $fullName  = trim(implode(' ', array_filter([$firstName, $midName, $lastName])));
    $normName  = mb_strtolower($fullName);

    // Parse phone
    $rawPhone    = cellVal($sheet, 'F', $row);
    $phoneDigits = preg_replace('/\D/', '', $rawPhone);

    // ── Match: phone first ────────────────────────────────────────────────
    $membershipId = null;

    if ($phoneDigits !== '' && isset($phoneIndex[$phoneDigits])) {
        $candidates = $phoneIndex[$phoneDigits];

        if (count($candidates) === 1) {
            // Unique phone → direct match
            $membershipId = $candidates[0];
        } else {
            // Shared phone (siblings) — pick the one not yet assigned,
            // or the one whose user name is closest
            foreach ($candidates as $cid) {
                if (!isset($assigned[$cid])) {
                    $membershipId = $cid;
                    break;
                }
            }
            // All already assigned — skip (duplicate form submission)
            if (!$membershipId) {
                $notFound++;
                continue;
            }
        }
    }

    // ── Fallback: name match ──────────────────────────────────────────────
    if (!$membershipId && isset($nameIndex[$normName])) {
        $membershipId = $nameIndex[$normName];
    }

    if (!$membershipId) {
        $notFound++;
        continue;
    }

    // Skip if already assigned a better (earlier) timestamp
    if (isset($assigned[$membershipId])) {
        // Keep earliest registration date
        if ($registeredAt < $assigned[$membershipId]) {
            $assigned[$membershipId] = $registeredAt;
            if (!$DRY_RUN) {
                DB::table('memberships')->where('id', $membershipId)
                    ->update(['created_at' => $registeredAt, 'updated_at' => $registeredAt]);
            }
        }
        continue;
    }

    $assigned[$membershipId] = $registeredAt;

    if (!$DRY_RUN) {
        DB::table('memberships')->where('id', $membershipId)
            ->update(['created_at' => $registeredAt, 'updated_at' => $registeredAt]);
    }
    $updated++;

    if ($updated <= 5 || $updated % 100 === 0) {
        echo "  [$updated] $fullName → $registeredAt (membership #$membershipId)\n";
    }
}

echo "\n══════════════════════════════════\n";
echo ($DRY_RUN ? "DRY-RUN " : "") . "RESULTS\n";
echo "══════════════════════════════════\n";
echo "Timestamps updated: $updated\n";
echo "Not matched:        $notFound\n";
echo "No timestamp:       $noTimestamp\n";
