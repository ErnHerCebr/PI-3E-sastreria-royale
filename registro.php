<?php

$host     = "localhost";      
$usuario  = "root";           
$password = "";               
$db_name  = "Dt_registro";    

// Establecer la conexión usando mysqli
$conexion = new mysqli($host, $usuario, $password, $db_name);

// Verifica si hubo un error de conexión
if ($conexion->connect_error) {
    // Si falla, retornamos una respuesta JSON con el error
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "error", 
        "message" => "Error de conexión a la base de datos: " . $conexion->connect_error
    ]);
    exit();
}


$conexion->set_charset("utf8mb4");


// PROCESAR EL REGISTRO
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Obtener y limpiar datos recibidos
    $nombre = isset($_POST['regName']) ? trim($_POST['regName']) : '';
    $email = isset($_POST['regEmail']) ? trim($_POST['regEmail']) : '';
    $pass = isset($_POST['regPassword']) ? $_POST['regPassword'] : '';

    // Validar que los campos no estén vacíos
    if (empty($nombre) || empty($email) || empty($pass)) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Todos los campos son obligatorios."]);
        exit();
    }

    // 1. Verificar si el correo ya existe en la base de datos
    $checkEmail = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    if (!$checkEmail) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "No se pudo preparar la consulta de verificación."]);
        exit();
    }
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();

    if ($result->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya está registrado."]);
        $checkEmail->close();
        exit();
    }
    $checkEmail->close();

    // Encriptar la contraseña 
    $passwordHash = password_hash($pass, PASSWORD_BCRYPT);

    // Insertar el nuevo usuario en la tabla 'usuarios'
    $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "No se pudo preparar el registro del usuario."]);
        exit();
    }
    $stmt->bind_param("sss", $nombre, $email, $passwordHash);

    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            "status" => "success", 
            "message" => "¡Usuario registrado correctamente!"
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            "status" => "error", 
            "message" => "Error al guardar el usuario: " . $stmt->error
        ]);
    }

    $stmt->close();
}

// Cerrar conexión
$conexion->close();
?>