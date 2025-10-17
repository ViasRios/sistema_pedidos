<?php
// Configuración de la base de datos
require_once 'config.php';

// ================== EXPORTAR CSV ==================
if (isset($_POST['exportar_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pedidos_export.csv');

    $output = fopen('php://output', 'w');

    // Encabezados (omitimos id, ya que es AUTO_INCREMENT)
    fputcsv($output, ['nombre_corto','cantidad','precio','subtotal','proveedor','cuenta',
                      'fecha_compra','fecha_llegada','forma_pago','usuario','estado','observaciones',
                      'ods','ods_id','factura','recibio','motivo','fecha_creacion','fecha_actualizacion','foto','url']);

    $result = $pdo->query("SELECT nombre_corto,cantidad,precio,subtotal,proveedor,cuenta,
                                  fecha_compra,fecha_llegada,forma_pago,usuario,estado,observaciones,
                                  ods,ods_id,factura,recibio,motivo,fecha_creacion,fecha_actualizacion,foto,url 
                           FROM pedidos");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// ================== IMPORTAR CSV ==================
$message_csv = '';
if (isset($_POST['importar_csv']) && isset($_FILES['importar_file'])) {
    $file = $_FILES['importar_file']['tmp_name'];
    $handle = fopen($file, 'r');

    $header = fgetcsv($handle); // Saltar encabezados

    while (($data = fgetcsv($handle)) !== FALSE) {
        // Si el CSV tiene 22 columnas (incluye id), la ignoramos
        if (count($data) == 22) {
            array_shift($data); // quita id
        }

        // Verificar que queden 21 columnas
        if (count($data) != 21) {
            continue; // omitir fila inválida o lanzar error
        }

        $stmt = $pdo->prepare("INSERT INTO pedidos 
        (nombre_corto,cantidad,precio,subtotal,proveedor,cuenta,
        fecha_compra,fecha_llegada,forma_pago,usuario,estado,observaciones,
        ods,ods_id,factura,recibio,motivo,fecha_creacion,fecha_actualizacion,foto,url)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute($data);
    }

    fclose($handle);
    $message_csv = "CSV importado correctamente!";
}




// Habilitar CORS para permitir solicitudes desde el mismo dominio
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Procesar filtros
$filtro_palabra = isset($_GET['buscar']) ? $_GET['buscar'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$ocultar_cancelados = isset($_GET['ocultar_cancelados']) ? true : false;
$solo_solicitud_transito = isset($_GET['solo_solicitud_transito']) ? true : false;
$solo_recibido = isset($_GET['solo_recibido']) ? true : false;

// Construir consulta con filtros
$sql = "SELECT * FROM pedidos WHERE 1=1";
$params = array();

if (!empty($filtro_palabra)) {
    $sql .= " AND (nombre_corto LIKE ? OR proveedor LIKE ? OR cuenta LIKE ? OR usuario LIKE ? OR observaciones LIKE ? OR ods LIKE ?)";
    $like_param = "%$filtro_palabra%";
    $params = array_merge($params, array_fill(0, 6, $like_param));
}

if (!empty($filtro_estado)) {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_fecha_desde)) {
    $sql .= " AND fecha_compra >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $sql .= " AND fecha_compra <= ?";
    $params[] = $filtro_fecha_hasta;
}

if ($solo_recibido) {
    $sql .= " AND estado = 'Recibido'";
}

if ($ocultar_cancelados) {
    $sql .= " AND estado != 'Cancelado'";
}

if ($solo_solicitud_transito) {
    $sql .= " AND (estado = 'Solicitud' OR estado = 'EnTransito')";
}

$sql .= " ORDER BY fecha_compra DESC";

// Preparar y ejecutar consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contador En Transito (en el conjunto actualmente mostrado)
$en_transito_count = 0;
foreach ($pedidos as $p) {
    if (($p['estado'] ?? '') === 'EnTransito') {
        $en_transito_count++;
    }
}

$vencidos_count = 0;
$hoy = date('Y-m-d');

foreach ($pedidos as $p) {
    $fecha_llegada = $p['fecha_llegada'] ?? null;
    $estado = $p['estado'] ?? '';
    if ($fecha_llegada && $fecha_llegada < $hoy && $estado === 'EnTransito') {
        $vencidos_count++;
    }
}


// ¿Mostrar total? Sólo si se filtró por fechas
$mostrar_total = !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta);

// Suma de subtotales de los pedidos filtrados, excluyendo Cancelado
$total_sin_cancelados = 0.0;
if ($mostrar_total) {
    foreach ($pedidos as $p) {
        if (strcasecmp(trim($p['estado'] ?? ''), 'Cancelado') !== 0) {
            $total_sin_cancelados += (float)$p['subtotal'];
        }
    }
}

// === Config de alineación ===
// Si tu tabla tiene la primera columna de "selección" (checkbox) pon true:
$tiene_columna_seleccion = true; // false si NO tienes checkbox

// Dónde quieres alinear el total: 'subtotal' o 'precio'
$alinear_en = 'subtotal'; // cambia a 'precio' si lo prefieres

// Cálculo de posiciones (cuenta columnas de tu thead)
$total_columnas  = 12 + ($tiene_columna_seleccion ? 1 : 0); // 14 base + 1 si hay checkbox
$index_precio    = ($tiene_columna_seleccion ? 4 : 3);      // posición de "Precio"
$index_subtotal  = ($tiene_columna_seleccion ? 5 : 4);      // posición de "Subtotal"
$target_index    = ($alinear_en === 'precio') ? $index_precio : $index_subtotal;

// colspans para tfoot
$colspan_antes   = $target_index - 1;                 // celdas antes de la columna objetivo
$colspan_despues = $total_columnas - $target_index;   // celdas después

// Obtener fecha actual para comparaciones
$hoy = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Pedidos</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #34495e;
            --secondary: #1abc9c;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger1: #e7973cff
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            padding: 30px 20px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--secondary);
        }

        h1 {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }

        button {
            padding: 12px 20px;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #16a085;
        }

        .filters {
            background-color: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .filters input, .filters select {
            padding: 10px;
            font-size: 1rem;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 100%;
        }

        .filters button {
            padding: 12px 20px;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .filters button:hover {
            background-color: #16a085;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: var(--primary);
            color: white;
            text-transform: uppercase;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-buttons button {
            padding: 6px 12px;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 4px;
            border: none;
            transition: all 0.3s ease;
        }

        .action-buttons .btn-edit {
            background-color: var(--secondary);
            color: white;
        }

        .action-buttons .btn-delete {
            background-color: var(--danger);
            color: white;
        }

        .status-checkbox {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .status-solicitud {
            background-color: #f1c40f;
            color: #fff;
        }

        .status-transito {
            background-color: #e67e22;
            color: #fff;
        }

        .status-recibido {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-devolver {
            background-color: #3c8ce7ff;
            color: #fff;
        }
        .status-devuelto {
            background-color: #e74c3c;
            color: #fff;
        }

        .status-cancelado {
            background-color: #95a5a6;
            color: #fff;
        }

        .close {
            font-size: 24px;
            cursor: pointer;
        }

        /* Filas según estado */
        .vencido {
            background-color: #ffe6e6 !important;
        }

        .devuelto {
            background-color: #fdecea !important;
        }

        #addPedidoModal .modal-body {
            max-height: 70vh;   /* 70% de la altura de la ventana */
            overflow-y: auto;
       }

       /* chip/ícono de vencido */
       .vencido-chip{
          display:inline-flex; align-items:center; gap:.25rem;
          font-size:.85rem; color:#dc3545;
        }
        .vencido-chip .dot{
          width:.5rem; height:.5rem; border-radius:50%;
          background:#dc3545; display:inline-block;
        }
        /* opcional: badge para “vence hoy” (naranja) */
        .badge-hoy{ background-color:#ffc107; color:#212529; }

    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row sticky-top-bar">
            <div class="col-12">
                <h1 class="text-center my-4">Sistema de Gestión de Pedidos</h1>
                <!-- Barra de herramientas -->
                <div class="d-flex justify-content-between mb-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPedidoModal">
                        <i class="fas fa-plus"></i> Nuevo Pedido
                    </button>
                    <div class="d-flex gap-2">
                  <!-- <span class="badge bg-danger" id="countVencidos">0</span> -->
                        <span class="badge rounded-pill bg-danger d-inline-flex align-items-center"
                              title="Pedidos vencidos">
                          <i class="fas fa-exclamation-triangle me-1"></i>
                          <span id="countVencidos"><?= (int)$vencidos_count ?></span>
                        </span>
                        <!-- Badge En Transito -->
                        <span class="badge rounded-pill bg-warning text-dark d-inline-flex align-items-center"
                              title="Pedidos en tránsito">
                          <i class="fas fa-truck-moving me-1"></i>
                          <span id="countEnTransito"><?= (int)$en_transito_count ?></span>
                        </span>
                        <!-- Exportar seleccionados -->
                        <form id="exportFormPdf" action="exportar_pdf.php" method="POST" class="d-inline" target="_blank">
                        
                        <button id="btn-exportar-pdf" type="submit" class="btn btn-danger" disabled>
                            <i class="fas fa-file-pdf"></i> Exportar PDF
                        </button>
                        
                        </form>

                    </div>
                </div>
                
                <!-- Filtros -->
                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="buscar" placeholder="Buscar..." value="<?= htmlspecialchars($filtro_palabra) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="Solicitud" <?= $filtro_estado == 'Solicitud' ? 'selected' : '' ?>>Solicitud</option>
                            <option value="EnTransito" <?= $filtro_estado == 'EnTransito' ? 'selected' : '' ?>>En Tránsito</option>
                            <option value="Recibido" <?= $filtro_estado == 'Recibido' ? 'selected' : '' ?>>Recibido</option>
                            <option value="EntregadoTecnico" <?= $filtro_estado == 'EntregadoTecnico' ? 'selected' : '' ?>>Entregado Técnico</option>
                            <option value="PorDevolver" <?= $filtro_estado == 'PorDevolver' ? 'selected' : '' ?>>Por Devolver</option>
                            <option value="Devuelto" <?= $filtro_estado == 'Devuelto' ? 'selected' : '' ?>>Devuelto</option>
                            <option value="Cancelado" <?= $filtro_estado == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">Desde</span>
                            <input type="date" class="form-control" name="fecha_desde" value="<?= $filtro_fecha_desde ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">Hasta</span>
                            <input type="date" class="form-control" name="fecha_hasta" value="<?= $filtro_fecha_hasta ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                           
                        </div> 
                    </div>
                </form>  
                    <div class="col-md-12">
                        <div class="d-flex gap-3 align-items-center">
                            <!-- Primer switch -->
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="solo_solicitud_transito" id="soloSolicitudTransito" <?= $solo_solicitud_transito ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="soloSolicitudTransito">Solo Solicitud/Tránsito</label>
                            </div>

                            <!-- Segundo switch -->
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ocultar_cancelados" id="ocultarCancelados" <?= $ocultar_cancelados ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="ocultarCancelados">Ocultar Cancelados</label>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="solo_recibido" id="soloRecibido"
                                        <?= $solo_recibido ? 'checked' : '' ?>
                                        onchange="
                                        if (this.checked) {
                                            // Limpia el select de estado (si existía selección)
                                            const sel = this.form.querySelector('select[name=estado]');
                                            if (sel) sel.value = '';
                                            // Desactiva 'Solo Solicitud/Tránsito' para que no choque
                                            const sst = this.form.querySelector('#soloSolicitudTransito');
                                            if (sst) sst.checked = false;
                                        }
                                        this.form.submit();
                                        ">
                                <label class="form-check-label" for="soloRecibido">Sólo Recibidos</label>
                            </div>

                            <!-- Exportar CSV -->
                            <form method="POST" class="d-inline">
                                <button type="submit" name="exportar_csv" class="btn btn-success">
                                    <i class="fas fa-file-csv"></i> Exportar 
                                </button>
                            </form>

                            <!-- Importar CSV -->
                            <form method="POST" enctype="multipart/form-data" class="d-inline">
                                <input type="file" name="importar_file" accept=".csv" required style="display:inline-block; width:auto;">
                                <button type="submit" name="importar_csv" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Importar
                                </button>
                            </form>


                            <?php if($message_csv != ''): ?>
                                <div class="alert alert-success mt-2"><?= htmlspecialchars($message_csv) ?></div>
                            <?php endif; ?>

                        </div>
                    </div>
              <!--  </form> -->
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="table-responsive">
                    <table id="tabla-pedidos" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width:36px;">
                                <input class="form-check-input" type="checkbox" id="select-all">
                                </th>
                                <th>ODS</th>
                                <th>Nombre</th>
                                <th>Foto</th>
                              <!--  <th>Cant</th> -->
                              <!--  <th>Precio</th> -->
                                <th>Importe</th>
                                <th>Proveedor</th>
                                <th>Fecha Compra</th>
                                <th>ETA</th>
                                <th>Forma Pago</th>
                                <th>Estado</th>
                                <th>Factura</th>
                                <th>Recibió</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <?php
                                $clase_fila = '';
                                if ($pedido['fecha_llegada'] < $hoy && $pedido['estado'] !== 'Recibido' && $pedido['estado'] !== 'Cancelado') {
                                    $clase_fila = 'vencido';
                                } elseif ($pedido['estado'] === 'Devuelto') {
                                    $clase_fila = 'devuelto';
                                }
                                ?>
                                <tr class="<?= $clase_fila ?> estado-<?= strtolower($pedido['estado']) ?>">
                                <td class="text-center">
                                <input
                                    class="form-check-input row-select"
                                    type="checkbox"
                                    value="<?= (int)$pedido['id'] ?>"
                                    data-subtotal="<?= (float)$pedido['subtotal'] ?>"
                                    data-estado="<?= htmlspecialchars($pedido['estado'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                </td>
                                <td>
                                    <?php 
                                    $id = $pedido['ods'] ?? $pedido['ods_id'] ?? null; // tomamos 'ods' primero, si no existe 'ods_id'

                                    if ($id): ?>
                                        <a href="http://localhost/VENTAS3/odsView/<?= $id ?>/" 
                                        target="_blank" class="text-decoration-underline ods-link" 
                                        title="Ver ODS en sistema externo">
                                            <?= htmlspecialchars($id) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                    <td><?= htmlspecialchars($pedido['nombre_corto']) ?></td>
                                    <td>
                                        <?php if (!empty($pedido['foto'])): ?>
                                            <img src="<?= htmlspecialchars($pedido['foto']) ?>" alt="Foto" width="100">
                                        <?php else: ?>
                                            Sin foto
                                        <?php endif; ?>
                                    </td>
                                  <!--  <td><?= $pedido['cantidad'] ?></td> -->
                                  <!--  <td>$<?= number_format($pedido['precio'], 2) ?></td> -->
                                    <td>$<?= number_format($pedido['subtotal'], 2) ?></td>
                                    <td><?= htmlspecialchars($pedido['proveedor']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($pedido['fecha_compra'])) ?></td>
                                    <!-- FECHA LLEGADA (editable) -->
                                    <td>
                                    <?php $fecha_y_m_d = date('Y-m-d', strtotime($pedido['fecha_llegada'])); ?>
                                    <input
                                        type="date"
                                        class="form-control form-control-sm"
                                        value="<?= $fecha_y_m_d ?>"
                                        onchange="updateCampo(<?= $pedido['id'] ?>, 'fecha_llegada', this.value)"
                                    >
                                    </td>
                                    <!-- FORMA PAGO (editable con 3 opciones) -->
                                    <td>
                                      <?= htmlspecialchars($pedido['forma_pago']) ?>
                                  <!--  <?php $fp = $pedido['forma_pago'] ?? ''; ?>
                                    <select class="form-select form-select-sm"
                                            onchange="updateCampo(<?= $pedido['id'] ?>, 'forma_pago', this.value)">
                                        <option value="Efectivo"     <?= $fp === 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="Transferencia"<?= $fp === 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                        <option value="Tarjeta"      <?= $fp === 'Tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                                    </select> -->
                                    </td>

                                    <td>
                                    <select class="form-select form-select-sm"
                                            data-current="<?= htmlspecialchars($pedido['estado']) ?>"
                                            onchange="cambiarEstado(this, <?= (int)$pedido['id'] ?>)">
                                        <?php
                                        $estados = ['Solicitud','EnTransito','Recibido','EntregadoTecnico','PorDevolver','Devuelto','Cancelado'];
                                        foreach ($estados as $estado):
                                            $sel = ($pedido['estado'] === $estado) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $estado ?>" <?= $sel ?>><?= $estado ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    </td>
                                    <!-- FACTURA (editable Si/No) -->
                                    <td>
                                    <select class="form-select form-select-sm"
                                            onchange="updateCampo(<?= $pedido['id'] ?>, 'factura', this.value)">
                                        <option value="No" <?= ($pedido['factura']=='No' ? 'selected':'') ?>>No</option>
                                        <option value="Si" <?= ($pedido['factura']=='Si' ? 'selected':'') ?>>Sí</option>
                                    </select>
                                    </td>
                                    <!-- RECIBIÓ (editable Si/No) -->
                                    <td>
                                    <select class="form-select form-select-sm"
                                            onchange="updateCampo(<?= $pedido['id'] ?>, 'recibio', this.value)">
                                        <option value="No" <?= ($pedido['recibio']=='No' ? 'selected':'') ?>>No</option>
                                        <option value="Si" <?= ($pedido['recibio']=='Si' ? 'selected':'') ?>>Sí</option>
                                    </select>
                                    </td>
                                    <td>
                                        <!--  <a href="ver.php?id=<?= $pedido['id'] ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a> -->
                                        <!-- Enlace para abrir el modal con el contenido de ver.php -->
                                          <a href="javascript:void(0);" class="btn btn-sm btn-info" data-id="<?= $pedido['id'] ?>">
                                              <i class="fas fa-eye"></i>
                                          </a>
                                        <a href="eliminar.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este pedido?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <!-- Total de los resultados filtrados (siempre visible, sin cancelados) -->
                        <tr class="table-secondary">
                            <td colspan="<?= $colspan_antes ?>" class="text-end fw-bold">
                                <div class="form-check">
                                  TOTAL  <input class="form-check-input" type="checkbox" id="incluirTotalPdf" name="incluir_total" value="1" form="exportFormPdf">
                                </div>
                            </td>
                            <td class="fw-bold">
                            $<?= number_format($total_sin_cancelados, 2) ?>
                            </td>
                            <td colspan="<?= $colspan_despues ?>"></td>
                        </tr>
                        
                        <!-- Total de los seleccionados con checkbox (se muestra sólo si hay selección) -->
                        <tr id="selectedTotalRow" class="table-warning d-none">
                            <td colspan="<?= $colspan_antes ?>" class="text-end fw-bold">
                                <div class="form-check">
                                  TOTAL seleccionados <input class="form-check-input" type="checkbox" id="incluirTotalPdf" name="incluir_total" value="1" form="exportFormPdf">
                                </div>
                            </td>
                            <td class="fw-bold" id="selectedTotalValue">$0.00</td>
                            <td colspan="<?= $colspan_despues ?>"></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="scripts.js"></script>
    
    <!-- Modal: Agregar Pedido -->
<div class="modal fade" id="addPedidoModal" tabindex="-1" aria-labelledby="addPedidoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formAgregarPedido" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="addPedidoModalLabel">Agregar Nuevo Pedido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <div id="alertAdd" class="alert d-none" role="alert"></div>

          <!-- ODS -->
          <div class="row mb-3 position-relative">
            <div class="col-md-6">
              <label for="ods" class="form-label">ODS</label>
              <div class="input-group">
                <input type="text" class="form-control" id="ods" name="ods" autocomplete="off" placeholder="Buscar por Idods...">
                <button class="btn btn-outline-secondary" type="button" id="btn-buscar-ods">
                  <i class="fas fa-search"></i>
                </button>
              </div>
              <input type="hidden" id="ods_id" name="ods_id">
              <div id="sugerencias-ods" class="list-group mt-1" style="display:none;"></div>
              <div class="form-text">Escribe al menos 2 caracteres para buscar ODS</div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <a id="link-ods" href="#" target="_blank" class="btn btn-outline-info btn-sm" style="display:none;">
                <i class="fas fa-external-link-alt"></i> Ver ODS en sistema externo
              </a>
            </div>
          </div>

          <!-- Datos principales -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="nombre_corto" class="form-label">Nombre *</label>
              <input type="text" class="form-control" id="nombre_corto" name="nombre_corto" required>
            </div>
            <div class="col-md-5">
              <label for="cantidad" class="form-label">Cantidad *</label>
              <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
            </div>
            
          </div>

          <div class="row mb-3">
            <div class="col-md-5">
              <label for="precio" class="form-label">Precio *</label>
              <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required>
            </div>
            <div class="col-md-6">
              <label for="proveedor" class="form-label">Proveedor *</label>
              <input type="text" class="form-control" id="proveedor" name="proveedor" required>
            </div>
           
          </div>

          <div class="row mb-3">
            <div class="col-md-5">
              <label for="fecha_compra" class="form-label">Fecha de Compra *</label>
              <input type="date" class="form-control" id="fecha_compra" name="fecha_compra" required>
            </div>
            <div class="col-md-5">
              <label for="fecha_llegada" class="form-label">Fecha Estimada de Llegada *</label>
              <input type="date" class="form-control" id="fecha_llegada" name="fecha_llegada" required>
            </div>
            
          </div>

          <div class="row mb-3">
            <div class="col-md-5">
              <label for="forma_pago" class="form-label">Forma de Pago</label>
              <input type="text" class="form-control" id="forma_pago" name="forma_pago">
              <!--  <option value="">Seleccione una opción</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta">Tarjeta</option>
              </select> -->
            </div>
            <div class="col-md-6">
              <label for="estado" class="form-label">Estado *</label>
              <select class="form-select" id="estado" name="estado" required>
                <option value="Solicitud">Solicitud</option>
                <option value="EnTransito">En Transito</option>
                <option value="Recibido">Recibido</option>
                <option value="EntregadoTecnico">Entregado Técnico</option>
                <option value="PorDevolver">Por Devolver</option>
                <option value="Devuelto">Devuelto</option>
                <option value="Cancelado">Cancelado</option>
              </select>
            </div>
          </div>

          <!-- Foto (subir imagen) -->
            <div class="row mb-3">
            <div class="col-md-6">
                <label for="foto" class="form-label">Foto (opcional)</label>
                <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                <div class="form-text">JPG, PNG, GIF o WEBP. Máx ~2MB.</div>
            </div>

            <div class="col-md-6">
                <label for="url" class="form-label">URL (opcional)</label>
                <input type="url" class="form-control" id="url" name="url" placeholder="https://...">
            </div>

            </div>


          <div class="row mb-3" id="div_motivo" style="display:none;">
            <div class="col-12">
              <label for="motivo" class="form-label">Motivo de devolución *</label>
              <textarea class="form-control" id="motivo" name="motivo" rows="2"></textarea>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <label for="factura" class="form-label">Factura</label>
              <select class="form-select" id="factura" name="factura" required>
                <option value="No">No</option>
                <option value="Si">Sí</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="recibio" class="form-label">¿Recibió?</label>
              <select class="form-select" id="recibio" name="recibio" required>
                <option value="No">No</option>
                <option value="Si">Sí</option>
              </select>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-12">
              <label for="observaciones" class="form-label">Observaciones</label>
              <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Pedido</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal ver.php-->
<?php
// Asumiendo que $pedidos es un arreglo de pedidos que obtuviste de la base de datos.
foreach ($pedidos as $pedido):
?>
    <!-- Modal con ID dinámico basado en el ID del pedido -->
    <div id="modalVer<?= $pedido['id'] ?>" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <!--  <h5 class="modal-title" id="modalLabel">Contenido del Pedido #<?= $pedido['id'] ?></h5> -->
                  
                </div>
                <div class="modal-body">
                    <!-- Aquí se cargará el contenido de ver.php -->
                     
                    <div id="verContent<?= $pedido['id'] ?>"></div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Enlazamos el archivo de Bootstrap (si no lo tienes ya) -->
<!-- Cargar Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<!-- Cargar Bootstrap 5 -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.min.js"></script>



<!-- Modal Devolver -->
<div class="modal fade" id="modalDevolver" tabindex="-1" aria-labelledby="modalDevolverLabel" aria-hidden="false">
  <div class="modal-dialog modal-md modal-dialog-scrollable">
    <form id="formDevolver">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDevolverLabel">Motivo de la devolución</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="pedidoIdDevolver" name="pedido_id" value="">
          <div class="mb-3">
            <label for="motivoDevolver" class="form-label">Describe el motivo *</label>
            <textarea class="form-control" id="motivoDevolver" name="motivo" rows="3" required></textarea>
            <div class="form-text">El estado se guardará como <b>Por Devolver</b>.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button id="btnCancelarDevolver" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Modal Cancelado -->
<div class="modal fade" id="modalCancelado" tabindex="-1" aria-labelledby="modalCanceladoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCanceladoLabel">Cancelar Pedido</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="formCancelado">
          <input type="hidden" id="pedidoIdCancelado" name="pedido_id" />
          
          <div class="mb-3">
            <label for="motivoCancelado" class="form-label">Motivo de Cancelación</label>
            <textarea class="form-control" id="motivoCancelado" name="motivo" rows="3" required></textarea>
          </div>
          
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-danger">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

    
</body>

</html>

<?php
// Función para obtener el color según el estado
function obtenerColorEstado($estado) {
    switch ($estado) {
        case 'Solicitud': return 'info';
        case 'EnTransito': return 'warning';
        case 'Recibido': return 'success';
        case 'PorDevolver': return 'danger1';
        case 'Devuelto': return 'danger';
        case 'Cancelado': return 'secondary';
        default: return 'secondary';
    }
}
?>

<script>
/* ============================================================
   BUSCADOR ODS EN LA TABLA (INDEX)
   ============================================================ */
function buscarODSEnIndex() {
  const searchInput = document.getElementById('search-ods');
  if (!searchInput) return;

  const searchTerm = searchInput.value.toLowerCase();
  const tableRows = document.querySelectorAll('#tabla-pedidos tbody tr');

  tableRows.forEach(row => {
    const odsCell = row.querySelector('.ods-link');
    const odsText = odsCell ? odsCell.textContent.toLowerCase() : '';
    row.style.display = odsText.includes(searchTerm) ? '' : 'none';
  });
}

// Utils de fecha (YYYY-MM-DD)
function getDateOnly(s){
  if (!s) return null;
  const [y,m,d] = String(s).trim().split('-').map(Number);
  if (!y || !m || !d) return null;
  const dt = new Date(y, m-1, d);
  dt.setHours(0,0,0,0);
  return dt;
}
function isHoy(fechaStr){
  const f = getDateOnly(fechaStr);
  if (!f) return false;
  const hoy = new Date(); hoy.setHours(0,0,0,0);
  return f.getTime() === hoy.getTime();
}
function isVencido(fechaStr, estado){
  const f = getDateOnly(fechaStr);
  if (!f) return false;
  const hoy = new Date(); hoy.setHours(0,0,0,0);
  // Usa tu normalizador existente:
  const est = (typeof normEstado === 'function') ? normEstado(estado) : String(estado||'').replace(/\s+/g,'');
  return est === 'PorDevolver' ? false : (est === 'EnTransito' && f.getTime() < hoy.getTime());
}

// Contador global
function recomputeVencidos(){
  const badge = document.getElementById('countVencidos');
  if (!badge) return;
  const n = document.querySelectorAll('#tabla-pedidos tbody .vencido-chip').length;
  badge.textContent = String(n);
}

/* Pinta en filas ya existentes (PHP) y futuras (JS):
   - Columna "Fecha Llegada" => badge naranja si es hoy; rojo si vencido
   - Columna "Estado" => agrega chip "Vencido" si aplica (NO cambia el estado)
*/
function decorarVencidosEnTabla(){
  const table = document.getElementById('tabla-pedidos');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  if (!tbody) return;

  // Índices según tu THEAD: 0 ✔, 1 ODS, 2 Nombre, 3 Foto, 4 Importe, 5 Proveedor,
  // 6 F. Compra, 7 F. Estimada Llegada, 8 Forma Pago, 9 Estado, 10 Factura, 11 Recibió, 12 Acciones
  const COL_FECHA_LLEGADA = 7;
  const COL_ESTADO        = 9;

  tbody.querySelectorAll('tr').forEach(tr => {
    const tds = tr.children;
    if (tds.length < 11) return;

    const tdFecha  = tds[COL_FECHA_LLEGADA];
    const tdEstado = tds[COL_ESTADO];

    // ⚠️ La fecha viene en un <input type="date">
    const inpFecha = tdFecha.querySelector('input[type="date"]');
    const fechaStr = (inpFecha ? inpFecha.value : (tdFecha.textContent||'')).trim();

    // Estado visible (del badge si existiera) o el texto del td
    // Estado visible: si hay <select>, tomamos su value; si no, intenta badge/texto
    const selEstado = tdEstado.querySelector('select');
    const estadoText = selEstado
      ? (selEstado.value || '').trim()
      : (tdEstado.querySelector('.badge')?.textContent || tdEstado.textContent || '').trim();

   // const estadoText = (tdEstado.querySelector('.badge')?.textContent || tdEstado.textContent || '').trim();

    // Limpia estilos previos
    tdFecha.classList.remove('bg-warning','bg-danger','text-dark','bg-warning-subtle');
    inpFecha?.classList.remove('border-warning','border-danger','border-2');

    // Aplica colores
    if (isHoy(fechaStr)) {
      // hoy → destacamos el input en naranja
      inpFecha?.classList.add('border-2','border-warning');
      // opcional leve de fondo:
      tdFecha.classList.add('bg-warning-subtle'); // si tu Bootstrap lo soporta
    } else if (isVencido(fechaStr, estadoText)) {
      // vencido → rojo en el input
      inpFecha?.classList.add('border-2','border-danger');
    }

    // chip “Vencido” (sólo aviso visual, no cambia estado)
    const yaTieneChip = tdEstado.querySelector('.vencido-chip');
    if (isVencido(fechaStr, estadoText)) {
      if (!yaTieneChip) {
        tdEstado.insertAdjacentHTML('beforeend',
          `<span class="vencido-chip ms-1" title="Vencido (fecha de llegada vencida)">
             <span class="dot"></span> Vencido
           </span>`);
      }
    } else if (yaTieneChip) {
      yaTieneChip.remove();
    }
  });

  recomputeVencidos();
}



// Inicialización del buscador ODS + listener de "estado" del form Agregar
document.addEventListener('DOMContentLoaded', function() {
  // Buscar en tiempo real (con debounce simple)
  decorarVencidosEnTabla();
  const searchInput = document.getElementById('search-ods');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      clearTimeout(window.buscarTimeout);
      window.buscarTimeout = setTimeout(buscarODSEnIndex, 300);
    });
  }

  // Mostrar/ocultar "motivo" en el formulario Agregar según estado
  const estadoElement = document.getElementById('estado');
  if (estadoElement) {
    estadoElement.addEventListener('change', function() {
      // (Tu lógica vive más abajo con normEstado; aquí no hace falta nada extra)
    });
  }
});
</script>

<script>
  /* ============================================================
   ESTADOS: NORMALIZAR + GUARDAR + MODAL DEVOLVER Y CANCELADO
   ============================================================ */

// === globals (usadas por varios handlers) ===
let estadoSelectRef = null;
let prevEstadoValue = '';

// Normaliza cualquier variante legacy a canónicos SIN espacios
function normEstado(v) {
  if (v && typeof v === 'object' && 'value' in v) v = v.value;
  const s = String(v ?? '');
  let t = s.toLowerCase();
  if (typeof t.normalize === 'function') t = t.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  t = t.replace(/\s+/g,' ').trim();

  const map = {
    'solicitud': 'Solicitud',
    'en transito': 'EnTransito', 'transito': 'EnTransito',
    'recibido': 'Recibido',
    'entregado tecnico': 'EntregadoTecnico',
    'por devolver': 'PorDevolver', 'devolver': 'PorDevolver',
    'devuelto': 'Devuelto',
    'cancelado': 'Cancelado'
  };
  return map[t] || s.replace(/\s+/g,'').trim();
}

async function saveEstado(pedidoId, estado, motivo) {
  estado = normEstado(estado);
  const body = new URLSearchParams({ pedido_id: pedidoId, estado });
  if (motivo) body.append('motivo', motivo); // Aquí se agrega el motivo

  const url = new URL('actualizar_estado.php', window.location.href);
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'fetch' },
    body: body.toString()
  });

  const txt = await res.text();
  let data; 
  try { data = JSON.parse(txt); } 
  catch { alert('Respuesta inválida'); throw new Error('Respuesta inválida del servidor'); }

  if (!res.ok || !data.ok) throw new Error(data.error || 'No se pudo actualizar');
  return data;
}

