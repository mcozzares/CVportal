<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica que el usuario esté autenticado; si no, redirige al login
function require_login(): void
{
    if (empty($_SESSION['id_usuario'])) {
        header('Location: /index.php');
        exit;
    }
}

// Función para registrar eventos en la tabla de auditoría
function log_action($username, $action, $details = null) {
    global $conexion;
    
    if (!isset($conexion)) {
        require_once __DIR__ . '/conect.php';
    }

    try {
        $sql = "INSERT INTO auditoria (username, action, details) VALUES (:user, :action, :details)";
        $stmt = $conexion->prepare($sql);
        
        $stmt->bindParam(':user', $username);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        
        $stmt->execute();
    } catch (Exception $e) {
        // Manejo silencioso de errores en el log para no interrumpir el flujo
        error_log("Error al escribir log de auditoría: " . $e->getMessage());
    }
}
