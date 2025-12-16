<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Usamos tu archivo de conexión existente: database.php  <-- CAMBIO AQUÍ
require_once '../config/database.php';

$email = isset($_POST['email']) ? $_POST['email'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    header('Location: login.php?error=campos_vacios');
    exit();
}

$sql = "SELECT id, email, password, id_sucursal FROM usuarios WHERE email = ?";
$stmt = $conexion->prepare($sql);

if ($stmt === false) {
    die("Error al preparar la consulta: " . $conexion->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_sucursal_id'] = $user['id_sucursal'];

        header('Location: index.php');
        exit();

    } else {
        header('Location: login.php?error=credenciales_invalidas');
        exit();
    }
} else {
    header('Location: login.php?error=credenciales_invalidas');
    exit();
}

$stmt->close();
$conexion->close();
?>