<?php
// php/pages/payroll_export_excel.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_sheet.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$columns = payroll_columns();
$rows = payroll_load_rows($month, $year);

$filename = 'Payroll_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
echo '<Worksheet ss:Name="Payroll"><Table>';

// Header row
echo '<Row>';
foreach ($columns as $c) {
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($c['label']) . '</Data></Cell>';
}
echo '</Row>';

foreach ($rows as $row_no => $r) {
    echo '<Row>';
    foreach ($columns as $c) {
        $key = $c['key'];
        $val = $r[$key] ?? '';
        $type = ($c['type'] === 'number') ? 'Number' : 'String';
        if ($type === 'Number' && $val === '') {
            $val = 0;
        }
        echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string)$val) . '</Data></Cell>';
    }
    echo '</Row>';
}

echo '</Table></Worksheet></Workbook>';
exit;
