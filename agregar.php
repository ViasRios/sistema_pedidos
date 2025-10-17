<?php
require_once 'config.php';

$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos principales
    $nombre_corto  = $_POST['nombre_corto'] ?? '';
    $cantidad      = (float)($_POST['cantidad'] ?? 0);
    $precio        = (float)($_POST['precio'] ?? 0);
    $subtotal      = $cantidad * $precio;
    $proveedor     = $_POST['proveedor'] ?? '';

    $fecha_compra  = $_POST['fecha_compra'] ?? '';
    $fecha_llegada = $_POST['fecha_llegada'] ?? '';
    $forma_pago    = $_POST['forma_pago'] ?? '';


    $estado        = $_POST['estado'] ?? 'Solicitud';
    $observaciones = $_POST['observaciones'] ?? null;
    $ods           = $_POST['ods'] ?? null;
    $ods_id        = $_POST['ods_id'] ?? null;
    $factura       = $_POST['factura'] ?? 'No';
    $recibio       = $_POST['recibio'] ?? 'No';
    $motivo        = $_POST['motivo'] ?? null;

    // Campo URL (opcional)
    $url = trim($_POST['url'] ?? '');
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        $url = null;
    }

    // --- Manejo de la FOTO (upload opcional) ---
    $foto_path = null;
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $tmp_name  = $_FILES['foto']['tmp_name'];
        $orig_name = basename($_FILES['foto']['name']);

        // Validar MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        $permitidos = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($mime, $permitidos, true)) {
            $msg = 'Formato de imagen no permitido';
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8', true, 400);
                echo json_encode(['ok'=>false, 'error'=>$msg]);
                exit;
            } else {
                $error = $msg;
            }
        }

        // Tamaño máx ~2MB
        if ($_FILES['foto']['size'] > 2*1024*1024) {
            $msg = 'La imagen supera 2MB';
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8', true, 400);
                echo json_encode(['ok'=>false, 'error'=>$msg]);
                exit;
            } else {
                $error = $msg;
            }
        }

        // Asegura carpeta uploads
        $dest_dir = __DIR__ . '/uploads';
        if (!is_dir($dest_dir)) {
            @mkdir($dest_dir, 0775, true);
        }

        // Nombre único
        $ext  = pathinfo($orig_name, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($orig_name, PATHINFO_FILENAME));
        $uniq = $safe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

        $dest_path  = $dest_dir . '/' . $uniq;     // físico
        $public_rel = 'uploads/' . $uniq;         // para BD

        if (move_uploaded_file($tmp_name, $dest_path)) {
            $foto_path = $public_rel;
        } else {
            $msg = 'No se pudo guardar la imagen';
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8', true, 500);
                echo json_encode(['ok'=>false, 'error'=>$msg]);
                exit;
            } else {
                $error = $msg;
            }
        }
    }

    try {
        // --- INSERT con 17 columnas y 17 valores ---
        $sql = "INSERT INTO pedidos (
            nombre_corto, cantidad, precio, subtotal, proveedor,
            fecha_compra, fecha_llegada, forma_pago, foto, url,
            estado, observaciones, ods, ods_id, factura, recibio, motivo
        ) VALUES (
            :nombre_corto, :cantidad, :precio, :subtotal, :proveedor,
            :fecha_compra, :fecha_llegada, :forma_pago, :foto, :url,
            :estado, :observaciones, :ods, :ods_id, :factura, :recibio, :motivo
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre_corto'  => $nombre_corto,
            ':cantidad'      => $cantidad,
            ':precio'        => $precio,
            ':subtotal'      => $subtotal,
            ':proveedor'     => $proveedor,
            ':fecha_compra'  => $fecha_compra,
            ':fecha_llegada' => $fecha_llegada,
            ':forma_pago'    => $forma_pago,
            ':foto'          => $foto_path,
            ':url'           => $url,
            ':estado'        => $estado,
            ':observaciones' => $observaciones,
            ':ods'           => $ods,
            ':ods_id'        => $ods_id,
            ':factura'       => $factura,
            ':recibio'       => $recibio,
            ':motivo'        => $motivo
        ]);

        // ... dentro del try, justo DESPUÉS de $stmt->execute([...]);
$pedido_id = $pdo->lastInsertId();

// Normaliza valores para el front (por si vienen null/empty)
$pedido = [
  'id'            => (int)$pedido_id,
  'nombre_corto'  => $nombre_corto,
  'cantidad'      => (float)$cantidad,
  'precio'        => (float)$precio,
  'subtotal'      => (float)$subtotal,
  'proveedor'     => $proveedor,
  'fecha_compra'  => $fecha_compra,
  'fecha_llegada' => $fecha_llegada,
  'forma_pago'    => $forma_pago,
  'foto'          => $foto_path,
  'url'           => $url,
  'estado'        => $estado ?: 'Solicitud',
  'observaciones' => $observaciones,
  'ods'           => $ods,
  'ods_id'        => $ods_id,
  'factura'       => $factura ?: 'No',
  'recibio'       => $recibio ?: 'No',
  'motivo'        => $motivo,
];

// Si es AJAX, responde con el pedido
if ($is_ajax) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true, 'message'=>'Pedido agregado correctamente', 'pedido'=>$pedido]);
  exit;
} else {
  header('Location: index.php?mensaje=Pedido agregado correctamente');
  exit;
}


        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true, 'message'=>'Pedido agregado correctamente']);
            exit;
        } else {
            header('Location: index.php?mensaje=Pedido agregado correctamente');
            exit;
        }
    } catch (PDOException $e) {
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode(['ok'=>false, 'error'=>'Error al agregar el pedido: '.$e->getMessage()]);
            exit;
        } else {
            $error = "Error al agregar el pedido: " . $e->getMessage();
        }
    }
}
