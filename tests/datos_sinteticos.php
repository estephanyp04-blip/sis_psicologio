<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function prueba_autores(mysqli $bd): void
{
    $base = $bd->query('SELECT DATABASE()')->fetch_row()[0];
    if (!preg_match('/^psicologia_test_[a-z]+_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Los datos sintéticos requieren una base de prueba.');
    $bd->query("INSERT INTO usuarios (cod_usuario,nombres,apellidos,usuario,password,cod_rol) VALUES
        (101,'Autor','Prueba','prueba_admin','hash-prueba',1),(102,'Psicóloga','Prueba','prueba_psicologa','hash-prueba',2),
        (103,'Docente','Uno','prueba_docente','hash-prueba',3),(104,'Director','Prueba','prueba_director','hash-prueba',4),
        (105,'Docente','Dos','docente_disponible_uno','hash-prueba',3),(106,'Docente','Tres','docente_disponible_dos','hash-prueba',3),
        (111,'Docente','Asignado','prueba_docente_dos','hash-prueba',3)");
    $bd->query('INSERT INTO docentes (cod_docente,cod_usuario) VALUES (201,103),(202,111)');
    $bd->query('UPDATE secciones SET gestion=YEAR(CURRENT_DATE())');
}

function prueba_estudiantes(mysqli $bd, int $cantidad): void
{
    $base = $bd->query('SELECT DATABASE()')->fetch_row()[0];
    if (!preg_match('/^psicologia_test_[a-z]+_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Los estudiantes sintéticos requieren una base de prueba.');
    for ($i = 1; $i <= $cantidad; ++$i) {
        $bd->query("INSERT INTO estudiantes (cod_estudiante,codigo,ci,nombres,apellidos,lugar_nacimiento,telefono)
            VALUES ($i,'TEST$i','TEST$i','Estudiante','Prueba $i','Lugar de ficha','111')");
        $bd->query("INSERT INTO historial_academico (cod_estudiante,cod_seccion,fecha_inicio,estado) VALUES ($i,1,DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY),'Activo')");
        $bd->query("INSERT INTO responsables (cod_responsable,nombres) VALUES ($i,'Tutor de ficha')");
        $bd->query("INSERT INTO estudiante_responsables (cod_estudiante,cod_responsable,parentesco,es_principal) VALUES ($i,$i,'Tutor',1)");
    }
}
