<?php
// actualizar_estado.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// NO enviar avisos al output (para que no rompan el JSON)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error.log');

// Limpia buffers/salidas previas (por si hay BOM o espacios en config.php)
while (ob_get_level() > 0) { ob_end_clean(); }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false, 'error'=>'Método no permitido']); exit;
    }

    $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    $estado    = trim($_POST['estado'] ?? '');
    $motivo    = trim($_POST['motivo'] ?? '');

    if ($pedido_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit;
    }

    // === Normalización de estado (acepta variantes y sin espacios) ===
    $canon = mb_strtolower(iconv('UTF-8','ASCII//TRANSLIT',$estado));
    $canon = preg_replace('/\s+/', ' ', trim($canon));
    $map = [
        'solicitud'          => 'Solicitud',
        'en transito'        => 'EnTransito',
        'transito'           => 'EnTransito',
        'recibido'           => 'Recibido',
        'entregado tecnico'  => 'EntregadoTecnico',
        'por devolver'       => 'PorDevolver',
        'devolver'           => 'PorDevolver',
        'devuelto'           => 'Devuelto',
        'cancelado'          => 'Cancelado',
    ];
    if (isset($map[$canon])) {
        $estado = $map[$canon];
    } else {
        // fallback: quita espacios si llegó "EnTransito", etc.
        $estado = str_replace(' ', '', $estado);
    }

    $permitidos = ['Solicitud','EnTransito','Recibido','EntregadoTecnico','PorDevolver','Devuelto','Cancelado'];
    if (!in_array($estado, $permitidos, true)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Estado inválido: '.$estado]); exit;
    }

    // Validación del motivo (requerido para "PorDevolver" y "Cancelado")
    if (($estado === 'PorDevolver' || $estado === 'Cancelado') && $motivo === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Motivo requerido para "' . $estado . '"']); exit;
    }

    // Verifica que exista el pedido
    $chk = $pdo->prepare('SELECT id FROM pedidos WHERE id = ?');
    $chk->execute([$pedido_id]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'Pedido no encontrado']); exit;
    }

    // Actualiza
    if ($estado === 'PorDevolver' || $estado === 'Cancelado') {
        $stmt = $pdo->prepare('UPDATE pedidos SET estado = :e, motivo = :m WHERE id = :id');
        $ok = $stmt->execute([':e'=>$estado, ':m'=>$motivo, ':id'=>$pedido_id]);
    } else {
        $stmt = $pdo->prepare('UPDATE pedidos SET estado = :e WHERE id = :id');
        $ok = $stmt->execute([':e'=>$estado, ':id'=>$pedido_id]);
    }

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'No se pudo actualizar']); exit;
    }

    echo json_encode(['ok'=>true, 'id'=>$pedido_id, 'estado'=>$estado]); exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}
