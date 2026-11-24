<?php require_once __DIR__.'/../conexion.php'; if(!$bd_disponible){header('Location: ../index.html');exit;} ?>
<?php require_once __DIR__.'/../seguridad.php'; exigir_admin(); 
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'alumnos';
if (!in_array($tipo, ['alumnos','grupos','materias','profesores'], true)) $tipo='alumnos';
require __DIR__ . '/../entradas/catalogo.php';