function cambiarEstado(select, pedidoId) {
  const nuevo  = normEstado(select.value);  // ← SIN espacios
  const actual = select.getAttribute('data-current') || select.value;

  if (nuevo === 'PorDevolver') {
    estadoSelectRef = select;
    prevEstadoValue = actual;
    document.getElementById('pedidoIdDevolver').value = pedidoId;
    document.getElementById('motivoDevolver').value = '';
    new bootstrap.Modal(document.getElementById('modalDevolver')).show();
    return;
  }

  if (nuevo === 'Cancelado') {
    estadoSelectRef = select;
    prevEstadoValue = actual;
    document.getElementById('pedidoIdCancelado').value = pedidoId;
    document.getElementById('motivoCancelado').value = '';  // Reseteamos el campo motivo
    new bootstrap.Modal(document.getElementById('modalCancelado')).show();
    return;
  }

  select.disabled = true;
  saveEstado(pedidoId, nuevo)
    .then(() => {
      select.setAttribute('data-current', nuevo);
      select.value = nuevo; // Actualiza el valor visible del select

      // Actualiza el contador si el estado cambia a "EnTransito"
      const badge = document.getElementById('countEnTransito');
      if (badge) {
        let count = parseInt(badge.textContent || '0', 10) || 0;
        if (actual !== 'EnTransito' && nuevo === 'EnTransito') count++;
        if (actual === 'EnTransito' && nuevo !== 'EnTransito') count = Math.max(0, count - 1);
        badge.textContent = String(count);
      }

      decorarVencidosEnTabla();
    })
    .catch(err => { alert(err.message); select.value = actual; })
    .finally(() => { select.disabled = false; });
}

