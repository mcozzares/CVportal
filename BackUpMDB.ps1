# === CONFIGURACIÓN ===
$containerName = "cv-db"       # Nombre del contenedor de BD
$appContainerName = "php-app"  # Nombre del contenedor de la App
$dbUser = "root"
$dbPassword = "superseguro"
$dbName = "cvportal"

# Configuración de consola
$ErrorActionPreference = "Continue"

# --- DETERMINAR RUTA DEL SCRIPT ---
if ($PSScriptRoot) { $scriptPath = $PSScriptRoot } 
else { $scriptPath = Split-Path -Parent -Path $MyInvocation.MyCommand.Definition }
if (-not $scriptPath) { $scriptPath = Get-Location }

Set-Location -Path $scriptPath

Clear-Host
Write-Host "=== DIAGNOSTICO Y BACKUP CV PORTAL ===" -ForegroundColor Cyan
Write-Host "Ruta: $scriptPath" -ForegroundColor Gray
Write-Host "----------------------------------------"

# 1. VERIFICAR DOCKER
Write-Host "1. Verificando estado de Docker..." -NoNewline
try {
    $dockerCheck = docker ps -q 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Error al ejecutar docker ps" }
    Write-Host " [OK]" -ForegroundColor Green
} catch {
    Write-Host " [ERROR]" -ForegroundColor Red
    Write-Error "Docker no está respondiendo. Asegúrate de que Docker Desktop esté abierto."
    Read-Host "Presiona ENTER para salir"
    exit
}

# 2. VERIFICAR CONTENEDORES
Write-Host "2. Buscando contenedores..."
$dbRunning = docker inspect -f '{{.State.Running}}' $containerName 2>$null
$appRunning = docker inspect -f '{{.State.Running}}' $appContainerName 2>$null

if ($dbRunning -ne 'true') { Write-Warning "   [!] Contenedor BD ($containerName) NO está corriendo." }
else { Write-Host "   [OK] Contenedor BD online." -ForegroundColor Green }

if ($appRunning -ne 'true') { Write-Warning "   [!] Contenedor App ($appContainerName) NO está corriendo." }
else { Write-Host "   [OK] Contenedor App online." -ForegroundColor Green }

if ($dbRunning -ne 'true' -or $appRunning -ne 'true') {
    Write-Error "Faltan contenedores necesarios. Ejecuta 'docker-compose up -d' primero."
    Read-Host "Presiona ENTER para salir"
    exit
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

# --- CREAR CARPETA PRINCIPAL 'BackUp' ---
$mainBackupDir = Join-Path -Path $scriptPath -ChildPath "BackUp"
if (-Not (Test-Path $mainBackupDir)) {
    New-Item -Path $mainBackupDir -ItemType Directory | Out-Null
}

# 3. BACKUP SQL
Write-Host "`n3. Backup de Base de Datos..." -ForegroundColor Yellow
$backupDir = Join-Path -Path $mainBackupDir -ChildPath "DBbackups"
if (-Not (Test-Path $backupDir)) { New-Item -Path $backupDir -ItemType Directory | Out-Null }

$sqlFile = Join-Path -Path $backupDir -ChildPath "backup_$timestamp.sql"

# Usamos cmd /c para evitar problemas de encoding de PowerShell con >
$dumpCmd = "docker exec $containerName mariadb-dump -u$dbUser -p$dbPassword --databases $dbName --add-drop-database"
cmd /c "$dumpCmd > ""$sqlFile"""

if (Test-Path $sqlFile) {
    $fileInfo = Get-Item $sqlFile
    if ($fileInfo.Length -gt 0) {
        Write-Host "   [OK] SQL guardado ($($fileInfo.Length) bytes)." -ForegroundColor Green
    } else {
        Write-Error "   [FALLO] El archivo SQL está vacío. Verifica usuario/pass de BD."
    }
} else {
    Write-Error "No se generó el archivo SQL."
}

# 4. BACKUP ARCHIVOS
Write-Host "`n4. Backup de CVs (Archivos)..." -ForegroundColor Yellow
$cvBaseDir = Join-Path -Path $mainBackupDir -ChildPath "CVbackUp"
if (-Not (Test-Path $cvBaseDir)) { New-Item -Path $cvBaseDir -ItemType Directory | Out-Null }

    Write-Host "   Consultando base de datos..." -ForegroundColor Gray
    $query = "SELECT name, stored_path, original_filename FROM cvs"
    
    # Construimos el comando como string para asegurar que las variables se expanden correctamente
    # antes de enviarlo a docker. Usamos cmd /c para ejecutarlo.
    $listCmd = "docker exec -i $containerName mariadb -u$dbUser -p$dbPassword $dbName -N -B -e ""$query"""
    
    # Ejecutar y capturar salida (array de líneas)
    $output = cmd /c $listCmd 2>&1

if (-not $output) {
    Write-Warning "   [!] La consulta no devolvió resultados (¿BD vacía?)."
} else {
    $count = 0
    foreach ($line in $output) {
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        # Filtrar posibles mensajes de error que mariadb mande a stdout
        if ($line -match "^ERROR") { continue }

        $parts = $line -split "`t"
        if ($parts.Count -ge 3) {
            $name = $parts[0]
            $stored = $parts[1]
            $original = $parts[2]

            # Reemplazo seguro de caracteres inválidos
            $invalidChars = '[\\/:*?"<>|]'
            $safeName = $name -replace $invalidChars, ''
            $safeName = $safeName.Trim()
            
            if (-not $safeName) { $safeName = "SinNombre" }

            $targetDir = Join-Path -Path $cvBaseDir -ChildPath $safeName
            if (-Not (Test-Path $targetDir)) { New-Item -Path $targetDir -ItemType Directory | Out-Null }

            # Ruta dentro del contenedor
            $srcPath = "/var/www/html/uploads/$stored"
            
            # Copia
            $dockerCpOutput = docker cp "${appContainerName}:${srcPath}" "$targetDir" 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                # Renombrar
                $tempFile = Join-Path -Path $targetDir -ChildPath $stored
                $finalFile = Join-Path -Path $targetDir -ChildPath $original
                
                if (Test-Path $tempFile) {
                    Move-Item -Path $tempFile -Destination $finalFile -Force
                    Write-Host "   + $safeName ($original)" -ForegroundColor Cyan
                    $count++
                } else {
                    Write-Warning "   [!] Docker cp dijo OK pero no veo el archivo: $stored"
                }
            } else {
                Write-Error "   [X] Falló copia de ${name}: $dockerCpOutput"
            }
        }
    }
    Write-Host "   [RESUMEN] $count archivos procesados." -ForegroundColor Green
}

Write-Host "================================"
Write-Host "   PROCESO TERMINADO"
Write-Host "================================"
Read-Host "Presiona ENTER para cerrar esta ventana..."
