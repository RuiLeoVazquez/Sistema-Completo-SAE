<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode' => 1,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function cuenta_activa(mysqli $conexion, string $campo, $valor): ?array
{
    $columna = $campo === 'id' ? 'u.id_user' : 'u.nombreuser';
    $consulta = $conexion->prepare("SELECT u.* FROM usuarios u
        LEFT JOIN alumnos a ON a.idalumn = u.idalumn
        LEFT JOIN profesores p ON p.idprof = u.idprof
        WHERE $columna = ? AND u.status_user = 'ALTA'
        AND (u.puesto = 'administrador'
            OR (u.puesto = 'alumno' AND a.estatus_al = 'ALTA')
            OR (u.puesto = 'maestro' AND p.estatus_prof = 'ALTA'))");
    $consulta->bind_param($campo === 'id' ? 'i' : 's', $valor);
    $consulta->execute();
    return $consulta->get_result()->fetch_assoc();
}

function exigir_sesion(): void
{
    global $conexion;
    $cuenta = isset($_SESSION['uid']) && $conexion
        ? cuenta_activa($conexion, 'id', $_SESSION['uid']) : null;
    if (!$cuenta || !hash_equals($_SESSION['firma_clave'] ?? '', hash('sha256', $cuenta['pwuser']))) {
        $_SESSION = [];
        header('Location: ' . RUTA_INICIO . 'login.php');
        exit;
    }
    actualizar_sesion($cuenta);
}

function actualizar_sesion(array $cuenta): void
{
    $_SESSION['uid'] = $cuenta['id_user'];
    $_SESSION['firma_clave'] = hash('sha256', $cuenta['pwuser']);
    $_SESSION['rol'] = $cuenta['puesto'];
    $_SESSION['usuario'] = $cuenta['nombreuser'];
    $_SESSION['idalumn'] = $cuenta['idalumn'];
    $_SESSION['idprof'] = $cuenta['idprof'];
}

function es_admin(): bool { return ($_SESSION['rol'] ?? '') === 'administrador'; }
function es_personal(): bool { return in_array($_SESSION['rol'] ?? '', ['administrador', 'maestro'], true); }
function alumno_actual(): int { return (int) ($_SESSION['idalumn'] ?? 0); }
function profesor_actual(): int { return (int) ($_SESSION['idprof'] ?? 0); }

function prohibir_acceso(): void
{
    http_response_code(403);
    exit('Tu cuenta no tiene acceso a esta sección.');
}

function exigir_admin(): void
{
    exigir_sesion();
    if (!es_admin()) {
        prohibir_acceso();
    }
    verificar_csrf();
}

function exigir_maestro(): void
{
    exigir_sesion();
    if (!es_personal()) {
        prohibir_acceso();
    }
    verificar_csrf();
}

function token_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . token_csrf() . '">';
}

function verificar_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals(token_csrf(), $token)) {
        http_response_code(400);
        exit('Vuelve a cargar el formulario e inténtalo de nuevo.');
    }
}

$rutaProyecto = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$carpetas = ['admin', 'consultas', 'crud', 'entradas', 'procesos'];
if (in_array(basename($rutaProyecto), $carpetas, true)
    && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== false
    && dirname(realpath($_SERVER['SCRIPT_FILENAME']), 2) === __DIR__) {
    $rutaProyecto = dirname($rutaProyecto);
}
define('RUTA_INICIO', rtrim($rutaProyecto, '/') . '/');
