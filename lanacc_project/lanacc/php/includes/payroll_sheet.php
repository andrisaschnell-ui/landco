<?php
// php/includes/payroll_sheet.php

function payroll_columns(): array {
    return [
        ['key' => 'no', 'label' => 'No', 'type' => 'number', 'editable' => false],
        ['key' => 'house', 'label' => 'HOUSE', 'type' => 'text', 'editable' => true],
        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'editable' => true],
        ['key' => 'role', 'label' => 'Role', 'type' => 'text', 'editable' => true],
        ['key' => 'salario_base_2024', 'label' => 'Salario Base 2024', 'type' => 'number', 'editable' => true],
        ['key' => 'salario_base_2025', 'label' => 'Salario Base 2025', 'type' => 'number', 'editable' => true],
        ['key' => 'alimentacao', 'label' => 'ALIMENTACAO', 'type' => 'number', 'editable' => true],
        ['key' => 'back_pay', 'label' => 'Pagamentos Retroativos (Back Payment)', 'type' => 'number', 'editable' => true],
        ['key' => 'dias_trabalho', 'label' => 'DIAS DE TRABALHO', 'type' => 'number', 'editable' => true],
        ['key' => 'salario_mensal', 'label' => 'SALARIO MENSAL', 'type' => 'number', 'editable' => false],
        ['key' => 'nightshift_hours', 'label' => 'NIGHTSHIFT HOURS', 'type' => 'number', 'editable' => true],
        ['key' => 'guardas_25', 'label' => '25% GUARDAS', 'type' => 'number', 'editable' => false],
        ['key' => 'horas_1_5', 'label' => 'HORAS CALCULADO 1.5', 'type' => 'number', 'editable' => true],
        ['key' => 'valor_1_5', 'label' => 'VALOR PARA TRABALHOS EXTRAORDINARIOS (CALCULADOOS 1.5)', 'type' => 'number', 'editable' => false],
        ['key' => 'horas_2', 'label' => 'HORAS CALCULADO 2', 'type' => 'number', 'editable' => true],
        ['key' => 'valor_2', 'label' => 'VALOR PARA TRABALHOS EXTRAORDINARIO (CALCULADO 2)', 'type' => 'number', 'editable' => false],
        ['key' => 'premios', 'label' => 'PREMIOS', 'type' => 'number', 'editable' => true],
        ['key' => 'gratificacoes', 'label' => 'GRATIFICACOES', 'type' => 'number', 'editable' => true],
        ['key' => 'dias_ferias', 'label' => 'DIAS DE FERIAS', 'type' => 'number', 'editable' => true],
        ['key' => 'feria_montante', 'label' => 'FERIA MONTANTE', 'type' => 'number', 'editable' => false],
        ['key' => 'total_remuneracao', 'label' => 'Total de Remuneração Mensal', 'type' => 'number', 'editable' => false],
        ['key' => 'salario_adiantado', 'label' => 'Salário adiantado (Advance)', 'type' => 'number', 'editable' => true],
        ['key' => 'irps', 'label' => 'IRPS (A)', 'type' => 'number', 'editable' => true],
        ['key' => 'divida', 'label' => 'DIVIDA (A)', 'type' => 'number', 'editable' => true],
        ['key' => 'inss', 'label' => 'INSS (A)', 'type' => 'number', 'editable' => false],
        ['key' => 'sind', 'label' => 'SIND (A)', 'type' => 'number', 'editable' => false],
        ['key' => 'total_deductions', 'label' => 'TOTAL DEDUCTIONS', 'type' => 'number', 'editable' => false],
        ['key' => 'salario_liquido', 'label' => 'SALARIO LIQUIDO', 'type' => 'number', 'editable' => false],
        ['key' => 'arredondamento', 'label' => 'ARREDONDAMENTO', 'type' => 'text', 'editable' => true],
        ['key' => 'rubrica', 'label' => 'RUBRICA', 'type' => 'number', 'editable' => false],
        ['key' => 'ah', 'label' => 'AH', 'type' => 'number', 'editable' => false],
    ];
}

function payroll_admin_keys(): array {
    $keys = [];
    foreach (payroll_columns() as $c) {
        if ($c['editable']) {
            $keys[] = $c['key'];
        }
    }
    return $keys;
}

