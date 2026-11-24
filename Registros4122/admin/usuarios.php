<?php

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../seguridad.php';
require_once __DIR__ . '/../cuentas.php';
require_once __DIR__ . '/../plantilla.php';
exigir_admin();

$aviso = $_SESSION['aviso_cuentas'] ?? null;
unset($_SESSION['aviso_cuentas']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $accion = $_POST['accion'] ?? '';
        $id = (int) ($_POST['id_user'] ?? 0);
        if ($accion === 'crear') {
            $rol = $_POST['rol'] ?? '';
            if (!in_array($rol, ['alumno', 'maestro'], true)) {
                throw new DomainException('Selecciona un tipo de cuenta válido.');
            }
            $persona = (int) ($_POST['persona'] ?? 0);
            $sql = $rol === 'alumno'
                ? "SELECT mail_al AS correo FROM alumnos WHERE idalumn = ? AND estatus_al = 'ALTA' FOR UPDATE"
                : "SELECT mail_prof AS correo FROM profesores WHERE idprof = ? AND estatus_prof = 'ALTA' FOR UPDATE";
            $conexion->begin_transaction();
            try {
                $consulta = $conexion->prepare($sql);
                $consulta->bind_param('i', $persona);
                $consulta->execute();
                $perfil = $consulta->get_result()->fetch_assoc();
                if (!$perfil) {
                    throw new DomainException('Selecciona un perfil activo.');
                }
                $correo = correo_cuenta($perfil['correo']);
                $clave = bin2hex(random_bytes(10));
                crear_cuenta($conexion, $rol, $correo, $clave, $persona, 'ALTA', true);
                $conexion->commit();
            } catch (Throwable $excepcion) {
                $conexion->rollback();
                throw $excepcion;
            }
            $aviso = ['texto' => 'Cuenta activada. Entrega la contraseña inicial de forma privada.', 'clave' => $clave];
        } elseif ($accion === 'crear_admin') {
            $correoAdmin = correo_cuenta($_POST['correo_admin'] ?? '');
            $claveAdmin = bin2hex(random_bytes(10));
            $hashAdmin = password_hash($claveAdmin, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombreuser, pwuser, puesto, status_user) VALUES (?, ?, 'administrador', 'ALTA')");
            $stmt->bind_param('ss', $correoAdmin, $hashAdmin);
            $stmt->execute();
            $aviso = ['texto' => 'Cuenta de Administrador creada correctamente.', 'clave' => $claveAdmin];
        } elseif ($accion === 'cambiar_rol') {
            $nuevoRol = $_POST['nuevo_rol'] ?? '';
            if ($id <= 0 || $id === (int) $_SESSION['uid'] || !in_array($nuevoRol, ['administrador', 'maestro', 'alumno'], true)) {
                throw new DomainException('Cambio de rol inválido.');
            }
            $consulta = $conexion->prepare('UPDATE usuarios SET puesto = ? WHERE id_user = ?');
            $consulta->bind_param('si', $nuevoRol, $id);
            $consulta->execute();
            $aviso = ['texto' => 'Rol de usuario actualizado correctamente.'];
        } elseif ($accion === 'estado') {
            $estado = $_POST['estado'] ?? '';
            if ($id <= 0 || $id === (int) $_SESSION['uid'] || !in_array($estado, ['ALTA', 'BAJA'], true)) {
                throw new DomainException('El cambio de estado es inválido.');
            }
            $consulta = $conexion->prepare('UPDATE usuarios SET status_user = ? WHERE id_user = ?');
            $consulta->bind_param('si', $estado, $id);
            $consulta->execute();
            $aviso = ['texto' => 'Estado de la cuenta actualizado.'];
        } elseif ($accion === 'reset' && $id > 0) {
            $clave = bin2hex(random_bytes(10));
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $consulta = $conexion->prepare('UPDATE usuarios SET pwuser = ? WHERE id_user = ?');
            $consulta->bind_param('si', $hash, $id);
            $consulta->execute();
            if (!$consulta->affected_rows) {
                throw new DomainException('La cuenta ya no está disponible.');
            }
            $aviso = ['texto' => 'Contraseña actualizada. Compártela de forma privada.', 'clave' => $clave];
        } elseif ($accion === 'eliminar' && $id > 0 && $id !== (int) $_SESSION['uid']) {
            $consulta = $conexion->prepare('DELETE FROM usuarios WHERE id_user = ?');
            $consulta->bind_param('i', $id);
            $consulta->execute();
            $aviso = ['texto' => 'Cuenta de usuario eliminada del sistema.'];
        } else {
            throw new DomainException('Selecciona una acción válida.');
        }
        $_SESSION['aviso_cuentas'] = $aviso;
        header('Location: usuarios.php', true, 303);
        exit;
    } catch (DomainException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (Throwable $excepcion) {
        error_log($excepcion->getMessage());
        $error = (int) $excepcion->getCode() === 1062
            ? 'El correo ya está asociado a otra cuenta.'
            : 'No se pudo completar el cambio.';
    }
}

