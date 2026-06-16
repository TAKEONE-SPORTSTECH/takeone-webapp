<?php
/**
 * Script: create_import_template.php
 * Creates the member import template xlsx that clubs download,
 * fill in, and upload back to the platform.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('TAKEONE SPORTSTECH')
    ->setTitle('Member Import Template')
    ->setDescription('Fill in member data and upload via the Members tab in your club admin panel.');

// ─────────────────────────────────────────────────────────────
// SHEET 1 — Import Data Sheet
// ─────────────────────────────────────────────────────────────
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Members');

// ── Colors ──────────────────────────────────────────────────
$primaryBg    = '3D2D8C'; // deep purple (primary color darker for dark headers)
$primaryLight = 'EDE9FC'; // light purple for required rows
$optionalBg   = 'F3F4F6'; // grey for optional header
$sampleBg     = 'FAFAFA';
$requiredStar = 'CC0000';
$borderColor  = 'D1D5DB';
$white        = 'FFFFFF';
$textDark     = '111827';
$textGray     = '6B7280';

// ── Column Definitions ───────────────────────────────────────
// [ key, header, Arabic sub-header, required, note, width ]
$columns = [
    ['first_name',     'First Name',        'الاسم الأول',        true,  '',                                         22],
    ['middle_name',    'Middle Name',        'اسم الأب',           false, '',                                         22],
    ['last_name',      'Last Name',          'اسم العائلة',        true,  '',                                         22],
    ['gender',         'Gender',             'الجنس',              true,  'Male / Female',                            14],
    ['date_of_birth',  'Date of Birth',      'تاريخ الميلاد',      true,  'YYYY-MM-DD  e.g. 2005-03-15',             22],
    ['phone',          'Phone',              'رقم الهاتف',         true,  'Include country code  e.g. +97366123456', 24],
    ['email',          'Email',              'البريد الإلكتروني',  false, '',                                         28],
    ['cpr_id',         'CPR / ID Number',    'رقم البطاقة الشخصية',false, '',                                         20],
    ['height_cm',      'Height (cm)',         'الطول (سم)',         false, 'Numeric only  e.g. 165',                  16],
    ['weight_kg',      'Weight (kg)',         'الوزن (كغ)',         false, 'Numeric only  e.g. 68',                   16],
    ['health_notes',   'Health Condition',   'الحالة الصحية',      false, 'Leave blank if none',                     30],
    ['emergency_1',    'Emergency Contact 1','رقم الطوارئ 1',      false, 'Include country code',                    24],
    ['emergency_2',    'Emergency Contact 2','رقم الطوارئ 2',      false, 'Include country code',                    24],
    ['package_name',   'Package Name',       'اسم الباقة',         false, 'Must match exactly as shown in the system',32],
];

$numCols = count($columns);
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

// ── Row 1: Title Banner ──────────────────────────────────────
$sheet->mergeCells('A1:' . $lastColLetter . '1');
$sheet->setCellValue('A1', 'TAKEONE — Member Import Template  |  قالب استيراد الأعضاء');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $white]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryBg]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// ── Row 2: Instructions ──────────────────────────────────────
$sheet->mergeCells('A2:' . $lastColLetter . '2');
$sheet->setCellValue('A2',
    '★ Required fields are marked with (*). Do not change column headers. Date format: YYYY-MM-DD. Delete sample rows before uploading.'
);
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '7C3AED']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryLight]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true, 'indent' => 1],
]);
$sheet->getRowDimension(2)->setRowHeight(22);

// ── Row 3: Column Headers ────────────────────────────────────
foreach ($columns as $ci => [$key, $header, $arHeader, $required, $note, $width]) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $label = $required ? $header . ' *' : $header;
    $sheet->setCellValue($colLetter . '3', $label);

    $bgColor  = $required ? $primaryBg : '4B5563';
    $sheet->getStyle($colLetter . '3')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $white]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => $white]]],
    ]);
    $sheet->getColumnDimensionByColumn($ci + 1)->setWidth($width);
}
$sheet->getRowDimension(3)->setRowHeight(24);

// ── Row 4: Arabic sub-headers ────────────────────────────────
foreach ($columns as $ci => [$key, $header, $arHeader, $required, $note, $width]) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $sheet->setCellValue($colLetter . '4', $arHeader);
    $sheet->getStyle($colLetter . '4')->applyFromArray([
        'font'      => ['size' => 9, 'color' => ['rgb' => $white]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $required ? '5B21B6' : '6B7280']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'readOrder'  => 2], // RTL
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => $white]]],
    ]);
}
$sheet->getRowDimension(4)->setRowHeight(18);

// ── Row 5: Note / format hints ───────────────────────────────
foreach ($columns as $ci => [$key, $header, $arHeader, $required, $note, $width]) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    if ($note !== '') {
        $sheet->setCellValue($colLetter . '5', $note);
    }
    $sheet->getStyle($colLetter . '5')->applyFromArray([
        'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => $textGray]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => $borderColor]]],
    ]);
}
$sheet->getRowDimension(5)->setRowHeight(15);

// ── Rows 6–8: Sample data ─────────────────────────────────────
$sampleData = [
    ['Ahmed',  'Khalil', 'Al Rashidi',  'Male',   '2008-05-14', '+97366112233', 'ahmed@example.com',  '850123456', '165', '62',  '',                  '+97366445566', '',             'Morning Class'],
    ['Fatema', 'Ali',    'Al Khamis',   'Female', '2010-11-02', '+97339887766', 'fatema@example.com', '860234567', '152', '48',  'Mild asthma',        '+97377112233', '+97399887766', ''],
    ['Yousuf', 'Hassan', 'Al Mousa',    'Male',   '2015-03-27', '+97366334455', '',                   '',          '',    '',   '',                   '',             '',             ''],
];

foreach ($sampleData as $ri => $row) {
    $excelRow = 6 + $ri;
    foreach ($row as $ci => $value) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet->setCellValueExplicit($colLetter . $excelRow, $value,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
    $sheet->getStyle('A' . $excelRow . ':' . $lastColLetter . $excelRow)->applyFromArray([
        'font'      => ['size' => 9, 'color' => ['rgb' => $textGray], 'italic' => true],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => $borderColor]]],
    ]);
    $sheet->getRowDimension($excelRow)->setRowHeight(18);
}

// ── Rows 9+: Empty data rows with light style ────────────────
for ($r = 9; $r <= 200; $r++) {
    $bgRow = ($r % 2 === 0) ? 'F9FAFB' : $white;
    $sheet->getStyle('A' . $r . ':' . $lastColLetter . $r)->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRow]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                       'color' => ['rgb' => $borderColor]]],
        'font'    => ['size' => 10],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(18);
}

// ── Data Validation: Gender dropdown ────────────────────────
$genderColIdx  = 4; // 1-based → column D
$genderColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($genderColIdx);
for ($r = 6; $r <= 200; $r++) {
    $validation = $sheet->getCell($genderColLetter . $r)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST)
               ->setErrorStyle(DataValidation::STYLE_INFORMATION)
               ->setAllowBlank(false)
               ->setShowDropDown(false)
               ->setFormula1('"Male,Female"');
}

// ── Freeze panes (header rows + first col) ───────────────────
$sheet->freezePane('B6');

// ── Sheet protection hint (lock rows 1-5) ────────────────────
// We just make the header rows visually distinct; actual lock would need a password.

// ─────────────────────────────────────────────────────────────
// SHEET 2 — Instructions / Legend
// ─────────────────────────────────────────────────────────────
$infoSheet = new Worksheet($spreadsheet, 'Instructions');
$spreadsheet->addSheet($infoSheet);

$infoSheet->mergeCells('A1:D1');
$infoSheet->setCellValue('A1', 'Member Import — Field Guide');
$infoSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $white]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryBg]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$infoSheet->getRowDimension(1)->setRowHeight(30);

$instructions = [
    ['Field',            'Required', 'Format / Notes'],
    ['First Name',       'Yes',      'Member\'s first name (Arabic or English)'],
    ['Middle Name',      'No',       'Father\'s name — optional'],
    ['Last Name',        'Yes',      'Family / surname'],
    ['Gender',           'Yes',      'Exactly: Male  or  Female'],
    ['Date of Birth',    'Yes',      'YYYY-MM-DD  e.g.  2005-03-15'],
    ['Phone',            'Yes',      'Include country code e.g. +97366112233'],
    ['Email',            'No',       'Must be unique on the platform if provided'],
    ['CPR / ID Number',  'No',       'National ID — must be unique if provided'],
    ['Height (cm)',      'No',       'Whole number only e.g. 165'],
    ['Weight (kg)',      'No',       'Whole number only e.g. 68'],
    ['Health Condition', 'No',       'Free text — leave blank if none'],
    ['Emergency Contact 1', 'No',   'Phone with country code'],
    ['Emergency Contact 2', 'No',   'Phone with country code'],
    ['Package Name',     'No',       'If provided must match a package name in your club exactly'],
    ['', '', ''],
    ['IMPORTANT NOTES', '', ''],
    ['1. Delete the yellow sample rows (rows 6–8) before uploading.', '', ''],
    ['2. Do not rename or reorder columns.', '', ''],
    ['3. Do not add extra columns.', '', ''],
    ['4. Rows with a missing First Name, Last Name, Gender, Date of Birth, or Phone will be skipped.', '', ''],
    ['5. If a member with the same email or CPR already exists in the system they will be skipped.', '', ''],
    ['6. Maximum 500 rows per upload.', '', ''],
];

foreach ($instructions as $ri => $row) {
    $excelRow = $ri + 2;
    foreach ($row as $ci => $value) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $infoSheet->setCellValue($colLetter . $excelRow, $value);
    }

    if ($ri === 0) {
        // Header row
        $infoSheet->getStyle('A' . $excelRow . ':C' . $excelRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4B5563']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    } elseif ($row[1] === 'Yes') {
        $infoSheet->getStyle('A' . $excelRow . ':C' . $excelRow)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryLight]],
        ]);
        $infoSheet->getStyle('B' . $excelRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '7C3AED']],
        ]);
    } elseif (str_starts_with($row[0] ?? '', 'IMPORTANT')) {
        $infoSheet->mergeCells('A' . $excelRow . ':C' . $excelRow);
        $infoSheet->getStyle('A' . $excelRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $requiredStar]],
        ]);
    } elseif (str_starts_with($row[0] ?? '', '1.') || str_starts_with($row[0] ?? '', '2.')
           || str_starts_with($row[0] ?? '', '3.') || str_starts_with($row[0] ?? '', '4.')
           || str_starts_with($row[0] ?? '', '5.') || str_starts_with($row[0] ?? '', '6.')) {
        $infoSheet->mergeCells('A' . $excelRow . ':C' . $excelRow);
        $infoSheet->getStyle('A' . $excelRow)->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => $textDark]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF7ED']],
        ]);
        $infoSheet->getStyle('A' . $excelRow)->getAlignment()->setWrapText(true);
        $infoSheet->getRowDimension($excelRow)->setRowHeight(22);
    }
}

$infoSheet->getColumnDimension('A')->setWidth(42);
$infoSheet->getColumnDimension('B')->setWidth(12);
$infoSheet->getColumnDimension('C')->setWidth(55);

// ─────────────────────────────────────────────────────────────
// Save
// ─────────────────────────────────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

$dstPath = __DIR__ . '/../public/files/member-import-template.xlsx';

// Ensure directory exists
if (!is_dir(dirname($dstPath))) {
    mkdir(dirname($dstPath), 0775, true);
}

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($dstPath);

echo "Template saved to: $dstPath\n";
