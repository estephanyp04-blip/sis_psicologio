<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function prueba_autores(mysqli $bd): void
{
    $base = $bd->query('SELECT DATABASE()')->fetch_row()[0];
    if (!preg_match('/^psicologia_test_[a-z]+_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Los datos sintéticos requieren una base de prueba.');
    $bd->query("INSERT INTO personas (id_persona,nombres,apellidos) VALUES
        (1001,'Autor','Prueba'),(1002,'Psicóloga','Prueba'),(1003,'Docente','Uno'),(1004,'Director','Prueba'),(1005,'Docente','Dos')");
    $bd->query("INSERT INTO usuarios (id_usuario,id_persona,usuario,password,id_rol) VALUES
        (101,1001,'prueba_admin','hash-prueba',1),(102,1002,'prueba_psicologa','hash-prueba',2),
        (103,1003,'prueba_docente','hash-prueba',3),(104,1004,'prueba_director','hash-prueba',4)");
    $bd->query('INSERT INTO docentes (id_docente,id_persona) VALUES (201,1003),(202,1005)');
    $bd->query('UPDATE secciones SET gestion=YEAR(CURRENT_DATE())');
}

function prueba_estudiantes(mysqli $bd, int $cantidad): void
{
    $base = $bd->query('SELECT DATABASE()')->fetch_row()[0];
    if (!preg_match('/^psicologia_test_[a-z]+_[a-f0-9]{12}$/', $base)) throw new RuntimeException('Los estudiantes sintéticos requieren una base de prueba.');
    for ($i = 1; $i <= $cantidad; ++$i) {
        $bd->query("INSERT INTO estudiantes (id_estudiante,codigo,ci,nombres,apellidos,lugar_nacimiento,telefono)
            VALUES ($i,'TEST$i','TEST$i','Estudiante','Prueba $i','Lugar de ficha','111')");
        $bd->query("INSERT INTO inscripciones (id_estudiante,id_seccion,fecha_inscripcion,estado) VALUES ($i,1,CURRENT_DATE(),'Activo')");
        $bd->query("INSERT INTO responsables (id_responsable,nombres) VALUES ($i,'Tutor de ficha')");
        $bd->query("INSERT INTO estudiante_responsables (id_estudiante,id_responsable,parentesco,es_principal) VALUES ($i,$i,'Tutor',1)");
    }
}
