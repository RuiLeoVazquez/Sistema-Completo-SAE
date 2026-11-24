<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo terminal');
}
if ($argc !== 3) {
    fwrite(STDERR, "Uso: php crear_admin_cli.php correo@ejemplo.com clave-segura\n");
    exit(1);
}
require __DIR__ . '/conexion.php';
require __DIR__ . '/cuentas.php';
if (!$bd_disponible) {
    fwrite(STDERR, "No se pudo conectar a MySQL.\n");
    exit(1);
}
try {
    $correo = correo_cuenta($argv[1]);
    validar_clave($argv[2]);
    $hash = password_hash($argv[2], PASSWORD_DEFAULT);
    $consulta = $conexion->prepare("INSERT INTO usuarios (nombreuser,pwuser,puesto,status_user) VALUES (?,?,'administrador','ALTA')");
    $consulta->bind_param('ss', $correo, $hash);
    $consulta->execute();
    echo "Administrador creado correctamente.\n";
} catch (Throwable $excepcion) {
    fwrite(STDERR, $excepcion instanceof DomainException ? $excepcion->getMessage() . "\n" : "No se pudo crear el administrador. Revisa si el correo ya está registrado.\n");
    exit(1);
}
