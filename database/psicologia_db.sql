-- Esquema canónico, versión 001_alinear_esquema. Sin datos personales.
-- Importar únicamente en una base vacía. Ver database/README.md.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `auditoria` (
  `id_auditoria` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(40) NOT NULL,
  `registro_id` int(10) unsigned DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_auditoria`),
  KEY `fk_auditoria_usuarios` (`id_usuario`),
  CONSTRAINT `fk_auditoria_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citas` (
  `id_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `id_derivacion` int(10) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('Pendiente','Atendida','Reprogramada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `motivo` varchar(500) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  UNIQUE KEY `uk_cita_profesional_fecha_hora` (`id_usuario`,`fecha`,`hora`),
  KEY `idx_citas_estudiante` (`id_estudiante`,`fecha`),
  KEY `fk_citas_derivaciones` (`id_derivacion`),
  CONSTRAINT `fk_citas_derivaciones` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cursos` (
  `id_curso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Secundaria',
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_curso`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `derivacion_evidencias` (
  `id_evidencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_derivacion` int(10) unsigned NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(50) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_evidencia`),
  KEY `idx_evidencia_derivacion` (`id_derivacion`),
  CONSTRAINT `fk_evidencia_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `derivaciones` (
  `id_derivacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_estudiante` int(10) unsigned NOT NULL,
  `id_docente` int(10) unsigned NOT NULL,
  `materia` varchar(150) DEFAULT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `estado` enum('Pendiente','En seguimiento','Atendido') NOT NULL DEFAULT 'Pendiente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `categorias` text DEFAULT NULL,
  `solicitar_cita` tinyint(1) NOT NULL DEFAULT 0,
  `id_profesional` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_derivacion`),
  KEY `idx_derivaciones_estado` (`estado`,`prioridad`,`fecha`),
  KEY `fk_derivaciones_estudiantes` (`id_estudiante`),
  KEY `fk_derivaciones_docentes` (`id_docente`),
  CONSTRAINT `fk_derivaciones_docentes` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_derivaciones_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `docentes` (
  `id_docente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `nombres` varchar(60) NOT NULL,
  `apellidos` varchar(60) NOT NULL,
  `ci` varchar(20) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `materias` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `materia` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_docente`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  UNIQUE KEY `ci` (`ci`),
  CONSTRAINT `fk_docentes_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `estudiantes` (
  `id_estudiante` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `ci` varchar(20) DEFAULT NULL,
  `rude` varchar(30) DEFAULT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `lugar_nacimiento` varchar(100) DEFAULT NULL,
  `sexo` enum('M','F') DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `celular` varchar(25) DEFAULT NULL,
  `nombre_padre` varchar(120) DEFAULT NULL,
  `telefono_padre` varchar(25) DEFAULT NULL,
  `nombre_madre` varchar(120) DEFAULT NULL,
  `telefono_madre` varchar(25) DEFAULT NULL,
  `nombre_tutor` varchar(120) DEFAULT NULL,
  `telefono_tutor` varchar(25) DEFAULT NULL,
  `id_curso` int(10) unsigned NOT NULL,
  `id_paralelo` int(10) unsigned NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL DEFAULT 'Mañana',
  `estado` enum('Activo','Retirado') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `genero` varchar(20) DEFAULT NULL,
  `padre` varchar(150) DEFAULT NULL,
  `madre` varchar(150) DEFAULT NULL,
  `tutor` varchar(150) DEFAULT NULL,
  `curso` varchar(10) DEFAULT NULL,
  `paralelo` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_estudiante`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `ci` (`ci`),
  UNIQUE KEY `rude` (`rude`),
  KEY `idx_estudiantes_nombre` (`apellidos`,`nombres`),
  KEY `idx_estudiantes_curso` (`id_curso`,`id_paralelo`,`turno`,`estado`),
  KEY `fk_estudiantes_paralelos` (`id_paralelo`),
  CONSTRAINT `fk_estudiantes_cursos` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiantes_paralelos` FOREIGN KEY (`id_paralelo`) REFERENCES `paralelos` (`id_paralelo`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `historia_familiares` (
  `id_familiar` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_historia` int(10) unsigned NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `relacion` varchar(100) DEFAULT NULL,
  `profesion` varchar(150) DEFAULT NULL,
  `ocupacion` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_familiar`),
  KEY `idx_familiares_historia` (`id_historia`),
  CONSTRAINT `fk_familiares_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `historia_opciones` (
  `id_opcion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_historia` int(10) unsigned NOT NULL,
  `grupo` varchar(80) NOT NULL,
  `valor` varchar(255) NOT NULL,
  PRIMARY KEY (`id_opcion`),
  KEY `idx_opciones_historia` (`id_historia`),
  CONSTRAINT `fk_opciones_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `historias_clinicas` (
  `id_historia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL COMMENT 'Psicologa responsable',
  `id_derivacion` int(11) unsigned DEFAULT NULL,
  `fecha_apertura` date NOT NULL,
  `motivo_consulta` text NOT NULL,
  `antecedentes` text DEFAULT NULL,
  `situacion_escolar` text DEFAULT NULL,
  `valoracion_familiar` text DEFAULT NULL,
  `evaluacion_inicial` text DEFAULT NULL,
  `impresion_diagnostica` text DEFAULT NULL,
  `plan_intervencion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('Activa','En seguimiento','Cerrada') NOT NULL DEFAULT 'Activa',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lugar_nacimiento` varchar(150) DEFAULT NULL,
  `celular_estudiante` varchar(30) DEFAULT NULL,
  `padre_madre` varchar(150) DEFAULT NULL,
  `derivado_por` varchar(150) DEFAULT NULL,
  `fecha_derivacion` date DEFAULT NULL,
  `tutor_curso` varchar(150) DEFAULT NULL,
  `talla` varchar(30) DEFAULT NULL,
  `peso` varchar(30) DEFAULT NULL,
  `valoracion` varchar(150) DEFAULT NULL,
  `enfermedades_actuales` text DEFAULT NULL,
  `cursos_repetidos` varchar(150) DEFAULT NULL,
  `dificultad_escolar` text DEFAULT NULL,
  `materia_agrada` varchar(150) DEFAULT NULL,
  `materia_desagrada` varchar(150) DEFAULT NULL,
  `relacion_escolar` text DEFAULT NULL,
  `contexto_familiar` text DEFAULT NULL,
  PRIMARY KEY (`id_historia`),
  UNIQUE KEY `id_estudiante` (`id_estudiante`),
  KEY `fk_historias_usuarios` (`id_usuario`),
  KEY `fk_historia_derivacion` (`id_derivacion`),
  CONSTRAINT `fk_historia_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_historias_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_historias_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `informes` (
  `id_informe` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL COMMENT 'Usuario que genera el informe',
  `tipo` enum('Mensual','Trimestral','Anual','Individual','General') NOT NULL DEFAULT 'Mensual',
  `titulo` varchar(180) NOT NULL,
  `mes` tinyint(3) unsigned DEFAULT NULL,
  `gestion` year(4) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `total_estudiantes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_derivaciones` int(10) unsigned NOT NULL DEFAULT 0,
  `derivaciones_pendientes` int(10) unsigned NOT NULL DEFAULT 0,
  `derivaciones_atendidas` int(10) unsigned NOT NULL DEFAULT 0,
  `total_citas` int(10) unsigned NOT NULL DEFAULT 0,
  `citas_atendidas` int(10) unsigned NOT NULL DEFAULT 0,
  `total_historias` int(10) unsigned NOT NULL DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `conclusiones` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `estado` enum('Borrador','Finalizado') NOT NULL DEFAULT 'Borrador',
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `numero_ficha` varchar(30) NOT NULL DEFAULT '',
  `fecha` date DEFAULT NULL,
  `id_estudiante` int(10) unsigned NOT NULL DEFAULT 0,
  `elaborado_por` int(10) unsigned DEFAULT NULL,
  `numero_atenciones` int(11) NOT NULL DEFAULT 0,
  `referido_por` varchar(150) DEFAULT NULL,
  `id_historia` int(10) unsigned DEFAULT NULL,
  `id_derivacion` int(10) unsigned DEFAULT NULL,
  `tipo_atencion` text DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `aspecto_cognitivo` text DEFAULT NULL,
  `aspectos_afectivos` text DEFAULT NULL,
  `diagnostico_acuerdos` text DEFAULT NULL,
  `recibido_por` varchar(150) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_informe`),
  KEY `fk_informes_usuarios` (`id_usuario`),
  CONSTRAINT `fk_informes_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `chk_informes_mes` CHECK (`mes` is null or `mes` between 1 and 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modulos` (
  `id_modulo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `ruta` varchar(100) DEFAULT NULL,
  `icono` varchar(60) DEFAULT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_modulo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `paralelos` (
  `id_paralelo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_curso` int(10) unsigned NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_paralelo`),
  UNIQUE KEY `uk_curso_paralelo` (`id_curso`,`nombre`),
  CONSTRAINT `fk_paralelos_cursos` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permisos` (
  `id_permiso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_rol` int(10) unsigned NOT NULL,
  `id_modulo` int(10) unsigned NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear` tinyint(1) NOT NULL DEFAULT 0,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `uk_permiso_rol_modulo` (`id_rol`,`id_modulo`),
  KEY `fk_permisos_modulos` (`id_modulo`),
  CONSTRAINT `fk_permisos_modulos` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permisos_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id_rol` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seguimientos` (
  `id_seguimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_historia` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_cita` int(10) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `descripcion` text NOT NULL,
  `tecnicas_aplicadas` text DEFAULT NULL,
  `acuerdos` text DEFAULT NULL,
  `proxima_sesion` date DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_seguimiento`),
  KEY `fk_seguimientos_historias` (`id_historia`),
  KEY `fk_seguimientos_usuarios` (`id_usuario`),
  KEY `fk_seguimientos_citas` (`id_cita`),
  CONSTRAINT `fk_seguimientos_citas` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_seguimientos_historias` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seguimientos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `apellido` varchar(60) NOT NULL,
  `usuario` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `id_rol` int(10) unsigned NOT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_usuarios_roles` (`id_rol`),
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (id_rol,nombre,estado) VALUES ('1','Administrador','Activo');
INSERT INTO `roles` (id_rol,nombre,estado) VALUES ('2','Psicóloga','Activo');
INSERT INTO `roles` (id_rol,nombre,estado) VALUES ('3','Docente','Activo');
INSERT INTO `roles` (id_rol,nombre,estado) VALUES ('4','Director','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('1','1ro de Secundaria','Secundaria','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('2','2do de Secundaria','Secundaria','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('3','3ro de Secundaria','Secundaria','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('4','4to de Secundaria','Secundaria','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('5','5to de Secundaria','Secundaria','Activo');
INSERT INTO `cursos` (id_curso,nombre,nivel,estado) VALUES ('6','6to de Secundaria','Secundaria','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('1','1','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('2','1','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('3','1','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('4','1','D','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('5','2','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('6','2','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('7','2','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('8','2','D','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('9','3','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('10','3','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('11','3','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('12','3','D','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('13','4','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('14','4','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('15','4','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('16','4','D','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('17','5','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('18','5','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('19','5','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('20','5','D','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('21','6','A','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('22','6','B','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('23','6','C','Activo');
INSERT INTO `paralelos` (id_paralelo,id_curso,nombre,estado) VALUES ('24','6','D','Activo');
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vista_citas` AS select `ci`.`id_cita` AS `id_cita`,`ci`.`id_estudiante` AS `id_estudiante`,`ci`.`id_usuario` AS `id_usuario`,`ci`.`id_derivacion` AS `id_derivacion`,`ci`.`fecha` AS `fecha`,`ci`.`hora` AS `hora`,`ci`.`estado` AS `estado`,`ci`.`observaciones` AS `observaciones`,`ci`.`motivo` AS `motivo`,`ci`.`fecha_registro` AS `fecha_registro`,`ci`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,concat(`u`.`nombre`,' ',`u`.`apellido`) AS `psicologa` from ((`citas` `ci` join `estudiantes` `e` on(`e`.`id_estudiante` = `ci`.`id_estudiante`)) join `usuarios` `u` on(`u`.`id_usuario` = `ci`.`id_usuario`));

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vista_derivaciones` AS select `d`.`id_derivacion` AS `id_derivacion`,`d`.`fecha` AS `fecha`,`d`.`id_estudiante` AS `id_estudiante`,`d`.`id_docente` AS `id_docente`,`d`.`materia` AS `materia`,`d`.`motivo` AS `motivo`,`d`.`observaciones` AS `observaciones`,`d`.`prioridad` AS `prioridad`,`d`.`estado` AS `estado`,`d`.`fecha_registro` AS `fecha_registro`,`d`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,`c`.`nombre` AS `curso`,`p`.`nombre` AS `paralelo`,concat(`doc`.`apellidos`,' ',`doc`.`nombres`) AS `docente` from ((((`derivaciones` `d` join `estudiantes` `e` on(`e`.`id_estudiante` = `d`.`id_estudiante`)) join `cursos` `c` on(`c`.`id_curso` = `e`.`id_curso`)) join `paralelos` `p` on(`p`.`id_paralelo` = `e`.`id_paralelo`)) join `docentes` `doc` on(`doc`.`id_docente` = `d`.`id_docente`));

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vista_estudiantes` AS select `e`.`id_estudiante` AS `id_estudiante`,`e`.`codigo` AS `codigo`,`e`.`ci` AS `ci`,`e`.`rude` AS `rude`,`e`.`nombres` AS `nombres`,`e`.`apellidos` AS `apellidos`,`e`.`fecha_nacimiento` AS `fecha_nacimiento`,`e`.`lugar_nacimiento` AS `lugar_nacimiento`,`e`.`sexo` AS `sexo`,`e`.`direccion` AS `direccion`,`e`.`telefono` AS `telefono`,`e`.`celular` AS `celular`,`e`.`nombre_padre` AS `nombre_padre`,`e`.`telefono_padre` AS `telefono_padre`,`e`.`nombre_madre` AS `nombre_madre`,`e`.`telefono_madre` AS `telefono_madre`,`e`.`nombre_tutor` AS `nombre_tutor`,`e`.`telefono_tutor` AS `telefono_tutor`,`e`.`id_curso` AS `id_curso`,`e`.`id_paralelo` AS `id_paralelo`,`e`.`turno` AS `turno`,`e`.`estado` AS `estado`,`e`.`fecha_registro` AS `fecha_registro`,`e`.`fecha_actualizacion` AS `fecha_actualizacion`,`c`.`nombre` AS `curso`,`p`.`nombre` AS `paralelo` from ((`estudiantes` `e` join `cursos` `c` on(`c`.`id_curso` = `e`.`id_curso`)) join `paralelos` `p` on(`p`.`id_paralelo` = `e`.`id_paralelo`));

SET FOREIGN_KEY_CHECKS=1;

-- Actualización 001
ALTER TABLE seguimientos ADD COLUMN recomendaciones TEXT NULL;
ALTER TABLE historias_clinicas ADD COLUMN evolucion_caso TINYINT UNSIGNED NULL;
ALTER TABLE informes ADD UNIQUE KEY uk_informes_ficha (numero_ficha);
ALTER TABLE informes ADD CONSTRAINT `fk_informes_id_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE;
ALTER TABLE informes ADD CONSTRAINT `fk_informes_elaborado_por` FOREIGN KEY (`elaborado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
ALTER TABLE informes ADD CONSTRAINT `fk_informes_id_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON UPDATE CASCADE;
ALTER TABLE informes ADD CONSTRAINT `fk_informes_id_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON UPDATE CASCADE;
CREATE TABLE informes_secuencia (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, ultimo BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO informes_secuencia (id, ultimo) SELECT 1, COALESCE(MAX(CAST(SUBSTRING(numero_ficha, 5) AS UNSIGNED)), 0) FROM informes WHERE numero_ficha REGEXP '^INF-[0-9]+$';
CREATE TABLE esquema_migraciones (version VARCHAR(80) NOT NULL PRIMARY KEY, aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO esquema_migraciones (version) VALUES ('001_alinear_esquema');

-- Actualización 002: vínculos explícitos y reserva atómica de la agenda única.
ALTER TABLE citas ADD turno_reservado TINYINT GENERATED ALWAYS AS (CASE WHEN estado='Cancelada' THEN NULL ELSE 1 END) STORED;
ALTER TABLE citas ADD UNIQUE KEY uk_citas_horario_vigente (fecha,hora,turno_reservado);
ALTER TABLE citas ADD INDEX idx_citas_usuario (id_usuario);
ALTER TABLE citas DROP INDEX uk_cita_profesional_fecha_hora;
ALTER TABLE historias_clinicas ADD id_cita INT UNSIGNED NULL;
ALTER TABLE historias_clinicas ADD CONSTRAINT fk_historias_clinicas_id_cita_origen FOREIGN KEY (id_cita) REFERENCES citas (id_cita) ON UPDATE CASCADE;
ALTER TABLE informes ADD id_seguimiento INT UNSIGNED NULL;
ALTER TABLE informes ADD CONSTRAINT fk_informes_id_seguimiento_origen FOREIGN KEY (id_seguimiento) REFERENCES seguimientos (id_seguimiento) ON UPDATE CASCADE;
INSERT INTO esquema_migraciones (version) VALUES ('002_trazabilidad');
