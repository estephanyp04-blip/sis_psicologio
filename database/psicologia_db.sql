-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: psicologia_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;

--
-- Table structure for table `categorias_derivacion`
--

DROP TABLE IF EXISTS `categorias_derivacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias_derivacion` (
  `cod_categoria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_categoria`),
  UNIQUE KEY `uk_categorias_derivacion_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_derivacion`
--

/*!40000 ALTER TABLE `categorias_derivacion` DISABLE KEYS */;
INSERT INTO `categorias_derivacion` VALUES (1,'Rendimiento Académico','Activo'),(2,'Acoso escolar / Acoso','Activo'),(3,'Conducta en Aula','Activo'),(4,'Dinámica Familiar','Activo'),(5,'Social / Emocional','Activo'),(6,'Otro','Activo');
/*!40000 ALTER TABLE `categorias_derivacion` ENABLE KEYS */;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;

--
-- Table structure for table `cursos`
--

DROP TABLE IF EXISTS `cursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cursos`
--

/*!40000 ALTER TABLE `cursos` DISABLE KEYS */;
INSERT INTO `cursos` VALUES (1,'1ro de Secundaria','Secundaria',1,'Activo'),(2,'2do de Secundaria','Secundaria',2,'Activo'),(3,'3ro de Secundaria','Secundaria',3,'Activo'),(4,'4to de Secundaria','Secundaria',4,'Activo'),(5,'5to de Secundaria','Secundaria',5,'Activo'),(6,'6to de Secundaria','Secundaria',6,'Activo');
/*!40000 ALTER TABLE `cursos` ENABLE KEYS */;

--
-- Table structure for table `derivacion_categorias`
--

