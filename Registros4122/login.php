<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/cuentas.php';
require_once __DIR__ . '/plantilla.php';

$mensaje = '';
$avisoExito = '';
$perfilSeleccionado = $_GET['perfil'] ?? $_POST['perfil'] ?? '';
$perfilesValidos = [
    'administrador' => [
        'titulo' => 'Acceso Administrativo',
        'subtitulo' => 'Control integral del sistema, gestión de usuarios, catálogos y configuración académica.',
        'badge' => 'Administrador',
        'color' => 'primary',
    ],
    'maestro' => [
        'titulo' => 'Portal Docente',
        'subtitulo' => 'Gestión de calificaciones, seguimiento de alumnos y administración de registros académicos.',
        'badge' => 'Maestro / Docente',
        'color' => 'success',
    ],
    'alumno' => [
        'titulo' => 'Portal del Estudiante',
        'subtitulo' => 'Actualización de información personal, formularios escolares y consulta de boletas.',
        'badge' => 'Alumno',
        'color' => 'info',
    ],
];

if (!isset($perfilesValidos[$perfilSeleccionado])) {
    $perfilSeleccionado = '';
}

// Verificar si ya existe al menos 1 administrador en el sistema
$existeAdmin = true;
if ($bd_disponible) {
    $resAdmin = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE puesto = 'administrador'");
    $existeAdmin = ((int) ($resAdmin->fetch_assoc()['total'] ?? 0)) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    if (isset($_POST['salir'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php', true, 303);
        exit;
    }

    // Creación única del primer administrador (solo si no existe ninguno)
    if (isset($_POST['crear_primer_admin']) && $bd_disponible) {
        try {
            $conexion->begin_transaction();
            $chk = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE puesto = 'administrador' FOR UPDATE");
            if (((int) ($chk->fetch_assoc()['total'] ?? 0)) > 0) {
                throw new DomainException('Ya existe un administrador registrado. Inicia sesión con su cuenta o solicita que te dé de alta.');
            }
            $correoAdmin = correo_cuenta($_POST['correo_admin'] ?? '');
            $claveAdmin = (string) ($_POST['clave_admin'] ?? '');
            validar_clave($claveAdmin);
            $hashAdmin = password_hash($claveAdmin, PASSWORD_DEFAULT);

            $stmt = $conexion->prepare("INSERT INTO usuarios (nombreuser, pwuser, puesto, status_user) VALUES (?, ?, 'administrador', 'ALTA')");
            $stmt->bind_param('ss', $correoAdmin, $hashAdmin);
            $stmt->execute();
            $conexion->commit();

            $existeAdmin = true;
            $perfilSeleccionado = 'administrador';
            $avisoExito = 'Administrador principal creado con éxito (' . $correoAdmin . '). Ya puedes iniciar sesión.';
        } catch (Throwable $e) {
            $conexion->rollback();
            $mensaje = $e instanceof DomainException ? $e->getMessage() : 'No se pudo registrar el administrador. Verifica el correo.';
        }
    } elseif ($bd_disponible) {
        $usuario = trim(is_string($_POST['usuario'] ?? null) ? $_POST['usuario'] : '');
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
        $cuenta = cuenta_activa($conexion, 'usuario', $usuario);
        if ($cuenta && password_verify($clave, $cuenta['pwuser'])) {
            if ($perfilSeleccionado !== '' && $cuenta['puesto'] !== $perfilSeleccionado) {
                $mensaje = 'Tu cuenta corresponde al perfil de ' . ucfirst($cuenta['puesto']) . '. Selecciona el botón correcto o ingresa sin filtro.';
            } else {
                session_regenerate_id(true);
                $_SESSION = [];
                actualizar_sesion($cuenta);
                header('Location: index.php', true, 303);
                exit;
            }
        } else {
            $mensaje = 'Credenciales inválidas. Verifica tu correo electrónico y contraseña o solicita la activación al administrador.';
        }
    }
} elseif ($bd_disponible && isset($_SESSION['uid'])) {
    exigir_sesion();
    header('Location: index.php');
    exit;
}

if (!$bd_disponible) {
    http_response_code(503);
}
inicio_pagina('Portal de Acceso Institucional');
?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10 text-center">
        <span class="badge rounded-pill text-bg-light border px-3 py-2 mb-2 text-uppercase fw-semibold" style="letter-spacing: .08em; color: #203b63 !important;">
            Sistema de Administración Escolar (SAE)
        </span>
        <h1 class="h2 fw-bold mb-2">Portal Institucional de Usuarios</h1>
        <p class="text-muted mb-0">Selecciona tu perfil de usuario para iniciar sesión en el módulo correspondiente</p>
    </div>
</div>

<?php if (!$bd_disponible): ?>
    <div class="alert alert-warning mx-auto" style="max-width: 42rem;">
        <strong>Base de datos fuera de servicio:</strong> Inicia el servicio MySQL en XAMPP y recarga esta página.
        <div class="mt-2"><a href="demo.html" class="alert-link">Abrir demostración local</a></div>
    </div>
<?php else: ?>

    <?php if (!$existeAdmin): ?>
    <!-- Registro único del Primer Administrador (se oculta automáticamente en cuanto se crea el primero) -->
    <section class="card mx-auto mb-4 border-warning border-2 shadow" style="max-width: 34rem;">
        <div class="card-body p-4">
            <span class="badge text-bg-warning text-dark mb-2">Configuración Inicial · Único Uso</span>
            <h2 class="h5 fw-bold mb-1">Crear Primer Administrador</h2>
            <p class="text-muted small mb-3">Aún no hay ningún administrador registrado. Crea la cuenta principal aquí; una vez creada, este formulario desaparecerá y solo ese administrador podrá dar de alta a otros.</p>
            <?php if ($mensaje): ?>
                <div class="alert alert-danger py-2 small" role="alert"><?= e($mensaje) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= campo_csrf() ?>
                <input type="hidden" name="crear_primer_admin" value="1">
                <div class="mb-3">
                    <label for="correo_admin" class="form-label fw-semibold">Correo del Administrador</label>
                    <input id="correo_admin" name="correo_admin" type="email" class="form-control" placeholder="admin@escuela.edu.mx" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label for="clave_admin" class="form-label fw-semibold">Contraseña (mínimo 10 caracteres)</label>
                    <input id="clave_admin" name="clave_admin" type="password" class="form-control" minlength="10" maxlength="72" placeholder="Escribe una contraseña segura" required>
                </div>
                <button class="btn btn-warning w-100 fw-bold">Registrar Administrador Principal</button>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <!-- Selector de 3 Perfiles -->
    <div class="row g-3 justify-content-center mb-4">
        <?php foreach ($perfilesValidos as $clavePerfil => $info): 
            $activo = ($perfilSeleccionado === $clavePerfil);
        ?>
            <div class="col-md-4">
                <a href="login.php?perfil=<?= e($clavePerfil) ?>#form-acceso" 
                   class="card h-100 text-decoration-none perfil-card <?= $activo ? 'border-' . $info['color'] . ' border-2 shadow' : 'border shadow-sm' ?>">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge text-bg-<?= e($info['color']) ?> px-3 py-2"><?= e($info['badge']) ?></span>
                            <?php if ($activo): ?>
                                <span class="text-<?= e($info['color']) ?> small fw-semibold">Seleccionado ✓</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h5 text-dark fw-bold mb-2"><?= e($info['titulo']) ?></h2>
                        <p class="text-muted small mb-4 flex-grow-1"><?= e($info['subtitulo']) ?></p>
                        <span class="btn btn-<?= $activo ? '' : 'outline-' ?><?= e($info['color']) ?> w-100 fw-semibold">
                            Ingresar como <?= e(ucfirst($clavePerfil === 'administrador' ? 'Administrador' : ($clavePerfil === 'maestro' ? 'Maestro' : 'Alumno'))) ?>
                        </span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Formulario Formal de Autenticación -->
    <section id="form-acceso" class="card mx-auto acceso shadow-sm border">
        <div class="card-body p-4 p-md-5">
            <?php if ($avisoExito): ?>
                <div class="alert alert-success" role="status"><?= e($avisoExito) ?></div>
            <?php endif; ?>

            <?php if ($perfilSeleccionado !== ''): $p = $perfilesValidos[$perfilSeleccionado]; ?>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge text-bg-<?= e($p['color']) ?>"><?= e($p['badge']) ?></span>
                    <a href="login.php" class="small text-muted text-decoration-none">Ver todos los perfiles</a>
                </div>
                <h2 class="h4 fw-bold mb-1"><?= e($p['titulo']) ?></h2>
                <p class="text-muted small mb-4">Ingresa tus credenciales institucionales para continuar.</p>
            <?php else: ?>
                <h2 class="h4 fw-bold mb-1">Inicio de sesión general</h2>
                <p class="text-muted small mb-4">Selecciona un perfil arriba o ingresa directamente con tu correo institucional.</p>
            <?php endif; ?>

            <?php if ($mensaje && $existeAdmin): ?>
                <div class="alert alert-danger" role="alert"><?= e($mensaje) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= campo_csrf() ?>
                <input type="hidden" name="perfil" value="<?= e($perfilSeleccionado) ?>">
                <div class="mb-3">
                    <label for="usuario" class="form-label fw-semibold">Correo electrónico institucional</label>
                    <input id="usuario" name="usuario" class="form-control" autocomplete="username" maxlength="100" placeholder="usuario@institucion.edu.mx" required>
                </div>
                <div class="mb-4">
                    <label for="clave" class="form-label fw-semibold">Contraseña</label>
                    <input id="clave" type="password" name="clave" class="form-control" autocomplete="current-password" placeholder="••••••••••" required>
                </div>
                <button class="btn btn-principal w-100 py-2 fw-semibold">Iniciar sesión</button>
            </form>

            <hr class="my-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                <span class="text-muted">¿Eres alumno de nuevo ingreso?</span>
                <a class="fw-semibold text-decoration-none" href="registro.php">Solicitar registro de alumno →</a>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php fin_pagina(); ?>
