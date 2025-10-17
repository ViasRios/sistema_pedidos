<?php
require_once 'config.php';

$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch');
if ($is_ajax) header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo $is_ajax ? json_encode(['ok'=>false,'error'=>'Método no permitido']) : 'Método no permitido';
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo $is_ajax ? json_encode(['ok'=>false,'error'=>'ID inválido']) : 'ID inválido';
  exit;
}

// === Recibir campos ===
$nombre_corto  = $_POST['nombre_corto'] ?? '';
$cantidad      = (float)($_POST['cantidad'] ?? 0);
$precio        = (float)($_POST['precio'] ?? 0);
$subtotal      = $cantidad * $precio;
$proveedor     = $_POST['proveedor'] ?? '';
$cuenta        = $_POST['cuenta'] ?? '';
$fecha_compra  = $_POST['fecha_compra'] ?? '';
$fecha_llegada = $_POST['fecha_llegada'] ?? '';
$forma_pago    = $_POST['forma_pago'] ?? '';
$usuario       = $_POST['usuario'] ?? '';
$estado        = $_POST['estado'] ?? 'Solicitud';
$observaciones = $_POST['observaciones'] ?? '';
$ods           = $_POST['ods'] ?? '';
$factura       = $_POST['factura'] ?? 'No';
$recibio       = $_POST['recibio'] ?? 'No';
$motivo        = $_POST['motivo'] ?? '';
$url           = trim($_POST['url'] ?? '');
$foto_actual   = $_POST['foto_actual'] ?? '';
$eliminar_foto = isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1';

// Validaciones simples


$allowed_si_no = ['Si','No'];
if (!in_array($factura, $allowed_si_no, true)) $factura = 'No';
if (!in_array($recibio, $allowed_si_no, true)) $recibio = 'No';

$allowed_estado = ['Solicitud','EnTransito','Recibido','EntregadoTecnico','PorDevolver','Devuelto','Cancelado'];
if (!in_array($estado, $allowed_estado, true)) $estado = 'Solicitud';

if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) $url = '';

$dc = DateTime::createFromFormat('Y-m-d', $fecha_compra);
$dl = DateTime::createFromFormat('Y-m-d', $fecha_llegada);
if (!$dc || $dc->format('Y-m-d') !== $fecha_compra)  { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Fecha de compra inválida']); exit; }
if (!$dl || $dl->format('Y-m-d') !== $fecha_llegada) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Fecha de llegada inválida']); exit; }

// === Manejo de FOTO ===
$foto_nueva_path = null;
if (!$eliminar_foto && !empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
  $tmp = $_FILES['foto']['tmp_name'];
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $tmp);
  finfo_close($finfo);
  $permitidos = ['image/jpeg','image/png','image/gif','image/webp'];
  if (!in_array($mime, $permitidos, true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Formato de imagen no permitido']);
    exit;
  }
  if ($_FILES['foto']['size'] > 2*1024*1024) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'La imagen supera 2MB']);
    exit;
  }
  $dest_dir = __DIR__ . '/uploads';
  if (!is_dir($dest_dir)) { @mkdir($dest_dir, 0775, true); }
  $orig = basename($_FILES['foto']['name']);
  $ext = pathinfo($orig, PATHINFO_EXTENSION);
  $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($orig, PATHINFO_FILENAME));
  $uniq = $safe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
  $dest_path = $dest_dir . '/' . $uniq;
  if (!move_uploaded_file($tmp, $dest_path)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'No se pudo guardar la imagen']);
    exit;
  }
  $foto_nueva_path = 'uploads/' . $uniq;
}

// === Construir UPDATE dinámico ===
$fields = [
  'nombre_corto'  => $nombre_corto,
  'cantidad'      => $cantidad,
  'precio'        => $precio,
  'subtotal'      => $subtotal,
  'proveedor'     => $proveedor,
  'fecha_compra'  => $fecha_compra,
  'fecha_llegada' => $fecha_llegada,
  'forma_pago'    => $forma_pago,
  'url'           => $url,
  'estado'        => $estado,
  'observaciones' => $observaciones,
  'ods'           => $ods,
  'factura'       => $factura,
  'recibio'       => $recibio,
  'motivo'        => $motivo,
];

if ($eliminar_foto) {
  $fields['foto'] = null;
} elseif ($foto_nueva_path) {
  $fields['foto'] = $foto_nueva_path;
}

$sets = [];
$params = [];
foreach ($fields as $col => $val) {
  $sets[] = "$col = :$col";
  $params[":$col"] = $val;
}
$params[':id'] = $id;

$sql = "UPDATE pedidos SET ".implode(', ', $sets)." WHERE id = :id";
$stmt = $pdo->prepare($sql);

try {
  $stmt->execute($params);
  // (Opcional) borrar físicamente la foto anterior si fue eliminada o reemplazada
   if (($eliminar_foto || $foto_nueva_path) && !empty($foto_actual)) { @unlink(__DIR__ . '/' . $foto_actual); }

  echo $is_ajax ? json_encode(['ok'=>true, 'message'=>'Actualizado']) : 'Actualizado';
} catch (PDOException $e) {
  http_response_code(500);
  echo $is_ajax ? json_encode(['ok'=>false,'error'=>'DB: '.$e->getMessage()]) : 'Error DB';
}
// Después de guardar los cambios correctamente en la base de datos:
header('Location: index.php?mensaje=Pedido%20actualizado%20correctamente');
exit;
