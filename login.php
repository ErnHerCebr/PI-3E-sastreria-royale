<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$conexion = new mysqli('localhost', 'root', '', 'Dt_registro');

if ($conexion->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar con la base de datos.'
    ]);
    exit();
}

$conexion->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido.'
    ]);
    $conexion->close();
    exit();
}

$email = trim($_POST['loginEmail'] ?? '');
$password = $_POST['loginPassword'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'El correo y la contraseña son obligatorios.'
    ]);
    $conexion->close();
    exit();
}

$stmt = $conexion->prepare('SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ? LIMIT 1');

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar la consulta de acceso.'
    ]);
    $conexion->close();
    exit();
}

$stmt->bind_param('s', $email);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (!$usuario || !password_verify($password, $usuario['password'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Correo o contraseña incorrectos.'
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

session_regenerate_id(true);
$_SESSION['usuario'] = [
    'id' => (int) $usuario['id'],
    'nombre' => $usuario['nombre'],
    'email' => $usuario['email'],
    'rol' => $usuario['rol']
];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'status' => 'success',
    'message' => 'Inicio de sesión correcto.',
    'nombre' => $usuario['nombre'],
    'email' => $usuario['email'],
    'rol' => $usuario['rol'],
    'csrfToken' => $_SESSION['csrf_token']
]);

$stmt->close();
$conexion->close();
?>
