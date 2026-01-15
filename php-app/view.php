<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conect.php';

require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo 'ID de CV no válido.';
    exit;
}

$sql = 'SELECT id, name, email, phone, original_filename, stored_path, converted_filename, uploaded_at 
        FROM cvs WHERE id = :id';
$sentencia = $conexion->prepare($sql);
$sentencia->bindParam(':id', $id, PDO::PARAM_INT);
$sentencia->execute();
$cv = $sentencia->fetch(PDO::FETCH_ASSOC);

if (!$cv) {
    http_response_code(404);
    echo 'CV no encontrado.';
    exit;
}

$originalPath     = htmlspecialchars($cv['stored_path'], ENT_QUOTES, 'UTF-8');
$originalFilename = htmlspecialchars($cv['original_filename'], ENT_QUOTES, 'UTF-8');
$originalUrl      = '/uploads/' . $originalPath;
$pdfUrl           = !empty($cv['converted_filename'])
    ? '/uploads/' . htmlspecialchars($cv['converted_filename'], ENT_QUOTES, 'UTF-8')
    : null;

$previewUrl = $pdfUrl;
if ($previewUrl === null) {
    $ext = strtolower(pathinfo($cv['original_filename'], PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $previewUrl = $originalUrl;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle CV - <?php echo htmlspecialchars($cv['name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
</head>
<body class="app-body">
<div class="main">
    <div class="nav-links">
        <a href="list.php">Volver al listado</a>
        <a href="logout.php">Cerrar sesión</a>
    </div>

    <h1>CV de <?php echo htmlspecialchars($cv['name'], ENT_QUOTES, 'UTF-8'); ?></h1>

    <p style="color: #666; font-size: 0.9em; margin-bottom: 20px;">
        <span style="font-weight: 500;">Subido el:</span> <?php echo date('d/m/Y', strtotime($cv['uploaded_at'])); ?>
    </p>

    <div class="contact-buttons" style="margin: 20px 0;">
        <?php if (!empty($cv['email']) && $cv['email'] !== 'No disponible'): ?>
            <?php 
                $gmailLink = 'https://mail.google.com/mail/?view=cm&fs=1&to=' . urlencode($cv['email']) . '&su=Contacto desde Portal CV&body=Hola ' . urlencode($cv['name']);
            ?>
            <a href="<?php echo $gmailLink; ?>" target="_blank" title="Enviar correo vía Gmail" style="text-decoration: none;">
                <img src="imagenes/Gmail-logo.webp" alt="Email" style="width: 36px; height: 26px; margin-right: 8px; vertical-align: middle;">
            </a>
        <?php endif; ?>
        
        <?php if (!empty($cv['phone']) && $cv['phone'] !== 'No disponible'): ?>
            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $cv['phone']), ENT_QUOTES, 'UTF-8'); ?>" title="Llamar">
                <img src="imagenes/telefono.webp" alt="Teléfono" style="width: 26px; height: 26px; margin-right: 8px; vertical-align: middle;">
            </a>
        <?php endif; ?>

        <br><br>

        <a href="<?php echo $originalUrl; ?>" target="_blank" class="btn-download">
            Descargar archivo original (<?php echo $originalFilename; ?>)
        </a>
    </div>

    <?php if ($previewUrl): ?>
        <div id="pdf-viewer" class="pdf-viewer-container" data-url="<?php echo $previewUrl; ?>"></div>
    <?php else: ?>
        <p>Vista previa no disponible.</p>
    <?php endif; ?>

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

document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById('pdf-viewer');
    if (!container) return;
    
    const url = container.getAttribute('data-url');
    if (!url) return;

    pdfjsLib.getDocument(url).promise.then(function(pdf) {
        const numPages = pdf.numPages;
        const pagesToShow = Math.min(numPages, 2);
        
        for (let pageNum = 1; pageNum <= pagesToShow; pageNum++) {
            pdf.getPage(pageNum).then(function(page) {
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                container.appendChild(canvas);

                const containerWidth = container.clientWidth;
                let viewport = page.getViewport({scale: 1});
                let scale = 1;

                if (pagesToShow === 2) {
                    const targetWidth = (containerWidth / 2) - 1;
                    scale = targetWidth / viewport.width;
                } else {
                    let targetWidth = containerWidth * 0.7; 
                    if (containerWidth < 800) targetWidth = containerWidth;
                    
                    scale = targetWidth / viewport.width;
                }
                
                if (!isFinite(scale) || scale <= 0) scale = 1;

                viewport = page.getViewport({scale: scale});

                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                page.render(renderContext);
            });
        }
    }).catch(function (err) {
        console.error('Error al cargar PDF:', err);
    });
});
</script>
</body>
</html>