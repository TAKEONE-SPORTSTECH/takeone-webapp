<?php

namespace App\Events\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Divisions from a spreadsheet — the organiser's own weight classes, typed
 * once in Excel instead of one form at a time (owner, 2026-10-15; reference
 * file: drafts/Categories - Our Own Categories.xlsx).
 *
 * This class only READS a file and WRITES a template. It decides nothing
 * about whether a row is a valid division and creates nothing: every row is
 * judged by the same rules as a division typed into the editor
 * (PersonalEventController::validateDivision) and created by the same code.
 *
 * ⚠️ Nothing here names a division. A row with no Name is left blank and the
 * caller gives it the group letter ("Group A") — never an age word, a weight
 * or anything else taken from a sport's own table (owner, 2026-10-15).
 *
 * Columns are found by their HEADER, in any order, so the reference file's
 * own layout (Gender · Min Age · Max Age · Min Weight · Max Weight · People
 * Count) imports as it is. A column we do not use ("People Count") is named
 * back as ignored rather than guessed at.
 */
class DivisionImport
{
    /** Rows per file. A championship runs a few hundred divisions at most. */
    public const MAX_ROWS = 300;

    /** Upload ceiling, in kilobytes. The reference file is 16 KB. */
    public const MAX_KB = 1024;

    /** The template's columns, in order. */
    public const COLUMNS = ['Name', 'Gender', 'Min Age', 'Max Age', 'Min Weight', 'Max Weight', 'Capacity'];

    /** Header text (normalised) → field. */
    private const HEADERS = [
        'name' => 'name', 'division' => 'name', 'divisionname' => 'name', 'group' => 'name', 'groupname' => 'name', 'title' => 'name',
        'gender' => 'gender', 'sex' => 'gender',
        'minage' => 'min_age', 'agemin' => 'min_age', 'agefrom' => 'min_age', 'minimumage' => 'min_age', 'fromage' => 'min_age',
        'maxage' => 'max_age', 'agemax' => 'max_age', 'ageto' => 'max_age', 'maximumage' => 'max_age', 'toage' => 'max_age',
        'minweight' => 'min_weight', 'weightmin' => 'min_weight', 'minkg' => 'min_weight', 'weightfrom' => 'min_weight', 'minimumweight' => 'min_weight',
        'maxweight' => 'max_weight', 'weightmax' => 'max_weight', 'maxkg' => 'max_weight', 'weightto' => 'max_weight', 'maximumweight' => 'max_weight',
        'capacity' => 'capacity', 'maxentrants' => 'capacity', 'cap' => 'capacity',
    ];

    /** What a Gender cell may say. Blank or mix(ed) is a mixed group. */
    private const GENDERS = [
        'male' => 'Male', 'm' => 'Male', 'boy' => 'Male', 'boys' => 'Male', 'men' => 'Male',
        'female' => 'Female', 'f' => 'Female', 'girl' => 'Female', 'girls' => 'Female', 'women' => 'Female',
        'mix' => null, 'mixed' => null, 'both' => null, 'any' => null, 'open' => null, '' => null,
    ];

    /**
     * Read the first sheet of an .xlsx or .csv file.
     *
     * The type is decided by the file's BYTES (IOFactory::identify), and only
     * the two readers we mean to support are allowed — a file renamed to .xlsx
     * that is something else is refused, not opened with whatever reader
     * happens to accept it.
     *
     * @return array{ok: bool, message?: string, rows?: list<array>, ignored?: list<string>}
     */
    public function read(string $path): array
    {
        try {
            $type = IOFactory::identify($path, [IOFactory::READER_XLSX, IOFactory::READER_CSV]);
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
            $grid = $sheet->toArray(null, true, false, false);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'That file could not be read. Upload the template as an Excel (.xlsx) or CSV file.'];
        }

        // The header is the first row that names at least two columns we know.
        $map = null;
        $headerAt = 0;
        foreach (array_slice($grid, 0, 10, true) as $i => $row) {
            $found = [];
            foreach ($row as $col => $cell) {
                $field = self::HEADERS[$this->key($cell)] ?? null;
                if ($field !== null && ! in_array($field, $found, true)) {
                    $found[$col] = $field;
                }
            }
            if (count($found) >= 2) {
                $map = $found;
                $headerAt = $i;
                break;
            }
        }
        if ($map === null) {
            return ['ok' => false, 'message' => 'No header row found. The first row should read: '.implode(', ', self::COLUMNS).'.'];
        }