// Submit del modal "PorDevolver" (guarda PorDevolver + motivo)
document.getElementById('formDevolver')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const pedidoId = document.getElementById('pedidoIdDevolver').value;
  const motivo   = document.getElementById('motivoDevolver').value.trim();  // Obtener el motivo
  if (!motivo) return document.getElementById('motivoDevolver').focus();  // Si no hay motivo, enfocamos el campo

  const btn = e.currentTarget.querySelector('button[type=submit]');
  btn.disabled = true;  // Deshabilitar el botón para evitar múltiples envíos
  try {
    await saveEstado(pedidoId, 'PorDevolver', motivo);  // Guardamos el estado "PorDevolver" y motivo
    bootstrap.Modal.getInstance(document.getElementById('modalDevolver')).hide();
    if (estadoSelectRef) {
      estadoSelectRef.value = 'PorDevolver';
      estadoSelectRef.setAttribute('data-current', 'PorDevolver');
    }
    decorarVencidosEnTabla();
  } catch (err) { alert(err.message); }
  finally { btn.disabled = false; }
});

// Submit del modal "Cancelado" (guarda Cancelado + motivo)
document.getElementById('formCancelado')?.addEventListener('submit', async (e)=>{
  e.preventDefault();  // Evita el comportamiento por defecto del formulario (recarga de página)
  
  const pedidoId = document.getElementById('pedidoIdCancelado').value;  // ID del pedido
  const motivo   = document.getElementById('motivoCancelado').value.trim();  // Obtener el motivo

  if (!motivo) return document.getElementById('motivoCancelado').focus();  // Si no hay motivo, enfocamos el campo

  const btn = e.currentTarget.querySelector('button[type=submit]');
  btn.disabled = true;  // Deshabilitar el botón para evitar múltiples envíos

  try {
    // Guardar el estado "Cancelado" junto con el motivo
    await saveEstado(pedidoId, 'Cancelado', motivo);
    
    // Cerrar el modal de Cancelado
    bootstrap.Modal.getInstance(document.getElementById('modalCancelado')).hide();

    // Actualizar el estado del pedido en la interfaz (visual)
    if (estadoSelectRef) {
      estadoSelectRef.value = 'Cancelado';  // Actualiza el valor del select
      estadoSelectRef.setAttribute('data-current', 'Cancelado');  // Actualiza el atributo de estado
    }

    // Llamar a la función para actualizar la vista si es necesario
    decorarVencidosEnTabla();

  } catch (err) {
    alert(err.message);  // Si ocurre un error, mostrarlo
  } finally {
    btn.disabled = false;  // Volver a habilitar el botón
  }
});