function payroll_row_numbers(): array {
    return range(7, 31);
}

function payroll_data_rows(): array {
    return range(7, 27);
}

function payroll_is_summary_row(int $row): bool {
    return $row >= 28;
}

function payroll_parse_number($v): float {
    if ($v === null || $v === '') {
        return 0.0;
    }
    if (is_numeric($v)) {
        return (float)$v;
    }
    $raw = (string)$v;
    $clean = preg_replace('/[^0-9,.\-]/', '', $raw);
    $clean = str_replace([' ', ','], ['', '.'], $clean);
    return is_numeric($clean) ? (float)$clean : 0.0;
}

function payroll_admin_defaults(): array {
    return [
        'house' => '',
        'name' => '',
        'role' => '',
        'salario_base_2024' => 0,
        'salario_base_2025' => 0,
        'alimentacao' => 0,
        'back_pay' => 0,
        'dias_trabalho' => 0,
        'nightshift_hours' => 0,
        'horas_1_5' => 0,
        'horas_2' => 0,
        'premios' => 0,
        'gratificacoes' => 0,
        'dias_ferias' => 0,
        'salario_adiantado' => 0,
        'irps' => 0,
        'divida' => 0,
        'arredondamento' => '',
    ];
}

function payroll_clear_admin_inputs(int $month, int $year): array {
    $rows = payroll_load_rows($month, $year);
    $defaults = payroll_admin_defaults();
    foreach (payroll_data_rows() as $row_no) {
        foreach ($defaults as $k => $v) {
            $rows[$row_no][$k] = $v;
        }
    }
    return payroll_save_rows($month, $year, $rows);
}

function payroll_list_bdo_files(string $dir): array {
    if (!is_dir($dir)) {
        return [];
    }
    $result = [];
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($full)) {
            continue;
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx' && $ext !== 'xls') {
            continue;
        }
        if (stripos($entry, 'BDO') === false) {
            continue;
        }
        [$m, $y] = payroll_extract_month_year_from_filename($entry);
        $result[] = [
            'label' => $entry,
            'path' => $full,
            'month' => $m,
            'year' => $y,
        ];
    }
    usort($result, function($a, $b) {
        return strcmp($a['label'], $b['label']);
    });
    return $result;
}

function payroll_extract_month_year_from_filename(string $name): array {
    $month = null;
    $year = null;
    if (preg_match('/\\b(20\\d{2})\\b/', $name, $ym)) {
        $year = (int)$ym[1];
    }
    if (preg_match('/^(\\d{1,2})\\D/', $name, $mm)) {
        $m = (int)$mm[1];
        if ($m >= 1 && $m <= 12) {
            $month = $m;
        }
    }
    return [$month, $year];
}

function payroll_import_from_xlsx(string $path, int $month, int $year): array {
    $input_rows = payroll_parse_xlsx_admin_rows($path);
    return payroll_save_rows($month, $year, $input_rows);
}

