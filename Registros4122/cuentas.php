<?php

function crear_cuenta(mysqli $conexion, string $rol, string $correo, string $clave, int $id, string $estado, bool $vincularCuentaAnterior = false): void
{
    $alumno = $rol === 'alumno' ? $id : null;
    $profesor = $rol === 'maestro' ? $id : null;
    if ($alumno === null && $profesor === null) {
        throw new InvalidArgumentException('Tipo de usuario inválido.');
    }

    // El registro de la persona debe estar bloqueado por la transacción del llamador.
    $existe = $conexion->prepare('SELECT id_user FROM usuarios WHERE idalumn = ? OR idprof = ? LIMIT 1');
    $existe->bind_param('ii', $alumno, $profesor);
    $existe->execute();
    if ($existe->get_result()->fetch_assoc()) {
        throw new DomainException('Esta persona ya tiene una cuenta vinculada.');
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);
    if ($vincularCuentaAnterior) {
        $vincular = $conexion->prepare('UPDATE usuarios SET pwuser = ?, idalumn = ?, idprof = ?, status_user = ?
            WHERE nombreuser = ? AND puesto = ? AND idalumn IS NULL AND idprof IS NULL');
        $vincular->bind_param('siisss', $hash, $alumno, $profesor, $estado, $correo, $rol);
        $vincular->execute();
        if ($vincular->affected_rows) {
            return;
        }
    }

    $consulta = $conexion->prepare('INSERT INTO usuarios
        (nombreuser, pwuser, puesto, idalumn, idprof, status_user) VALUES (?, ?, ?, ?, ?, ?)');
    $consulta->bind_param('sssiis', $correo, $hash, $rol, $alumno, $profesor, $estado);
    $consulta->execute();
}

function correo_cuenta(string $correo): string
{
    $correo = strtolower(trim($correo));
    if (strlen($correo) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Escribe un correo electrónico válido de hasta 100 caracteres.');
    }
    return $correo;
}

function validar_clave(string $clave): void
{
    if (strlen($clave) < 10 || strlen($clave) > 72 || strpos($clave, "\0") !== false) {
        throw new DomainException('Usa una contraseña de entre 10 y 72 bytes.');
    }
}