DROP TABLE IF EXISTS `derivacion_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `derivacion_categorias` (
  `cod_derivacion` int(10) unsigned NOT NULL,
  `cod_categoria` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_derivacion`,`cod_categoria`),
  KEY `idx_derivacion_categorias_categoria` (`cod_categoria`),
  CONSTRAINT `fk_derivacion_categorias_categoria` FOREIGN KEY (`cod_categoria`) REFERENCES `categorias_derivacion` (`cod_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_derivacion_categorias_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `derivacion_categorias`
--

/*!40000 ALTER TABLE `derivacion_categorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `derivacion_categorias` ENABLE KEYS */;

--
-- Table structure for table `derivacion_evidencias`
--

DROP TABLE IF EXISTS `derivacion_evidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `derivacion_evidencias`
--

/*!40000 ALTER TABLE `derivacion_evidencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `derivacion_evidencias` ENABLE KEYS */;

--
-- Table structure for table `derivaciones`
--

DROP TABLE IF EXISTS `derivaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `derivaciones`
--

/*!40000 ALTER TABLE `derivaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `derivaciones` ENABLE KEYS */;

--
-- Table structure for table `docente_materias`
--

DROP TABLE IF EXISTS `docente_materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docente_materias` (
  `cod_docente` int(10) unsigned NOT NULL,
  `cod_materia` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_docente`,`cod_materia`),
  KEY `idx_docente_materias_materia` (`cod_materia`),
  CONSTRAINT `fk_docente_materias_docente` FOREIGN KEY (`cod_docente`) REFERENCES `docentes` (`cod_docente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_docente_materias_materia` FOREIGN KEY (`cod_materia`) REFERENCES `materias` (`cod_materia`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docente_materias`
--

/*!40000 ALTER TABLE `docente_materias` DISABLE KEYS */;
/*!40000 ALTER TABLE `docente_materias` ENABLE KEYS */;

--
-- Table structure for table `docentes`
--

DROP TABLE IF EXISTS `docentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docentes` (
  `cod_docente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_usuario` int(10) unsigned NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_docente`),
  UNIQUE KEY `uk_docentes_usuario` (`cod_usuario`),
  CONSTRAINT `fk_docentes_usuarios` FOREIGN KEY (`cod_usuario`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docentes`
--

/*!40000 ALTER TABLE `docentes` DISABLE KEYS */;
/*!40000 ALTER TABLE `docentes` ENABLE KEYS */;

--
-- Table structure for table `esquema_migraciones_normalizadas`
--

DROP TABLE IF EXISTS `esquema_migraciones_normalizadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `esquema_migraciones_normalizadas` (
  `version` varchar(80) NOT NULL,
  `aplicada_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `esquema_migraciones_normalizadas`
--

/*!40000 ALTER TABLE `esquema_migraciones_normalizadas` DISABLE KEYS */;
INSERT INTO `esquema_migraciones_normalizadas` VALUES ('006_roles_permisos_dinamicos','2026-10-22 01:13:58'),('007_permisos_cambio_estado','2026-10-22 01:13:58'),('008_limites_permisos_datos_estudiantiles','2026-10-22 01:13:58');
/*!40000 ALTER TABLE `esquema_migraciones_normalizadas` ENABLE KEYS */;

--
-- Table structure for table `estudiante_responsables`
--

DROP TABLE IF EXISTS `estudiante_responsables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiante_responsables`
--

/*!40000 ALTER TABLE `estudiante_responsables` DISABLE KEYS */;
/*!40000 ALTER TABLE `estudiante_responsables` ENABLE KEYS */;

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiantes`
--

/*!40000 ALTER TABLE `estudiantes` DISABLE KEYS */;
/*!40000 ALTER TABLE `estudiantes` ENABLE KEYS */;

--
-- Table structure for table `grupos_opciones_historia`
--

DROP TABLE IF EXISTS `grupos_opciones_historia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grupos_opciones_historia` (
  `cod_grupo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  PRIMARY KEY (`cod_grupo`),
  UNIQUE KEY `uk_grupos_opciones_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grupos_opciones_historia`
--

/*!40000 ALTER TABLE `grupos_opciones_historia` DISABLE KEYS */;
INSERT INTO `grupos_opciones_historia` VALUES (1,'atencion_distraccion','Atención y distracción'),(2,'actividad_motora','Actividad motora'),(3,'dificultades_socioemocionales','Dificultades socioemocionales'),(4,'estrategias_previas','Estrategias previas'),(5,'conductas_riesgo','Conductas de riesgo'),(6,'adaptacion_normas','Adaptación a normas');
/*!40000 ALTER TABLE `grupos_opciones_historia` ENABLE KEYS */;

--
-- Table structure for table `historia_familiares`
--

DROP TABLE IF EXISTS `historia_familiares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historia_familiares`
--

/*!40000 ALTER TABLE `historia_familiares` DISABLE KEYS */;
/*!40000 ALTER TABLE `historia_familiares` ENABLE KEYS */;

--
-- Table structure for table `historia_opciones`
--

DROP TABLE IF EXISTS `historia_opciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historia_opciones` (
  `cod_historia` int(10) unsigned NOT NULL,
  `cod_opcion` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cod_historia`,`cod_opcion`),
  KEY `idx_historia_opciones_opcion` (`cod_opcion`),
  CONSTRAINT `fk_historia_opciones_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_historia_opciones_opcion` FOREIGN KEY (`cod_opcion`) REFERENCES `opciones_historia` (`cod_opcion`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historia_opciones`
--

/*!40000 ALTER TABLE `historia_opciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `historia_opciones` ENABLE KEYS */;

--
-- Table structure for table `historial_academico`
--

DROP TABLE IF EXISTS `historial_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_academico`
--

/*!40000 ALTER TABLE `historial_academico` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_academico` ENABLE KEYS */;

--
-- Table structure for table `historias_clinicas`
--

DROP TABLE IF EXISTS `historias_clinicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historias_clinicas`
--

/*!40000 ALTER TABLE `historias_clinicas` DISABLE KEYS */;
/*!40000 ALTER TABLE `historias_clinicas` ENABLE KEYS */;

--
-- Table structure for table `informe_individual`
--

DROP TABLE IF EXISTS `informe_individual`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `informe_individual`
--

/*!40000 ALTER TABLE `informe_individual` DISABLE KEYS */;
/*!40000 ALTER TABLE `informe_individual` ENABLE KEYS */;

--
-- Table structure for table `informe_resumen`
--

DROP TABLE IF EXISTS `informe_resumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `informe_resumen`
--

/*!40000 ALTER TABLE `informe_resumen` DISABLE KEYS */;
/*!40000 ALTER TABLE `informe_resumen` ENABLE KEYS */;

--
-- Table structure for table `informes`
--

DROP TABLE IF EXISTS `informes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `informes`
--

/*!40000 ALTER TABLE `informes` DISABLE KEYS */;
/*!40000 ALTER TABLE `informes` ENABLE KEYS */;

--
-- Table structure for table `informes_secuencia`
--

DROP TABLE IF EXISTS `informes_secuencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `informes_secuencia` (
  `id` tinyint(3) unsigned NOT NULL,
  `ultimo` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `informes_secuencia`
--

/*!40000 ALTER TABLE `informes_secuencia` DISABLE KEYS */;
INSERT INTO `informes_secuencia` VALUES (1,0);
/*!40000 ALTER TABLE `informes_secuencia` ENABLE KEYS */;

--
-- Table structure for table `instituciones`
--

DROP TABLE IF EXISTS `instituciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instituciones` (
  `cod_institucion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_institucion`),
  UNIQUE KEY `uk_instituciones_nombre` (`nombre`),
  UNIQUE KEY `uk_instituciones_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instituciones`
--

/*!40000 ALTER TABLE `instituciones` DISABLE KEYS */;
INSERT INTO `instituciones` VALUES (1,'U.E. Cañada Pailita B','UE-CPB',NULL,NULL,'Activo');
/*!40000 ALTER TABLE `instituciones` ENABLE KEYS */;

--
-- Table structure for table `materias`
--

DROP TABLE IF EXISTS `materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materias` (
  `cod_materia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_materia`),
  UNIQUE KEY `uk_materias_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materias`
--

/*!40000 ALTER TABLE `materias` DISABLE KEYS */;
INSERT INTO `materias` VALUES (1,'Ciencias Sociales','Activo'),(2,'Matemática','Activo'),(3,'Biología','Activo'),(4,'Inglés','Activo'),(5,'Lenguaje','Activo'),(6,'Educación Física','Activo'),(7,'Física','Activo'),(8,'Química','Activo'),(9,'Tecnología','Activo'),(10,'Religión','Activo');
/*!40000 ALTER TABLE `materias` ENABLE KEYS */;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES (1,'Dashboard','/index.php','bi-speedometer2',1,'Activo'),(2,'Estudiantes','/estudiantes/listar.php','bi-people-fill',2,'Activo'),(3,'Derivaciones','/derivaciones/listar.php','bi-send',3,'Activo'),(4,'Citas','/citas/listar.php','bi-calendar-check',4,'Activo'),(5,'Historias clinicas','/historias_clinicas/listar.php','bi-folder2-open',5,'Activo'),(6,'Informes','/informes/listar.php','bi-file-earmark-bar-graph',6,'Activo'),(7,'Usuarios','/usuarios/listar.php','bi-person-gear',7,'Activo'),(8,'Roles y permisos','/roles/listar.php','bi-shield-lock',8,'Activo'),(9,'Docentes','/docentes/listar.php','bi-person-badge-fill',9,'Activo'),(10,'Seguimientos','/seguimientos/ver.php','bi-journal-medical',10,'Activo'),(11,'Estadísticas','/estadisticas/index.php','bi-bar-chart-line-fill',11,'Activo');
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;

--
-- Table structure for table `opciones_historia`
--

DROP TABLE IF EXISTS `opciones_historia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opciones_historia` (
  `cod_opcion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_grupo` int(10) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_opcion`),
  UNIQUE KEY `uk_opcion_grupo_descripcion` (`cod_grupo`,`descripcion`),
  CONSTRAINT `fk_opciones_historia_grupo` FOREIGN KEY (`cod_grupo`) REFERENCES `grupos_opciones_historia` (`cod_grupo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opciones_historia`
--

/*!40000 ALTER TABLE `opciones_historia` DISABLE KEYS */;
INSERT INTO `opciones_historia` VALUES (1,1,'Se distrae mirando a cualquier lado','Activo'),(2,1,'Se olvida sus carpetas o tareas','Activo'),(3,1,'Habla constantemente','Activo'),(4,2,'Se mueve constantemente en su asiento','Activo'),(5,3,'Falta de interés por aprender','Activo'),(6,4,'Conversación con el tutor/a a cargo','Activo'),(7,4,'Mayor supervisión','Activo'),(8,2,'Se para y se sienta constantemente','Activo'),(9,4,'Ninguna','Activo'),(10,3,'No participa en las actividades','Activo'),(11,1,'Se equivoca por descuido','Activo'),(12,1,'No termina de hacer tareas, se atrasa','Activo'),(13,1,'Lenguaje inapropiado / conducta obscena','Activo'),(14,3,'Se le observa triste, deprimido, desganado','Activo'),(15,3,'Cambia de humor frecuentemente','Activo'),(16,3,'Prefiere estar solo(a)','Activo'),(17,3,'Se calla y aguanta si lo molestan','Activo'),(18,3,'Tartamudea / habla nerviosamente','Activo'),(19,3,'Se muerde / come las uñas','Activo'),(20,3,'Duda al expresar su opinión','Activo'),(21,3,'Se mantiene callado','Activo'),(22,4,'Conversación con él/ella','Activo'),(23,4,'Llamadas de atención','Activo'),(24,4,'Premios y sanciones','Activo'),(25,5,'Conducta delictiva','Activo'),(26,5,'Pandillaje','Activo'),(27,5,'Sospecha de consumo de alcohol/drogas','Activo'),(28,5,'Problema sexual','Activo'),(29,6,'No obedece el reglamento del Colegio','Activo'),(30,6,'Le es difícil seguir indicaciones - protesta','Activo'),(31,6,'No cumple normas establecidas','Activo'),(32,6,'Propicia el desorden','Activo'),(33,6,'Miente','Activo'),(34,6,'Agrede de manera verbal','Activo'),(35,6,'Agrede de manera física','Activo'),(36,6,'Llama por apodos / Bullying','Activo'),(37,6,'Sus compañeros lo rechazan','Activo');
/*!40000 ALTER TABLE `opciones_historia` ENABLE KEYS */;

--
-- Table structure for table `paralelos`
--

DROP TABLE IF EXISTS `paralelos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paralelos` (
  `cod_paralelo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_paralelo`),
  UNIQUE KEY `uk_paralelos_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paralelos`
--

/*!40000 ALTER TABLE `paralelos` DISABLE KEYS */;
INSERT INTO `paralelos` VALUES (1,'A','Activo'),(2,'B','Activo'),(3,'C','Activo'),(4,'D','Activo');
/*!40000 ALTER TABLE `paralelos` ENABLE KEYS */;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (1,1,1,1,1,1,1,0),(2,1,2,1,1,1,1,1),(3,1,3,1,1,1,1,1),(4,1,4,1,1,1,1,1),(5,1,5,1,1,1,1,0),(6,1,6,1,1,1,1,0),(7,1,7,1,1,1,1,1),(8,1,8,1,1,1,1,0),(9,2,1,1,0,0,0,0),(10,2,2,1,1,1,0,1),(11,2,3,1,0,0,0,1),(12,2,4,1,1,1,0,1),(13,2,5,1,1,1,0,0),(14,2,6,1,1,1,0,0),(15,3,1,0,0,0,0,0),(16,3,2,0,0,0,0,0),(17,3,3,1,1,1,1,0),(18,4,1,0,0,0,0,0),(19,4,6,1,0,0,0,0),(20,1,9,1,1,1,1,0),(21,1,10,1,1,1,1,0),(22,1,11,1,1,1,1,0),(23,2,10,1,1,1,0,0),(24,2,11,1,0,0,0,0);
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;

--
-- Table structure for table `responsables`
--

DROP TABLE IF EXISTS `responsables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `responsables` (
  `cod_responsable` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(150) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`cod_responsable`),
  KEY `idx_responsables_nombre` (`nombres`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `responsables`
--

/*!40000 ALTER TABLE `responsables` DISABLE KEYS */;
/*!40000 ALTER TABLE `responsables` ENABLE KEYS */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `cod_rol` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cod_rol`),
  UNIQUE KEY `uk_roles_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Acceso total al sistema','Activo','2026-10-06 05:12:57'),(2,'Psicóloga','Gestiona derivaciones, citas, historias clínicas, seguimientos e informes','Activo','2026-10-06 05:12:57'),(3,'Docente','Consulta estudiantes y registra derivaciones','Activo','2026-10-06 05:12:57'),(4,'Director','Consulta informes y estadísticas autorizadas','Activo','2026-10-06 05:12:57');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;

--
-- Table structure for table `secciones`
--

DROP TABLE IF EXISTS `secciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `secciones` (
  `cod_seccion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_institucion` int(10) unsigned NOT NULL,
  `cod_curso` int(10) unsigned NOT NULL,
  `cod_paralelo` int(10) unsigned NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `gestion` year(4) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`cod_seccion`),
  UNIQUE KEY `uk_seccion` (`cod_institucion`,`cod_curso`,`cod_paralelo`,`turno`,`gestion`),
  KEY `idx_secciones_curso` (`cod_curso`),
  KEY `idx_secciones_paralelo` (`cod_paralelo`),
  CONSTRAINT `fk_secciones_cursos` FOREIGN KEY (`cod_curso`) REFERENCES `cursos` (`cod_curso`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_instituciones` FOREIGN KEY (`cod_institucion`) REFERENCES `instituciones` (`cod_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_paralelos` FOREIGN KEY (`cod_paralelo`) REFERENCES `paralelos` (`cod_paralelo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secciones`
--

/*!40000 ALTER TABLE `secciones` DISABLE KEYS */;
INSERT INTO `secciones` VALUES (1,1,1,1,'Mañana',2026,'Activo'),(2,1,1,1,'Tarde',2026,'Activo'),(3,1,1,2,'Mañana',2026,'Activo'),(4,1,1,2,'Tarde',2026,'Activo'),(5,1,1,3,'Mañana',2026,'Activo'),(6,1,1,3,'Tarde',2026,'Activo'),(7,1,1,4,'Mañana',2026,'Activo'),(8,1,1,4,'Tarde',2026,'Activo'),(9,1,2,1,'Mañana',2026,'Activo'),(10,1,2,1,'Tarde',2026,'Activo'),(11,1,2,2,'Mañana',2026,'Activo'),(12,1,2,2,'Tarde',2026,'Activo'),(13,1,2,3,'Mañana',2026,'Activo'),(14,1,2,3,'Tarde',2026,'Activo'),(15,1,2,4,'Mañana',2026,'Activo'),(16,1,2,4,'Tarde',2026,'Activo'),(17,1,3,1,'Mañana',2026,'Activo'),(18,1,3,1,'Tarde',2026,'Activo'),(19,1,3,2,'Mañana',2026,'Activo'),(20,1,3,2,'Tarde',2026,'Activo'),(21,1,3,3,'Mañana',2026,'Activo'),(22,1,3,3,'Tarde',2026,'Activo'),(23,1,3,4,'Mañana',2026,'Activo'),(24,1,3,4,'Tarde',2026,'Activo'),(25,1,4,1,'Mañana',2026,'Activo'),(26,1,4,1,'Tarde',2026,'Activo'),(27,1,4,2,'Mañana',2026,'Activo'),(28,1,4,2,'Tarde',2026,'Activo'),(29,1,4,3,'Mañana',2026,'Activo'),(30,1,4,3,'Tarde',2026,'Activo'),(31,1,4,4,'Mañana',2026,'Activo'),(32,1,4,4,'Tarde',2026,'Activo'),(33,1,5,1,'Mañana',2026,'Activo'),(34,1,5,1,'Tarde',2026,'Activo'),(35,1,5,2,'Mañana',2026,'Activo'),(36,1,5,2,'Tarde',2026,'Activo'),(37,1,5,3,'Mañana',2026,'Activo'),(38,1,5,3,'Tarde',2026,'Activo'),(39,1,5,4,'Mañana',2026,'Activo'),(40,1,5,4,'Tarde',2026,'Activo'),(41,1,6,1,'Mañana',2026,'Activo'),(42,1,6,1,'Tarde',2026,'Activo'),(43,1,6,2,'Mañana',2026,'Activo'),(44,1,6,2,'Tarde',2026,'Activo'),(45,1,6,3,'Mañana',2026,'Activo'),(46,1,6,3,'Tarde',2026,'Activo'),(47,1,6,4,'Mañana',2026,'Activo'),(48,1,6,4,'Tarde',2026,'Activo');
/*!40000 ALTER TABLE `secciones` ENABLE KEYS */;

--
-- Table structure for table `seguimientos`
--

DROP TABLE IF EXISTS `seguimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seguimientos`
--

/*!40000 ALTER TABLE `seguimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `seguimientos` ENABLE KEYS */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','Sistema',NULL,'admin@local.test',NULL,'admin','$2y$10$FdwXgTBRFXMgmS89Q50zEexUysvoaLGBIeNgWJeOzsQyANXXm6D8.',1,'Activo',NULL,'2026-10-06 05:12:57','2026-10-22 01:13:58');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;

--
-- Temporary table structure for view `vista_citas`
--

DROP TABLE IF EXISTS `vista_citas`;
/*!50001 DROP VIEW IF EXISTS `vista_citas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vista_citas` AS SELECT
 1 AS `cod_cita`,
  1 AS `cod_estudiante`,
  1 AS `cod_psicologa`,
  1 AS `cod_derivacion`,
  1 AS `fecha`,
  1 AS `hora`,
  1 AS `estado`,
  1 AS `observaciones`,
  1 AS `motivo`,
  1 AS `fecha_registro`,
  1 AS `fecha_actualizacion`,
  1 AS `estudiante`,
  1 AS `psicologa` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vista_derivaciones`
--

DROP TABLE IF EXISTS `vista_derivaciones`;
/*!50001 DROP VIEW IF EXISTS `vista_derivaciones`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vista_derivaciones` AS SELECT
 1 AS `cod_derivacion`,
  1 AS `fecha`,
  1 AS `cod_estudiante`,
  1 AS `cod_docente`,
  1 AS `cod_materia`,
  1 AS `materia`,
  1 AS `motivo`,
  1 AS `observaciones`,
  1 AS `prioridad`,
  1 AS `estado`,
  1 AS `solicitar_cita`,
  1 AS `cod_psicologa_solicitada`,
  1 AS `fecha_registro`,
  1 AS `fecha_actualizacion`,
  1 AS `estudiante`,
  1 AS `curso`,
  1 AS `paralelo`,
  1 AS `turno`,
  1 AS `docente` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vista_estudiantes`
--

DROP TABLE IF EXISTS `vista_estudiantes`;
/*!50001 DROP VIEW IF EXISTS `vista_estudiantes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vista_estudiantes` AS SELECT
 1 AS `cod_estudiante`,
  1 AS `codigo`,
  1 AS `ci`,
  1 AS `rude`,
  1 AS `nombres`,
  1 AS `apellidos`,
  1 AS `fecha_nacimiento`,
  1 AS `lugar_nacimiento`,
  1 AS `sexo`,
  1 AS `direccion`,
  1 AS `telefono`,
  1 AS `estado`,
  1 AS `cod_seccion`,
  1 AS `cod_curso`,
  1 AS `curso`,
  1 AS `cod_paralelo`,
  1 AS `paralelo`,
  1 AS `turno`,
  1 AS `gestion`,
  1 AS `cod_institucion`,
  1 AS `institucion` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `vista_citas`
--

/*!50001 DROP VIEW IF EXISTS `vista_citas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_citas` AS select `c`.`cod_cita` AS `cod_cita`,`c`.`cod_estudiante` AS `cod_estudiante`,`c`.`cod_psicologa` AS `cod_psicologa`,`c`.`cod_derivacion` AS `cod_derivacion`,`c`.`fecha` AS `fecha`,`c`.`hora` AS `hora`,`c`.`estado` AS `estado`,`c`.`observaciones` AS `observaciones`,`c`.`motivo` AS `motivo`,`c`.`fecha_registro` AS `fecha_registro`,`c`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,concat(`u`.`nombres`,' ',`u`.`apellidos`) AS `psicologa` from ((`citas` `c` join `estudiantes` `e` on(`e`.`cod_estudiante` = `c`.`cod_estudiante`)) join `usuarios` `u` on(`u`.`cod_usuario` = `c`.`cod_psicologa`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_derivaciones`
--

/*!50001 DROP VIEW IF EXISTS `vista_derivaciones`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_derivaciones` AS select `d`.`cod_derivacion` AS `cod_derivacion`,`d`.`fecha` AS `fecha`,`d`.`cod_estudiante` AS `cod_estudiante`,`d`.`cod_docente` AS `cod_docente`,`d`.`cod_materia` AS `cod_materia`,`m`.`nombre` AS `materia`,`d`.`motivo` AS `motivo`,`d`.`observaciones` AS `observaciones`,`d`.`prioridad` AS `prioridad`,`d`.`estado` AS `estado`,`d`.`solicitar_cita` AS `solicitar_cita`,`d`.`cod_psicologa_solicitada` AS `cod_psicologa_solicitada`,`d`.`fecha_registro` AS `fecha_registro`,`d`.`fecha_actualizacion` AS `fecha_actualizacion`,concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`,`ve`.`curso` AS `curso`,`ve`.`paralelo` AS `paralelo`,`ve`.`turno` AS `turno`,concat(`u`.`nombres`,' ',`u`.`apellidos`) AS `docente` from (((((`derivaciones` `d` join `estudiantes` `e` on(`e`.`cod_estudiante` = `d`.`cod_estudiante`)) join `docentes` `doc` on(`doc`.`cod_docente` = `d`.`cod_docente`)) join `usuarios` `u` on(`u`.`cod_usuario` = `doc`.`cod_usuario`)) left join `materias` `m` on(`m`.`cod_materia` = `d`.`cod_materia`)) left join `vista_estudiantes` `ve` on(`ve`.`cod_estudiante` = `e`.`cod_estudiante`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_estudiantes`
--

/*!50001 DROP VIEW IF EXISTS `vista_estudiantes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_estudiantes` AS select `e`.`cod_estudiante` AS `cod_estudiante`,`e`.`codigo` AS `codigo`,`e`.`ci` AS `ci`,`e`.`rude` AS `rude`,`e`.`nombres` AS `nombres`,`e`.`apellidos` AS `apellidos`,`e`.`fecha_nacimiento` AS `fecha_nacimiento`,`e`.`lugar_nacimiento` AS `lugar_nacimiento`,`e`.`sexo` AS `sexo`,`e`.`direccion` AS `direccion`,`e`.`telefono` AS `telefono`,`e`.`estado` AS `estado`,`s`.`cod_seccion` AS `cod_seccion`,`c`.`cod_curso` AS `cod_curso`,`c`.`nombre` AS `curso`,`p`.`cod_paralelo` AS `cod_paralelo`,`p`.`nombre` AS `paralelo`,`s`.`turno` AS `turno`,`s`.`gestion` AS `gestion`,`i`.`cod_institucion` AS `cod_institucion`,`i`.`nombre` AS `institucion` from (((((`estudiantes` `e` left join `historial_academico` `ha` on(`ha`.`cod_estudiante` = `e`.`cod_estudiante` and `ha`.`estado` = 'Activo')) left join `secciones` `s` on(`s`.`cod_seccion` = `ha`.`cod_seccion`)) left join `cursos` `c` on(`c`.`cod_curso` = `s`.`cod_curso`)) left join `paralelos` `p` on(`p`.`cod_paralelo` = `s`.`cod_paralelo`)) left join `instituciones` `i` on(`i`.`cod_institucion` = `s`.`cod_institucion`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-21 21:13:59
