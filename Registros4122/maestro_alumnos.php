<?php
require_once __DIR__ . '/conexion.php';
if (!$bd_disponible) {
    header('Location: index.html');
    exit;
}
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/plantilla.php';
exigir_maestro();

$mensaje = '';
$clase = 'success';
$editar = null;
$idProf = profesor_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idAlumn = (int) ($_POST['idalumn'] ?? 0);

    if ($accion === 'actualizar_alumno' && $idAlumn > 0) {
        try {
            // Verificar que el alumno pertenece a una materia o grupo del maestro (o que sea admin)
            if (!es_admin()) {
                $chk = $conexion->prepare("SELECT 1 FROM asignar_materia WHERE idalumn = ? AND idprof = ?
                    UNION SELECT 1 FROM asignar_grupo WHERE idalumn = ? AND idprof = ? LIMIT 1");
                $chk->bind_param('iiii', $idAlumn, $idProf, $idAlumn, $idProf);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) {
                    throw new DomainException('Solo puedes modificar los registros de alumnos inscritos en tus materias o grupos.');
                }
            }

            $nombre = trim($_POST['nombre_al'] ?? '');
            $apaterno = trim($_POST['apaterno_al'] ?? '');
            $amaterno = trim($_POST['amaterno_al'] ?? '');
            $dom = trim($_POST['dom_al'] ?? '');
            $mail = trim($_POST['mail_al'] ?? '');
            $tel = trim($_POST['tel_al'] ?? '');

            if ($nombre === '' || $apaterno === '' || $mail === '') {
                throw new DomainException('Nombre, apellido paterno y correo electrónico son obligatorios.');
            }

            $stmt = $conexion->prepare("UPDATE alumnos SET nombre_al = ?, apaterno_al = ?, amaterno_al = ?, dom_al = ?, mail_al = ?, tel_al = ? WHERE idalumn = ?");
            $stmt->bind_param('ssssssi', $nombre, $apaterno, $amaterno, $dom, $mail, $tel, $idAlumn);
            $stmt->execute();

            $mensaje = 'Registro del alumno actualizado correctamente.';
        } catch (Throwable $e) {
            $mensaje = $e instanceof DomainException ? $e->getMessage() : 'No se pudo actualizar el registro del alumno.';
            $clase = 'danger';
        }
    }
}

if (isset($_GET['editar'])) {
    $idEdit = (int) $_GET['editar'];
    if (es_admin()) {
        $stmt = $conexion->prepare("SELECT * FROM alumnos WHERE idalumn = ?");
        $stmt->bind_param('i', $idEdit);
    } else {
        $stmt = $conexion->prepare("SELECT DISTINCT a.* FROM alumnos a
            LEFT JOIN asignar_materia am ON am.idalumn = a.idalumn
            LEFT JOIN asignar_grupo ag ON ag.idalumn = a.idalumn
            WHERE a.idalumn = ? AND (am.idprof = ? OR ag.idprof = ?)");
        $stmt->bind_param('iii', $idEdit, $idProf, $idProf);
    }
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

if (es_admin()) {
    $alumnos = $conexion->query("SELECT * FROM alumnos ORDER BY apaterno_al, nombre_al");
} else {
    $stmt = $conexion->prepare("SELECT DISTINCT a.* FROM alumnos a
        LEFT JOIN asignar_materia am ON am.idalumn = a.idalumn
        LEFT JOIN asignar_grupo ag ON ag.idalumn = a.idalumn
        WHERE am.idprof = ? OR ag.idprof = ?
        ORDER BY a.apaterno_al, a.nombre_al");
    $stmt->bind_param('ii', $idProf, $idProf);
    $stmt->execute();
    $alumnos = $stmt->get_result();
}

inicio_pagina('Gestión de Alumnos (Docente)', true);
?>
<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">← Volver al panel docente</a>
<h1 class="h2 fw-bold">Gestión de Alumnos y Registros</h1>
<p class="text-muted">Consulta y actualiza la información de contacto y datos académicos de los alumnos asignados a tus cursos.</p>

<?php if ($mensaje): ?>
    <div class="alert alert-<?= e($clase) ?>"><?= e($mensaje) ?></div>
<?php endif; ?>

<?php if ($editar): ?>
<section class="card mb-4 border-primary">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">Editar datos del alumno: <?= e($editar['matricula_al']) ?></h2>
        <form method="post" class="row g-3">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="actualizar_alumno">
            <input type="hidden" name="idalumn" value="<?= e($editar['idalumn']) ?>">
            <div class="col-md-4">
                <label class="form-label">Nombre(s)</label>
                <input class="form-control" name="nombre_al" value="<?= e($editar['nombre_al']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido paterno</label>
                <input class="form-control" name="apaterno_al" value="<?= e($editar['apaterno_al']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido materno</label>
                <input class="form-control" name="amaterno_al" value="<?= e($editar['amaterno_al']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" name="mail_al" value="<?= e($editar['mail_al']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="tel" class="form-control" name="tel_al" value="<?= e($editar['tel_al']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Domicilio</label>
                <input class="form-control" name="dom_al" value="<?= e($editar['dom_al']) ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-success">Guardar cambios</button>
                <a href="maestro_alumnos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">Alumnos asignados</h2>
        <?php if ($alumnos->num_rows === 0): ?>
            <p class="text-muted mb-0">Aún no tienes alumnos asignados a tus materias o grupos.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Matrícula</th>
                            <th>Nombre completo</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($al = $alumnos->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($al['matricula_al']) ?></td>
                            <td><?= e(trim($al['nombre_al'] . ' ' . $al['apaterno_al'] . ' ' . $al['amaterno_al'])) ?></td>
                            <td><?= e($al['mail_al']) ?></td>
                            <td><?= e($al['tel_al'] ?: '—') ?></td>
                            <td><span class="badge text-bg-<?= $al['estatus_al'] === 'ALTA' ? 'success' : 'secondary' ?>"><?= e($al['estatus_al']) ?></span></td>
                            <td>
                                <a href="maestro_alumnos.php?editar=<?= e($al['idalumn']) ?>" class="btn btn-sm btn-outline-primary">Modificar datos</a>
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