// Al cerrar el modal sin guardar, revertimos el select
document.getElementById('modalDevolver')?.addEventListener('hidden.bs.modal', () => {
  if (estadoSelectRef) {
    estadoSelectRef.value = prevEstadoValue;
    estadoSelectRef = null;
    prevEstadoValue = '';
  }
});
document.getElementById('modalCancelado')?.addEventListener('hidden.bs.modal', () => {
  if (estadoSelectRef) {
    estadoSelectRef.value = prevEstadoValue;
    estadoSelectRef = null;
    prevEstadoValue = '';
  }
});

// Mostrar/ocultar motivo en el formulario "Agregar" según estado
document.addEventListener('change', (e) => {
  if (e.target?.id === 'estado') {
    const divMotivo   = document.getElementById('div_motivo');
    const inputMotivo = document.getElementById('motivo');
    const est = normEstado(e.target.value); // 👈 compara canónico
    if (divMotivo && inputMotivo) {
      if (est === 'PorDevolver' || est === 'Cancelado') {
        divMotivo.style.display = 'block';
        inputMotivo.setAttribute('required', 'required');
      } else {
        divMotivo.style.display = 'none';
        inputMotivo.removeAttribute('required');
      }
    }
  }
});
</script>

<script>
  document.getElementById('form_editar_pedido_' + <?= (int)$pedido['id'] ?>).addEventListener('submit', async function(e) {
    e.preventDefault();  // Prevenir que el formulario se envíe de forma tradicional

    const alertBox = document.getElementById('alert_box');
    alertBox.className = 'alert alert-info';
    alertBox.textContent = 'Guardando...';
    alertBox.classList.remove('d-none');

    const formData = new FormData(this);  // Obtener los datos del formulario

    try {
        const response = await fetch('update_pedido.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: formData
        });

        const data = await response.json();  // Suponiendo que se devuelve un JSON

        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Error al guardar');
        }

        alertBox.className = 'alert alert-success';
        alertBox.textContent = 'Cambios guardados correctamente';
        // Redirigir a index.php y recargar la página
        window.location.href = 'index.php';
        // Cerrar el modal después de guardar
        const modal = new bootstrap.Modal(document.getElementById('modalVer' + pedidoId));
        modal.hide();
    } catch (error) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'No se pudo guardar: ' + error.message;
    }
});