$perfiles = $conexion->query("
    SELECT a.idalumn AS id, 'alumno' AS tipo, CONCAT_WS(' ', a.nombre_al, a.apaterno_al, a.amaterno_al) AS nombre, a.mail_al AS correo
    FROM alumnos a LEFT JOIN usuarios u ON u.idalumn = a.idalumn
    WHERE a.estatus_al = 'ALTA' AND u.id_user IS NULL
    UNION ALL
    SELECT p.idprof, 'maestro', CONCAT_WS(' ', p.nombre_prof, p.apaterno_prof, p.amaterno_prof), p.mail_prof
    FROM profesores p LEFT JOIN usuarios u ON u.idprof = p.idprof
    WHERE p.estatus_prof = 'ALTA' AND u.id_user IS NULL
    ORDER BY nombre");
$cuentas = $conexion->query("SELECT u.id_user, u.nombreuser, u.puesto, u.status_user,
    CASE WHEN a.idalumn IS NOT NULL THEN CONCAT_WS(' ', a.nombre_al, a.apaterno_al, a.amaterno_al)
         WHEN p.idprof IS NOT NULL THEN CONCAT_WS(' ', p.nombre_prof, p.apaterno_prof, p.amaterno_prof)
         ELSE 'Administrador del Sistema' END AS nombre,
    COALESCE(a.matricula_al, p.nocontrol_prof, 'ADMIN') AS identificador
    FROM usuarios u LEFT JOIN alumnos a ON a.idalumn = u.idalumn LEFT JOIN profesores p ON p.idprof = u.idprof
    ORDER BY (u.status_user = 'PENDIENTE') DESC, u.id_user DESC");
inicio_pagina('Administración de Cuentas y Roles', true);
?>
<a href="../index.php" class="btn btn-outline-secondary btn-sm mb-3">← Volver al panel principal</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold mb-1">Administración Integral de Usuarios</h1>
        <p class="text-muted mb-0">Gestiona altas, permisos por rol (Administrador, Maestro, Alumno), activaciones y contraseñas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
        <a class="btn btn-primary" href="../entradas/registrar_alumno.php">+ Alta de Alumno</a>
        <a class="btn btn-success" href="../entradas/registrar_profesor.php">+ Alta de Profesor</a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($aviso): ?>
    <div class="alert alert-success" role="status">
        <?= e($aviso['texto']) ?>
        <?php if (isset($aviso['clave'])): ?><div class="mt-1">Contraseña generada: <code class="fs-6"><?= e($aviso['clave']) ?></code></div><?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <?php if ($perfiles->num_rows): ?>
    <div class="col-lg-7">
        <section class="card h-100"><div class="card-body p-4">
            <h2 class="h5 fw-bold">Habilitar acceso a perfil registrado</h2>
            <p class="text-muted small">Vincula un alumno o profesor dado de alta con una cuenta de acceso.</p>
            <form method="post" class="d-flex flex-wrap align-items-end gap-2">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="rol" id="rol">
                <div class="flex-grow-1">
                    <label class="form-label" for="persona">Perfil y correo registrado</label>
                    <select name="persona" id="persona" class="form-select" required>
                        <?php while ($perfil = $perfiles->fetch_assoc()): ?>
                            <option value="<?= e($perfil['id']) ?>" data-rol="<?= e($perfil['tipo']) ?>">
                                <?= e(($perfil['tipo'] === 'alumno' ? 'Alumno' : 'Profesor') . ' · ' . $perfil['nombre'] . ' · ' . $perfil['correo']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button class="btn btn-outline-primary">Crear acceso</button>
            </form>
            <script>
                const persona = document.getElementById('persona');
                const rol = document.getElementById('rol');
                function actualizarRol() { rol.value = persona.selectedOptions[0].dataset.rol; }
                persona.addEventListener('change', actualizarRol);
                actualizarRol();
            </script>
        </div></section>
    </div>
    <?php endif; ?>

    <div class="<?= $perfiles->num_rows ? 'col-lg-5' : 'col-12' ?>">
        <section class="card h-100"><div class="card-body p-4">
            <h2 class="h5 fw-bold">Registrar nuevo Administrador</h2>
            <p class="text-muted small">Otorga control total del sistema a otro administrador.</p>
            <form method="post" class="d-flex flex-wrap align-items-end gap-2">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="crear_admin">
                <div class="flex-grow-1">
                    <label class="form-label" for="correo_admin">Correo del administrador</label>
                    <input type="email" name="correo_admin" id="correo_admin" class="form-control" placeholder="admin@institucion.edu.mx" required>
                </div>
                <button class="btn btn-warning fw-semibold">Crear Admin</button>
            </form>
        </div></section>
    </div>
</div>

<section class="card"><div class="card-body p-4">
    <h2 class="h5 fw-bold">Directorio de Cuentas y Control de Permisos</h2>
    <p class="text-muted small">Revisa la matrícula y el correo antes de activar una solicitud o modificar privilegios.</p>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead class="table-dark"><tr><th>Nombre</th><th>Matrícula / Control</th><th>Correo / Usuario</th><th>Rol asignado</th><th>Estatus</th><th>Acciones de control</th></tr></thead>
        <tbody>
        <?php while ($cuenta = $cuentas->fetch_assoc()): $activa = $cuenta['status_user'] === 'ALTA'; ?>
            <tr>
                <td class="fw-semibold"><?= e($cuenta['nombre'] ?? '—') ?></td>
                <td><?= e($cuenta['identificador'] ?? '—') ?></td>
                <td><?= e($cuenta['nombreuser']) ?></td>
                <td>
                    <?php if ((int) $cuenta['id_user'] !== (int) $_SESSION['uid']): ?>
                        <form method="post" class="d-flex gap-1">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="cambiar_rol">
                            <input type="hidden" name="id_user" value="<?= e($cuenta['id_user']) ?>">
                            <select name="nuevo_rol" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="administrador" <?= $cuenta['puesto'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                <option value="maestro" <?= $cuenta['puesto'] === 'maestro' ? 'selected' : '' ?>>Maestro</option>
                                <option value="alumno" <?= $cuenta['puesto'] === 'alumno' ? 'selected' : '' ?>>Alumno</option>
                            </select>
                        </form>
                    <?php else: ?>
                        <span class="badge text-bg-warning text-dark">Administrador (Tú)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $badgeStatus = ['ALTA' => 'success', 'BAJA' => 'danger', 'PENDIENTE' => 'warning text-dark'][$cuenta['status_user']] ?? 'secondary';
                    $labelStatus = ['ALTA' => 'Activo', 'BAJA' => 'Bloqueado', 'PENDIENTE' => 'Pendiente'][$cuenta['status_user']] ?? $cuenta['status_user'];
                    ?>
                    <span class="badge bg-<?= $badgeStatus ?>"><?= $labelStatus ?></span>
                </td>
                <td><div class="d-flex flex-wrap gap-1">
                    <?php if ((int) $cuenta['id_user'] !== (int) $_SESSION['uid']): ?>
                        <form method="post">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id_user" value="<?= e($cuenta['id_user']) ?>">
                            <input type="hidden" name="estado" value="<?= $activa ? 'BAJA' : 'ALTA' ?>">
                            <button class="btn btn-sm btn-outline-<?= $activa ? 'warning' : 'success' ?>"><?= $activa ? 'Bloquear' : 'Activar' ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('¿Restablecer la contraseña de este usuario?')">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="reset">
                        <input type="hidden" name="id_user" value="<?= e($cuenta['id_user']) ?>">
                        <button class="btn btn-sm btn-outline-secondary">Nueva clave</button>
                    </form>
                    <?php if ((int) $cuenta['id_user'] !== (int) $_SESSION['uid']): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar permanentemente esta cuenta de usuario?')">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_user" value="<?= e($cuenta['id_user']) ?>">
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div></section>
<?php fin_pagina(); ?>
