<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use App\Support\Countries;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Registrations → "Import from Excel": many athletes into one event at once.
 *
 * Reads the sheets federations ALREADY keep, not only our template: the
 * header may sit on any of the first rows, in English or Arabic; club and
 * coach may be merged down a block (a blank club means "same as above");
 * gender may be F/M or ذكر/أنثى; dates may be Excel dates.
 *
 * What is missing is left blank, to be completed later (decided with the user,
 * 2026-09-26) — never guessed. Only a row that cannot be entered at all (no
 * name, no club, an ID that belongs elsewhere, already entered, listed twice)
 * is held back. A club not yet known is added as a PENDING club with its
 * coach, so its athletes can go in; the Clubs page links or merges it later.
 * Club names are compared the way Arabic is written in practice
 * (أ/إ/آ = ا, ة = ه, ى = ي), so two spellings of one club are one club.
 *
 * Values only — a formula is never calculated. Every row that is entered goes
 * through FederationEnrolment, the same door as "Enroll athlete".
 */
class FederationBulkImport
{
    public const MAX_ROWS = 300;

    public const MAX_NEW_CLUBS = 50;

    /** column => [template heading, words a heading may contain (normalised)] — most specific first */
    private const COLS = [
        'takeone' => ['TakeOne ID', ['takeone', 'تيك ون']],
        'club' => ['Club / النادي', ['club', 'النادي', 'نادي']],
        'coach' => ['Coach / المدرب', ['coach', 'instructor', 'trainer', 'المدرب', 'مدرب']],
        'natid' => ['National ID (CPR) / الرقم الشخصي', ['national id', 'cpr', 'passport', 'الرقم الشخصي', 'الرقم الوطني', 'الهوية', 'رقم الجواز']],
        'nat' => ['Nationality / الجنسية', ['nationality', 'country', 'الجنسية']],
        'name' => ['Full name / الاسم الكامل', ['full name', 'participant', 'athlete', 'player', 'name', 'اسم اللاعب', 'الاسم', 'الإسم', 'اللاعب']],
        'gender' => ['Gender (M/F) / الجنس', ['gender', 'f/m', 'm/f', 'sex', 'الجنس', 'ذكر', 'انثى', 'أنثى']],
        'weight' => ['Weight (kg) / الوزن', ['weight', 'kg', 'الوزن', 'وزن']],
        'dob' => ['Date of birth / تاريخ الميلاد', ['date of birth', 'birth', 'dob', 'الميلاد', 'تاريخ']],
        'cat' => ['Category / الفئة', ['categor', 'division', 'event type', 'الفئة', 'الفئات', 'فئة']],
    ];

    public function __construct(
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationEnrolment $enrolment,
    ) {}

    public function event(array $access, string $uuid): ?FederationEvent
    {
        $q = FederationEvent::whereIn('federation_id', $this->ids($access))->where('uuid', $uuid);
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q->first();
    }

    // ------------------------------------------------------------ template