</script>

<script>
/* ============================================================
   SUBMIT DEL MODAL AGREGAR (AJAX) + BÚSQUEDA ODS (INPUT)
   ============================================================ */

// util seguro
const $ = (id) => document.getElementById(id);

// Envío por fetch del modal Agregar
const formAgregar = $('formAgregarPedido');
formAgregar.addEventListener('submit', async (e) => {
  e.preventDefault();

  const alertBox = $('alertAdd');
  alertBox?.classList.add('d-none');

  const fd = new FormData(formAgregar);

  try {
    const res = await fetch('agregar.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' }, // ← importante p/JSON
      body: fd
    });

    const raw = await res.text(); // leer como texto primero
    let data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      console.error('JSON inválido desde agregar.php:', raw);
      throw new Error('Respuesta inválida del servidor (no JSON). Revisa notices/warnings en PHP.');
    }

    if (!res.ok || data.ok !== true) {
      throw new Error(data?.error || 'Error desconocido al agregar el pedido');
    }

    // cerrar modal de forma segura
    const modalEl = $('addPedidoModal');
    if (modalEl) {
      (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
    }

    // limpia el form
    formAgregar.reset();

    // oculta opcionales SOLO si existen (sin reventar)
    $('div_motivo')?.style && ( $('div_motivo').style.display = 'none' );
    $('link-ods')?.style && ( $('link-ods').style.display = 'none' );
    $('sugerencias-ods')?.style && ( $('sugerencias-ods').style.display = 'none' );

    // 🔥 inyecta la nueva fila si llegó
    if (data.pedido) {
      appendPedidoRow(data.pedido);
      decorarVencidosEnTabla();
    } else {
      console.warn('No vino data.pedido en la respuesta:', data);
      // (opcional) podrías recargar o volver a pedir la lista
      // reloadPedidos();
    }

  } catch (err) {
    console.error(err);
    if (alertBox) {
      alertBox.textContent = err.message;
      alertBox.className = 'alert alert-danger';
      alertBox.classList.remove('d-none');
    } else {
      alert(err.message);
    }
  }
});

