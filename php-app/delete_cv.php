<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conect.php';

require_login();

// Restricción de seguridad: Solo administradores pueden eliminar registros
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Acceso denegado: No tienes permisos para realizar esta acción.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = $_POST['id'];

    // Recuperamos información de los archivos para eliminarlos del disco
    $sqlSelect = "SELECT stored_path, converted_filename, name FROM cvs WHERE id = :id";
    $stmtSelect = $conexion->prepare($sqlSelect);
    $stmtSelect->execute([':id' => $id]);
    $cv = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if ($cv) {
        $uploadDir = __DIR__ . '/uploads/';

        // Eliminación del archivo original
        if (!empty($cv['stored_path']) && file_exists($uploadDir . $cv['stored_path'])) {
            unlink($uploadDir . $cv['stored_path']);
        }

        // Eliminación de la versión PDF convertida
        if (!empty($cv['converted_filename']) && 
            $cv['converted_filename'] !== $cv['stored_path'] && 
            file_exists($uploadDir . $cv['converted_filename'])) {
            unlink($uploadDir . $cv['converted_filename']);
        }

        // Eliminación del registro en la base de datos
        $sqlDelete = "DELETE FROM cvs WHERE id = :id";
        $stmtDelete = $conexion->prepare($sqlDelete);
        
        try {
            $stmtDelete->execute([':id' => $id]);
            
            $usuarioLog = $_SESSION['nombre'] ?? 'Admin';
            $nombreCandidato = $cv['name'] ?? 'Desconocido';
            log_action($usuarioLog, 'DELETE', "Eliminó el CV de: $nombreCandidato");

            header('Location: /list.php');
            exit;
        } catch (PDOException $e) {
            die("Error al eliminar el CV: " . $e->getMessage());
        }
    } else {
        die("CV no encontrado.");
    }
} else {
    header('Location: /list.php');
    exit;
}
