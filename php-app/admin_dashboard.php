<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conect.php';

require_login();

// Acceso restringido únicamente a administradores
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /list.php');
    exit;
}

$uploadDir = __DIR__ . '/uploads';
$totalSize = 0;
$fileCount = 0;

// Cálculo del espacio ocupado en disco por los archivos
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $uploadDir . '/' . $file;
            if (is_file($filePath)) {
                $totalSize += filesize($filePath);
                $fileCount++;
            }
        }
    }
}

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
} 

// Conteo total de registros en la base de datos
$sql = "SELECT COUNT(*) as total FROM cvs";
$stmt = $conexion->query($sql);
$dbCount = $stmt->fetch()['total'];

// Verificación del estado de conexión con LDAP
$ldapStatus = "Offline";
$ldapColor = "red";
$ldap_host = getenv('LDAP_HOST') ?: 'openldap';
$conn = @fsockopen($ldap_host, 389, $errno, $errstr, 1);
if ($conn) {
    $ldapStatus = "Online";
    $ldapColor = "green";
    fclose($conn);
}

// Recuperación de los últimos registros de auditoría
$sqlLogs = "SELECT * FROM auditoria ORDER BY created_at DESC LIMIT 20";
$stmtLogs = $conexion->query($sqlLogs);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración IT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 50px;
        }
        
        .card {
            background: transparent;
            padding: 20px;
            border: 2px solid #999;
            border-radius: 6px;
            text-align: left;
            transition: all 0.2s ease;
        }
        
        .card:hover {
            border-color: #aaa;
        }

        .card h3 { 
            margin-top: 0; 
            color: #666; 
            font-size: 0.85em; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .card .value { 
            font-size: 2em; 
            font-weight: 600; 
            color: #2c3e50; 
            margin-bottom: 5px; 
        }
        
        .card .sub { 
            font-size: 0.85em; 
            color: #888; 
        }
        
        .status-dot {
            height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;
        }
        
        .logs-card {
            padding: 0 !important;
            overflow: hidden;
            border: 2px solid #999;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            background-color: #fff;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.1em;
            color: #333;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-header .subtitle {
            display: block;
            font-size: 0.85em;
            color: #888;
            margin-top: 5px;
            font-weight: 400;
            text-transform: none;
        }

        .section-header {
            margin-top: 40px;
            margin-bottom: 15px;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.1em;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-header .subtitle {
            display: block;
            margin-top: 5px;
            color: #888;
            font-size: 0.85em;
            font-weight: 400;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }

        .logs-table thead th {
            background-color: #f9fafb;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.05em;
            padding: 15px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .logs-table td {
            padding: 15px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            color: #374151;
        }

        .logs-table tr:last-child td {
            border-bottom: none;
        }

        .col-date { white-space: nowrap; color: #6b7280; }
        .col-user { font-weight: 600; color: #111827; }
        .col-ip { color: #9ca3af; font-size: 0.9em; text-align: right; }
        
        .highlight-name {
            font-weight: 500;
            color: #1f2937;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .action-icon { width: 14px; height: 14px; }
        
        .action-login { background-color: #e3f2fd; color: #1565c0; }
        .action-upload { background-color: #e8f5e9; color: #2e7d32; }
        .action-delete { background-color: #ffebee; color: #c62828; }

        body.dark-mode .card h3 { color: #cbd5e1; }
        body.dark-mode .card .value { color: #bfdbfe; }
        body.dark-mode .card .sub { color: #94a3b8; }

        body.dark-mode .logs-card {
            background-color: transparent;
            border-color: #4b5563;
        }
        
        body.dark-mode .card-header {
            background-color: transparent;
            border-bottom-color: #4b5563;
        }
        
        body.dark-mode .card-header h3 { color: #e5e7eb; }

        body.dark-mode .section-header h3 { color: #e5e7eb; }
        body.dark-mode .section-header .subtitle { color: #9ca3af; }

        body.dark-mode .logs-table thead th {
            background-color: #1f2937;
            color: #9ca3af;
            border-bottom-color: #374151;
        }

        body.dark-mode .logs-table td {
            border-bottom-color: #374151;
            color: #d1d5db;
        }

        body.dark-mode .col-user { color: #f3f4f6; }
        body.dark-mode .col-ip { color: #6b7280; }
        body.dark-mode .highlight-name { color: #e5e7eb; }

        @media (max-width: 768px) {
            .logs-table thead { display: none; }
            .logs-table, .logs-table tbody, .logs-table tr, .logs-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .logs-table td {
                padding: 10px 20px;
                border: none;
            }
            .logs-table tr {
                border-bottom: 1px solid #eee;
                padding-bottom: 15px;
                padding-top: 15px;
            }
        }
    </style>
</head>
<body class="app-body">
<div class="main">
    <div class="nav-links">
        <a href="list.php">Volver</a>
        <span style="color: #666; margin: 0 10px;">|</span>
        <span style="color: #2c3e50; font-weight: bold;">IT Admin</span>
        <a href="logout.php">Cerrar sesión</a>
    </div>

    <h1>Panel de Administración IT</h1>
    <p>Monitorización del sistema y registros de auditoría.</p>
    
    <p><strong>Estado del Sistema</strong></p>

    <div class="dashboard-grid">
        <div class="card">
            <h3>Almacenamiento</h3>
            <div class="value"><?php echo formatBytes($totalSize); ?></div>
            <div class="sub"><?php echo $fileCount; ?> archivos PDF</div>
        </div>

        <div class="card">
            <h3>Base de Datos</h3>
            <div class="value"><?php echo $dbCount; ?></div>
            <div class="sub">Registros totales</div>
        </div>

        <div class="card">
            <h3>Estado LDAP</h3>
            <div class="value" style="display: flex; align-items: center;">
                <span class="status-dot" style="background-color: <?php echo $ldapColor; ?>;"></span>
                <?php echo $ldapStatus; ?>
            </div>
            <div class="sub">Host: <?php echo $ldap_host; ?></div>
        </div>
        
        <div class="card">
            <h3>Servidor</h3>
            <div class="value" style="font-size: 1.5em;">PHP <?php echo phpversion(); ?></div>
            <div class="sub"><?php echo $_SERVER['SERVER_ADDR']; ?></div>
        </div>
    </div>

    <div class="section-header">
        <h3>Auditoría de Acciones</h3>
        <span class="subtitle">Últimos 20 registros</span>
    </div>

    <div class="card logs-card">
        <div class="table-responsive">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): 
                    $actionClass = '';
                    $icon = '';
                    
                    if ($log['action'] === 'DELETE') {
                        $actionClass = 'action-delete';
                        $icon = '<svg class="action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
                    } elseif ($log['action'] === 'UPLOAD') {
                        $actionClass = 'action-upload';
                        $icon = '<svg class="action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>';
                    } else {
                        $actionClass = 'action-login';
                        $icon = '<svg class="action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>';
                    }

                    $details = htmlspecialchars($log['details']);
                    $details = preg_replace('/(de:\s+)(.+)/u', '$1<span class="highlight-name">$2</span>', $details);
                ?>
                    <tr>
                        <td class="col-date"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                        <td class="col-user"><?php echo htmlspecialchars($log['username']); ?></td>
                        <td class="col-action"><span class="badge <?php echo $actionClass; ?>"><?php echo $icon . htmlspecialchars($log['action']); ?></span></td>
                        <td class="col-details"><?php echo $details; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($logs) === 0): ?>
            <p style="color: #888; padding: 20px; text-align: center;">No hay registros de actividad aún.</p>
        <?php endif; ?>
    </div>
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