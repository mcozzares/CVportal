<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conect.php';

require_login();

ob_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Debes seleccionar un archivo de CV válido.';
    } else {
        // Aseguramos que existe el directorio de subidas
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $originalName = $_FILES['cv']['name'];
        $tmpPath      = $_FILES['cv']['tmp_name'];
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Generamos un nombre único para evitar conflictos
        $storedName = uniqid('cv_', true) . '.' . $extension;
        $storedPath = $uploadDir . $storedName;

        if (!move_uploaded_file($tmpPath, $storedPath)) {
            $error = 'Error al guardar el archivo subido.';
        } else {
            $convertedName = null;
            // Si el archivo ya es PDF lo usamos directamente, si no, convertimos
            if ($extension === 'pdf') {
                $convertedName = $storedName;
            } else {
                try {
                    $outputName = uniqid('cv_', true) . '.pdf'; 
                    $tempDir = sys_get_temp_dir();
                    $tempDirUri = str_replace('\\', '/', $tempDir);
                    $userInstallDir = 'file:///' . $tempDirUri . '/lo_user_' . uniqid();
                    
                    // Ejecutamos LibreOffice en modo headless para la conversión
                    $cmd = sprintf(
                        'soffice -env:UserInstallation=%s --headless --convert-to pdf --outdir %s %s 2>&1',
                        escapeshellarg($userInstallDir),
                        escapeshellarg($uploadDir),
                        escapeshellarg($storedPath)
                    );

                    $output    = [];
                    $returnVar = 0;
                    exec($cmd, $output, $returnVar);

                    $generatedPdf = $uploadDir . pathinfo($storedName, PATHINFO_FILENAME) . '.pdf';

                    if ($returnVar === 0 && file_exists($generatedPdf)) {
                        rename($generatedPdf, $uploadDir . $outputName);
                        $convertedName = $outputName;
                    }
                } catch (Throwable $e) {
                    $error = 'Excepción al convertir: ' . $e->getMessage();
                }
            }
            
            // Extracción de datos de contacto desde el contenido del PDF
            $email      = 'No disponible';
            $phone      = 'No disponible';

            try {
                $srcFile = $convertedName ? ($uploadDir . $convertedName) : $storedPath;
                $txtFile = $uploadDir . uniqid('txt_', true) . '.txt';
                $content = '';

                $output = [];
                $ret = 0;
                exec(sprintf('pdftotext %s %s 2>&1', escapeshellarg($srcFile), escapeshellarg($txtFile)), $output, $ret);
                if (file_exists($txtFile)) {
                    $content .= file_get_contents($txtFile) . "\n";
                    unlink($txtFile);
                }

                $output = [];
                exec(sprintf('pdftotext -layout %s %s 2>&1', escapeshellarg($srcFile), escapeshellarg($txtFile)), $output, $ret);
                if (file_exists($txtFile)) {
                    $content .= file_get_contents($txtFile) . "\n";
                    unlink($txtFile);
                }
                
                $cmdPandoc = sprintf('pandoc %s -t plain 2>&1', escapeshellarg($srcFile));
                $pandocOutput = [];
                $pandocRet = 0;
                exec($cmdPandoc, $pandocOutput, $pandocRet);
                if (!empty($pandocOutput) && $pandocRet === 0) {
                    $content .= implode("\n", $pandocOutput);
                }

                if (!empty($content)) {
                    // Búsqueda de direcciones de correo electrónico
                    if (preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $content, $matches)) {
                        foreach ($matches[0] as $match) {
                            if (filter_var($match, FILTER_VALIDATE_EMAIL)) {
                                $email = $match;
                                break;
                            }
                        }
                    }
                    
                    // Búsqueda de números de teléfono
                    if (preg_match('/(?:\+?34[\s.-]?)?[6789]\d{2}[\s.-]?\d{3}[\s.-]?\d{3}/', $content, $matches)) {
                        $phone = $matches[0];
                    }
                }
            } catch (Exception $e) {
                $error .= " | Excepción extracción: " . $e->getMessage();
            }

            try {
                // Insertamos el registro en la base de datos
                $sql = 'INSERT INTO cvs (name, email, phone, original_filename, stored_path, converted_filename, uploaded_at)
                        VALUES (:name, :email, :phone, :original_filename, :stored_path, :converted_filename, NOW())';

                $sentencia = $conexion->prepare($sql);
                $sentencia->bindParam(':name', $name);
                $sentencia->bindParam(':email', $email);
                $sentencia->bindParam(':phone', $phone);
                $sentencia->bindParam(':original_filename', $originalName);
                $sentencia->bindParam(':stored_path', $storedName);
                $sentencia->bindParam(':converted_filename', $convertedName);

                $isOk = $sentencia->execute();

                if ($isOk) {
                    $usuarioLog = $_SESSION['nombre'] ?? 'Desconocido';
                    log_action($usuarioLog, 'UPLOAD', "Subió CV de: $name");

                    header('Location: success.php');
                    exit;
                } else {
                    $error = 'Error al guardar los datos en la base de datos.';
                }
            } catch (PDOException $e) {
                $error = 'Error al guardar los datos en la base de datos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir CV</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
</head>
<body class="app-body">
<div class="main">
    <div class="nav-links">
        <a href="list.php">Listado de CVs</a>
        <a href="logout.php">Cerrar sesión</a>
    </div>
    <h1>Subir nuevo CV</h1>
    <p>Introduce el nombre de la persona y selecciona un archivo de CV (Word, PDF, etc.).</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label for="name">Nombre completo</label>
        <input type="text" id="name" name="name" required>

        <label for="cv">Archivo de CV</label>
        <input type="file" id="cv" name="cv" required>

        <button type="submit">Subir CV</button>
    </form>
</div>

<div class="theme-switch">
    <button id="theme-light" class="theme-btn" type="button">Light</button>
    <button id="theme-dark" class="theme-btn" type="button">Dark</button>
</div>
<script>
(function() {
    const btnLight = document.getElementById('theme-light');
    const btnDark = document.getElementById('theme-dark');
    if (!btnLight || !btnDark) return;

    const body = document.body;

    const updateButtons = (isDark) => {
        if (isDark) {
            btnLight.classList.remove('active');
            btnDark.classList.add('active');
        } else {
            btnLight.classList.add('active');
            btnDark.classList.remove('active');
        }
    };

    const setTheme = (isDark) => {
        body.classList.toggle('dark-mode', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateButtons(isDark);
    };

    const savedTheme = localStorage.getItem('theme');
    const isDark = savedTheme === 'dark';
    setTheme(isDark);

    btnLight.addEventListener('click', () => setTheme(false));
    btnDark.addEventListener('click', () => setTheme(true));
})();
</script>
</body>
</html>