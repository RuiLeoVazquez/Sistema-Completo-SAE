<?php require_once __DIR__.'/../conexion.php'; if(!$bd_disponible){header('Location: ../index.html');exit;} ?>
<?php require_once __DIR__.'/../seguridad.php'; exigir_maestro(); 
require_once __DIR__ . '/../conexion.php';
function h($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
$mensaje=''; $clase='success';
require_once __DIR__ . '/guardar_notas.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        guardar_notas($conexion, [(int) ($_POST['asignacion'] ?? 0) => $_POST]);
        $mensaje = 'Calificaciones guardadas correctamente.';
    } catch (DomainException $excepcion) {
        $mensaje = $excepcion->getMessage();
        $clase = 'danger';
    } catch (Throwable $excepcion) {
        error_log($excepcion->getMessage());
        $mensaje = 'No se pudieron guardar las calificaciones.';
        $clase = 'danger';
    }
}
$alcance = es_admin() ? '' : ' WHERE am.idprof = ' . profesor_actual();
$alumnos=$conexion->query("SELECT am.idasignar_materia, a.matricula_al,a.nombre_al,a.apaterno_al,a.amaterno_al,m.descripcion_mat,am.unidad1,am.unidad2,am.unidad3,am.unidad4 FROM asignar_materia am JOIN alumnos a ON a.idalumn=am.idalumn JOIN materias m ON m.idmateria=am.idmateria$alcance ORDER BY a.apaterno_al,a.nombre_al");
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Captura individual de calificaciones</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/estilos.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark barra"><div class="container"><a class="navbar-brand" href="../index.php">Sistema Escolar</a><a class="btn btn-light btn-sm" href="../index.php">Inicio</a></div></nav><main class="container py-4"><h2 class="mb-3">Captura individual de calificaciones</h2><p class="text-muted">Registra las calificaciones de las cuatro unidades para cada alumno y materia.</p><?php if($mensaje):?><div class="alert alert-<?php echo $clase;?>"><?php echo h($mensaje);?></div><?php endif;?><div class="card"><div class="card-body table-responsive"><table class="table table-bordered table-striped align-middle"><thead class="table-dark"><tr><th>Matrícula</th><th>Alumno</th><th>Materia</th><th>Unidad 1</th><th>Unidad 2</th><th>Unidad 3</th><th>Unidad 4</th><th>Acción</th></tr></thead><tbody><?php if (!$alumnos->num_rows): ?><tr><td colspan="8">Aún no hay materias asignadas para capturar.</td></tr><?php endif; ?><?php while($a=$alumnos->fetch_assoc()):?><?php $formulario = "notas-" . (int) $a["idasignar_materia"]; ?><tr><td><?php echo h($a['matricula_al']);?></td><td><?php echo h($a['nombre_al'].' '.$a['apaterno_al'].' '.$a['amaterno_al']);?></td><td><?php echo h($a['descripcion_mat']);?></td><?php for($i=1;$i<=4;$i++):?><td><input class="form-control" type="number" form="<?= h($formulario) ?>" name="unidad<?php echo $i;?>" min="0" max="100" step="0.01" value="<?php echo h($a['unidad'.$i]);?>" aria-label="Unidad <?php echo $i;?>"></td><?php endfor;?><td><form method="post" id="<?= h($formulario) ?>"><?= campo_csrf() ?><input type="hidden" name="asignacion" value="<?= (int) $a["idasignar_materia"] ?>"><button class="btn btn-primary btn-sm">Guardar</button></form></td></tr><?php endwhile;?></tbody></table></div></div></main></body></html>