function payroll_parse_xlsx_admin_rows(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException('File not found.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open spreadsheet.');
    }

    $workbook_xml = $zip->getFromName('xl/workbook.xml');
    $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (!$workbook_xml || !$rels_xml) {
        $zip->close();
        throw new RuntimeException('Invalid spreadsheet structure.');
    }

    $rels = [];
    $rels_doc = new DOMDocument();
    $rels_doc->loadXML($rels_xml);
    foreach ($rels_doc->getElementsByTagName('Relationship') as $rel) {
        $id = $rel->getAttribute('Id');
        $target = $rel->getAttribute('Target');
        $rels[$id] = 'xl/' . ltrim($target, '/');
    }

    $wb_doc = new DOMDocument();
    $wb_doc->loadXML($workbook_xml);
    $sheets = $wb_doc->getElementsByTagName('sheet');
    $sheet_path = null;
    foreach ($sheets as $sheet) {
        $name = strtolower(trim($sheet->getAttribute('name')));
        if ($name === 'folha de salarios' || $name === 'folha de salario' || $name === 'folhe de salario') {
            $rid = $sheet->getAttribute('r:id');
            $sheet_path = $rels[$rid] ?? null;
            break;
        }
    }
    if (!$sheet_path) {
        $zip->close();
        throw new RuntimeException('Sheet "Folha de salarios" not found.');
    }

    $shared_strings = [];
    $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared_xml) {
        $ss_doc = new DOMDocument();
        $ss_doc->loadXML($shared_xml);
        foreach ($ss_doc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $shared_strings[] = $text;
        }
    }

    $sheet_xml = $zip->getFromName($sheet_path);
    $zip->close();
    if (!$sheet_xml) {
        throw new RuntimeException('Sheet XML not found.');
    }

    $sheet_doc = new DOMDocument();
    $sheet_doc->loadXML($sheet_xml);
    $rows = [];
    foreach ($sheet_doc->getElementsByTagName('row') as $row) {
        $r_idx = (int)$row->getAttribute('r');
        $rows[$r_idx] = [];
        foreach ($row->getElementsByTagName('c') as $cell) {
            $ref = $cell->getAttribute('r');
            $col = preg_replace('/\d+/', '', $ref);
            $type = $cell->getAttribute('t');
            $v = '';
            $v_node = $cell->getElementsByTagName('v')->item(0);
            if ($v_node) {
                $v = $v_node->textContent;
            } else {
                $is_node = $cell->getElementsByTagName('is')->item(0);
                if ($is_node) {
                    $t_node = $is_node->getElementsByTagName('t')->item(0);
                    $v = $t_node ? $t_node->textContent : '';
                    $type = 'inlineStr';
                }
            }
            if ($type === 's') {
                $idx = (int)$v;
                $v = $shared_strings[$idx] ?? '';
            }
            $rows[$r_idx][$col] = $v;
        }
    }

    $header_row = 6;
    $header_map = $rows[$header_row] ?? [];
    $col_by_header = [];
    foreach ($header_map as $col => $label) {
        $key = strtolower(trim((string)$label));
        $col_by_header[$key] = $col;
    }

    $header_to_key = [
        'house' => 'house',
        'name' => 'name',
        'role' => 'role',
        'salario base 2024' => 'salario_base_2024',
        'salario base 2025' => 'salario_base_2025',
        'alimentacao' => 'alimentacao',
        'pagamentos retroativos (back payment)' => 'back_pay',
        'dias de trabalho' => 'dias_trabalho',
        'nightshift hours' => 'nightshift_hours',
        'horas calculado 1.5' => 'horas_1_5',
        'horas calculado 2' => 'horas_2',
        'premios' => 'premios',
        'gratificacoes' => 'gratificacoes',
        'dias de ferias' => 'dias_ferias',
        'salário adiantado (advance)' => 'salario_adiantado',
        'salario adiantado (advance)' => 'salario_adiantado',
        'irps (a)' => 'irps',
        'divida (a)' => 'divida',
        'arredondamento' => 'arredondamento',
    ];

    $input_rows = [];
    foreach (payroll_data_rows() as $row_no) {
        $input_rows[$row_no] = [];
        foreach ($header_to_key as $header_label => $key) {
            $col = $col_by_header[$header_label] ?? null;
            if (!$col) {
                continue;
            }
            $val = $rows[$row_no][$col] ?? '';
            if (in_array($key, ['house', 'name', 'role', 'arredondamento'], true)) {
                $input_rows[$row_no][$key] = trim((string)$val);
            } else {
                $input_rows[$row_no][$key] = payroll_parse_number($val);
            }
        }
    }

    return $input_rows;
}

function payroll_delete_month(int $month, int $year): void {
    payroll_ensure_table_exists();
    DB::run("DELETE FROM payroll_sheet_rows WHERE month=? AND year=?", [$month, $year]);
}

