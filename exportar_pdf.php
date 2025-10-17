<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('America/Mexico_City');
$now = date('d/m/Y H:i');

// Asegúrate de que Dompdf esté instalado: composer require dompdf/dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
if (empty($ids)) {
  die('Sin selección para exportar.');
}

// Traer registros seleccionados
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT * FROM pedidos WHERE id IN ($placeholders) ORDER BY fecha_compra DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$incluir_total = !empty($_POST['incluir_total']);

$pdf_total_cols = 15;   // total de columnas en tu thead (sin Foto)
$idx_objetivo   = 5;    // 6 = debajo de Subtotal (5 sería Precio)

$colspan_antes   = $idx_objetivo - 1;
$colspan_despues = $pdf_total_cols - $idx_objetivo;

$total_sin_cancelados = 0.0;
if ($incluir_total) {
  foreach ($rows as $r) {
    if (strcasecmp(trim($r['estado'] ?? ''), 'Cancelado') !== 0) {
      $total_sin_cancelados += (float)$r['subtotal'];
    }
  }
  $tfootHtml = '
    <tfoot>
      <tr>
        <td colspan="'.$colspan_antes.'" style="text-align:right;font-weight:bold;background:#f2f3f7;border:1px solid #fff8e1;">
          TOTAL
        </td>
        <td style="font-weight:bold;border:1px solid #fff8e1;">
          $'.number_format($total_sin_cancelados, 2).'
        </td>
        <td colspan="'.$colspan_despues.'"></td>
      </tr>
    </tfoot>';
} else {
  $tfootHtml = '';
}

// Helper para fechas y HTML safe
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dmy($ymd){ return $ymd ? date('d/m/Y', strtotime($ymd)) : ''; }


// Armar HTML
$now = date('d/m/Y H:i');
$css = <<<CSS
  @page { margin: 18mm 12mm; }
  body  { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#222; }
  h1    { font-size: 18px; margin: 0 0 4px; }
  .meta { font-size: 10px; color:#666; margin-bottom: 10px; }
  table { width:100%; border-collapse: collapse; }
  th,td { border:1px solid #090909ff; padding:6px 8px; vertical-align: top; }
  th    { background:#f2f3f7; font-weight:600; }
  tr:nth-child(even) td { background:#fafbfc; }
  .num  { text-align: right; white-space: nowrap; }
  .nowrap { white-space: nowrap; }
  .url  { color:#0d6efd; text-decoration: none; }
   tfoot td { background:#fff8e1; }
  .footer { position: fixed; bottom: -10mm; left:0; right:0; text-align:center; font-size:10px; color:#777; }
  .pageno:before { content: counter(page) " / " counter(pages); }
CSS;

$thead = <<<HTML
  <tr>
    <th>ODS</th>
    <th>Nombre</th>
    <th class="num">Cant</th>
    <th class="num">Precio</th>
    <th class="num">Subtotal</th>
    <th>Proveedor</th>
    <th class="nowrap">F. Compra</th>
    <th class="nowrap">F. Llegada</th>
    <th>Forma Pago</th>
    <th>Estado</th>
    <th>Factura</th>
    <th>Recibió</th>
  </tr>
HTML;

$rowsHtml = '';
foreach ($rows as $r) {
  $ods = $r['ods'] ?: ($r['ods_id'] ?? '');
  $url = trim($r['url'] ?? '');
  $urlTxt = $url ? h($url) : '';

  $rowsHtml .= '<tr>'
    . '<td>' . h($ods) . '</td>'
    . '<td>' . h($r['nombre_corto']) . '</td>'
    . '<td class="num">' . (float)$r['cantidad'] . '</td>'
    . '<td class="num">$' . number_format((float)$r['precio'], 2) . '</td>'
    . '<td class="num">$' . number_format((float)$r['subtotal'], 2) . '</td>'
    . '<td>' . h($r['proveedor']) . '</td>'
    . '<td class="nowrap">' . h(dmy($r['fecha_compra'])) . '</td>'
    . '<td class="nowrap">' . h(dmy($r['fecha_llegada'])) . '</td>'
    . '<td>' . h($r['forma_pago']) . '</td>'
    . '<td>' . h($r['estado']) . '</td>'
    . '<td>' . h($r['factura']) . '</td>'
    . '<td>' . h($r['recibio']) . '</td>'
    . '</tr>';
}


$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>$css</style>
</head>
<body>
  <h1>Pedidos seleccionados</h1>
  <div class="meta">Generado: $now</div>

  <table>
    <thead>$thead</thead>
    <tbody>$rowsHtml</tbody>
    $tfootHtml
  </table>

  <div class="footer">
    Página <span class="pageno"></span>
  </div>
</body>
</html>
HTML;

// Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true); // permite data-uri e imágenes locales
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape'); // horizontal para tablas anchas
$dompdf->render();

// Descargar = true, Ver en navegador = false
$dompdf->stream('pedidos_' . date('Ymd_His') . '.pdf', ['Attachment' => false]);
exit;
