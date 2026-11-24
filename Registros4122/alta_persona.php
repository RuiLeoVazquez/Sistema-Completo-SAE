<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/cuentas.php';
if (!$bd_disponible) {
    http_response_code(503);
    exit('Inicia MySQL en XAMPP y vuelve a abrir el formulario.');
}

if (!isset($registroPublico) && !isset($rolAlta)) { prohibir_acceso(); }
$publico = $registroPublico ?? false;
$rol = $publico ? 'alumno' : $rolAlta;
if (!$publico) {
    exigir_admin();
} elseif (isset($_SESSION['uid']) && cuenta_activa($conexion, 'id', $_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}

$profesor = $rol === 'maestro';
$tipo = $profesor ? 'profesor' : 'alumno';
$titulo = $publico ? 'Registro de alumno' : 'Alta de ' . $tipo;
$volver = $publico ? 'login.php' : '../admin/usuarios.php';
$campos = [
    'identificador' => [$profesor ? 'Número de control' : 'Matrícula', 20, true],
    'nombre' => ['Nombre', 60, true],
    'paterno' => ['Apellido paterno', 60, true],
    'materno' => ['Apellido materno', 60, false],
    'correo' => ['Correo electrónico', 100, true],
    'telefono' => ['Teléfono', 20, false],
    'domicilio' => ['Domicilio', 150, false],
];
$datos = [];
$error = '';
$mensaje = $_SESSION['alta_' . $tipo] ?? null;
unset($_SESSION['alta_' . $tipo]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    try {
        foreach ($campos as $campo => [$etiqueta, $limite, $requerido]) {
            $entrada = $_POST[$campo] ?? '';
            $valor = is_string($entrada) ? trim($entrada) : '';
            $datos[$campo] = $valor;
            if (($requerido && $valor === '') || preg_match_all('/./us', $valor) > $limite) {
                throw new DomainException('Revisa el campo «' . $etiqueta . '».');
            }
        }
        $datos['correo'] = correo_cuenta($datos['correo']);
        $clave = $publico ? ($_POST['clave'] ?? '') : bin2hex(random_bytes(10));
        if (!is_string($clave)) {
            throw new DomainException('Escribe una contraseña válida.');
        }
        validar_clave($clave);
        if ($publico && $clave !== ($_POST['confirmacion'] ?? '')) {
            throw new DomainException('Las contraseñas deben coincidir.');
        }

        $conexion->begin_transaction();
        try {
            $sql = $profesor
                ? 'INSERT INTO profesores (nocontrol_prof,nombre_prof,apaterno_prof,amaterno_prof,mail_prof,tel_prof,dom_prof) VALUES (?,?,?,?,?,?,?)'
                : 'INSERT INTO alumnos (matricula_al,nombre_al,apaterno_al,amaterno_al,mail_al,tel_al,dom_al) VALUES (?,?,?,?,?,?,?)';
            $consulta = $conexion->prepare($sql);
            $consulta->bind_param('sssssss', $datos['identificador'], $datos['nombre'], $datos['paterno'],
                $datos['materno'], $datos['correo'], $datos['telefono'], $datos['domicilio']);
            $consulta->execute();
            crear_cuenta($conexion, $rol, $datos['correo'], $clave, $conexion->insert_id, $publico ? 'PENDIENTE' : 'ALTA');
            $conexion->commit();
        } catch (Throwable $excepcion) {
            $conexion->rollback();
            throw $excepcion;
        }

        $_SESSION['alta_' . $tipo] = $publico
            ? ['texto' => 'Registro recibido. El administrador debe revisar tus datos y activar tu cuenta.']
            : ['texto' => 'Perfil y cuenta activos. Entrega la contraseña inicial de forma privada.',
               'correo' => $datos['correo'], 'clave' => $clave];
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']), true, 303);
        exit;
    } catch (DomainException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (mysqli_sql_exception $excepcion) {
        error_log($excepcion->getMessage());
        $error = $excepcion->getCode() === 1062
            ? 'Ese correo o identificador ya está registrado. Solicita ayuda al administrador.'
            : 'No se pudo guardar el registro. Inténtalo de nuevo.';
    } catch (Throwable $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo guardar el registro. Revisa los datos e inténtalo de nuevo.';
    }
}
require __DIR__ . '/plantilla.php';
inicio_pagina($titulo);
?>
<a class="btn btn-outline-secondary btn-sm mb-3" href="<?= e($volver) ?>">Volver</a>
<section class="card"><div class="card-body p-4 p-md-5">
    <h1 class="h3"><?= e($titulo) ?></h1>
    <p class="text-muted"><?= $publico
        ? 'Completa tus datos. Podrás entrar con el correo y la contraseña que registres cuando se active tu cuenta.'
        : 'Captura los datos para crear el perfil y habilitar el acceso con su correo electrónico.' ?></p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?>
        <div class="alert alert-success" role="status">
            <?= e($mensaje['texto']) ?>
            <?php if (isset($mensaje['clave'])): ?>
                <div>Correo <strong><?= e($mensaje['correo']) ?></strong></div>
                <div>Contraseña inicial <code><?= e($mensaje['clave']) ?></code></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <?= campo_csrf() ?>
        <?php foreach ($campos as $campo => [$etiqueta, $limite, $requerido]): ?>
            <div class="col-md-6">
                <label class="form-label" for="<?= e($campo) ?>"><?= e($etiqueta) ?><?= $requerido ? '' : ' (opcional)' ?></label>
                <input class="form-control" id="<?= e($campo) ?>" name="<?= e($campo) ?>"
                       type="<?= $campo === 'correo' ? 'email' : ($campo === 'telefono' ? 'tel' : 'text') ?>"
                       maxlength="<?= $limite ?>" value="<?= e($datos[$campo] ?? '') ?>" <?= $requerido ? 'required' : '' ?>>
            </div>
        <?php endforeach; ?>
        <?php if ($publico): ?>
            <?php foreach (['clave' => 'Contraseña', 'confirmacion' => 'Confirmar contraseña'] as $campo => $etiqueta): ?>
                <div class="col-md-6">
                    <label class="form-label" for="<?= e($campo) ?>"><?= e($etiqueta) ?></label>
                    <input class="form-control" id="<?= e($campo) ?>" name="<?= e($campo) ?>" type="password"
                           autocomplete="new-password" minlength="10" maxlength="72" required>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="col-12"><button class="btn btn-primary"><?= $publico ? 'Enviar registro' : 'Dar de alta' ?></button></div>
    </form>
</div></section>
<?php fin_pagina(); ?>