// ====== Buscador ODS en el input del modal Agregar (autocomplete) ======
let searchTimeout = null;

function buscarODS() {
  const odsInput = document.getElementById('ods');
  const sugerenciasOds = document.getElementById('sugerencias-ods');
  const linkOds = document.getElementById('link-ods');

  if (linkOds) linkOds.style.display = 'none';

  if (!odsInput || odsInput.value.length < 2) {
    if (sugerenciasOds) sugerenciasOds.style.display = 'none';
    return;
  }

  fetch(`buscar_ods.php?term=${encodeURIComponent(odsInput.value)}`)
    .then(r => {
      if (!r.ok) throw new Error('Respuesta no válida del servidor');
      return r.json();
    })
    .then(data => {
      if (!sugerenciasOds) return;
      if (data.error) {
        sugerenciasOds.innerHTML = `<div class="list-group-item text-danger">${data.error}</div>`;
        sugerenciasOds.style.display = 'block';
        return;
      }
      if (!Array.isArray(data) || data.length === 0) {
        sugerenciasOds.innerHTML = '<div class="list-group-item">No se encontraron resultados</div>';
        sugerenciasOds.style.display = 'block';
        return;
      }

      sugerenciasOds.innerHTML = '';
      data.forEach(ods => {
        const a = document.createElement('a');
        a.href = '#';
        a.className = 'list-group-item list-group-item-action';
        a.innerHTML = `<strong>${ods.Idods}</strong>`;
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          if (odsInput) odsInput.value = ods.Idods;
          const odsId = document.getElementById('ods_id');
          if (odsId) odsId.value = ods.Idods;
          sugerenciasOds.style.display = 'none';
          if (ods.Idods && linkOds) {
            linkOds.href = `http://localhost/VENTAS3/odsView/${ods.Idods.split(' - ')[0]}/`;
            linkOds.style.display = 'inline-block';
          }
        });
        sugerenciasOds.appendChild(a);
      });
      sugerenciasOds.style.display = 'block';
    })
    .catch(err => {
      if (!sugerenciasOds) return;
      sugerenciasOds.innerHTML = `<div class="list-group-item text-danger">Error: ${err.message}</div>`;
      sugerenciasOds.style.display = 'block';
    });
}

