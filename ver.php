<?php
require_once 'config.php';

// 1) Toma el id de la URL y valida
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  http_response_code(400);
  echo "ID inválido";
  exit;
}

// 2) Consulta el pedido
try {
  $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
  $stmt->execute([$id]);
  $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  http_response_code(500);
  echo "Error de base de datos";
  exit;
}

// 3) Si no existe, corta
if (!$pedido) {
  http_response_code(404);
  echo "Pedido no encontrado";
  exit;
}

// 4) A PARTIR DE AQUÍ ya es seguro usar $pedido
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Fechas para inputs
$fecha_compra_y_m_d  = !empty($pedido['fecha_compra'])  ? date('Y-m-d', strtotime($pedido['fecha_compra']))  : '';
$fecha_llegada_y_m_d = !empty($pedido['fecha_llegada']) ? date('Y-m-d', strtotime($pedido['fecha_llegada'])) : '';

// Catálogos
$forma_pago_opts = ['Efectivo','Transferencia','Tarjeta'];
$si_no_opts      = ['Si','No'];
$estado_opts     = ['Solicitud','EnTransito','Recibido','EntregadoTecnico','PorDevolver','Devuelto','Cancelado'];

// Colores de estado (para badge superior)
$estado_colors = [
  'Solicitud'          => 'info',
  'EnTransito'        => 'warning',
  'Recibido'           => 'success',
  'EntregadoTecnico'  => 'primary',
  'PorDevolver'       => 'warning',
  'Devuelto'           => 'danger',
  'Cancelado'          => 'secondary',
];
$estadoColor = $estado_colors[$pedido['estado']] ?? 'secondary';

// Enlaces ODS
$ods_mostrar = $pedido['ods'] ?? $pedido['ods_id'] ?? '';
$ods_link    = $ods_mostrar ? "http://localhost/VENTAS3/odsView/".rawurlencode($ods_mostrar)."/" : null;
?>



