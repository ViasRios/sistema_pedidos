<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
  exit;
}

$pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
$campo = $_POST['campo'] ?? '';
$valor = $_POST['valor'] ?? '';

$permitidos = ['fecha_llegada','factura','recibio'];
if ($pedido_id <= 0 || !in_array($campo, $permitidos, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos']);
  exit;
}

// Normalizaciones básicas
switch ($campo) {
  case 'fecha_llegada':
    // Espera Y-m-d del input date — valida formato simple
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$d || $d->format('Y-m-d') !== $valor) {
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'Fecha inválida (usa YYYY-MM-DD)']);
      exit;
    }
    break;

  case 'factura':
  case 'recibio':
    $valor = ($valor === 'Si') ? 'Si' : 'No';
    break;

  /*  case 'forma_pago':
    $allowed = ['Efectivo','Transferencia','Tarjeta'];
    if (!in_array($valor, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Forma de pago inválida']);
        exit;
    } 
    break; */

}

// Construye SQL seguro
$sql = "UPDATE pedidos SET {$campo} = :valor WHERE id = :id";
$stmt = $pdo->prepare($sql);

try {
  $stmt->execute([':valor' => $valor, ':id' => $pedido_id]);
  echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB: '.$e->getMessage()]);
}