document.addEventListener('input', function(e){
  if (e.target && e.target.id === 'ods') {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(buscarODS, 300);
  }
});

document.getElementById('btn-buscar-ods')?.addEventListener('click', buscarODS);

document.addEventListener('click', function(e) {
  const odsInput = document.getElementById('ods');
  const sugerenciasOds = document.getElementById('sugerencias-ods');
  if (!odsInput || !sugerenciasOds) return;
  if (!odsInput.contains(e.target) && !sugerenciasOds.contains(e.target)) {
    sugerenciasOds.style.display = 'none';
  }
});

document.addEventListener('keypress', function(e){
  if (e.target && e.target.id === 'ods' && e.key === 'Enter') {
    e.preventDefault();
    if (searchTimeout) clearTimeout(searchTimeout);
    buscarODS();
  }
});

/* ============================================================
   ACTUALIZAR CAMPOS INDIVIDUALES (fecha_llegada, forma_pago, factura, recibio)
   ============================================================ */
function updateCampo(pedido_id, campo, valor) {
  const permitidos = ['fecha_llegada', 'factura', 'recibio'];
  if (!permitidos.includes(campo)) return;

  const body = new URLSearchParams();
  body.append('pedido_id', pedido_id);
  body.append('campo', campo);
  body.append('valor', valor);

  fetch('actualizar_campo.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch'},
    body
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) throw new Error(data.error || 'Error al actualizar');
    // 👇 repinta si afecta vencidos
    if (campo === 'fecha_llegada' || campo === 'estado') {
      decorarVencidosEnTabla();
    }
  })
  .catch(err => {
    alert('No se pudo guardar: ' + err.message);
  });
}


/* ============================================================
   SELECCIÓN DE FILAS + EXPORTAR PDF + SUMA SELECCIONADOS
   (Unificado en un solo DOMContentLoaded)
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  const selectAll         = document.getElementById('select-all');
  const pdfBtn            = document.getElementById('btn-exportar-pdf');
  const pdfForm           = document.getElementById('exportFormPdf');

  // Suma seleccionados
  const selectedTotalRow  = document.getElementById('selectedTotalRow');
  const selectedTotalSpan = document.getElementById('selectedTotalValue');

  // Helpers locales para selección
  const getChecks   = () => Array.from(document.querySelectorAll('.row-select'));
  const getSelected = () => getChecks().filter(cb => cb.checked);

  // Habilitar/Deshabilitar botón PDF
  const updateButton = () => { if (pdfBtn) pdfBtn.disabled = (getSelected().length === 0); };

  // Total de seleccionados (ignorando Cancelado)
  function formatMoney(n) { return '$' + Number(n).toFixed(2); }
  function updateSelectedTotal() {
    const selected = getSelected();
    let total = 0;
    selected.forEach(cb => {
      const estado = (cb.dataset.estado || '').trim();
      if (estado !== 'Cancelado') {
        const s = parseFloat(cb.dataset.subtotal || 0);
        total += isNaN(s) ? 0 : s;
      }
    });
    if (selected.length > 0) {
      selectedTotalSpan && (selectedTotalSpan.textContent = formatMoney(total));
      selectedTotalRow?.classList.remove('d-none');
    } else {
      selectedTotalRow?.classList.add('d-none');
    }
  }

  //colores


  // Seleccionar todo
  selectAll?.addEventListener('change', () => {
    getChecks().forEach(cb => cb.checked = selectAll.checked);
    updateButton();
    updateSelectedTotal();
  });

  // Cambio por fila
  document.addEventListener('change', (e) => {
    if (e.target?.classList?.contains('row-select')) {
      if (!e.target.checked && selectAll?.checked) selectAll.checked = false;
      updateButton();
      updateSelectedTotal();
    }
  });

  // Inyectar IDs seleccionados al enviar PDF
  function injectIds(form) {
    form.querySelectorAll('input[name="ids[]"]').forEach(n => n.remove());
    getSelected().map(cb => cb.value).forEach(id => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
      form.appendChild(inp);
    });
  }

  // Incluir total en PDF si está marcado
  function injectIncluirTotal(form) {
    form.querySelectorAll('input[name="incluir_total"]').forEach(n => n.remove());
    const cb = document.getElementById('incluirTotalPdf');
    if (cb && cb.checked) {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'incluir_total';
      inp.value = '1';
      form.appendChild(inp);
    }
  }

  // Submit PDF
  pdfForm?.addEventListener('submit', (e) => {
    if (getSelected().length === 0) { e.preventDefault(); return; }
    injectIds(pdfForm);
    injectIncluirTotal(pdfForm);
  });

  // Estado inicial
  updateButton();
  updateSelectedTotal();
});
</script>

<script>
/* ============================================================
   HELPERS + CONSTRUCCIÓN/INSERCIÓN DE FILA NUEVA
   (Únicos: antes estaban duplicados)
   ============================================================ */

