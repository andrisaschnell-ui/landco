<?php
// php/pages/payroll_payslip.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_sheet.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$row_no = (int)($_GET['row'] ?? 0);

$rows = payroll_load_rows($month, $year);
if (!isset($rows[$row_no])) {
    http_response_code(404);
    echo 'Payslip not found.';
    exit;
}

$r = $rows[$row_no];
$name = trim((string)($r['name'] ?? 'Employee'));
$safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
$filename = 'Payslip_' . $safe_name . '_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.docx';

$lines = [
    'PAYSLIP',
    'Employee: ' . $name,
    'Role: ' . ($r['role'] ?? ''),
    'House: ' . ($r['house'] ?? ''),
    'Period: ' . month_name($month) . ' ' . $year,
    '',
    'Salario Base 2024: ' . number_format((float)$r['salario_base_2024'], 2, '.', ''),
    'Salario Base 2025: ' . number_format((float)$r['salario_base_2025'], 2, '.', ''),
    'Alimentacao: ' . number_format((float)$r['alimentacao'], 2, '.', ''),
    'Back Payment: ' . number_format((float)$r['back_pay'], 2, '.', ''),
    'Dias de Trabalho: ' . number_format((float)$r['dias_trabalho'], 2, '.', ''),
    'Salario Mensal: ' . number_format((float)$r['salario_mensal'], 2, '.', ''),
    'Nightshift Hours: ' . number_format((float)$r['nightshift_hours'], 2, '.', ''),
    '25% Guardas: ' . number_format((float)$r['guardas_25'], 2, '.', ''),
    'Horas 1.5: ' . number_format((float)$r['horas_1_5'], 2, '.', ''),
    'Valor 1.5: ' . number_format((float)$r['valor_1_5'], 2, '.', ''),
    'Horas 2: ' . number_format((float)$r['horas_2'], 2, '.', ''),
    'Valor 2: ' . number_format((float)$r['valor_2'], 2, '.', ''),
    'Premios: ' . number_format((float)$r['premios'], 2, '.', ''),
    'Gratificacoes: ' . number_format((float)$r['gratificacoes'], 2, '.', ''),
    'Dias de Ferias: ' . number_format((float)$r['dias_ferias'], 2, '.', ''),
    'Feria Montante: ' . number_format((float)$r['feria_montante'], 2, '.', ''),
    'Total de Remuneração Mensal: ' . number_format((float)$r['total_remuneracao'], 2, '.', ''),
    'Salário adiantado: ' . number_format((float)$r['salario_adiantado'], 2, '.', ''),
    'IRPS: ' . number_format((float)$r['irps'], 2, '.', ''),
    'Divida: ' . number_format((float)$r['divida'], 2, '.', ''),
    'INSS: ' . number_format((float)$r['inss'], 2, '.', ''),
    'SIND: ' . number_format((float)$r['sind'], 2, '.', ''),
    'Total Deductions: ' . number_format((float)$r['total_deductions'], 2, '.', ''),
    'Salario Liquido: ' . number_format((float)$r['salario_liquido'], 2, '.', ''),
];

function xml_escape(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$paras = '';
foreach ($lines as $line) {
    if (trim($line) === '') {
        $paras .= '<w:p/>';
        continue;
    }
    $paras .= '<w:p><w:r><w:t xml:space="preserve">' . xml_escape($line) . '</w:t></w:r></w:p>';
}

$document_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:body>' . $paras
    . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
    . '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>'
    . '</w:sectPr></w:body></w:document>';

$content_types_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '</Types>';

$rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

$word_rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

$tmp = tempnam(sys_get_temp_dir(), 'payslip_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $content_types_xml);
$zip->addFromString('_rels/.rels', $rels_xml);
$zip->addFromString('word/document.xml', $document_xml);
$zip->addFromString('word/_rels/document.xml.rels', $word_rels_xml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;
