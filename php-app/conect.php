<?php
$servidor   = "db";
$usuario    = "cvuser";
$contrasena = "cvpass";
$baseDatos  = "cvportal";
$puerto     = 3306;

$dsn = "mysql:host=$servidor;port=$puerto;dbname=$baseDatos;charset=utf8mb4";

try {
    $conexion = new PDO($dsn, $usuario, $contrasena, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
