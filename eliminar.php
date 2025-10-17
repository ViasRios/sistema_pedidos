<?php
require_once 'config.php';

// Toma el id por GET (como tu enlace) o por POST (si luego usas form)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}
if (!$id) {
    header('Location: index.php?error=ID%20inv%C3%A1lido');
    exit;
}

try {
    // 1) Obtener datos (foto) antes de eliminar
    $stmt = $pdo->prepare('SELECT foto FROM pedidos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        header('Location: index.php?error=Pedido%20no%20encontrado');
        exit;
    }

    // 2) Eliminar el registro
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM pedidos WHERE id = ?');
    $del->execute([$id]);
    $pdo->commit();

    // 3) Si tenía foto, intentar eliminar archivo (sólo dentro de /uploads)
    if (!empty($pedido['foto'])) {
        $rutaRelativa = ltrim($pedido['foto'], '/\\');
        $rutaFoto = __DIR__ . DIRECTORY_SEPARATOR . $rutaRelativa;

        $dirUploads = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
        $realFoto   = @realpath($rutaFoto);

        if ($dirUploads && $realFoto && strpos($realFoto, $dirUploads) === 0 && is_file($realFoto)) {
            @unlink($realFoto);
        }
    }

    header('Location: index.php?mensaje=Pedido%20eliminado');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // Puedes loguear $e->getMessage() si lo necesitas
    header('Location: index.php?error=No%20se%20pudo%20eliminar');
    exit;
}