        $ignored = [];
        foreach ($grid[$headerAt] as $col => $cell) {
            $text = trim((string) $cell);
            if ($text !== '' && ! isset($map[$col])) {
                $ignored[] = mb_substr($text, 0, 40);
            }
        }

        $rows = [];
        foreach (array_slice($grid, $headerAt + 1, null, true) as $i => $row) {
            $raw = [];
            foreach ($map as $col => $field) {
                $raw[$field] = $row[$col] ?? null;
            }
            if (collect($raw)->every(fn ($v) => trim((string) $v) === '')) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                return ['ok' => false, 'message' => 'That file has more than '.self::MAX_ROWS.' divisions. Split it into smaller files.'];
            }
            $rows[] = $this->row($raw, $i + 1);
        }

        if ($rows === []) {
            return ['ok' => false, 'message' => 'The file has a header but no divisions under it.'];
        }

        return ['ok' => true, 'rows' => $rows, 'ignored' => array_values(array_unique($ignored))];
    }

    /**
     * One row, normalised into the fields a division takes. Problems that are
     * about the CELL (a gender we cannot read, text where a number goes) are
     * recorded here; whether the numbers make a valid division is the
     * division rules' job, run by the caller.
     */
    private function row(array $raw, int $line): array
    {
        $errors = [];

        $genderText = $this->key($raw['gender'] ?? '');
        if (array_key_exists($genderText, self::GENDERS)) {
            $gender = self::GENDERS[$genderText];
        } else {
            $gender = null;
            $errors[] = 'Gender must be male, female or mix.';
        }

        $num = function (string $field, string $label, bool $int) use ($raw, &$errors) {
            $v = trim((string) ($raw[$field] ?? ''));
            if ($v === '') {
                return null;
            }
            if (! is_numeric($v)) {
                $errors[] = $label.' must be a number.';

                return null;
            }

            // Excel keeps 65.1 as 65.09999999999999 — read it as people typed it.
            return $int ? (int) round((float) $v) : round((float) $v, 2);
        };

        return [
            'line' => $line,
            'name' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($raw['name'] ?? ''))), 0, 80),
            'gender' => $gender,
            'min_age' => $num('min_age', 'Min Age', true),
            'max_age' => $num('max_age', 'Max Age', true),
            'min_weight' => $num('min_weight', 'Min Weight', false),
            'max_weight' => $num('max_weight', 'Max Weight', false),
            'capacity' => $num('capacity', 'Capacity', true),
            'errors' => $errors,
        ];
    }

    /** "Min Age" / "min_age" / "MIN-AGE" → "minage". */
    private function key(mixed $cell): string
    {
        return preg_replace('/[^a-z]/', '', strtolower(trim((string) $cell)));
    }

    /**
     * The template, as .xlsx bytes: the columns, example rows, and a second
     * sheet saying how to fill it in.
     */
    public function template(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Divisions');
        $sheet->fromArray([self::COLUMNS,
            ['', 'male', 5, 6, 17, 23, ''],
            ['', 'female', 13, 16, 45.4, 48.3, ''],
            ['', 'mix', 8, 9, 34.8, 39.8, ''],
        ]);
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setWidth($c === 'A' ? 28 : 13);
        }
        $sheet->freezePane('A2');

        $help = $book->createSheet()->setTitle('How to fill');
        $help->fromArray([
            ['Column', 'What to write'],
            ['Name', 'Optional. Leave it blank and the division is called Group A, Group B, … in order.'],
            ['Gender', 'male, female, or mix for a mixed group.'],
            ['Min Age / Max Age', 'Whole years, from 2 to 100. Leave one blank for no limit on that side.'],
            ['Min Weight / Max Weight', 'Kilograms, from 10 to 300. Decimals are fine (45.4). Leave one blank for no limit.'],
            ['Capacity', 'Optional. The most entrants the division takes. Blank means no cap.'],
            ['', ''],
            ['', 'Only the first sheet is read. A division already in the event with the same gender, ages and weights is skipped.'],
        ]);
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(26);
        $help->getColumnDimension('B')->setWidth(100);
        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }
}
