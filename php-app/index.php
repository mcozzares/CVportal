<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth_ldap.php';

$error = '';

// Si el usuario ya tiene sesión iniciada, redirigimos al listado
if (!empty($_SESSION['id_usuario'])) {
    header('Location: /list.php');
    exit;
}

// Procesar el formulario de login
if (isset($_POST['enviar'])) {
    $nombre = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($nombre) || empty($password)) {
        $error = 'Rellena todos los campos.';
    } else {
        // Intentamos autenticación principal vía LDAP
        $ldap_result = authenticate_user_ldap($nombre, $password);

        if ($ldap_result['success']) {
            $_SESSION['id_usuario'] = $ldap_result['user_id'];
            $_SESSION['nombre'] = $ldap_result['username'];
            $_SESSION['role'] = $ldap_result['role'];
            $_SESSION['auth_source'] = 'ldap';

            log_action($nombre, 'LOGIN', 'Inicio de sesión exitoso vía LDAP');

            header('Location: /list.php');
            exit;
        } else {
            // Si falla LDAP, verificamos en la base de datos local (fallback)
            $sql = "SELECT * FROM usuarios WHERE nombre = :nombre";
            $sentencia = $conexion->prepare($sql);
            $sentencia->setFetchMode(PDO::FETCH_ASSOC);
            $sentencia->bindParam(':nombre', $nombre);
            $sentencia->execute();
            $fila = $sentencia->fetch();

            if ($fila && $fila['nombre'] === $nombre && $fila['password'] === $password) {
                $_SESSION['id_usuario'] = $fila['id'];
                $_SESSION['nombre'] = $fila['nombre'];
                $_SESSION['role'] = $fila['role'] ?? 'user';
                $_SESSION['auth_source'] = 'local';

                header('Location: /list.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CV Portal - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
</head>
<body class="app-body login-page">
    <div class="login-wrapper">
        <div class="main login-card">
            <div class="login-header">
                <div class="logo-crop-container">
                    <img src="imagenes/CVLOGO.png" alt="Logo CV Portal" class="login-logo-img">
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="input-group">
                    <label for="username">USUARIO</label>
                    <input type="text" id="username" name="username" placeholder="Nombre de usuario" required>
                </div>

                <div class="input-group">
                    <label for="password">CONTRASEÑA</label>
                    <input type="password" id="password" name="password" placeholder="••••••" required>
                </div>

                <div style="text-align: left; margin-top: 25px;">
                    <button type="submit" name="enviar" class="btn-login" title="Iniciar Sesión">
                        <svg class="icon-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                        <span class="text-go">Go!</span>
                    </button>
                </div>
            </form>
            
            <div class="login-footer">
                <p class="created-by">Created by <a href="https://www.linkedin.com/in/marcos-collado-ca%C3%B1izares-91607525a/" target="_blank" title="Ver perfil de LinkedIn" style="text-decoration: none; color: inherit;"><span>Mcözares</span></a></p>
            </div>
        </div>
    </div>
</body>
</html>