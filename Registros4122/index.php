<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/plantilla.php';
exigir_sesion();

if (!es_personal()) {
    require __DIR__ . '/panel_alumno.php';
    exit;
}

$secciones = [];

if (es_admin()) {
    $secciones['Control Total de Administración y Usuarios'] = [
        'Administrar cuentas, roles y solicitudes' => 'admin/usuarios.php',
        'Dar de alta a un alumno' => 'entradas/registrar_alumno.php',
        'Dar de alta a un profesor' => 'entradas/registrar_profesor.php',
        'Gestión integral de catálogos (Alumnos, Profesores, Materias, Grupos)' => 'entradas/catalogo.php?tipo=alumnos',
        'Registrar nueva materia' => 'entradas/registrar_materia.php',
        'Registrar nuevo grupo' => 'entradas/registrar_grupo.php',
        'Asignar grupo a alumno/profesor' => 'procesos/asignar_grupo.php',
        'Asignar materia y carga académica' => 'procesos/asignar_materia.php',
    ];
    $secciones['Listados Generales del Sistema'] = [
        'Listado general de alumnos' => 'consultas/lista_alumnos.php',
        'Alumnos dados de baja' => 'consultas/alumnos_baja.php',
        'Listado general de profesores' => 'consultas/lista_profesores.php',
        'Listado de materias' => 'consultas/lista_materias.php',
        'Listado de grupos' => 'consultas/lista_grupos.php',
        'Boletas generales' => 'consultas/boletas.php',
    ];
} else {
    // Panel exclusivo para Maestro: modificación de registros de alumnos asignados y calificaciones
    $secciones['Gestión de Alumnos y Registros Académicos'] = [
        'Actualizar datos y registros de mis alumnos' => 'maestro_alumnos.php',
        'Asignar alumno a mis grupos' => 'procesos/asignar_grupo.php',
    ];
}

$secciones['Evaluación y Calificaciones'] = [
    'Captura individual de calificaciones' => 'procesos/calificaciones_individual.php',
    'Captura masiva de calificaciones' => 'procesos/calificaciones_masiva.php',
];

$secciones['Reportes y Consultas Académicas'] = [
    'Alumnos por grupo' => 'consultas/reporte_alumnos_grupo.php',
    'Materias por alumno' => 'consultas/reporte_materias_alumno.php',
    'Calificaciones por materia y grupo' => 'consultas/reporte_calificaciones.php',
    'Boletas de calificaciones' => 'consultas/reporte_boleta.php',
];

inicio_pagina('Panel Institucional', true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <span class="etiqueta text-uppercase"><?= es_admin() ? 'Acceso Total · Administrador' : 'Acceso Docente · Maestro' ?></span>
        <h1 class="h2 fw-bold mb-1">Panel de <?= es_admin() ? 'Administración General' : 'Control Docente' ?></h1>
        <p class="text-muted mb-0">Sesión iniciada como: <strong><?= e($_SESSION['usuario']) ?></strong></p>
    </div>
</div>

<?php foreach ($secciones as $seccion => $enlaces): ?>
<section class="my-4">
    <h2 class="h5 fw-bold mb-3 text-secondary"><?= e($seccion) ?></h2>
    <div class="row g-3">
        <?php foreach ($enlaces as $titulo => $url): ?>
            <div class="col-md-6 col-lg-4">
                <a class="card panel-enlace h-100 p-3 d-flex justify-content-center" href="<?= e($url) ?>">
                    <span><?= e($titulo) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>
<?php fin_pagina(); ?>