function payroll_ensure_table_exists(): void {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS payroll_sheet_rows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month TINYINT UNSIGNED NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  row_no SMALLINT UNSIGNED NOT NULL,
  no_val SMALLINT UNSIGNED NULL,
  house VARCHAR(40) NULL,
  name VARCHAR(150) NULL,
  role VARCHAR(80) NULL,
  salario_base_2024 DECIMAL(14,2) NOT NULL DEFAULT 0,
  salario_base_2025 DECIMAL(14,2) NOT NULL DEFAULT 0,
  alimentacao DECIMAL(14,2) NOT NULL DEFAULT 0,
  back_pay DECIMAL(14,2) NOT NULL DEFAULT 0,
  dias_trabalho DECIMAL(6,2) NOT NULL DEFAULT 0,
  salario_mensal DECIMAL(14,2) NOT NULL DEFAULT 0,
  nightshift_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
  guardas_25 DECIMAL(14,2) NOT NULL DEFAULT 0,
  horas_1_5 DECIMAL(8,2) NOT NULL DEFAULT 0,
  valor_1_5 DECIMAL(14,2) NOT NULL DEFAULT 0,
  horas_2 DECIMAL(8,2) NOT NULL DEFAULT 0,
  valor_2 DECIMAL(14,2) NOT NULL DEFAULT 0,
  premios DECIMAL(14,2) NOT NULL DEFAULT 0,
  gratificacoes DECIMAL(14,2) NOT NULL DEFAULT 0,
  dias_ferias DECIMAL(8,2) NOT NULL DEFAULT 0,
  feria_montante DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_remuneracao DECIMAL(14,2) NOT NULL DEFAULT 0,
  salario_adiantado DECIMAL(14,2) NOT NULL DEFAULT 0,
  irps DECIMAL(14,2) NOT NULL DEFAULT 0,
  divida DECIMAL(14,2) NOT NULL DEFAULT 0,
  inss DECIMAL(14,2) NOT NULL DEFAULT 0,
  sind DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
  salario_liquido DECIMAL(14,2) NOT NULL DEFAULT 0,
  arredondamento VARCHAR(40) NULL,
  rubrica DECIMAL(14,2) NOT NULL DEFAULT 0,
  ah DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_sheet (month, year, row_no)
) ENGINE=InnoDB;
SQL;
    DB::run($sql);
}

function payroll_blank_row(int $row_no): array {
    return [
        'row_no' => $row_no,
        'no' => null,
        'house' => '',
        'name' => '',
        'role' => '',
        'salario_base_2024' => 0,
        'salario_base_2025' => 0,
        'alimentacao' => 0,
        'back_pay' => 0,
        'dias_trabalho' => 0,
        'salario_mensal' => 0,
        'nightshift_hours' => 0,
        'guardas_25' => 0,
        'horas_1_5' => 0,
        'valor_1_5' => 0,
        'horas_2' => 0,
        'valor_2' => 0,
        'premios' => 0,
        'gratificacoes' => 0,
        'dias_ferias' => 0,
        'feria_montante' => 0,
        'total_remuneracao' => 0,
        'salario_adiantado' => 0,
        'irps' => 0,
        'divida' => 0,
        'inss' => 0,
        'sind' => 0,
        'total_deductions' => 0,
        'salario_liquido' => 0,
        'arredondamento' => '',
        'rubrica' => 0,
        'ah' => 0,
    ];
}

function payroll_load_rows(int $month, int $year): array {
    payroll_ensure_table_exists();
    $rows = DB::query(
        "SELECT * FROM payroll_sheet_rows WHERE month=? AND year=? ORDER BY row_no",
        [$month, $year]
    );
    $by_row = [];
    foreach ($rows as $r) {
        $by_row[(int)$r['row_no']] = [
            'row_no' => (int)$r['row_no'],
            'no' => $r['no_val'],
            'house' => $r['house'] ?? '',
            'name' => $r['name'] ?? '',
            'role' => $r['role'] ?? '',
            'salario_base_2024' => (float)$r['salario_base_2024'],
            'salario_base_2025' => (float)$r['salario_base_2025'],
            'alimentacao' => (float)$r['alimentacao'],
            'back_pay' => (float)$r['back_pay'],
            'dias_trabalho' => (float)$r['dias_trabalho'],
            'salario_mensal' => (float)$r['salario_mensal'],
            'nightshift_hours' => (float)$r['nightshift_hours'],
            'guardas_25' => (float)$r['guardas_25'],
            'horas_1_5' => (float)$r['horas_1_5'],
            'valor_1_5' => (float)$r['valor_1_5'],
            'horas_2' => (float)$r['horas_2'],
            'valor_2' => (float)$r['valor_2'],
            'premios' => (float)$r['premios'],
            'gratificacoes' => (float)$r['gratificacoes'],
            'dias_ferias' => (float)$r['dias_ferias'],
            'feria_montante' => (float)$r['feria_montante'],
            'total_remuneracao' => (float)$r['total_remuneracao'],
            'salario_adiantado' => (float)$r['salario_adiantado'],
            'irps' => (float)$r['irps'],
            'divida' => (float)$r['divida'],
            'inss' => (float)$r['inss'],
            'sind' => (float)$r['sind'],
            'total_deductions' => (float)$r['total_deductions'],
            'salario_liquido' => (float)$r['salario_liquido'],
            'arredondamento' => (string)($r['arredondamento'] ?? ''),
            'rubrica' => (float)$r['rubrica'],
            'ah' => (float)$r['ah'],
        ];
    }
    $all_rows = [];
    foreach (payroll_row_numbers() as $row_no) {
        $all_rows[$row_no] = $by_row[$row_no] ?? payroll_blank_row($row_no);
    }
    $all_rows = payroll_calculate_rows($all_rows);
    return $all_rows;
}

