<?php
// php/pages/payroll_template_excel.php
require_once __DIR__ . '/../includes/payroll_sheet.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$columns = payroll_columns();
$template_rows = payroll_template_rows($month, $year);

function col_letter(int $idx): string {
    $idx += 1;
    $s = '';
    while ($idx > 0) {
        $mod = ($idx - 1) % 26;
        $s = chr(65 + $mod) . $s;
        $idx = (int)(($idx - 1) / 26);
    }
    return $s;
}

$col_map = [];
foreach ($columns as $i => $c) {
    $col_map[$c['key']] = col_letter($i);
}

function cell_ref(string $key, int $row, array $col_map): string {
    return $col_map[$key] . $row;
}

function xml_escape(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function make_cell(string $col, int $row, $val, ?string $formula = null): string {
    $r = $col . $row;
    $attrs = ' r="' . $r . '"';
    if ($formula !== null) {
        $attrs .= ' ';
    }
    if ($formula !== null) {
        $attrs .= '><f>' . xml_escape(ltrim($formula, '=')) . '</f>';
    } else {
        $attrs .= '>';
    }
    if ($val !== '' && $val !== null) {
        if (is_numeric($val)) {
            $attrs .= '<v>' . $val . '</v>';
        } else {
            $attrs = ' r="' . $r . '" t="s"' . '>' . $attrs . '<v>' . $val . '</v>';
        }
    }
    return '<c' . $attrs . '</c>';
}

$shared_strings = [];
function ss_index(string $s, array &$shared_strings): int {
    $idx = array_search($s, $shared_strings, true);
    if ($idx === false) {
        $shared_strings[] = $s;
        return count($shared_strings) - 1;
    }
    return $idx;
}

// Build sheet XML rows
$rows_xml = [];

// Row 4: title
$r = 4;
$cells = [];
$cells[] = '<c r="A' . $r . '" t="s"><v>' . ss_index('FOLHA DE SALARIOS', $shared_strings) . '</v></c>';
$rows_xml[] = '<row r="' . $r . '">' . implode('', $cells) . '</row>';

// Row 5: metadata (company, NUIT, year)
$r = 5;
$cells = [];
$cells[] = '<c r="H' . $r . '" t="s"><v>' . ss_index('EMPRESA :', $shared_strings) . '</v></c>';
$cells[] = '<c r="M' . $r . '" t="s"><v>' . ss_index('LANDCO LIMITADA', $shared_strings) . '</v></c>';
$cells[] = '<c r="U' . $r . '" t="s"><v>' . ss_index('NUIT :', $shared_strings) . '</v></c>';
$cells[] = '<c r="W' . $r . '" t="s"><v>' . ss_index('400122792', $shared_strings) . '</v></c>';
$cells[] = '<c r="AF' . $r . '" t="s"><v>' . ss_index('ANO :', $shared_strings) . '</v></c>';
$cells[] = '<c r="AG' . $r . '" t="s"><v>' . ss_index((string)$year, $shared_strings) . '</v></c>';
$rows_xml[] = '<row r="' . $r . '">' . implode('', $cells) . '</row>';

// Header row 6
$r = 6;
$cells = [];
foreach ($columns as $i => $c) {
    $col = col_letter($i);
    $cells[] = '<c r="' . $col . $r . '" t="s"><v>' . ss_index($c['label'], $shared_strings) . '</v></c>';
}
$rows_xml[] = '<row r="' . $r . '">' . implode('', $cells) . '</row>';

// Data rows 7-31
for ($r = 7; $r <= 31; $r++) {
    $cells = [];
    foreach ($columns as $i => $c) {
        $key = $c['key'];
        $col = col_letter($i);
        $val = $template_rows[$r][$key] ?? '';
        $formula = null;

        if ($r <= 27) {
            if ($key === 'salario_mensal') {
                $formula = '=' . cell_ref('salario_base_2025', $r, $col_map) . '/30*' . cell_ref('dias_trabalho', $r, $col_map)
                    . '+' . cell_ref('alimentacao', $r, $col_map) . '+' . cell_ref('back_pay', $r, $col_map);
            } elseif ($key === 'guardas_25') {
                $formula = '=IF(' . cell_ref('dias_trabalho', $r, $col_map) . '=0,0,'
                    . cell_ref('salario_mensal', $r, $col_map) . '/' . cell_ref('dias_trabalho', $r, $col_map)
                    . '/8*' . cell_ref('nightshift_hours', $r, $col_map) . '*0.25)';
            } elseif ($key === 'valor_1_5') {
                $formula = '=' . cell_ref('salario_base_2024', $r, $col_map) . '/192*' . cell_ref('horas_1_5', $r, $col_map) . '*1.5';
            } elseif ($key === 'horas_2' && $r === 8) {
                $formula = '=4.5*8';
            } elseif ($key === 'horas_2' && $r === 9) {
                $formula = '=5*8';
            } elseif ($key === 'valor_2') {
                $formula = '=' . cell_ref('salario_mensal', $r, $col_map) . '/192*' . cell_ref('horas_2', $r, $col_map) . '*2';
            } elseif ($key === 'feria_montante') {
                $formula = '=' . cell_ref('salario_mensal', $r, $col_map) . '/30*' . cell_ref('dias_ferias', $r, $col_map);
            } elseif ($key === 'total_remuneracao') {
                $formula = '=' . cell_ref('salario_mensal', $r, $col_map) . '+' . cell_ref('guardas_25', $r, $col_map)
                    . '+' . cell_ref('valor_1_5', $r, $col_map) . '+' . cell_ref('valor_2', $r, $col_map)
                    . '+' . cell_ref('premios', $r, $col_map) . '+' . cell_ref('gratificacoes', $r, $col_map)
                    . '+' . cell_ref('feria_montante', $r, $col_map);
            } elseif ($key === 'inss') {
                $formula = '=' . cell_ref('total_remuneracao', $r, $col_map) . '*3%';
            } elseif ($key === 'sind') {
                $formula = '=' . cell_ref('total_remuneracao', $r, $col_map) . '*0.01';
            } elseif ($key === 'total_deductions') {
                $formula = '=' . cell_ref('salario_adiantado', $r, $col_map) . '+' . cell_ref('irps', $r, $col_map)
                    . '+' . cell_ref('divida', $r, $col_map) . '+' . cell_ref('inss', $r, $col_map) . '+' . cell_ref('sind', $r, $col_map);
            } elseif ($key === 'salario_liquido') {
                $formula = '=' . cell_ref('total_remuneracao', $r, $col_map) . '-' . cell_ref('total_deductions', $r, $col_map);
            } elseif ($key === 'salario_adiantado' && $r === 14) {
                $formula = '=2500+3000';
            } elseif ($key === 'salario_adiantado' && $r === 21) {
                $formula = '=' . cell_ref('valor_1_5', $r, $col_map) . '+' . cell_ref('valor_2', $r, $col_map);
            }
        }

        if ($r === 28) {
            if (in_array($key, ['salario_base_2024','salario_base_2025','dias_trabalho','salario_mensal','rubrica','ah','no','house','name','role'], true)) {
                $val = '';
            } else {
                $formula = '=SUM(' . $col_map[$key] . '7:' . $col_map[$key] . '27)';
            }
        }
        if ($r === 29) {
            if ($key === 'inss') {
                $formula = '=' . cell_ref('total_remuneracao', 28, $col_map) . '*0.07';
            } elseif ($key === 'rubrica') {
                $formula = '=' . cell_ref('rubrica', 28, $col_map) . '*0.04';
            } else {
                $val = '';
            }
        }
        if ($r === 30) {
            $val = '';
            $formula = null;
        }
        if ($r === 31) {
            if ($key === 'rubrica') {
                $formula = '=SUM(' . $col_map['rubrica'] . '28:' . $col_map['rubrica'] . '30)';
            } elseif ($key === 'salario_liquido') {
                $formula = '=SUM(' . $col_map['salario_liquido'] . '28:' . $col_map['salario_liquido'] . '30)';
            } elseif ($key === 'ah') {
                $formula = '=SUM(' . $col_map['ah'] . '7:' . $col_map['ah'] . '30)';
            } else {
                $val = '';
            }
        }

        if ($val !== '' && !is_numeric($val)) {
            $val = ss_index((string)$val, $shared_strings);
        }
        $cells[] = make_cell($col, $r, $val, $formula);
    }
    $rows_xml[] = '<row r="' . $r . '">' . implode('', $cells) . '</row>';
}

$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
  . '<sheetData>' . implode('', $rows_xml) . '</sheetData>'
  . '</worksheet>';

$sst_items = '';
foreach ($shared_strings as $s) {
    $sst_items .= '<si><t>' . xml_escape($s) . '</t></si>';
}
$shared_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared_strings) . '" uniqueCount="' . count($shared_strings) . '">'
  . $sst_items . '</sst>';

$workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
  . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
  . '<sheets><sheet name="Folha de salarios" sheetId="1" r:id="rId1"/></sheets>'
  . '</workbook>';

$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
  . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
  . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
  . '</Relationships>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
  . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
  . '</Relationships>';

$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
  . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
  . '<Default Extension="xml" ContentType="application/xml"/>'
  . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
  . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
  . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
  . '</Types>';

$filename = 'BDO_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '_Template.xlsx';
$tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $content_types);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('xl/workbook.xml', $workbook_xml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
$zip->addFromString('xl/sharedStrings.xml', $shared_xml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit;
