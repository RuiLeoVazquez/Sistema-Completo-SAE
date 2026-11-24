<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo terminal');
}
if ($argc !== 5 || !in_array($argv[1], ['maestro', 'alumno'], true) || (int) $argv[4] < 1) {
    fwrite(STDERR, "Uso: php crear_usuarios_cli.php maestro|alumno correo clave id_persona\n");
    exit(1);
}
require __DIR__ . '/conexion.php';
require __DIR__ . '/cuentas.php';
if (!$bd_disponible) {
    fwrite(STDERR, "No se pudo conectar a MySQL.\n");
    exit(1);
}
[, $rol, $correo, $clave, $id] = $argv;
$id = (int) $id;
try {
    $correo = correo_cuenta($correo);
    validar_clave($clave);
    $conexion->begin_transaction();
    $sql = $rol === 'maestro'
        ? "SELECT mail_prof AS correo FROM profesores WHERE idprof = ? AND estatus_prof = 'ALTA' FOR UPDATE"
        : "SELECT mail_al AS correo FROM alumnos WHERE idalumn = ? AND estatus_al = 'ALTA' FOR UPDATE";
    $consulta = $conexion->prepare($sql);
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $perfil = $consulta->get_result()->fetch_assoc();
    if (!$perfil || strcasecmp(trim($perfil['correo']), $correo) !== 0) {
        throw new DomainException('El correo debe corresponder al perfil activo seleccionado.');
    }
    crear_cuenta($conexion, $rol, $correo, $clave, $id, 'ALTA');
    $conexion->commit();
    echo "Usuario creado.\n";
} catch (Throwable $excepcion) {
    $conexion->rollback();
    fwrite(STDERR, $excepcion instanceof DomainException ? $excepcion->getMessage() . "\n" : "No se pudo crear la cuenta.\n");
    exit(1);
}
