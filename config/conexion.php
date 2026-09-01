<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = '127.0.0.1';
$usuario = 'root';
$password = '';
$bd = 'psicologia_db';
$puerto = 3306;

try {
    $conexion = new mysqli($host, $usuario, $password, '', $puerto);
    $conexion->set_charset('utf8mb4');
    $conexion->query("CREATE DATABASE IF NOT EXISTS `$bd` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conexion->select_db($bd);
} catch (mysqli_sql_exception $error) {
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Verifique que MySQL este iniciado y la configuracion en config/conexion.php.');
}

function asegurar_columna(mysqli $conexion, string $tabla, string $columna, string $definicion): void
{
    $tabla = $conexion->real_escape_string($tabla);
    $columna = $conexion->real_escape_string($columna);
    $resultado = $conexion->query("SHOW COLUMNS FROM `$tabla` LIKE '$columna'");

    if ($resultado->num_rows === 0) {
        $conexion->query("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
    }

    $resultado->free();
}

function asegurar_esquema(mysqli $conexion): void
{
    $conexion->query("CREATE TABLE IF NOT EXISTS roles (
        id_rol INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("INSERT IGNORE INTO roles (id_rol, nombre) VALUES
        (1, 'Administrador'),
        (2, 'Psicóloga'),
        (3, 'Docente')");

    $conexion->query("CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        id_rol INT NOT NULL DEFAULT 0,
        nombre VARCHAR(150) NOT NULL,
        apellido VARCHAR(100) NOT NULL DEFAULT '',
        usuario VARCHAR(100) NOT NULL DEFAULT '',
        password VARCHAR(255) NOT NULL DEFAULT '',
        correo VARCHAR(150) NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'Activo',
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuarios_rol (id_rol)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    asegurar_columna(
        $conexion,
        'usuarios',
        'estado',
        "VARCHAR(30) NOT NULL DEFAULT 'Activo'"
    );

    foreach ([
        'apellido' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'usuario' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'password' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'correo' => 'VARCHAR(150) NULL',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'usuarios', $columna, $definicion);
    }

    $conexion->query("CREATE TABLE IF NOT EXISTS docentes (
        id_docente INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NULL DEFAULT NULL,
        nombres VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        telefono VARCHAR(30) NULL,
        correo VARCHAR(150) NULL,
        materia VARCHAR(150) NULL,
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'id_usuario' => 'INT NULL DEFAULT NULL',
        'telefono' => 'VARCHAR(30) NULL',
        'correo' => 'VARCHAR(150) NULL',
        'materia' => 'VARCHAR(150) NULL',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'docentes', $columna, $definicion);
    }

    $conexion->query("CREATE TABLE IF NOT EXISTS estudiantes (
        id_estudiante INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NULL,
        ci VARCHAR(30) NOT NULL,
        nombres VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        fecha_nacimiento DATE NULL,
        lugar_nacimiento VARCHAR(150) NULL,
        sexo VARCHAR(20) NULL,
        genero VARCHAR(20) NULL,
        curso VARCHAR(10) NOT NULL,
        paralelo VARCHAR(10) NOT NULL,
        turno VARCHAR(20) NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'Activo',
        padre VARCHAR(150) NULL,
        madre VARCHAR(150) NULL,
        tutor VARCHAR(150) NULL,
        telefono VARCHAR(30) NULL,
        nombre_tutor VARCHAR(150) NULL,
        telefono_tutor VARCHAR(30) NULL,
        direccion TEXT NULL,
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'codigo' => 'VARCHAR(50) NULL',
        'fecha_nacimiento' => 'DATE NULL',
        'lugar_nacimiento' => 'VARCHAR(150) NULL',
        'sexo' => 'VARCHAR(20) NULL',
        'genero' => 'VARCHAR(20) NULL',
        'turno' => 'VARCHAR(20) NULL',
        'padre' => 'VARCHAR(150) NULL',
        'madre' => 'VARCHAR(150) NULL',
        'tutor' => 'VARCHAR(150) NULL',
        'telefono' => 'VARCHAR(30) NULL',
        'nombre_tutor' => 'VARCHAR(150) NULL',
        'telefono_tutor' => 'VARCHAR(30) NULL',
        'direccion' => 'TEXT NULL',
        'fecha_registro' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'estudiantes', $columna, $definicion);
    }

    $conexion->query("CREATE TABLE IF NOT EXISTS citas (
        id_cita INT AUTO_INCREMENT PRIMARY KEY,
        id_estudiante INT NOT NULL,
        id_usuario INT NULL DEFAULT NULL,
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
        observaciones TEXT NULL,
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_citas_horario (fecha, hora),
        INDEX idx_citas_estudiante (id_estudiante)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query(
        "ALTER TABLE citas
         MODIFY COLUMN id_usuario INT NULL DEFAULT NULL"
    );

    asegurar_columna($conexion, 'citas', 'id_derivacion', 'INT NULL DEFAULT NULL');

    $conexion->query("CREATE TABLE IF NOT EXISTS derivaciones (
        id_derivacion INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NULL,
        id_estudiante INT NOT NULL,
        id_docente INT NOT NULL DEFAULT 0,
        id_psicologa INT NULL DEFAULT NULL,
        id_profesional INT NULL DEFAULT NULL,
        materia VARCHAR(100) NULL,
        categorias TEXT NULL,
        motivo TEXT NOT NULL,
        prioridad VARCHAR(20) NOT NULL DEFAULT 'Media',
        estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
        observaciones TEXT NULL,
        solicitar_cita TINYINT(1) NOT NULL DEFAULT 0,
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL DEFAULT NULL,
        INDEX idx_derivaciones_estudiante (id_estudiante),
        INDEX idx_derivaciones_docente (id_docente)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("CREATE TABLE IF NOT EXISTS derivacion_evidencias (
        id_evidencia INT AUTO_INCREMENT PRIMARY KEY,
        id_derivacion INT NOT NULL,
        nombre_original VARCHAR(255) NOT NULL,
        ruta VARCHAR(500) NOT NULL,
        tipo_mime VARCHAR(50) NOT NULL,
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_evidencia_derivacion (id_derivacion),
        CONSTRAINT fk_evidencia_derivacion
            FOREIGN KEY (id_derivacion) REFERENCES derivaciones(id_derivacion)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("CREATE TABLE IF NOT EXISTS historias_clinicas (
        id_historia INT AUTO_INCREMENT PRIMARY KEY,
        id_estudiante INT NOT NULL,
        id_usuario INT NULL DEFAULT NULL,
        fecha_apertura DATE NOT NULL,
        lugar_nacimiento VARCHAR(150) NULL,
        celular_estudiante VARCHAR(30) NULL,
        padre_madre VARCHAR(150) NULL,
        derivado_por VARCHAR(150) NULL,
        fecha_derivacion DATE NULL,
        tutor_curso VARCHAR(150) NULL,
        talla VARCHAR(30) NULL,
        peso VARCHAR(30) NULL,
        valoracion VARCHAR(150) NULL,
        enfermedades_actuales TEXT NULL,
        motivo_consulta TEXT NOT NULL,
        situacion_escolar VARCHAR(50) NULL,
        cursos_repetidos VARCHAR(150) NULL,
        dificultad_escolar TEXT NULL,
        materia_agrada VARCHAR(150) NULL,
        materia_desagrada VARCHAR(150) NULL,
        relacion_escolar TEXT NULL,
        antecedentes TEXT NULL,
        valoracion_familiar VARCHAR(50) NULL,
        contexto_familiar TEXT NULL,
        evaluacion_inicial TEXT NULL,
        impresion_diagnostica TEXT NULL,
        plan_intervencion TEXT NULL,
        observaciones TEXT NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'Activa',
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uk_historia_estudiante (id_estudiante),
        INDEX idx_historia_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'lugar_nacimiento' => 'VARCHAR(150) NULL',
        'celular_estudiante' => 'VARCHAR(30) NULL',
        'padre_madre' => 'VARCHAR(150) NULL',
        'derivado_por' => 'VARCHAR(150) NULL',
        'fecha_derivacion' => 'DATE NULL',
        'tutor_curso' => 'VARCHAR(150) NULL',
        'talla' => 'VARCHAR(30) NULL',
        'peso' => 'VARCHAR(30) NULL',
        'valoracion' => 'VARCHAR(150) NULL',
        'enfermedades_actuales' => 'TEXT NULL',
        'situacion_escolar' => 'VARCHAR(50) NULL',
        'cursos_repetidos' => 'VARCHAR(150) NULL',
        'dificultad_escolar' => 'TEXT NULL',
        'materia_agrada' => 'VARCHAR(150) NULL',
        'materia_desagrada' => 'VARCHAR(150) NULL',
        'relacion_escolar' => 'TEXT NULL',
        'valoracion_familiar' => 'VARCHAR(50) NULL',
        'contexto_familiar' => 'TEXT NULL',
        'evaluacion_inicial' => 'TEXT NULL',
        'fecha_registro' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'fecha_actualizacion' => 'DATETIME NULL DEFAULT NULL',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'historias_clinicas', $columna, $definicion);
    }

    $conexion->query("CREATE TABLE IF NOT EXISTS historia_opciones (
        id_opcion INT AUTO_INCREMENT PRIMARY KEY,
        id_historia INT NOT NULL,
        grupo VARCHAR(80) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        INDEX idx_opciones_historia (id_historia),
        CONSTRAINT fk_opciones_historia
            FOREIGN KEY (id_historia) REFERENCES historias_clinicas(id_historia)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("CREATE TABLE IF NOT EXISTS historia_familiares (
        id_familiar INT AUTO_INCREMENT PRIMARY KEY,
        id_historia INT NOT NULL,
        nombre VARCHAR(150) NOT NULL,
        edad INT NULL,
        relacion VARCHAR(100) NULL,
        profesion VARCHAR(150) NULL,
        ocupacion VARCHAR(150) NULL,
        observaciones TEXT NULL,
        INDEX idx_familiares_historia (id_historia),
        CONSTRAINT fk_familiares_historia
            FOREIGN KEY (id_historia) REFERENCES historias_clinicas(id_historia)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("CREATE TABLE IF NOT EXISTS seguimientos (
        id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
        id_historia INT NOT NULL,
        id_usuario INT NULL DEFAULT NULL,
        fecha DATE NOT NULL,
        evolucion TEXT NOT NULL,
        recomendaciones TEXT NULL,
        proxima_cita DATE NULL,
        INDEX idx_seguimientos_historia (id_historia),
        CONSTRAINT fk_seguimientos_historia
            FOREIGN KEY (id_historia) REFERENCES historias_clinicas(id_historia)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conexion->query("CREATE TABLE IF NOT EXISTS informes (
        id_informe INT AUTO_INCREMENT PRIMARY KEY,
        numero_ficha VARCHAR(30) NOT NULL UNIQUE,
        fecha DATE NOT NULL,
        id_estudiante INT NOT NULL,
        elaborado_por INT NULL DEFAULT NULL,
        numero_atenciones INT NOT NULL DEFAULT 0,
        referido_por VARCHAR(150) NULL,
        id_historia INT NULL DEFAULT NULL,
        id_derivacion INT NULL DEFAULT NULL,
        tipo_atencion TEXT NOT NULL,
        motivo TEXT NOT NULL,
        diagnostico TEXT NULL,
        aspecto_cognitivo TEXT NOT NULL,
        aspectos_afectivos TEXT NOT NULL,
        diagnostico_acuerdos TEXT NULL,
        recomendaciones TEXT NULL,
        recibido_por VARCHAR(150) NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'Borrador',
        fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL DEFAULT NULL,
        INDEX idx_informes_estudiante (id_estudiante),
        INDEX idx_informes_fecha (fecha),
        INDEX idx_informes_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'numero_ficha' => "VARCHAR(30) NOT NULL DEFAULT ''",
        'fecha' => 'DATE NULL',
        'id_estudiante' => 'INT NOT NULL DEFAULT 0',
        'elaborado_por' => 'INT NULL DEFAULT NULL',
        'numero_atenciones' => 'INT NOT NULL DEFAULT 0',
        'referido_por' => 'VARCHAR(150) NULL',
        'id_historia' => 'INT NULL DEFAULT NULL',
        'id_derivacion' => 'INT NULL DEFAULT NULL',
        'tipo_atencion' => 'TEXT NULL',
        'motivo' => 'TEXT NULL',
        'diagnostico' => 'TEXT NULL',
        'aspecto_cognitivo' => 'TEXT NULL',
        'aspectos_afectivos' => 'TEXT NULL',
        'diagnostico_acuerdos' => 'TEXT NULL',
        'recomendaciones' => 'TEXT NULL',
        'recibido_por' => 'VARCHAR(150) NULL',
        'estado' => "VARCHAR(30) NOT NULL DEFAULT 'Borrador'",
        'fecha_registro' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'fecha_actualizacion' => 'DATETIME NULL DEFAULT NULL',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'informes', $columna, $definicion);
    }

    foreach ([
        'fecha' => 'DATE NULL',
        'materia' => 'VARCHAR(100) NULL',
        'prioridad' => "VARCHAR(20) NOT NULL DEFAULT 'Media'",
        'categorias' => 'TEXT NULL',
        'solicitar_cita' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'id_profesional' => 'INT NULL DEFAULT NULL',
    ] as $columna => $definicion) {
        asegurar_columna($conexion, 'derivaciones', $columna, $definicion);
    }
}

asegurar_esquema($conexion);
