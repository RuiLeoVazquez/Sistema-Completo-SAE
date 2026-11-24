<?php

if (!function_exists('alumno_actual')) {
    http_response_code(404);
    exit;
}

$id = alumno_actual();
$mensaje = '';
$clase = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'actualizar_perfil') {
        try {
            $nombre = trim($_POST['nombre_al'] ?? '');
            $apaterno = trim($_POST['apaterno_al'] ?? '');
            $amaterno = trim($_POST['amaterno_al'] ?? '');
            $tel = trim($_POST['tel_al'] ?? '');
            $dom = trim($_POST['dom_al'] ?? '');

            if ($nombre === '' || $apaterno === '') {
                throw new DomainException('Tu nombre y apellido paterno son obligatorios.');
            }

            $upd = $conexion->prepare('UPDATE alumnos SET nombre_al = ?, apaterno_al = ?, amaterno_al = ?, tel_al = ?, dom_al = ? WHERE idalumn = ?');
            $upd->bind_param('sssssi', $nombre, $apaterno, $amaterno, $tel, $dom, $id);
            $upd->execute();

            $mensaje = 'Tus datos personales se han actualizado correctamente.';
        } catch (Throwable $e) {
            $mensaje = $e instanceof DomainException ? $e->getMessage() : 'No se pudieron actualizar tus datos.';
            $clase = 'danger';
        }
    }
}

$consulta = $conexion->prepare('SELECT * FROM alumnos WHERE idalumn = ?');
$consulta->bind_param('i', $id);
$consulta->execute();
$perfil = $consulta->get_result()->fetch_assoc();

$consulta = $conexion->prepare("SELECT m.descripcion_mat, CONCAT_WS(' ', p.nombre_prof, p.apaterno_prof) AS profesor,
    am.unidad1, am.unidad2, am.unidad3, am.unidad4, am.calificacion
    FROM asignar_materia am JOIN materias m ON m.idmateria = am.idmateria
    JOIN profesores p ON p.idprof = am.idprof
    WHERE am.idalumn = ? ORDER BY m.descripcion_mat");
$consulta->bind_param('i', $id);
$consulta->execute();
$materias = $consulta->get_result();

inicio_pagina('Portal del Estudiante', true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <span class="etiqueta text-uppercase">Portal del Alumno</span>
        <h1 class="h2 fw-bold mb-1">Mi Expediente y Calificaciones</h1>
        <p class="text-muted mb-0">Matrícula: <strong><?= e($perfil['matricula_al']) ?></strong> · Correo: <?= e($perfil['mail_al']) ?></p>
    </div>
    <a href="consultas/reporte_boleta.php" class="btn btn-outline-primary btn-sm">Ver boleta imprimible</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-<?= e($clase) ?>" role="alert"><?= e($mensaje) ?></div>
<?php endif; ?>

<!-- Formulario de actualización de datos del alumno -->
<section class="card mb-4 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1">Formulario de Datos Personales</h2>
        <p class="text-muted small mb-4">Completa o actualiza tu información personal y de contacto.</p>
        <form method="post" class="row g-3">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="actualizar_perfil">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="nombre_al">Nombre(s)</label>
                <input id="nombre_al" name="nombre_al" class="form-control" maxlength="60" value="<?= e($perfil['nombre_al']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="apaterno_al">Apellido paterno</label>
                <input id="apaterno_al" name="apaterno_al" class="form-control" maxlength="60" value="<?= e($perfil['apaterno_al']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="amaterno_al">Apellido materno</label>
                <input id="amaterno_al" name="amaterno_al" class="form-control" maxlength="60" value="<?= e($perfil['amaterno_al']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="tel_al">Teléfono de contacto</label>
                <input id="tel_al" name="tel_al" type="tel" class="form-control" maxlength="20" value="<?= e($perfil['tel_al']) ?>" placeholder="Ej. 55 1234 5678">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold" for="dom_al">Domicilio completo</label>
                <input id="dom_al" name="dom_al" class="form-control" maxlength="150" value="<?= e($perfil['dom_al']) ?>" placeholder="Calle, número, colonia, alcaldía/municipio">
            </div>
            <div class="col-12">
                <button class="btn btn-principal">Guardar mis datos</button>
            </div>
        </form>
    </div>
</section>

<!-- Consulta de Calificaciones -->
<section class="card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1">Mis Materias y Calificaciones</h2>
        <p class="text-muted small mb-3">Consulta tus evaluaciones parciales por unidad y tu promedio final.</p>
        <?php if ($materias->num_rows === 0): ?>
            <div class="alert alert-light border text-muted mb-0">Aún no tienes materias inscritas en este periodo.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Materia</th>
                            <th>Profesor</th>
                            <th class="text-center">Unidad 1</th>
                            <th class="text-center">Unidad 2</th>
                            <th class="text-center">Unidad 3</th>
                            <th class="text-center">Unidad 4</th>
                            <th class="text-center">Promedio Final</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($materia = $materias->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($materia['descripcion_mat']) ?></td>
                            <td><?= e($materia['profesor']) ?></td>
                            <td class="text-center"><?= e($materia['unidad1'] ?? '—') ?></td>
                            <td class="text-center"><?= e($materia['unidad2'] ?? '—') ?></td>
                            <td class="text-center"><?= e($materia['unidad3'] ?? '—') ?></td>
                            <td class="text-center"><?= e($materia['unidad4'] ?? '—') ?></td>
                            <td class="text-center fw-bold">
                                <?php if ($materia['calificacion'] !== null): ?>
                                    <span class="badge text-bg-<?= ((float)$materia['calificacion'] >= 70) ? 'success' : 'danger' ?> fs-6">
                                        <?= e($materia['calificacion']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php fin_pagina(); ?>