// Helpers
function money(n){ return '$' + Number(n||0).toFixed(2); }
function escapeHtml(s) {
  const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' };
  return String(s ?? '').replace(/[&<>"']/g, m => map[m]);
}
function fmtDate(s){ return (s && s.length>=8) ? s : ''; } // AAAA-MM-DD

// Mapea estado -> clase del badge (usa tus clases CSS)
function estadoBadgeClass(estado){
  const e = String(estado||'').toLowerCase().replace(/\s+/g,'');
  if (e==='solicitud')    return 'status-solicitud';
  if (e==='entransito')   return 'status-transito';
  if (e==='recibido')     return 'status-recibido';
  if (e==='pordevolver')  return 'status-devolver';
  if (e==='devuelto')     return 'status-devuelto';
  if (e==='cancelado')    return 'status-cancelado';
  return 'badge bg-secondary';
}

// Select de estado (usa tu cambiarEstado ya existente)
function estadoSelectHtml(p){
  const opciones = [
    ['Solicitud','Solicitud'],
    ['EnTransito','En tránsito'],
    ['Recibido','Recibido'],
    ['PorDevolver','Por devolver'],
    ['Devuelto','Devuelto'],
    ['Cancelado','Cancelado'],
  ];
  const actual = String(p.estado||'Solicitud').replace(/\s+/g,'').trim();
  const opts = opciones.map(([val,label])=>{
    const sel = (val===actual)?' selected':'';
    return `<option value="${val}"${sel}>${label}</option>`;
  }).join('');
  return `<select class="form-select form-select-sm estado-select"
                  data-current="${escapeHtml(actual)}"
                  onchange="cambiarEstado(this, ${Number(p.id)})">
            ${opts}
          </select>`;
}

// Badge Sí/No
function badgeYesNo(v){
  const vv = String(v||'No').toLowerCase();
  const yes = (vv==='si' || vv==='sí' || vv==='yes' || vv==='1' || vv==='true');
  return yes
    ? '<span class="badge bg-success">Sí</span>'
    : '<span class="badge bg-secondary">No</span>';
}

// Helper para comparar fechas (formato YYYY-MM-DD)
function getDateOnly(s) {
  if (!s) return null;
  const [y,m,d] = s.split('-').map(Number);
  return new Date(y, m-1, d);
}

// Construye el <tr> según TU <thead> (ODS, Nombre, Cant, Precio, Subtotal, Proveedor, Fechas, Forma Pago, Estado, Factura, Recibió, Acciones)
function buildPedidoRow(p){
  // --- Fechas y avisos visuales ---
  const hoy = new Date(); hoy.setHours(0,0,0,0);

  // Parse YYYY-MM-DD a Date (solo día)
  function getDateOnly(s){
    if (!s) return null;
    const [y,m,d] = String(s).split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m-1, d);
  }

  const llegada = getDateOnly(p.fecha_llegada);
  const estadoCanon = String(p.estado||'').replace(/\s+/g,''); // 'EnTransito' / 'Solicitud'...

  // Fecha llegada: badge naranja si es hoy; rojo si vencido (y seguía EnTransito)
  let fechaLlegadaHtml = escapeHtml(fmtDate(p.fecha_llegada));
  let vencidoChip = '';
  if (llegada) {
    if (llegada.getTime() === hoy.getTime()) {
      // vence hoy → naranja
      fechaLlegadaHtml = `<span class="badge bg-warning text-dark">${fechaLlegadaHtml}</span>`;
    } else if (llegada.getTime() < hoy.getTime() && estadoCanon === 'EnTransito') {
      // vencido (solo aviso visual, NO cambiamos p.estado)
      fechaLlegadaHtml = `<span class="badge bg-danger">${fechaLlegadaHtml}</span>`;
      vencidoChip = `
        <span class="vencido-chip" title="Vencido (fecha de llegada vencida)">
          <span class="dot"></span> Vencido
        </span>`;
    }
  }

  // --- Resto de celdas como las traías ---
  const odsTxt  = p.ods_id ? `${p.ods_id}` : (p.ods || '');
  const odsLink = p.ods_id
    ? `<a class="ods-link" href="http://localhost/VENTAS3/odsView/${encodeURIComponent(String(p.ods_id).split(' - ')[0])}/" target="_blank">${escapeHtml(odsTxt)}</a>`
    : `<span class="ods-link">${escapeHtml(odsTxt)}</span>`;

  const estadoClass = estadoBadgeClass(p.estado);
  const estadoBadge = `<span class="badge ${estadoClass}">
                         ${escapeHtml(String(p.estado||'Solicitud').replace(/([A-Z])/g,' $1').trim())}
                       </span>`;

  const rowSelect = `<input type="checkbox" class="form-check-input row-select"
                           value="${Number(p.id)}"
                           data-estado="${escapeHtml(p.estado||'')}"
                           data-subtotal="${Number(p.subtotal)||0}">`;

  const acciones = `
    <button class="btn btn-sm btn-primary" onclick="viewDetails(${Number(p.id)})">Ver</button>
    <button class="btn btn-sm btn-danger" onclick="deleteRecord(${Number(p.id)})">Eliminar</button>
  `;

  return `
    <tr id="row-pedido-${Number(p.id)}">
      <td class="text-center" style="width:36px;">${rowSelect}</td>
      <td>${odsLink}</td>
      <td>${escapeHtml(p.nombre_corto)}</td>
      <td class="text-end">${Number(p.cantidad)||0}</td>
      <td class="text-end">${money(p.precio)}</td>
      <td class="text-end">${money(p.subtotal)}</td>
      <td>${escapeHtml(p.proveedor||'')}</td>
      <td>${escapeHtml(fmtDate(p.fecha_compra))}</td>
      <td>${fechaLlegadaHtml}</td> <!-- 👈 usa la versión con color -->
      <td>${escapeHtml(p.forma_pago||'')}</td>
      <td>
        ${estadoBadge}
        ${vencidoChip}              <!-- 👈 aviso visual “Vencido” (si aplica) -->
        <div class="mt-1">${estadoSelectHtml(p)}</div>
      </td>
      <td>${badgeYesNo(p.factura)}</td>
      <td>${badgeYesNo(p.recibio)}</td>
      <td>${acciones}</td>
    </tr>
  `;
}

// Inserta/reemplaza la fila y actualiza contador "En Transito" + filtros activos
function appendPedidoRow(p){
  const tbody = document.querySelector('#tabla-pedidos tbody');
  if (!tbody) return;

  const html = buildPedidoRow(p);
  const tmp  = document.createElement('tbody');
  tmp.innerHTML = html.trim();
  const newRow = tmp.firstElementChild;

  const prev = document.getElementById(`row-pedido-${Number(p.id)}`);
  if (prev && prev.parentNode) prev.parentNode.replaceChild(newRow, prev);
  else tbody.insertBefore(newRow, tbody.firstChild); // al inicio

  // Actualiza badge # EnTransito (si lo usas)
  const badge = document.getElementById('countEnTransito');
  if (badge) {
    const est = String(p.estado||'').replace(/\s+/g,'').toLowerCase();
    if (est === 'entransito') {
      let c = parseInt(badge.textContent||'0',10) || 0;
      badge.textContent = String(c + 1);
    }
  }
  decorarVencidosEnTabla();
  // Reaplica filtro ODS si hay texto en buscador
  const search = document.getElementById('search-ods');
  if (search && search.value.trim() !== '') {
    buscarODSEnIndex?.();
  }
}
</script>


<!-- script Modal Ver.php -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const botones = document.querySelectorAll("a.btn-info");

    botones.forEach(boton => {
        boton.addEventListener("click", function () {
            const pedidoId = this.dataset.id;

            // IDs dinámicos según el pedido
            const modalId = "modalVer" + pedidoId;
            const contentId = "verContent" + pedidoId;
            console.log("Buscando ID:", contentId);
            console.log("Elemento encontrado:", document.getElementById(contentId));

            // Cargar contenido dinámico
            document.getElementById(contentId).innerHTML =
                "Cargando información del pedido " + pedidoId + "...";

            fetch("ver.php?id=" + pedidoId)
                .then(res => res.text())
                .then(data => {
                    document.getElementById(contentId).innerHTML = data;
                })
                .catch(err => {
                    document.getElementById(contentId).innerHTML = "Error cargando datos";
                });

            // Mostrar el modal dinámico
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        });
    });
});

</script>


<script>
    
</script>