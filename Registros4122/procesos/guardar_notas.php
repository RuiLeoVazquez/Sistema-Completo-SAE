<?php

function guardar_notas(mysqli $conexion, array $lote): void
{
    if (!$lote) {
        throw new DomainException('Selecciona una asignación para guardar.');
    }
    $admin = es_admin() ? 1 : 0;
    $profesor = profesor_actual();
    $buscar = $conexion->prepare('SELECT idasignar_materia FROM asignar_materia
        WHERE idasignar_materia = ? AND (? = 1 OR idprof = ?) FOR UPDATE');
    $guardar = $conexion->prepare('UPDATE asignar_materia
        SET unidad1 = ?, unidad2 = ?, unidad3 = ?, unidad4 = ?, calificacion = ?
        WHERE idasignar_materia = ?');

    $conexion->begin_transaction();
    try {
        ksort($lote, SORT_NUMERIC);
        foreach ($lote as $id => $notas) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false || !is_array($notas)) {
                throw new DomainException('La asignación es inválida.');
            }
            $buscar->bind_param('iii', $id, $admin, $profesor);
            $buscar->execute();
            if (!$buscar->get_result()->fetch_assoc()) {
                throw new DomainException('Tu cuenta no puede editar esa asignación.');
            }
            $valores = [];
            for ($unidad = 1; $unidad <= 4; $unidad++) {
                $valor = $notas['unidad' . $unidad] ?? null;
                if ($valor !== '' && (!is_numeric($valor) || $valor < 0 || $valor > 100)) {
                    throw new DomainException('Revisa las cuatro unidades. Las calificaciones deben estar entre 0 y 100.');
                }
                $valores[] = $valor === '' ? null : (float) $valor;
            }
            $presentes = array_filter($valores, fn($valor) => $valor !== null);
            $promedio = $presentes ? array_sum($presentes) / count($presentes) : null;
            $guardar->bind_param('dddddi', $valores[0], $valores[1], $valores[2], $valores[3], $promedio, $id);
            $guardar->execute();
        }
        $conexion->commit();
    } catch (Throwable $excepcion) {
        $conexion->rollback();
        throw $excepcion;
    }
}