    /** The event's own template, as .xlsx bytes. */
    public function template(array $access, FederationEvent $e): string
    {
        $clubs = collect($this->clubMap($access))->pluck('name')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        $types = $this->types($e);
        $order = ['no', 'club', 'coach', 'name', 'gender', 'weight', 'dob', 'nat', 'natid', 'cat', 'takeone'];
        $heads = array_map(fn ($k) => $k === 'no' ? 'No. / الرقم' : self::COLS[$k][0], $order);
        $last = chr(ord('A') + count($order) - 1);

        $book = new Spreadsheet;
        $book->getProperties()->setCreator('TakeOne')->setTitle($e->name.' — athletes');
        $sh = $book->getActiveSheet()->setTitle('Athletes');
        $sh->fromArray($heads, null, 'A1');
        $sh->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle('A1:'.$last.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E2C4F');
        $sh->getStyle('A1:'.$last.'1')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getRowDimension(1)->setRowHeight(34);
        foreach ([7, 30, 20, 30, 12, 11, 15, 16, 18, 22, 15] as $i => $w) {
            $sh->getColumnDimension(chr(ord('A') + $i))->setWidth($w);
        }
        $sh->freezePane('A2');
        $sh->fromArray([
            [1, $clubs[0] ?? 'Your club', 'Coach name', 'Example: Sara Ahmed (delete this row)', 'F', 41, '2012-03-04', 'Bahrain', '', $types[0] ?? '', ''],
            [2, '', '', 'Example: same club as above (club left blank)', 'M', 45, '2011', '', '', '', ''],
        ], null, 'A2');
        $sh->getStyle('A2:'.$last.'3')->getFont()->setItalic(true)->getColor()->setRGB('8A93A6');
        $sh->getStyle('G2:G'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $lists = $book->createSheet()->setTitle('Lists');
        $lists->fromArray([['Clubs / الأندية', 'Categories / الفئات', 'Gender / الجنس']], null, 'A1');
        foreach ($clubs as $i => $c) {
            $lists->setCellValueExplicit('A'.($i + 2), $c, DataType::TYPE_STRING);
        }
        foreach ($types as $i => $t) {
            $lists->setCellValueExplicit('B'.($i + 2), $t, DataType::TYPE_STRING);
        }
        $lists->fromArray([['M'], ['F']], null, 'C2');
        $lists->getStyle('A1:C1')->getFont()->setBold(true);
        $lists->getColumnDimension('A')->setWidth(40);
        $lists->getColumnDimension('B')->setWidth(28);

        // Dropdowns that SUGGEST (a warning, not a stop): a new club may be typed in.
        $drop = function (string $range, string $source, string $prompt) use ($sh) {
            $v = $sh->getCell(explode(':', $range)[0])->getDataValidation();
            $v->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(false)->setShowInputMessage(true)
                ->setPromptTitle('Pick or type')->setPrompt($prompt)->setFormula1($source);
            $sh->setDataValidation($range, $v);
        };
        $n = self::MAX_ROWS + 1;
        $drop('E2:E'.$n, 'Lists!$C$2:$C$3', 'M or F');
        if ($clubs !== []) {
            $drop('B2:B'.$n, 'Lists!$A$2:$A$'.(count($clubs) + 1), 'Pick the club, or type a new one. Blank = same as the row above.');
        }
        if ($types !== []) {
            $drop('J2:J'.$n, 'Lists!$B$2:$B$'.(count($types) + 1), 'The category they enter (several: separate with commas)');
        }

        $help = $book->createSheet()->setTitle('Instructions');
        $rows = [[$e->name], [''],
            ['One athlete per row on the "Athletes" sheet. Delete the grey example rows.'],
            ['Only the athlete\'s name and club are needed. Anything else you do not have yet can stay blank and be completed later.'],
            ['Club: pick it from the list, or type a new club — it is added as a pending club. A blank club means the same club as the row above.'],
            ['Gender: M or F (ذكر / أنثى also work).'],
            ['Date of birth: YYYY-MM-DD. If you only know the year, type the year — the date is left blank until it is known.'],
            ['Nationality: country name or code (Bahrain, BH, BHR).'],
            ['National ID: recommended — an ID that is already registered is matched to that athlete, so nobody is created twice.'],
            ['TakeOne ID: for athletes who already have one. They enter under their current club.'],
            ['Up to '.self::MAX_ROWS.' athletes per file. You will see every row checked before anything is imported.'],
            [''], ['This event\'s categories:']];
        foreach ($types as $t) {
            $rows[] = ['  • '.$t];
        }
        $help->fromArray($rows, null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getColumnDimension('A')->setWidth(125);

        $book->setActiveSheetIndex(0);
        $tmp = tempnam(sys_get_temp_dir(), 'fedtpl');
        (new Xlsx($book))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    // ------------------------------------------------------------- preview

    /** @return array{ok:bool, message?:string, rows?:array<int,array<string,mixed>>, sheet?:string} */
    public function preview(array $access, FederationEvent $e, UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            if (! $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx && ! $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xls && ! $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
                return ['ok' => false, 'message' => 'Upload an Excel file (.xlsx).'];
            }
            $reader->setReadDataOnly(true);
            $book = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'That file could not be read. Save it as .xlsx and try again.'];
        }

        // The first sheet with a header we recognise; the header on any of its first 20 rows.
        $found = null;
        foreach ($book->getAllSheets() as $sheet) {
            if (mb_strtolower($sheet->getTitle()) === 'lists' || mb_strtolower($sheet->getTitle()) === 'instructions') {
                continue;
            }
            $grid = $sheet->toArray(null, false, false, false);   // values as stored — never calculated
            foreach (array_slice($grid, 0, 20, true) as $r => $cells) {
                $map = $this->headerMap($cells);
                if (isset($map['name']) && count($map) >= 2) {
                    $found = [$sheet->getTitle(), $grid, $r, $map];
                    break 2;
                }
            }
        }
        $book->disconnectWorksheets();
        if (! $found) {
            return ['ok' => false, 'message' => 'No athletes list found — the file needs a column for the athlete\'s name (e.g. "Full name" / "اسم اللاعب").'];
        }
        [$title, $grid, $headRow, $col] = $found;

        $types = $this->types($e);
        $clubs = $this->clubMap($access);
        $entered = $this->enteredIds($e);
        $visible = $this->enrolment->visibleIds($access)->flip();
        $this->idsCache = $this->ids($access);
        $out = [];
        $seen = [];
        $club = '';
        $coach = '';
        foreach ($grid as $r => $raw) {
            if ($r <= $headRow) {
                continue;
            }
            $get = fn (string $k) => isset($col[$k]) ? $this->clean($raw[$col[$k]] ?? '') : '';
            // Club and coach run down a block (merged cells): blank = the one above.
            if ($get('club') !== '') {
                $club = $get('club');
                $coach = $get('coach');
            } elseif ($get('coach') !== '') {
                $coach = $get('coach');
            }
            $name = mb_substr($get('name'), 0, 150);
            $tk = strtoupper($get('takeone'));
            if ($name === '' && $tk === '') {
                continue;
            }
            if (str_starts_with(mb_strtolower($name), 'example:')) {
                continue;
            }
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
            $out[] = $this->check($r + 1, $raw, $col, $name, $tk, $club, $coach, $types, $clubs, $entered, $visible, $seen);
        }

        return ['ok' => true, 'rows' => $out, 'sheet' => $title];
    }

    /** @param  array<int, mixed>  $cells  @return array<string, int> column key => index */
    private function headerMap(array $cells): array
    {
        $map = [];
        $taken = [];
        foreach (self::COLS as $key => [, $words]) {
            foreach ($cells as $i => $h) {
                if (isset($taken[$i])) {
                    continue;
                }
                $n = $this->norm((string) $h);
                if ($n === '') {
                    continue;
                }
                foreach ($words as $w) {
                    if (str_contains($n, $this->norm($w))) {
                        $map[$key] = $i;
                        $taken[$i] = true;
                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    private function check(int $line, array $raw, array $col, string $name, string $tk, string $club, string $coach, array $types, array $clubs, Collection $entered, Collection $visible, array &$seen): array
    {
        $get = fn (string $k) => isset($col[$k]) ? $this->clean($raw[$col[$k]] ?? '') : '';
        $row = ['line' => $line, 'name' => $name, 'status' => 'ready', 'problems' => [], 'notes' => [], 'coach' => $coach ?: null];
        $stop = function (string $m) use (&$row) { $row['problems'][] = $m; };
        $note = function (string $m) use (&$row) { $row['notes'][] = $m; };

        // Weight and categories: blank is fine; a value we cannot read is dropped and said so.
        $w = str_replace(',', '.', $get('weight'));
        $row['weight'] = is_numeric($w) && (float) $w >= 10 && (float) $w <= 250 ? round((float) $w, 1) : null;
        if ($w !== '' && $row['weight'] === null) {
            $note('Weight "'.$w.'" ignored.');
        }
        $picked = [];
        foreach (array_filter(array_map('trim', preg_split('/[,;\/،]+/u', $get('cat')) ?: [])) as $a) {
            $hit = collect($types)->first(fn ($t) => $this->norm($t) === $this->norm($a));
            $hit ? $picked[] = $hit : $note('Category "'.$a.'" is not one of this event\'s — left blank.');
        }
        if ($picked === [] && count($types) === 1) {
            $picked = [$types[0]];   // one category only: entering the event means entering it
        }
        $row['types'] = array_values(array_unique($picked));

        // Who: a TakeOne ID, a national ID already registered, or somebody new.
        $natId = mb_substr($get('natid'), 0, 40);
        $athlete = $tk !== '' ? FederationAthlete::with('user')->where('takeone_id', $tk)->first() : null;
        if ($athlete && ! $visible->has($athlete->id)) {
            $athlete = null;
            $stop('TakeOne ID '.$tk.' belongs to an athlete registered elsewhere — ask their club to request a transfer.');
        } elseif ($tk !== '' && ! $athlete) {
            $stop('No athlete has the TakeOne ID '.$tk.'.');
        }
        if (! $athlete && $tk === '' && $natId !== '' && ($h = FederationAthlete::hashNationalId($natId))) {
            $found = FederationAthlete::with('user')->where('national_id_hash', $h)->first();
            if ($found && $visible->has($found->id)) {
                $athlete = $found;
            } elseif ($found) {
                $stop('This national ID is already registered with another club — ask their club to request a transfer.');
            }
        }

        // No ID at all: the same name at the same (known) club IS that athlete —
        // a federation's own sheet rarely carries IDs, and this is how nobody
        // already on a club's roster is created a second time.
        if (! $athlete && $tk === '' && $natId === '' && $name !== '' && $club !== '' && ($known = $clubs[$this->norm($club)] ?? null)) {
            $athlete = $this->rosterByName($known['key'])[$this->norm($name)] ?? null;
            if ($athlete) {
                $note('Matched by name to '.$athlete->takeone_id.' at this club.');
            }
        }

        if ($athlete) {
            $row += ['kind' => 'existing', 'person' => $athlete->takeone_id, 'clubName' => $club ?: null];
            $row['name'] = $athlete->user?->full_name ?: ($name ?: $athlete->takeone_id);
            if ($entered->has($athlete->id)) {
                return ['status' => 'entered', 'problems' => ['Already entered in this event.']] + $row;
            }
            $key = 'a'.$athlete->id;
        } else {
            $row['kind'] = 'new';
            if ($name === '') {
                $stop('The athlete\'s name is missing.');
            }
            if ($club === '') {
                $stop('No club — put the club on this row or on a row above.');
            } else {
                $hit = $clubs[$this->norm($club)] ?? null;
                $seen['club:'.$this->norm($club)] ??= $club;   // a new club reads as its first spelling
                $row['clubName'] = $hit['name'] ?? $seen['club:'.$this->norm($club)];
                $hit ? $row['club'] = $hit['key'] : $row['newClub'] = true;
            }
            $g = $this->norm($get('gender'));
            $row['gender'] = in_array($g, ['m', 'male', 'ذكر', 'boy'], true) ? 'Male' : (in_array($g, ['f', 'female', 'انثي', 'انثى', 'girl'], true) ? 'Female' : null);
            if ($g !== '' && ! $row['gender']) {
                $note('Gender "'.$get('gender').'" ignored.');
            }
            [$row['dob'], $dobNote] = $this->date($raw[$col['dob'] ?? -1] ?? null);
            if ($dobNote) {
                $note($dobNote);
            }
            $nat = $get('nat');
            $row['nat'] = $nat !== '' ? Countries::iso2($nat) : null;
            if ($nat !== '' && ! $row['nat']) {
                $note('Nationality "'.$nat.'" not recognised — left blank.');
            }
            $row['natId'] = $natId !== '' ? $natId : null;
            $key = 'n'.$this->norm($name).'|'.$this->norm($club);
        }
        if (isset($seen[$key])) {
            $stop('Listed twice in this file (row '.$seen[$key].').');
        }
        $seen[$key] = $line;

        $row['status'] = $row['problems'] === [] ? 'ready' : 'error';

        return $row;
    }

    /** @return array{0:?string, 1:?string} the date, and a note when something was left out */
    private function date(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [null, null];
        }
        if (is_numeric($v) && (int) $v == $v && (int) $v >= 1900 && (int) $v <= 2100) {
            return [null, 'Birth year '.(int) $v.' only — date left blank.'];
        }
        try {
            $d = is_numeric($v) ? \Carbon\Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))
                : \Carbon\Carbon::createFromFormat('!Y-m-d', preg_replace('#[/.]#', '-', trim((string) $v)));
        } catch (\Throwable) {
            $d = null;
        }
        if (! $d || ! $d->isPast() || $d->year < 1900) {
            return [null, 'Date of birth "'.$v.'" not readable — left blank.'];
        }

        return [$d->toDateString(), null];
    }

    // -------------------------------------------------------------- import

    /**
     * Enter the rows the preview found ready. New clubs first (pending, with
     * their coach), then each athlete through FederationEnrolment.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{results: array<int, array{line:int, ok:bool, message:string}>, clubs: int}
     */
    public function import(User $by, array $access, FederationEvent $e, array $rows): array
    {
        $rows = array_slice($rows, 0, self::MAX_ROWS);
        $clubs = $this->clubMap($access);
        $made = 0;
        foreach ($rows as $i => $r) {
            if (! empty($r['person']) || ! empty($r['club']) || empty($r['clubName'])) {
                continue;
            }
            $k = $this->norm((string) $r['clubName']);
            if (! isset($clubs[$k])) {
                if ($made >= self::MAX_NEW_CLUBS) {
                    continue;
                }
                $app = FederationClubApplication::create([
                    'federation_id' => $access['root']->id,
                    'federation_event_id' => $e->id,
                    'name' => mb_substr(trim((string) $r['clubName']), 0, 150),
                    'coach' => ! empty($r['coach']) ? mb_substr(trim((string) $r['coach']), 0, 150) : null,
                    'logo_shape' => 'circle',
                    'invite_token' => Str::random(48),
                    'status' => FederationClubApplication::STATUS_PENDING,
                ]);
                activity()->causedBy($by)->performedOn($app)->withProperties(['event' => $e->uuid])->log('federation.club_added_by_import');
                $clubs[$k] = ['key' => $this->registry->clubKey($app), 'name' => $app->name];
                $made++;
            }
            $rows[$i]['club'] = $clubs[$k]['key'];
        }

        $res = [];
        foreach ($rows as $r) {
            $payload = ['event' => $e->uuid, 'weight' => $r['weight'] ?? null, 'types' => (array) ($r['types'] ?? [])];
            if (! empty($r['person'])) {
                $payload['person'] = (string) $r['person'];
            } else {
                $payload['club'] = (string) ($r['club'] ?? '');
                $payload['new'] = [
                    'name' => (string) ($r['name'] ?? ''), 'gender' => in_array($r['gender'] ?? null, ['Male', 'Female'], true) ? $r['gender'] : null,
                    'dob' => $r['dob'] ?? null, 'nat' => $r['nat'] ?? null, 'natId' => $r['natId'] ?? null,
                ];
            }
            $x = $this->enrolment->enrol($by, $access, $payload, true);
            $res[] = ['line' => (int) ($r['line'] ?? 0), 'ok' => $x['ok'], 'message' => $x['message']];
        }

        return ['results' => $res, 'clubs' => $made];
    }

    // ------------------------------------------------------------- helpers

    /** @var array<string, array<string, FederationAthlete>> */
    private array $rosters = [];

    /** A club's current athletes, by normalised name (ambiguous names are left out). */
    private function rosterByName(string $clubKey): array
    {
        if (isset($this->rosters[$clubKey])) {
            return $this->rosters[$clubKey];
        }
        $out = [];
        $dupes = [];
        $club = $this->registry->clubFromKey($clubKey, $this->idsCache ??= collect());
        if ($club) {
            foreach ($this->registry->roster($club) as $a) {
                $n = $this->norm((string) ($a->user?->full_name ?: $a->user?->name));
                if ($n === '') {
                    continue;
                }
                isset($out[$n]) ? $dupes[$n] = true : $out[$n] = $a;
            }
        }

        return $this->rosters[$clubKey] = array_diff_key($out, $dupes);
    }

    private ?Collection $idsCache = null;

    /** Compared the way names are actually written — see App\Federations\Support\ClubName. */
    private function norm(string $s): string
    {
        return \App\Federations\Support\ClubName::norm($s);
    }

    private function clean(mixed $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    /** @return array<int, string> the event's per-athlete categories */
    private function types(FederationEvent $e): array
    {
        return collect($this->registry->eventForInvite($e)['fees'] ?? [])->where('unit', 'athlete')->pluck('type')->values()->all();
    }

    /** @return array<string, array{key:string, name:string}> normalised name => club */
    private function clubMap(array $access): array
    {
        $out = [];
        foreach ($this->registry->clubsOf($this->ids($access)) as $c) {
            $row = $this->registry->club($c, false);
            $out[$this->norm((string) $row['name'])] ??= ['key' => $row['id'], 'name' => (string) $row['name']];
        }

        return $out;
    }

    private function enteredIds(FederationEvent $e): Collection
    {
        return FederationEntryAthlete::where('status', 'Active')
            ->whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))
            ->pluck('federation_athlete_id')->flip();
    }

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }
}
