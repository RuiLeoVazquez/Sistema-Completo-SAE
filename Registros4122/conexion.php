<?php
/* Conexión e instalación inicial del esquema escolar. */
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }
$conexion = null;
$bd_disponible = false;
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dbname = getenv('DB_NAME') ?: 'sae_escolar';
    if (!class_exists('mysqli')) { throw new RuntimeException('Extensión mysqli no disponible'); }
    $puerto = (int) (getenv('DB_PORT') ?: 3306);
    $conexion = new mysqli($host, $user, $pass, '', $puerto);
    $conexion->set_charset('utf8mb4');
    // Instalación inicial no destructiva. Requiere permiso CREATE DATABASE/TABLE.
    $nombreSeguro = str_replace('`', '``', $dbname);
    $conexion->query("CREATE DATABASE IF NOT EXISTS `{$nombreSeguro}` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci");
    $conexion->select_db($dbname);
    $sql = file_get_contents(__DIR__ . '/base_datos_sae.sql');
    if ($sql === false) throw new RuntimeException('No se encontró el esquema SQL');
    $sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
    // Ejecutar solo CREATE TABLE; no borrar ni modificar datos existentes.
    foreach (explode(';', $sql) as $sentencia) {
        $sentencia = trim($sentencia);
        if (preg_match('/^CREATE\s+TABLE\b/i', $sentencia)) {
            $sentencia = preg_replace('/^CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $sentencia);
            $conexion->query($sentencia);
        }
    }
    // Actualizar instalaciones anteriores sin borrar registros.
    $tipoRol = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'puesto'")->fetch_assoc()['Type'];
    if (strpos($tipoRol, "'alumno'") === false || strpos($tipoRol, "'maestro'") === false) {
        $conexion->query("ALTER TABLE usuarios MODIFY puesto ENUM('administrador','maestro','alumno') NOT NULL");
    }
    $tipoUsuario = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'nombreuser'")->fetch_assoc()['Type'];
    if ($tipoUsuario === 'varchar(80)') {
        $conexion->query('ALTER TABLE usuarios MODIFY nombreuser VARCHAR(100) NOT NULL');
    }
    $tipoEstado = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'status_user'")->fetch_assoc()['Type'];
    if (strpos($tipoEstado, "'PENDIENTE'") === false && strpos($tipoEstado, "'pendiente'") === false) {
        $conexion->query("ALTER TABLE usuarios MODIFY status_user ENUM('ALTA','BAJA','PENDIENTE') NOT NULL DEFAULT 'ALTA'");
    }
    $columnas = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'idprof'");
    if ($columnas->num_rows === 0) {
        $conexion->query("ALTER TABLE usuarios ADD COLUMN idprof INT NULL");
        $conexion->query("ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_prof FOREIGN KEY (idprof) REFERENCES profesores(idprof) ON DELETE SET NULL");
    }
    $bd_disponible = true;
} catch (Throwable $e) {
    error_log('Sistema Escolar: conexión/instalación SQL no disponible: ' . $e->getMessage());
    if ($conexion instanceof mysqli) { try { $conexion->close(); } catch (Throwable $ignorado) {} }
    $conexion = null;
    $bd_disponible = false;
}
