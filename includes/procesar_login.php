<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- CAMBIO: AHORA TAMBIÉN SELECCIONAMOS id_sucursal ---
    $sql = "SELECT u.id, u.nombre, u.email, u.password, u.id_sucursal, r.nombre_rol 
            FROM usuarios u
            JOIN usuario_roles ur ON u.id = ur.id_usuario
            JOIN roles r ON ur.id_rol = r.id
            WHERE u.email = ? AND u.activo = 1";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        if (password_verify($password, $usuario['password'])) {
            // --- CAMBIO: GUARDAMOS LA SUCURSAL EN LA SESIÓN ---
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nombre'] = $usuario['nombre'];
            $_SESSION['user_rol'] = $usuario['nombre_rol'];
            $_SESSION['user_sucursal_id'] = $usuario['id_sucursal']; // ¡Importante!

            header("Location: ../public/index.php");
            exit();
        }
    }
    
    header("Location: ../public/login.php?error=1");
    exit();
}