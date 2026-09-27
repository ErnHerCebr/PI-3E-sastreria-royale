<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['rol'] ?? '') !== 'administrador_general') {
    responder(403, ['status' => 'error', 'message' => 'Acceso reservado al Administrador general.']);
}

$conexion = new mysqli('localhost', 'root', '', 'Dt_registro');
if ($conexion->connect_error) {
    responder(500, ['status' => 'error', 'message' => 'No se pudo conectar con la base de datos.']);
}
$conexion->set_charset('utf8mb4');

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo === 'GET') {
    $sucursales = $conexion->query("SELECT s.id, s.nombre, s.direccion, s.telefono, s.gerente_id, u.nombre AS gerente, (SELECT COUNT(*) FROM inventarios i WHERE i.sucursal_id = s.id) AS productos_inventario, (SELECT COALESCE(SUM(i.cantidad), 0) FROM inventarios i WHERE i.sucursal_id = s.id) AS unidades FROM sucursales s LEFT JOIN usuarios u ON u.id = s.gerente_id ORDER BY s.nombre");
    $gerentes = $conexion->query("SELECT u.id, u.nombre, u.email, u.sucursal_id, s.nombre AS sucursal FROM usuarios u LEFT JOIN sucursales s ON s.id = u.sucursal_id WHERE u.rol = 'gerente' ORDER BY u.nombre");
    $inventario = $conexion->query('SELECT i.id, i.producto, i.cantidad, i.actualizado, s.nombre AS sucursal FROM inventarios i JOIN sucursales s ON s.id = i.sucursal_id ORDER BY s.nombre, i.producto');
    $ventas = $conexion->query('SELECT v.id, v.producto, v.cantidad, v.total, v.fecha, s.nombre AS sucursal FROM ventas v JOIN sucursales s ON s.id = v.sucursal_id ORDER BY v.fecha DESC LIMIT 100');
    $resumen = $conexion->query("SELECT (SELECT COUNT(*) FROM sucursales) AS sucursales, (SELECT COUNT(*) FROM usuarios WHERE rol = 'gerente') AS gerentes, (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS clientes, (SELECT COALESCE(SUM(cantidad), 0) FROM inventarios) AS unidades, (SELECT COUNT(*) FROM ventas) AS ventas, (SELECT COALESCE(SUM(total), 0) FROM ventas) AS ingresos")->fetch_assoc();

    responder(200, [
        'status' => 'success',
        'resumen' => $resumen,
        'sucursales' => $sucursales->fetch_all(MYSQLI_ASSOC),
        'gerentes' => $gerentes->fetch_all(MYSQLI_ASSOC),
        'inventario' => $inventario->fetch_all(MYSQLI_ASSOC),
        'ventas' => $ventas->fetch_all(MYSQLI_ASSOC)
    ]);
}

if ($metodo !== 'POST') {
    responder(405, ['status' => 'error', 'message' => 'Método no permitido.']);
}

$csrfRecibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfRecibido)) {
    responder(419, ['status' => 'error', 'message' => 'La sesión expiró. Inicia sesión nuevamente.']);
}

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $datos['accion'] ?? '';

if ($accion === 'crear_sucursal' || $accion === 'actualizar_sucursal') {
    $id = (int) ($datos['id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $direccion = trim($datos['direccion'] ?? '');
    $telefono = trim($datos['telefono'] ?? '');
    if ($nombre === '' || $direccion === '') {
        responder(400, ['status' => 'error', 'message' => 'Nombre y dirección son obligatorios.']);
    }
    if ($accion === 'crear_sucursal') {
        $stmt = $conexion->prepare('INSERT INTO sucursales (nombre, direccion, telefono) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $nombre, $direccion, $telefono);
    } else {
        $stmt = $conexion->prepare('UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ? WHERE id = ?');
        $stmt->bind_param('sssi', $nombre, $direccion, $telefono, $id);
    }
    if (!$stmt->execute()) {
        responder(409, ['status' => 'error', 'message' => 'No se pudo guardar. Verifica que el nombre no esté duplicado.']);
    }
    responder(200, ['status' => 'success', 'message' => 'Sucursal guardada.']);
}

if ($accion === 'eliminar_sucursal') {
    $id = (int) ($datos['id'] ?? 0);
    $stmt = $conexion->prepare('DELETE FROM sucursales WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        responder(409, ['status' => 'error', 'message' => 'No se puede eliminar: la sucursal tiene ventas asociadas.']);
    }
    responder(200, ['status' => 'success', 'message' => 'Sucursal eliminada.']);
}

if ($accion === 'crear_gerente') {
    $nombre = trim($datos['nombre'] ?? '');
    $email = trim($datos['email'] ?? '');
    $password = $datos['password'] ?? '';
    $sucursalId = (int) ($datos['sucursal_id'] ?? 0);
    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $sucursalId < 1) {
        responder(400, ['status' => 'error', 'message' => 'Completa los datos; la contraseña debe tener al menos 8 caracteres.']);
    }
    $sucursal = $conexion->prepare('SELECT id FROM sucursales WHERE id = ?');
    $sucursal->bind_param('i', $sucursalId);
    $sucursal->execute();
    if ($sucursal->get_result()->num_rows === 0) {
        responder(400, ['status' => 'error', 'message' => 'La sucursal seleccionada no existe.']);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, rol, sucursal_id) VALUES (?, ?, ?, 'gerente', ?)");
    $stmt->bind_param('sssi', $nombre, $email, $hash, $sucursalId);
    if (!$stmt->execute()) {
        responder(409, ['status' => 'error', 'message' => 'No se pudo crear el gerente; verifica que el correo no esté registrado.']);
    }
    $gerenteId = $stmt->insert_id;
    $asignar = $conexion->prepare('UPDATE sucursales SET gerente_id = ? WHERE id = ?');
    $asignar->bind_param('ii', $gerenteId, $sucursalId);
    $asignar->execute();
    responder(200, ['status' => 'success', 'message' => 'Gerente creado y asignado.']);
}

if ($accion === 'asignar_gerente') {
    $gerenteId = (int) ($datos['gerente_id'] ?? 0);
    $sucursalId = (int) ($datos['sucursal_id'] ?? 0);
    $gerente = $conexion->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'gerente'");
    $gerente->bind_param('i', $gerenteId);
    $gerente->execute();
    $sucursal = $conexion->prepare('SELECT id FROM sucursales WHERE id = ?');
    $sucursal->bind_param('i', $sucursalId);
    $sucursal->execute();
    if ($gerente->get_result()->num_rows === 0 || $sucursal->get_result()->num_rows === 0) {
        responder(400, ['status' => 'error', 'message' => 'El gerente o la sucursal seleccionada no existe.']);
    }
    $conexion->begin_transaction();
    $quitar = $conexion->prepare('UPDATE sucursales SET gerente_id = NULL WHERE gerente_id = ?');
    $quitar->bind_param('i', $gerenteId);
    $quitar->execute();
    $asignar = $conexion->prepare('UPDATE sucursales SET gerente_id = ? WHERE id = ?');
    $asignar->bind_param('ii', $gerenteId, $sucursalId);
    $asignar->execute();
    $actualizar = $conexion->prepare('UPDATE usuarios SET sucursal_id = ? WHERE id = ? AND rol = \'gerente\'');
    $actualizar->bind_param('ii', $sucursalId, $gerenteId);
    $actualizar->execute();
    $conexion->commit();
    responder(200, ['status' => 'success', 'message' => 'Asignación actualizada.']);
}

responder(400, ['status' => 'error', 'message' => 'Acción administrativa desconocida.']);