<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Pedido #<?= (int)$pedido['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  body { background:#f6f7f9; }
  .card { box-shadow: 0 6px 20px rgba(0,0,0,.06); border:0; }
  .thumb { height: 160px; width: 100%; object-fit: cover; border-radius: .5rem; }
  .kv small { color:#6c757d; }
  .form-section-title { font-size:.9rem; color:#6c757d; text-transform:uppercase; letter-spacing:.04em; }
</style>
</head>
<body class="p-3 p-md-4">

<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">

    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <span id="estadoBadge" class="badge bg-<?= htmlspecialchars($estadoColor) ?>">
            <?= htmlspecialchars($pedido['estado']) ?>
        </span>
        <span id="facturaBadge" class="badge bg-<?= ($pedido['factura']==='Si' ? 'success' : 'secondary') ?>">
            Factura: <?= htmlspecialchars($pedido['factura']) ?>
        </span>
        <span id="recibioBadge" class="badge bg-<?= ($pedido['recibio']==='Si' ? 'success' : 'secondary') ?>">
            Recibió: <?= htmlspecialchars($pedido['recibio']) ?>
        </span>
        </div>

    <h5 class="h4 mb-0"><strong>Editar Pedido #<?= (int)$pedido['id'] ?></strong></h5>

    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
  </div>

  <div id="alert_box" class="alert d-none" role="alert"></div>
  <form method="POST" action="update_pedido.php" id="form_editar_pedido_<?= $pedido['id'] ?>" enctype="multipart/form-data" class="row g-4">
    <!-- <form id="form_editar_pedido" enctype="multipart/form-data" class="row g-4"> -->
    <input type="hidden" name="id" value="<?= (int)$pedido['id'] ?>">
    <input type="hidden" name="foto_actual" value="<?= h($pedido['foto'] ?? '') ?>">

    <!-- IZQUIERDA: Foto y enlaces -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title mb-2">Evidencia</div>

          <?php if (!empty($pedido['foto'])): ?>
            <a href="<?= h($pedido['foto']) ?>" target="_blank" rel="noopener">
              <img src="<?= h($pedido['foto']) ?>" alt="Foto del pedido" class="thumb mb-2">
            </a>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="1" id="eliminar_foto" name="eliminar_foto">
              <label class="form-check-label" for="eliminar_foto">Eliminar foto actual</label>
            </div>
          <?php else: ?>
            <div class="alert alert-light border d-flex align-items-center" role="alert">
              <i class="fa-regular fa-image me-2"></i> Sin foto
            </div>
          <?php endif; ?>

          <div class="mb-3">
            <label for="foto" class="form-label">Subir nueva foto</label>
            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
            <div class="form-text">JPG, PNG, GIF o WEBP. Máx 2MB.</div>
          </div>

          <hr>

          <div class="form-section-title mb-2">Enlaces</div>
          <div class="d-grid gap-2 mb-2">
            <?php if ($ods_link): ?>
              <a class="btn btn-outline-info btn-sm" href="<?= h($ods_link) ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-up-right-from-square"></i> Ver ODS externo
              </a>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-sm" disabled>
                <i class="fa-solid fa-up-right-from-square"></i> Sin ODS
              </button>
            <?php endif; ?>
          </div>

          
          <div class="col-md-13">
              <label for="url" class="form-label">URL (opcional)</label>
              <input type="url" class="form-control" id="url" name="url" value="<?= h($pedido['url'] ?? '') ?>" placeholder="https://...">
              <!-- Mostrar la URL como un enlace si ya está disponible -->
              <?php if (!empty($pedido['url'])): ?>
                  <div class="mt-2">
                      <a href="<?= htmlspecialchars($pedido['url']) ?>" target="_blank"><?= htmlspecialchars($pedido['url']) ?></a>
                  </div>
              <?php else: ?>
                  <p class="mt-2">No hay URL disponible.</p>
              <?php endif; ?>
          </div>

        </div>
      </div>
    </div>

    <!-- DERECHA: Campos -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-body">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">ODS</label>
              <input type="text" class="form-control" name="ods" value="<?= h($pedido['ods'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Nombre *</label>
              <input type="text" class="form-control" name="nombre_corto" required value="<?= h($pedido['nombre_corto']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Cantidad *</label>
              <input type="number" class="form-control" name="cantidad" id="inp_cantidad" min="1" required value="<?= (float)$pedido['cantidad'] ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Precio *</label>
              <input type="number" class="form-control" name="precio" id="inp_precio" step="0.01" min="0" required value="<?= (float)$pedido['precio'] ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Proveedor *</label>
              <input type="text" class="form-control" name="proveedor" required value="<?= h($pedido['proveedor']) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Fecha de compra *</label>
              <input type="date" class="form-control" name="fecha_compra" required value="<?= h($fecha_compra_y_m_d) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">ETA *</label>
              <input type="date" class="form-control" name="fecha_llegada" required value="<?= h($fecha_llegada_y_m_d) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Forma de pago</label>
              <input type="text" class="form-control" id="forma_pago" name="forma_pago" value="<?= h($pedido['forma_pago']) ?>">
          <!--    <select class="form-select" name="forma_pago">
                <?php foreach ($forma_pago_opts as $op): ?>
                  <option value="<?= h($op) ?>" <?= ($pedido['forma_pago'] === $op ? 'selected':'') ?>><?= h($op) ?></option>
                <?php endforeach; ?>
              </select> -->
            </div>

            <div class="col-md-4">
              <label class="form-label">Estado *</label>
              <select class="form-select" name="estado" required>
                <?php foreach ($estado_opts as $op): ?>
                  <option value="<?= h($op) ?>" <?= ($pedido['estado'] === $op ? 'selected':'') ?>><?= h($op) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Factura</label>
              <select class="form-select" name="factura">
                <?php foreach ($si_no_opts as $op): ?>
                  <option value="<?= h($op) ?>" <?= ($pedido['factura'] === $op ? 'selected':'') ?>><?= h($op) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">¿Recibió?</label>
              <select class="form-select" name="recibio">
                <?php foreach ($si_no_opts as $op): ?>
                  <option value="<?= h($op) ?>" <?= ($pedido['recibio'] === $op ? 'selected':'') ?>><?= h($op) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"><?= h($pedido['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label">Motivo (devolución/cancelación)</label>
              <textarea class="form-control" name="motivo" rows="2"><?= h($pedido['motivo'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label">Importe (auto)</label>
              <input type="text" class="form-control" id="inp_subtotal" value="<?= number_format((float)$pedido['subtotal'], 2) ?>" readonly>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Guardar
            </button>
          </div>

        </div>
      </div>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// recalcula subtotal en el cliente (visual)
const $cant = document.getElementById('inp_cantidad');
const $prec = document.getElementById('inp_precio');
const $sub  = document.getElementById('inp_subtotal');
function recalc() {
  const c = parseFloat($cant.value || 0);
  const p = parseFloat($prec.value || 0);
  $sub.value = (c * p).toFixed(2);
}
$cant.addEventListener('input', recalc);
$prec.addEventListener('input', recalc);

// submit por fetch para no salir de ver.php
const form = document.getElementById('form_editar_pedido');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const alertBox = document.getElementById('alert_box');
  alertBox.className = 'alert alert-info';
  alertBox.textContent = 'Guardando...';
  alertBox.classList.remove('d-none');

  try {
    const fd = new FormData(form);
    const res = await fetch('update_pedido.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body: fd
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error al guardar');

    alertBox.className = 'alert alert-success';
    alertBox.textContent = 'Cambios guardados correctamente';
  } catch (err) {
    alertBox.className = 'alert alert-danger';
    alertBox.textContent = 'No se pudo guardar: ' + err.message;
  }
});
</script>
</body>
</html>

<script>
(function(){
  const estadoSel  = document.querySelector('select[name="estado"]');
  const facturaSel = document.querySelector('select[name="factura"]');
  const recibioSel = document.querySelector('select[name="recibio"]');

  const estadoBadge  = document.getElementById('estadoBadge');
  const facturaBadge = document.getElementById('facturaBadge');
  const recibioBadge = document.getElementById('recibioBadge');

  const estadoColorMap = {
    'Solicitud':          'bg-info',
    'En Transito':        'bg-warning',
    'Recibido':           'bg-success',
    'Entregado Tecnico':  'bg-primary',
    'Por Devolver':       'bg-secondary',
    'Devuelto':           'bg-danger',
    'Cancelado':          'bg-secondary'
  };

  if (estadoSel && estadoBadge) {
    estadoSel.addEventListener('change', () => {
      estadoBadge.textContent = estadoSel.value;
      estadoBadge.className = 'badge ' + (estadoColorMap[estadoSel.value] || 'bg-secondary');
    });
  }

  if (facturaSel && facturaBadge) {
    facturaSel.addEventListener('change', () => {
      const ok = (facturaSel.value === 'Si');
      facturaBadge.textContent = 'Factura: ' + facturaSel.value;
      facturaBadge.className = 'badge ' + (ok ? 'bg-success' : 'bg-secondary');
    });
  }

  if (recibioSel && recibioBadge) {
    recibioSel.addEventListener('change', () => {
      const ok = (recibioSel.value === 'Si');
      recibioBadge.textContent = 'Recibió: ' + recibioSel.value;
      recibioBadge.className = 'badge ' + (ok ? 'bg-success' : 'bg-secondary');
    });
  }
})();
</script>
