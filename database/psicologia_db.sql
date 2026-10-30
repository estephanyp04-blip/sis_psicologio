
SET NAMES utf8mb4;
SET UNIQUE_CHECKS = 0;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `cod_auditoria` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cod_usuario` int(10) unsigned DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(40) NOT NULL,
  `registro_id` int(10) unsigned DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_auditoria`),
  KEY `idx_auditoria_usuario` (`cod_usuario`),
  KEY `idx_auditoria_fecha` (`fecha`),
  CONSTRAINT `fk_auditoria_usuarios` FOREIGN KEY (`cod_usuario`) REFERENCES `usuarios` (`cod_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categorias_derivacion`;
CREATE TABLE `categorias_derivacion` (
  `cod_categoria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_categoria`),
  UNIQUE KEY `uk_categorias_derivacion_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorias_derivacion` VALUES (1,'Rendimiento Académico','Activo'),(2,'Acoso escolar / Acoso','Activo'),(3,'Conducta en Aula','Activo'),(4,'Dinámica Familiar','Activo'),(5,'Social / Emocional','Activo'),(6,'Otro','Activo');

DROP TABLE IF EXISTS `citas`;
CREATE TABLE `citas` (
  `cod_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_psicologa` int(10) unsigned NOT NULL,
  `cod_derivacion` int(10) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('Pendiente','Atendida','Reprogramada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `motivo` varchar(500) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `turno_reservado` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'Cancelada' then NULL else 1 end) STORED,
  PRIMARY KEY (`cod_cita`),
  UNIQUE KEY `uk_citas_psicologa_horario` (`cod_psicologa`,`fecha`,`hora`,`turno_reservado`),
  KEY `idx_citas_estudiante` (`cod_estudiante`,`fecha`),
  KEY `idx_citas_derivacion` (`cod_derivacion`),
  CONSTRAINT `fk_citas_derivaciones` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cursos`;
CREATE TABLE `cursos` (
  `cod_curso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Secundaria',
  `orden` tinyint(3) unsigned NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_curso`),
  UNIQUE KEY `uk_cursos_nombre_nivel` (`nombre`,`nivel`),
  UNIQUE KEY `uk_cursos_nivel_orden` (`nivel`,`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cursos` VALUES (1,'1ro de Secundaria','Secundaria',1,'Activo'),(2,'2do de Secundaria','Secundaria',2,'Activo'),(3,'3ro de Secundaria','Secundaria',3,'Activo'),(4,'4to de Secundaria','Secundaria',4,'Activo'),(5,'5to de Secundaria','Secundaria',5,'Activo'),(6,'6to de Secundaria','Secundaria',6,'Activo');

DROP TABLE IF EXISTS `derivacion_categorias`;
CREATE TABLE `derivacion_categorias` (
  `cod_derivacion` int(10) unsigned NOT NULL,
  `cod_categoria` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_derivacion`,`cod_categoria`),
  KEY `idx_derivacion_categorias_categoria` (`cod_categoria`),
  CONSTRAINT `fk_derivacion_categorias_categoria` FOREIGN KEY (`cod_categoria`) REFERENCES `categorias_derivacion` (`cod_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_derivacion_categorias_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `derivacion_evidencias`;
CREATE TABLE `derivacion_evidencias` (
  `cod_evidencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_derivacion` int(10) unsigned NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_evidencia`),
  KEY `idx_evidencia_derivacion` (`cod_derivacion`),
  CONSTRAINT `fk_evidencia_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `derivaciones`;
CREATE TABLE `derivaciones` (
  `cod_derivacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_docente` int(10) unsigned NOT NULL,
  `cod_materia` int(10) unsigned DEFAULT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `estado` enum('Pendiente','En seguimiento','Atendido') NOT NULL DEFAULT 'Pendiente',
  `solicitar_cita` tinyint(1) NOT NULL DEFAULT 0,
  `cod_psicologa_solicitada` int(10) unsigned DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_derivacion`),
  KEY `idx_derivaciones_estudiante` (`cod_estudiante`),
  KEY `idx_derivaciones_docente` (`cod_docente`),
  KEY `idx_derivaciones_materia` (`cod_materia`),
  KEY `idx_derivaciones_estado` (`estado`,`prioridad`,`fecha`),
  KEY `idx_derivaciones_psicologa` (`cod_psicologa_solicitada`),
  CONSTRAINT `fk_derivaciones_docentes` FOREIGN KEY (`cod_docente`) REFERENCES `docentes` (`cod_docente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_derivaciones_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_derivaciones_materias` FOREIGN KEY (`cod_materia`) REFERENCES `materias` (`cod_materia`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_derivaciones_psicologa` FOREIGN KEY (`cod_psicologa_solicitada`) REFERENCES `usuarios` (`cod_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `docente_materias`;
CREATE TABLE `docente_materias` (
  `cod_docente` int(10) unsigned NOT NULL,
  `cod_materia` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_docente`,`cod_materia`),
  KEY `idx_docente_materias_materia` (`cod_materia`),
  CONSTRAINT `fk_docente_materias_docente` FOREIGN KEY (`cod_docente`) REFERENCES `docentes` (`cod_docente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_docente_materias_materia` FOREIGN KEY (`cod_materia`) REFERENCES `materias` (`cod_materia`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `docentes`;
CREATE TABLE `docentes` (
  `cod_docente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_usuario` int(10) unsigned NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_docente`),
  UNIQUE KEY `uk_docentes_usuario` (`cod_usuario`),
  CONSTRAINT `fk_docentes_usuarios` FOREIGN KEY (`cod_usuario`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `esquema_migraciones_normalizadas`;
CREATE TABLE `esquema_migraciones_normalizadas` (
  `version` varchar(80) NOT NULL,
  `aplicada_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `esquema_migraciones_normalizadas` VALUES ('006_roles_permisos_dinamicos','2026-10-22 01:13:58'),('007_permisos_cambio_estado','2026-10-22 01:13:58'),('008_limites_permisos_datos_estudiantiles','2026-10-22 01:13:58');

DROP TABLE IF EXISTS `estudiante_responsables`;
CREATE TABLE `estudiante_responsables` (
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_responsable` int(10) unsigned NOT NULL,
  `parentesco` enum('Padre','Madre','Tutor','Otro') NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_estudiante`,`cod_responsable`,`parentesco`),
  KEY `idx_est_responsable` (`cod_responsable`),
  CONSTRAINT `fk_est_resp_estudiante` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_est_resp_responsable` FOREIGN KEY (`cod_responsable`) REFERENCES `responsables` (`cod_responsable`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `estudiantes`;
CREATE TABLE `estudiantes` (
  `cod_estudiante` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `estado` enum('Activo','Retirado') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_estudiante`),
  UNIQUE KEY `uk_estudiantes_codigo` (`codigo`),
  UNIQUE KEY `uk_estudiantes_ci` (`ci`),
  UNIQUE KEY `uk_estudiantes_rude` (`rude`),
  KEY `idx_estudiantes_nombre` (`apellidos`,`nombres`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `grupos_opciones_historia`;
CREATE TABLE `grupos_opciones_historia` (
  `cod_grupo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  PRIMARY KEY (`cod_grupo`),
  UNIQUE KEY `uk_grupos_opciones_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `grupos_opciones_historia` VALUES (1,'atencion_distraccion','Atención y distracción'),(2,'actividad_motora','Actividad motora'),(3,'dificultades_socioemocionales','Dificultades socioemocionales'),(4,'estrategias_previas','Estrategias previas'),(5,'conductas_riesgo','Conductas de riesgo'),(6,'adaptacion_normas','Adaptación a normas');

DROP TABLE IF EXISTS `historia_familiares`;
CREATE TABLE `historia_familiares` (
  `cod_familiar` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_historia` int(10) unsigned NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `edad` tinyint(3) unsigned DEFAULT NULL,
  `relacion` varchar(100) DEFAULT NULL,
  `profesion` varchar(150) DEFAULT NULL,
  `ocupacion` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`cod_familiar`),
  KEY `idx_historia_familiares_historia` (`cod_historia`),
  CONSTRAINT `fk_historia_familiares_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historia_opciones`;
CREATE TABLE `historia_opciones` (
  `cod_historia` int(10) unsigned NOT NULL,
  `cod_opcion` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_historia`,`cod_opcion`),
  KEY `idx_historia_opciones_opcion` (`cod_opcion`),
  CONSTRAINT `fk_historia_opciones_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_historia_opciones_opcion` FOREIGN KEY (`cod_opcion`) REFERENCES `opciones_historia` (`cod_opcion`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historial_academico`;
CREATE TABLE `historial_academico` (
  `cod_historial_academico` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_seccion` int(10) unsigned NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('Activo','Retirado','Finalizado') NOT NULL DEFAULT 'Activo',
  `activo` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'Activo' then 1 else NULL end) STORED,
  PRIMARY KEY (`cod_historial_academico`),
  UNIQUE KEY `uk_historial_estudiante_activo` (`cod_estudiante`,`activo`),
  KEY `idx_historial_estudiante_fecha` (`cod_estudiante`,`fecha_inicio`,`cod_historial_academico`),
  KEY `idx_historial_seccion_estado` (`cod_seccion`,`estado`),
  CONSTRAINT `fk_historial_estudiante` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_historial_seccion` FOREIGN KEY (`cod_seccion`) REFERENCES `secciones` (`cod_seccion`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historias_clinicas`;
CREATE TABLE `historias_clinicas` (
  `cod_historia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_psicologa` int(10) unsigned NOT NULL,
  `cod_derivacion_origen` int(10) unsigned DEFAULT NULL,
  `cod_cita_origen` int(10) unsigned DEFAULT NULL,
  `fecha_apertura` date NOT NULL,
  `motivo_consulta` text NOT NULL,
  `antecedentes` text DEFAULT NULL,
  `situacion_escolar` text DEFAULT NULL,
  `valoracion_familiar` text DEFAULT NULL,
  `evaluacion_inicial` text DEFAULT NULL,
  `impresion_diagnostica` text DEFAULT NULL,
  `plan_intervencion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `talla_cm` decimal(5,2) DEFAULT NULL,
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `valoracion_fisica` varchar(255) DEFAULT NULL,
  `enfermedades_actuales` text DEFAULT NULL,
  `cursos_repetidos` varchar(150) DEFAULT NULL,
  `dificultad_escolar` text DEFAULT NULL,
  `materia_agrada` varchar(150) DEFAULT NULL,
  `materia_desagrada` varchar(150) DEFAULT NULL,
  `relacion_escolar` text DEFAULT NULL,
  `contexto_familiar` text DEFAULT NULL,
  `tutor_curso` varchar(150) DEFAULT NULL,
  `estado` enum('Activa','En seguimiento','Cerrada') NOT NULL DEFAULT 'Activa',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_historia`),
  UNIQUE KEY `uk_historia_estudiante` (`cod_estudiante`),
  KEY `idx_historias_psicologa` (`cod_psicologa`),
  KEY `idx_historias_derivacion` (`cod_derivacion_origen`),
  KEY `idx_historias_cita` (`cod_cita_origen`),
  CONSTRAINT `fk_historias_cita` FOREIGN KEY (`cod_cita_origen`) REFERENCES `citas` (`cod_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_historias_derivacion` FOREIGN KEY (`cod_derivacion_origen`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_historias_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_historias_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `informe_individual`;
CREATE TABLE `informe_individual` (
  `cod_informe` int(10) unsigned NOT NULL,
  `cod_estudiante` int(10) unsigned NOT NULL,
  `cod_historia` int(10) unsigned DEFAULT NULL,
  `cod_derivacion` int(10) unsigned DEFAULT NULL,
  `cod_seguimiento` int(10) unsigned DEFAULT NULL,
  `numero_atenciones` int(10) unsigned NOT NULL DEFAULT 0,
  `referido_por` varchar(150) DEFAULT NULL,
  `tipo_atencion` text DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `aspecto_cognitivo` text DEFAULT NULL,
  `aspectos_afectivos` text DEFAULT NULL,
  `diagnostico_acuerdos` text DEFAULT NULL,
  `recibido_por` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`cod_informe`),
  KEY `idx_informe_individual_estudiante` (`cod_estudiante`),
  KEY `fk_informe_individual_historia` (`cod_historia`),
  KEY `fk_informe_individual_derivacion` (`cod_derivacion`),
  KEY `fk_informe_individual_seguimiento` (`cod_seguimiento`),
  CONSTRAINT `fk_informe_individual_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_informe_individual_estudiante` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_informe_individual_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_informe_individual_informe` FOREIGN KEY (`cod_informe`) REFERENCES `informes` (`cod_informe`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_informe_individual_seguimiento` FOREIGN KEY (`cod_seguimiento`) REFERENCES `seguimientos` (`cod_seguimiento`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `informe_resumen`;
CREATE TABLE `informe_resumen` (
  `cod_informe` int(10) unsigned NOT NULL,
  `total_estudiantes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_derivaciones` int(10) unsigned NOT NULL DEFAULT 0,
  `derivaciones_pendientes` int(10) unsigned NOT NULL DEFAULT 0,
  `derivaciones_atendidas` int(10) unsigned NOT NULL DEFAULT 0,
  `total_citas` int(10) unsigned NOT NULL DEFAULT 0,
  `citas_atendidas` int(10) unsigned NOT NULL DEFAULT 0,
  `total_historias` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_informe`),
  CONSTRAINT `fk_informe_resumen_informe` FOREIGN KEY (`cod_informe`) REFERENCES `informes` (`cod_informe`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `informes`;
CREATE TABLE `informes` (
  `cod_informe` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero_ficha` varchar(30) NOT NULL,
  `cod_elaborado_por` int(10) unsigned NOT NULL,
  `tipo` enum('Mensual','Trimestral','Anual','Individual','General') NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `conclusiones` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `estado` enum('Borrador','Finalizado') NOT NULL DEFAULT 'Borrador',
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_informe`),
  UNIQUE KEY `uk_informes_numero_ficha` (`numero_ficha`),
  KEY `idx_informes_elaborado_por` (`cod_elaborado_por`),
  CONSTRAINT `fk_informes_elaborado_por` FOREIGN KEY (`cod_elaborado_por`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `informes_secuencia`;
CREATE TABLE `informes_secuencia` (
  `cod_secuencia` tinyint(3) unsigned NOT NULL,
  `ultimo` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_secuencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `informes_secuencia` VALUES (1,0);

DROP TABLE IF EXISTS `materias`;
CREATE TABLE `materias` (
  `cod_materia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_materia`),
  UNIQUE KEY `uk_materias_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materias` VALUES (1,'Ciencias Sociales','Activo'),(2,'Matemática','Activo'),(3,'Biología','Activo'),(4,'Inglés','Activo'),(5,'Lenguaje','Activo'),(6,'Educación Física','Activo'),(7,'Física','Activo'),(8,'Química','Activo'),(9,'Tecnología','Activo'),(10,'Religión','Activo');

DROP TABLE IF EXISTS `modulos`;
CREATE TABLE `modulos` (
  `cod_modulo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `ruta` varchar(100) DEFAULT NULL,
  `icono` varchar(60) DEFAULT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_modulo`),
  UNIQUE KEY `uk_modulos_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modulos` VALUES (1,'Dashboard','/index.php','bi-speedometer2',1,'Activo'),(2,'Estudiantes','/estudiantes/listar.php','bi-people-fill',2,'Activo'),(3,'Derivaciones','/derivaciones/listar.php','bi-send',3,'Activo'),(4,'Citas','/citas/listar.php','bi-calendar-check',4,'Activo'),(5,'Historias clinicas','/historias_clinicas/listar.php','bi-folder2-open',5,'Activo'),(6,'Informes','/informes/listar.php','bi-file-earmark-bar-graph',6,'Activo'),(7,'Usuarios','/usuarios/listar.php','bi-person-gear',7,'Activo'),(8,'Roles y permisos','/roles/listar.php','bi-shield-lock',8,'Activo'),(9,'Docentes','/docentes/listar.php','bi-person-badge-fill',9,'Activo'),(10,'Seguimientos','/seguimientos/listar.php','bi-journal-medical',10,'Activo'),(11,'Estadísticas','/estadisticas/index.php','bi-bar-chart-line-fill',11,'Activo');

DROP TABLE IF EXISTS `opciones_historia`;
CREATE TABLE `opciones_historia` (
  `cod_opcion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_grupo` int(10) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_opcion`),
  UNIQUE KEY `uk_opcion_grupo_descripcion` (`cod_grupo`,`descripcion`),
  CONSTRAINT `fk_opciones_historia_grupo` FOREIGN KEY (`cod_grupo`) REFERENCES `grupos_opciones_historia` (`cod_grupo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `opciones_historia` VALUES (1,1,'Se distrae mirando a cualquier lado','Activo'),(2,1,'Se olvida sus carpetas o tareas','Activo'),(3,1,'Habla constantemente','Activo'),(4,2,'Se mueve constantemente en su asiento','Activo'),(5,3,'Falta de interés por aprender','Activo'),(6,4,'Conversación con el tutor/a a cargo','Activo'),(7,4,'Mayor supervisión','Activo'),(8,2,'Se para y se sienta constantemente','Activo'),(9,4,'Ninguna','Activo'),(10,3,'No participa en las actividades','Activo'),(11,1,'Se equivoca por descuido','Activo'),(12,1,'No termina de hacer tareas, se atrasa','Activo'),(13,1,'Lenguaje inapropiado / conducta obscena','Activo'),(14,3,'Se le observa triste, deprimido, desganado','Activo'),(15,3,'Cambia de humor frecuentemente','Activo'),(16,3,'Prefiere estar solo(a)','Activo'),(17,3,'Se calla y aguanta si lo molestan','Activo'),(18,3,'Tartamudea / habla nerviosamente','Activo'),(19,3,'Se muerde / come las uñas','Activo'),(20,3,'Duda al expresar su opinión','Activo'),(21,3,'Se mantiene callado','Activo'),(22,4,'Conversación con él/ella','Activo'),(23,4,'Llamadas de atención','Activo'),(24,4,'Premios y sanciones','Activo'),(25,5,'Conducta delictiva','Activo'),(26,5,'Pandillaje','Activo'),(27,5,'Sospecha de consumo de alcohol/drogas','Activo'),(28,5,'Problema sexual','Activo'),(29,6,'No obedece el reglamento del Colegio','Activo'),(30,6,'Le es difícil seguir indicaciones - protesta','Activo'),(31,6,'No cumple normas establecidas','Activo'),(32,6,'Propicia el desorden','Activo'),(33,6,'Miente','Activo'),(34,6,'Agrede de manera verbal','Activo'),(35,6,'Agrede de manera física','Activo'),(36,6,'Llama por apodos / Bullying','Activo'),(37,6,'Sus compañeros lo rechazan','Activo');

DROP TABLE IF EXISTS `paralelos`;
CREATE TABLE `paralelos` (
  `cod_paralelo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_paralelo`),
  UNIQUE KEY `uk_paralelos_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `paralelos` VALUES (1,'A','Activo'),(2,'B','Activo'),(3,'C','Activo'),(4,'D','Activo');

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE `permisos` (
  `cod_permiso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_rol` int(10) unsigned NOT NULL,
  `cod_modulo` int(10) unsigned NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear` tinyint(1) NOT NULL DEFAULT 0,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_cambiar_estado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_permiso`),
  UNIQUE KEY `uk_permiso_rol_modulo` (`cod_rol`,`cod_modulo`),
  KEY `idx_permisos_modulo` (`cod_modulo`),
  CONSTRAINT `fk_permisos_modulos` FOREIGN KEY (`cod_modulo`) REFERENCES `modulos` (`cod_modulo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permisos_roles` FOREIGN KEY (`cod_rol`) REFERENCES `roles` (`cod_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permisos` VALUES (1,1,1,1,1,1,1,0),(2,1,2,1,1,1,1,1),(3,1,3,1,1,1,1,1),(4,1,4,1,1,1,1,1),(5,1,5,1,1,1,1,0),(6,1,6,1,1,1,1,0),(7,1,7,1,1,1,1,1),(8,1,8,1,1,1,1,0),(9,2,1,1,0,0,0,0),(10,2,2,1,1,1,0,1),(11,2,3,1,0,0,0,1),(12,2,4,1,1,1,0,1),(13,2,5,1,1,1,0,0),(14,2,6,1,1,1,0,0),(15,3,1,0,0,0,0,0),(16,3,2,0,0,0,0,0),(17,3,3,1,1,1,1,0),(18,4,1,0,0,0,0,0),(19,4,6,1,0,0,0,0),(20,1,9,1,1,1,1,0),(21,1,10,1,1,1,1,0),(22,1,11,1,1,1,1,0),(23,2,10,1,1,1,0,0),(24,2,11,1,0,0,0,0);

DROP TABLE IF EXISTS `responsables`;
CREATE TABLE `responsables` (
  `cod_responsable` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(150) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`cod_responsable`),
  KEY `idx_responsables_nombre` (`nombres`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `cod_rol` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_rol`),
  UNIQUE KEY `uk_roles_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` VALUES (1,'Administrador','Acceso total al sistema','Activo','2026-10-06 05:12:57'),(2,'Psicóloga','Gestiona derivaciones, citas, historias clínicas, seguimientos e informes','Activo','2026-10-06 05:12:57'),(3,'Docente','Consulta estudiantes y registra derivaciones','Activo','2026-10-06 05:12:57'),(4,'Director','Consulta informes y estadísticas autorizadas','Activo','2026-10-06 05:12:57');

DROP TABLE IF EXISTS `secciones`;
CREATE TABLE `secciones` (
  `cod_seccion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_curso` int(10) unsigned NOT NULL,
  `cod_paralelo` int(10) unsigned NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `gestion` year(4) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_seccion`),
  UNIQUE KEY `uk_seccion` (`cod_curso`,`cod_paralelo`,`turno`,`gestion`),
  KEY `idx_secciones_curso` (`cod_curso`),
  KEY `idx_secciones_paralelo` (`cod_paralelo`),
  CONSTRAINT `fk_secciones_cursos` FOREIGN KEY (`cod_curso`) REFERENCES `cursos` (`cod_curso`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_paralelos` FOREIGN KEY (`cod_paralelo`) REFERENCES `paralelos` (`cod_paralelo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `secciones` VALUES (1,1,1,'Mañana',2026,'Activo'),(2,1,1,'Tarde',2026,'Activo'),(3,1,2,'Mañana',2026,'Activo'),(4,1,2,'Tarde',2026,'Activo'),(5,1,3,'Mañana',2026,'Activo'),(6,1,3,'Tarde',2026,'Activo'),(7,1,4,'Mañana',2026,'Activo'),(8,1,4,'Tarde',2026,'Activo'),(9,2,1,'Mañana',2026,'Activo'),(10,2,1,'Tarde',2026,'Activo'),(11,2,2,'Mañana',2026,'Activo'),(12,2,2,'Tarde',2026,'Activo'),(13,2,3,'Mañana',2026,'Activo'),(14,2,3,'Tarde',2026,'Activo'),(15,2,4,'Mañana',2026,'Activo'),(16,2,4,'Tarde',2026,'Activo'),(17,3,1,'Mañana',2026,'Activo'),(18,3,1,'Tarde',2026,'Activo'),(19,3,2,'Mañana',2026,'Activo'),(20,3,2,'Tarde',2026,'Activo'),(21,3,3,'Mañana',2026,'Activo'),(22,3,3,'Tarde',2026,'Activo'),(23,3,4,'Mañana',2026,'Activo'),(24,3,4,'Tarde',2026,'Activo'),(25,4,1,'Mañana',2026,'Activo'),(26,4,1,'Tarde',2026,'Activo'),(27,4,2,'Mañana',2026,'Activo'),(28,4,2,'Tarde',2026,'Activo'),(29,4,3,'Mañana',2026,'Activo'),(30,4,3,'Tarde',2026,'Activo'),(31,4,4,'Mañana',2026,'Activo'),(32,4,4,'Tarde',2026,'Activo'),(33,5,1,'Mañana',2026,'Activo'),(34,5,1,'Tarde',2026,'Activo'),(35,5,2,'Mañana',2026,'Activo'),(36,5,2,'Tarde',2026,'Activo'),(37,5,3,'Mañana',2026,'Activo'),(38,5,3,'Tarde',2026,'Activo'),(39,5,4,'Mañana',2026,'Activo'),(40,5,4,'Tarde',2026,'Activo'),(41,6,1,'Mañana',2026,'Activo'),(42,6,1,'Tarde',2026,'Activo'),(43,6,2,'Mañana',2026,'Activo'),(44,6,2,'Tarde',2026,'Activo'),(45,6,3,'Mañana',2026,'Activo'),(46,6,3,'Tarde',2026,'Activo'),(47,6,4,'Mañana',2026,'Activo'),(48,6,4,'Tarde',2026,'Activo');

DROP TABLE IF EXISTS `seguimientos`;
CREATE TABLE `seguimientos` (
  `cod_seguimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_historia` int(10) unsigned NOT NULL,
  `cod_psicologa` int(10) unsigned NOT NULL,
  `cod_cita` int(10) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `descripcion` text NOT NULL,
  `tecnicas_aplicadas` text DEFAULT NULL,
  `acuerdos` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `proxima_sesion` date DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_seguimiento`),
  KEY `idx_seguimientos_historia` (`cod_historia`,`fecha`),
  KEY `idx_seguimientos_psicologa` (`cod_psicologa`),
  KEY `idx_seguimientos_cita` (`cod_cita`),
  CONSTRAINT `fk_seguimientos_citas` FOREIGN KEY (`cod_cita`) REFERENCES `citas` (`cod_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_seguimientos_historias` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seguimientos_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `cod_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(80) NOT NULL DEFAULT '',
  `apellidos` varchar(80) NOT NULL DEFAULT '',
  `ci` varchar(20) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `usuario` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `cod_rol` int(10) unsigned NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cod_usuario`),
  UNIQUE KEY `uk_usuarios_usuario` (`usuario`),
  UNIQUE KEY `uk_usuarios_ci` (`ci`),
  UNIQUE KEY `uk_usuarios_correo` (`correo`),
  KEY `idx_usuarios_rol` (`cod_rol`),
  KEY `idx_usuarios_nombre` (`apellidos`,`nombres`),
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`cod_rol`) REFERENCES `roles` (`cod_rol`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES (1,'Administrador','Sistema',NULL,'admin@local.test',NULL,'admin','$2y$10$6pccIPxthNDrSsFm03L5TO501JshAvm16BK5E4mSbiB6mTm3cPDcy',1,'Activo',NULL,'2026-10-06 05:12:57','2026-10-22 01:13:58');

DROP VIEW IF EXISTS `vista_derivaciones`;

DROP VIEW IF EXISTS `vista_citas`;

DROP VIEW IF EXISTS `vista_estudiantes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_citas` AS select `c`.`cod_cita` AS `cod_cita`,`c`.`cod_estudiante` AS `cod_estudiante`,`c`.`cod_psicologa` AS `cod_psicologa`,`c`.`cod_derivacion` AS `cod_derivacion`,`c`.`fecha` AS `fecha`,`c`.`hora` AS `hora`,`c`.`estado` AS `estado`,`c`.`observaciones` AS `observaciones`,`c`.`motivo` AS `motivo`,`c`.`fecha_registro` AS `fecha_registro`,`c`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,concat(`u`.`nombres`,' ',`u`.`apellidos`) AS `psicologa` from ((`citas` `c` join `estudiantes` `e` on(`e`.`cod_estudiante` = `c`.`cod_estudiante`)) join `usuarios` `u` on(`u`.`cod_usuario` = `c`.`cod_psicologa`));

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_estudiantes` AS select `e`.`cod_estudiante` AS `cod_estudiante`,`e`.`codigo` AS `codigo`,`e`.`ci` AS `ci`,`e`.`rude` AS `rude`,`e`.`nombres` AS `nombres`,`e`.`apellidos` AS `apellidos`,`e`.`fecha_nacimiento` AS `fecha_nacimiento`,`e`.`lugar_nacimiento` AS `lugar_nacimiento`,`e`.`sexo` AS `sexo`,`e`.`direccion` AS `direccion`,`e`.`telefono` AS `telefono`,`e`.`estado` AS `estado`,`s`.`cod_seccion` AS `cod_seccion`,`c`.`cod_curso` AS `cod_curso`,`c`.`nombre` AS `curso`,`p`.`cod_paralelo` AS `cod_paralelo`,`p`.`nombre` AS `paralelo`,`s`.`turno` AS `turno`,`s`.`gestion` AS `gestion` from (((((`estudiantes` `e` left join `historial_academico` `ha` on(`ha`.`cod_estudiante` = `e`.`cod_estudiante` and `ha`.`estado` = 'Activo')) left join `secciones` `s` on(`s`.`cod_seccion` = `ha`.`cod_seccion`)) left join `cursos` `c` on(`c`.`cod_curso` = `s`.`cod_curso`)) left join `paralelos` `p` on(`p`.`cod_paralelo` = `s`.`cod_paralelo`)));

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_derivaciones` AS select `d`.`cod_derivacion` AS `cod_derivacion`,`d`.`fecha` AS `fecha`,`d`.`cod_estudiante` AS `cod_estudiante`,`d`.`cod_docente` AS `cod_docente`,`d`.`cod_materia` AS `cod_materia`,`m`.`nombre` AS `materia`,`d`.`motivo` AS `motivo`,`d`.`observaciones` AS `observaciones`,`d`.`prioridad` AS `prioridad`,`d`.`estado` AS `estado`,`d`.`solicitar_cita` AS `solicitar_cita`,`d`.`cod_psicologa_solicitada` AS `cod_psicologa_solicitada`,`d`.`fecha_registro` AS `fecha_registro`,`d`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,`ve`.`curso` AS `curso`,`ve`.`paralelo` AS `paralelo`,`ve`.`turno` AS `turno`,concat(`u`.`nombres`,' ',`u`.`apellidos`) AS `docente` from (((((`derivaciones` `d` join `estudiantes` `e` on(`e`.`cod_estudiante` = `d`.`cod_estudiante`)) join `docentes` `doc` on(`doc`.`cod_docente` = `d`.`cod_docente`)) join `usuarios` `u` on(`u`.`cod_usuario` = `doc`.`cod_usuario`)) left join `materias` `m` on(`m`.`cod_materia` = `d`.`cod_materia`)) left join `vista_estudiantes` `ve` on(`ve`.`cod_estudiante` = `e`.`cod_estudiante`));

SET UNIQUE_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 1;
