<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conect.php';

require_login();

// Gestión de la búsqueda de perfiles
$search = $_GET['q'] ?? '';
$searchParam = "%$search%";

// Consulta adaptada para filtrar por nombre si hay búsqueda
if (!empty($search)) {
    $sql = "SELECT id, name FROM cvs WHERE name LIKE :search ORDER BY uploaded_at DESC";
    $sentencia = $conexion->prepare($sql);
    $sentencia->bindParam(':search', $searchParam);
} else {
    // Si no busca nada, mostramos todo como siempre
    $sql = "SELECT id, name FROM cvs ORDER BY uploaded_at DESC";
    $sentencia = $conexion->prepare($sql);
}

$sentencia->execute();
$cvs = $sentencia->fetchAll(PDO::FETCH_ASSOC);

// Verificamos si el usuario es administrador para mostrar opciones adicionales
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Personas - CVs</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
    <style>
        .delete-btn {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: auto;
            font-size: 0.9em;
        }
        .delete-btn:hover {
            background-color: #cc0000;
        }
    </style>
</head>
<body class="app-body">
<div class="main">
    <div class="nav-links">
        <a href="upload.php">Subir CV</a>
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" style="color: #e67e22; font-weight: bold;">Dashboard IT</a>
            <span style="color: #666; margin: 0 10px;">|</span>
            <span style="color: #2c3e50; font-weight: bold;">IT Admin</span>
        <?php endif; ?>
        <a href="logout.php">Cerrar sesión</a>
    </div>

    <h1>Portal de CV</h1>
    <p>Explora los perfiles de la gente de tu empresa de forma sencilla. Haz clic en un nombre para ver el CV completo.</p>
    
    <!-- Barra de búsqueda integrada y minimalista -->
    <form method="get" class="search-form">
        <input type="text" name="q" placeholder="Buscar perfil..." value="<?php echo htmlspecialchars($search); ?>">
        <?php if (!empty($search)): ?>
            <a href="list.php" class="clear-search" title="Limpiar búsqueda">✕</a>
        <?php endif; ?>
        <button type="submit" class="search-btn" title="Buscar">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </button>
    </form>

    <p><strong>Personas</strong></p>

    <?php if (!$cvs): ?>
        <?php if (!empty($search)): ?>
            <p>No se encontraron perfiles con ese nombre.</p>
        <?php else: ?>
            <p>Todavía no hay ningún CV registrado.</p>
        <?php endif; ?>
    <?php else: ?>
        <ul class="cv-list">
            <?php foreach ($cvs as $cv): ?>
                <li style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="view.php?id=<?php echo urlencode((string)$cv['id']); ?>" style="display: flex; align-items: center; flex-grow: 1;">
                        <img src="imagenes/cv-emoji.png" alt="CV" style="width: 48px; height: 48px; margin-right: 15px;"> 
                        <span style="font-size: 1.1em;"><?php echo htmlspecialchars($cv['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    
                    <?php if ($isAdmin): ?>
                        <form action="delete_cv.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres borrar este CV? Esta acción no se puede deshacer.');" style="margin: 0;">
                            <input type="hidden" name="id" value="<?php echo $cv['id']; ?>">
                            <button type="submit" class="delete-btn" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
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
</script>
</body>
</html>