function payroll_save_rows(int $month, int $year, array $input_rows): array {
    payroll_ensure_table_exists();
    $rows = payroll_load_rows($month, $year);
    $columns = payroll_columns();
    $editable_keys = [];
    foreach ($columns as $c) {
        if ($c['editable']) {
            $editable_keys[] = $c['key'];
        }
    }

    foreach ($input_rows as $row_no => $row_data) {
        $row_no = (int)$row_no;
        if (!isset($rows[$row_no])) {
            continue;
        }
        foreach ($editable_keys as $key) {
            if (!array_key_exists($key, $row_data)) {
                continue;
            }
            if (in_array($key, ['house', 'name', 'role', 'arredondamento'], true)) {
                $rows[$row_no][$key] = trim((string)$row_data[$key]);
            } else {
                $rows[$row_no][$key] = payroll_parse_number($row_data[$key]);
            }
        }
    }

    $rows = payroll_calculate_rows($rows);

    $db = DB::get();
    $db->beginTransaction();
    $sql = "INSERT INTO payroll_sheet_rows
      (month, year, row_no, no_val, house, name, role,
       salario_base_2024, salario_base_2025, alimentacao, back_pay, dias_trabalho,
       salario_mensal, nightshift_hours, guardas_25, horas_1_5, valor_1_5,
       horas_2, valor_2, premios, gratificacoes, dias_ferias, feria_montante,
       total_remuneracao, salario_adiantado, irps, divida, inss, sind,
       total_deductions, salario_liquido, arredondamento, rubrica, ah)
      VALUES
      (?, ?, ?, ?, ?, ?, ?,
       ?, ?, ?, ?, ?,
       ?, ?, ?, ?, ?,
       ?, ?, ?, ?, ?, ?,
       ?, ?, ?, ?, ?, ?,
       ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
       no_val=VALUES(no_val),
       house=VALUES(house),
       name=VALUES(name),
       role=VALUES(role),
       salario_base_2024=VALUES(salario_base_2024),
       salario_base_2025=VALUES(salario_base_2025),
       alimentacao=VALUES(alimentacao),
       back_pay=VALUES(back_pay),
       dias_trabalho=VALUES(dias_trabalho),
       salario_mensal=VALUES(salario_mensal),
       nightshift_hours=VALUES(nightshift_hours),
       guardas_25=VALUES(guardas_25),
       horas_1_5=VALUES(horas_1_5),
       valor_1_5=VALUES(valor_1_5),
       horas_2=VALUES(horas_2),
       valor_2=VALUES(valor_2),
       premios=VALUES(premios),
       gratificacoes=VALUES(gratificacoes),
       dias_ferias=VALUES(dias_ferias),
       feria_montante=VALUES(feria_montante),
       total_remuneracao=VALUES(total_remuneracao),
       salario_adiantado=VALUES(salario_adiantado),
       irps=VALUES(irps),
       divida=VALUES(divida),
       inss=VALUES(inss),
       sind=VALUES(sind),
       total_deductions=VALUES(total_deductions),
       salario_liquido=VALUES(salario_liquido),
       arredondamento=VALUES(arredondamento),
       rubrica=VALUES(rubrica),
       ah=VALUES(ah)";
    $stmt = $db->prepare($sql);

    foreach ($rows as $r) {
        $stmt->execute([
            $month, $year, $r['row_no'], $r['no'],
            $r['house'], $r['name'], $r['role'],
            $r['salario_base_2024'], $r['salario_base_2025'], $r['alimentacao'], $r['back_pay'], $r['dias_trabalho'],
            $r['salario_mensal'], $r['nightshift_hours'], $r['guardas_25'], $r['horas_1_5'], $r['valor_1_5'],
            $r['horas_2'], $r['valor_2'], $r['premios'], $r['gratificacoes'], $r['dias_ferias'], $r['feria_montante'],
            $r['total_remuneracao'], $r['salario_adiantado'], $r['irps'], $r['divida'], $r['inss'], $r['sind'],
            $r['total_deductions'], $r['salario_liquido'], $r['arredondamento'], $r['rubrica'], $r['ah'],
        ]);
    }
    $db->commit();

    return $rows;
}

function payroll_calculate_rows(array $rows): array {
    $data_rows = payroll_data_rows();

    foreach ($data_rows as $row_no) {
        $r = $rows[$row_no] ?? payroll_blank_row($row_no);
        if ($r['no'] === null || $r['no'] === '') {
            $r['no'] = $row_no - 6;
        }

        $salario_base_2024 = payroll_parse_number($r['salario_base_2024']);
        $salario_base_2025 = payroll_parse_number($r['salario_base_2025']);
        $alimentacao = payroll_parse_number($r['alimentacao']);
        $back_pay = payroll_parse_number($r['back_pay']);
        $dias_trabalho = payroll_parse_number($r['dias_trabalho']);
        $nightshift_hours = payroll_parse_number($r['nightshift_hours']);
        $horas_1_5 = payroll_parse_number($r['horas_1_5']);
        $horas_2 = payroll_parse_number($r['horas_2']);
        $premios = payroll_parse_number($r['premios']);
        $gratificacoes = payroll_parse_number($r['gratificacoes']);
        $dias_ferias = payroll_parse_number($r['dias_ferias']);
        $salario_adiantado = payroll_parse_number($r['salario_adiantado']);
        $irps = payroll_parse_number($r['irps']);
        $divida = payroll_parse_number($r['divida']);

        $salario_mensal = ($salario_base_2025 / 30.0) * $dias_trabalho + $alimentacao + $back_pay;
        $guardas_25 = $dias_trabalho > 0
            ? ($salario_mensal / $dias_trabalho / 8.0) * $nightshift_hours * 0.25
            : 0.0;
        $valor_1_5 = ($salario_base_2024 / 192.0) * $horas_1_5 * 1.5;
        $valor_2 = ($salario_mensal / 192.0) * $horas_2 * 2.0;
        $feria_montante = ($salario_mensal / 30.0) * $dias_ferias;
        $total_remuneracao = $salario_mensal + $guardas_25 + $valor_1_5 + $valor_2 + $premios + $gratificacoes + $feria_montante;
        $inss = $total_remuneracao * 0.03;
        $sind = $total_remuneracao * 0.01;
        $total_deductions = $salario_adiantado + $irps + $divida + $inss + $sind;
        $salario_liquido = $total_remuneracao - $total_deductions;

        $r['salario_mensal'] = $salario_mensal;
        $r['guardas_25'] = $guardas_25;
        $r['valor_1_5'] = $valor_1_5;
        $r['valor_2'] = $valor_2;
        $r['feria_montante'] = $feria_montante;
        $r['total_remuneracao'] = $total_remuneracao;
        $r['inss'] = $inss;
        $r['sind'] = $sind;
        $r['total_deductions'] = $total_deductions;
        $r['salario_liquido'] = $salario_liquido;
        $rows[$row_no] = $r;
    }

    // Row 28 totals across data rows
    $sum_keys = [
        'alimentacao','back_pay',
        'nightshift_hours','guardas_25','horas_1_5','valor_1_5','horas_2','valor_2','premios','gratificacoes',
        'dias_ferias','feria_montante','total_remuneracao','salario_adiantado','irps','divida','inss','sind',
        'total_deductions','salario_liquido'
    ];
    $rows[28] = $rows[28] ?? payroll_blank_row(28);
    foreach ($sum_keys as $k) {
        $rows[28][$k] = 0.0;
        foreach ($data_rows as $row_no) {
            $rows[28][$k] += payroll_parse_number($rows[$row_no][$k] ?? 0);
        }
    }
    $rows[28]['salario_base_2024'] = '';
    $rows[28]['salario_base_2025'] = '';
    $rows[28]['dias_trabalho'] = '';
    $rows[28]['salario_mensal'] = '';

    // Rubrica summary logic
    $rows[7]['rubrica']  = sum_range($rows, 7, 14, 'total_remuneracao');
    $rows[15]['rubrica'] = sum_range($rows, 15, 17, 'total_remuneracao') - 6500;
    $rows[18]['rubrica'] = sum_range($rows, 18, 21, 'total_remuneracao');
    $rows[22]['rubrica'] = sum_range($rows, 22, 24, 'total_remuneracao');
    $rows[25]['rubrica'] = sum_range($rows, 25, 27, 'total_remuneracao') + 6500;
    $rows[28]['rubrica'] = sum_range($rows, 7, 27, 'total_remuneracao');
    $rows[29] = $rows[29] ?? payroll_blank_row(29);
    $rows[29]['inss'] = payroll_parse_number($rows[28]['total_remuneracao']) * 0.07;
    $rows[29]['rubrica'] = payroll_parse_number($rows[28]['rubrica']) * 0.04;

    // AH column (1.04 multiplier) and totals
    foreach ([7, 15, 18, 22, 25] as $row_no) {
        if (isset($rows[$row_no])) {
            $rows[$row_no]['ah'] = payroll_parse_number($rows[$row_no]['rubrica']) * 1.04;
        }
    }
    $rows[31] = $rows[31] ?? payroll_blank_row(31);
    $rows[31]['rubrica'] = sum_range($rows, 28, 30, 'rubrica');
    $rows[31]['salario_liquido'] = sum_range($rows, 28, 30, 'salario_liquido');
    $rows[31]['ah'] = sum_range($rows, 7, 30, 'ah');

    return $rows;
}

function sum_range(array $rows, int $start, int $end, string $key): float {
    $sum = 0.0;
    for ($i = $start; $i <= $end; $i++) {
        if (isset($rows[$i])) {
            $sum += payroll_parse_number($rows[$i][$key] ?? 0);
        }
    }
    return $sum;
}

function payroll_clear_row(int $month, int $year, int $row_no): array {
    $rows = payroll_load_rows($month, $year);
    if (!isset($rows[$row_no])) {
        return $rows;
    }
    $defaults = payroll_admin_defaults();
    foreach ($defaults as $k => $v) {
        $rows[$row_no][$k] = $v;
    }
    return payroll_save_rows($month, $year, $rows);
}

function payroll_delete_row_shift(int $month, int $year, int $row_no): array {
    $rows = payroll_load_rows($month, $year);
    $data_rows = payroll_data_rows();
    if (!in_array($row_no, $data_rows, true)) {
        return $rows;
    }
    $shift_keys = array_merge(['no'], payroll_admin_keys());
    $max_row = max($data_rows);
    for ($r = $row_no; $r < $max_row; $r++) {
        foreach ($shift_keys as $k) {
            $rows[$r][$k] = $rows[$r + 1][$k] ?? ($k === 'no' ? null : '');
        }
    }
    $defaults = payroll_admin_defaults();
    foreach ($shift_keys as $k) {
        if ($k === 'no') {
            $rows[$max_row][$k] = null;
        } else {
            $rows[$max_row][$k] = $defaults[$k] ?? '';
        }
    }
    return payroll_save_rows($month, $year, $rows);
}

function payroll_add_row(int $month, int $year): array {
    $rows = payroll_load_rows($month, $year);
    $data_rows = payroll_data_rows();
    $max_no = 0;
    foreach ($data_rows as $r) {
        $v = $rows[$r]['no'] ?? 0;
        if (is_numeric($v) && (int)$v > $max_no) {
            $max_no = (int)$v;
        }
    }
    $target_row = null;
    foreach ($data_rows as $r) {
        $name = trim((string)($rows[$r]['name'] ?? ''));
        $house = trim((string)($rows[$r]['house'] ?? ''));
        if ($name === '' && $house === '') {
            $target_row = $r;
            break;
        }
    }
    if ($target_row === null) {
        return $rows;
    }
    $rows[$target_row]['no'] = $max_no + 1;
    return payroll_save_rows($month, $year, $rows);
}
