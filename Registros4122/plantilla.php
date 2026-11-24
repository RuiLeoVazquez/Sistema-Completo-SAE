<?php

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inicio_pagina(string $titulo, bool $sesion = false): void
{
    header('Cache-Control: no-store');
    $rolActual = $_SESSION['rol'] ?? '';
    $etiquetasRol = [
        'administrador' => ['Administrador', 'warning text-dark'],
        'maestro' => ['Maestro / Docente', 'success'],
        'alumno' => ['Alumno', 'info text-dark'],
    ];
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | Sistema de Administración Escolar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(RUTA_INICIO) ?>assets/estilos.css" rel="stylesheet">
</head>
<body>
<header class="barra text-white shadow-sm">
    <nav class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
        <div class="d-flex align-items-center gap-3">
            <a class="text-white text-decoration-none fw-bold fs-5" href="<?= e(RUTA_INICIO) ?>index.php">
                Sistema Escolar <span class="fw-normal opacity-75 fs-6">| SAE</span>
            </a>
            <?php if ($sesion && isset($etiquetasRol[$rolActual])): ?>
                <span class="badge bg-<?= $etiquetasRol[$rolActual][1] ?> fw-semibold">
                    <?= e($etiquetasRol[$rolActual][0]) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($sesion): ?>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-white-50 d-none d-md-inline"><?= e($_SESSION['usuario'] ?? '') ?></span>
                <form method="post" action="<?= e(RUTA_INICIO) ?>login.php" class="m-0">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="salir" value="1">
                    <button class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                </form>
            </div>
        <?php endif; ?>
    </nav>
</header>
<main class="container py-4 py-md-5">
<?php
}

function fin_pagina(): void
{
    echo '</main><footer class="border-top py-3 mt-5 text-center text-muted small">Sistema de Administración Escolar · Control de Accesos y Registros Académicos</footer></body></html>';
}
