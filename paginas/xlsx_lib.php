<?php
// Utilidades para Excel (.xlsx) sin librerías externas: escribir (xlsx_enviar) y leer (xlsx_leer). Requiere la extensión ZipArchive.
// Es una librería: no está en $permitidas de index.php; la incluyen ferias.php, plantilla_alumnos.php y carga_masiva.php.

function xlsx_col($i){ $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; }
function xlsx_txt($t){ return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$t), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
// $filas: cada celda es texto, número o ['fecha' => 'Y-m-d H:i:s'].
// $colTexto: índices (base 0) de columnas con formato Texto (para que Excel no convierta códigos como el UID NFC en números).
function xlsx_enviar(array $cab, array $filas, array $anchos, $archivo, $hoja, array $colTexto = []){
  $rows = '<row r="1">';
  foreach ($cab as $i => $t) $rows .= '<c r="' . xlsx_col($i) . '1" s="1" t="inlineStr"><is><t>' . xlsx_txt($t) . '</t></is></c>';
  $rows .= '</row>'; $n = 1;
  foreach ($filas as $f) {
    $n++; $rows .= '<row r="' . $n . '">';
    foreach ($f as $i => $v) {
      $ref = xlsx_col($i) . $n;
      if (is_array($v)) { $ts = strtotime(($v['fecha'] ?? '') . ' UTC'); $rows .= $ts === false ? '<c r="' . $ref . '"/>' : '<c r="' . $ref . '" s="2"><v>' . ($ts / 86400 + 25569) . '</v></c>'; }
      elseif (is_int($v) || is_float($v)) $rows .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
      else $rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_txt($v) . '</t></is></c>';
    }
    $rows .= '</row>';
  }
  $cols = '';
  foreach ($anchos as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"' . (in_array($i, $colTexto, true) ? ' style="3"' : '') . '/>';
  $ult = xlsx_col(count($cab) - 1) . max(1, $n);
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $rows . '</sheetData><autoFilter ref="A1:' . $ult . '"/></worksheet>';
  $hdr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
  $ns = 'http://schemas.openxmlformats.org/';
  $zip = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
  $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml', $hdr . '<Types xmlns="' . $ns . 'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $zip->addFromString('_rels/.rels', $hdr . '<Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml', $hdr . '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships"><sheets><sheet name="' . xlsx_txt($hoja) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels', $hdr . '<Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $zip->addFromString('xl/styles.xml', $hdr . '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy\ hh:mm"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2E7D62"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
  $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
  $zip->close();
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $archivo . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp); @unlink($tmp); exit;
}

// ---------------------------- Lectura de .xlsx ----------------------------
const XLSX_NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const XLSX_NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const XLSX_NS_PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';

function _xlsx_num($v){
  $v = trim((string)$v);
  if ($v === '' || !is_numeric($v)) return $v;
  $f = (float)$v;
  if (floor($f) == $f && abs($f) < 1e15) return sprintf('%.0f', $f);
  return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
}
function _xlsx_xp($el, $xp){
  $el->registerXPathNamespace('m', XLSX_NS_MAIN);
  $el->registerXPathNamespace('r', XLSX_NS_REL);
  $el->registerXPathNamespace('p', XLSX_NS_PKG);
  $r = $el->xpath($xp);
  return $r === false || $r === null ? [] : $r;
}

// Lee la PRIMERA hoja de un .xlsx. Devuelve [n° de fila de Excel => [celdas como texto]] (solo filas que existen en el archivo).
// Lanza RuntimeException con un mensaje apto para mostrar al usuario.
function xlsx_leer($ruta){
  if (!class_exists('ZipArchive')) throw new RuntimeException('Este servidor no puede leer archivos .xlsx. Guarda tu Excel como CSV y súbelo de nuevo.');
  $zip = new ZipArchive();
  if ($zip->open($ruta) !== true) throw new RuntimeException('El archivo no es un Excel (.xlsx) válido o está dañado.');
  try {
    $leer = function($nombre) use ($zip){
      $st = $zip->statName($nombre);
      if ($st === false) return null;
      if ($st['size'] > 20 * 1024 * 1024) throw new RuntimeException('El archivo Excel es demasiado grande.');
      $x = $zip->getFromName($nombre);
      return $x === false ? null : $x;
    };
    $xml = fn($t) => simplexml_load_string((string)$t, 'SimpleXMLElement', LIBXML_NONET);

    // Ruta de la primera hoja (por defecto la convención de Excel)
    $hoja = 'xl/worksheets/sheet1.xml';
    $wb = $leer('xl/workbook.xml'); $rels = $leer('xl/_rels/workbook.xml.rels');
    if ($wb !== null && $rels !== null && ($w = $xml($wb)) && ($r = $xml($rels))) {
      $sh = _xlsx_xp($w, '//m:sheets/m:sheet');
      if ($sh) {
        $rid = (string)($sh[0]->attributes(XLSX_NS_REL)['id'] ?? '');
        foreach (_xlsx_xp($r, '//p:Relationship') as $rl) {
          if ((string)$rl['Id'] === $rid && $rid !== '') {
            $t = (string)$rl['Target'];
            $hoja = ltrim($t !== '' && $t[0] === '/' ? $t : 'xl/' . $t, '/');
          }
        }
      }
    }

    // Textos compartidos
    $ss = [];
    $t = $leer('xl/sharedStrings.xml');
    if ($t !== null && ($x = $xml($t))) {
      foreach (_xlsx_xp($x, '//m:si') as $si) $ss[] = implode('', array_map('strval', _xlsx_xp($si, './m:t | ./m:r/m:t')));
    }

    $t = $leer($hoja);
    if ($t === null || !($x = $xml($t))) throw new RuntimeException('No se encontró la hoja de datos dentro del Excel.');
    $filas = []; $auto = 0;
    foreach (_xlsx_xp($x, '//m:sheetData/m:row') as $row) {
      $n = (int)$row['r']; if ($n <= 0) $n = $auto + 1; $auto = $n;
      $cel = []; $pos = 0;
      foreach (_xlsx_xp($row, './m:c') as $c) {
        $ref = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$c['r']));
        if ($ref !== '') { $i = 0; foreach (str_split($ref) as $ch) $i = $i * 26 + (ord($ch) - 64); $pos = $i - 1; }
        $tipo = (string)$c['t'];
        $v = _xlsx_xp($c, './m:v'); $v = $v ? (string)$v[0] : '';
        if ($tipo === 's') $val = $ss[(int)$v] ?? '';
        elseif ($tipo === 'inlineStr') $val = implode('', array_map('strval', _xlsx_xp($c, './m:is/m:t | ./m:is/m:r/m:t')));
        elseif ($tipo === 'str' || $tipo === 'e') $val = $v;
        elseif ($tipo === 'b') $val = $v === '1' ? 'VERDADERO' : 'FALSO';
        else $val = _xlsx_num($v);
        $cel[$pos] = $val; $pos++;
      }
      if (!$cel) { $filas[$n] = []; continue; }
      $max = max(array_keys($cel)); $fila = [];
      for ($i = 0; $i <= $max; $i++) $fila[] = $cel[$i] ?? '';
      $filas[$n] = $fila;
    }
    ksort($filas);
    return $filas;
  } finally {
    $zip->close();
  }
}
