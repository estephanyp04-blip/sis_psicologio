-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-10-2026 a las 07:39:52
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `psicologia_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `cod_auditoria` bigint(20) UNSIGNED NOT NULL,
  `cod_usuario` int(10) UNSIGNED DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(40) NOT NULL,
  `registro_id` int(10) UNSIGNED DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_derivacion`
--

CREATE TABLE `categorias_derivacion` (
  `cod_categoria` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_derivacion`
--

INSERT INTO `categorias_derivacion` (`cod_categoria`, `nombre`, `estado`) VALUES
(1, 'Rendimiento Académico', 'Activo'),
(2, 'Acoso escolar / Acoso', 'Activo'),
(3, 'Conducta en Aula', 'Activo'),
(4, 'Dinámica Familiar', 'Activo'),
(5, 'Social / Emocional', 'Activo'),
(6, 'Otro', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `cod_cita` int(10) UNSIGNED NOT NULL,
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_psicologa` int(10) UNSIGNED NOT NULL,
  `cod_derivacion` int(10) UNSIGNED DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('Pendiente','Atendida','Reprogramada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `motivo` varchar(500) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `turno_reservado` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'Cancelada' then NULL else 1 end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `cod_curso` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Secundaria',
  `orden` tinyint(3) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`cod_curso`, `nombre`, `nivel`, `orden`, `estado`) VALUES
(1, '1ro de Secundaria', 'Secundaria', 1, 'Activo'),
(2, '2do de Secundaria', 'Secundaria', 2, 'Activo'),
(3, '3ro de Secundaria', 'Secundaria', 3, 'Activo'),
(4, '4to de Secundaria', 'Secundaria', 4, 'Activo'),
(5, '5to de Secundaria', 'Secundaria', 5, 'Activo'),
(6, '6to de Secundaria', 'Secundaria', 6, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivaciones`
--

CREATE TABLE `derivaciones` (
  `cod_derivacion` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_docente` int(10) UNSIGNED NOT NULL,
  `cod_materia` int(10) UNSIGNED DEFAULT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `estado` enum('Pendiente','En seguimiento','Atendido') NOT NULL DEFAULT 'Pendiente',
  `solicitar_cita` tinyint(1) NOT NULL DEFAULT 0,
  `cod_psicologa_solicitada` int(10) UNSIGNED DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_categorias`
--

CREATE TABLE `derivacion_categorias` (
  `cod_derivacion` int(10) UNSIGNED NOT NULL,
  `cod_categoria` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_evidencias`
--

CREATE TABLE `derivacion_evidencias` (
  `cod_evidencia` int(10) UNSIGNED NOT NULL,
  `cod_derivacion` int(10) UNSIGNED NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `cod_docente` int(10) UNSIGNED NOT NULL,
  `cod_persona` int(10) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_materias`
--

CREATE TABLE `docente_materias` (
  `cod_docente` int(10) UNSIGNED NOT NULL,
  `cod_materia` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
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
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiante_responsables`
--

CREATE TABLE `estudiante_responsables` (
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_responsable` int(10) UNSIGNED NOT NULL,
  `parentesco` enum('Padre','Madre','Tutor','Otro') NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos_opciones_historia`
--

CREATE TABLE `grupos_opciones_historia` (
  `cod_grupo` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grupos_opciones_historia`
--

INSERT INTO `grupos_opciones_historia` (`cod_grupo`, `codigo`, `nombre`) VALUES
(1, 'atencion_distraccion', 'Atención y distracción'),
(2, 'actividad_motora', 'Actividad motora'),
(3, 'dificultades_socioemocionales', 'Dificultades socioemocionales'),
(4, 'estrategias_previas', 'Estrategias previas'),
(5, 'conductas_riesgo', 'Conductas de riesgo'),
(6, 'adaptacion_normas', 'Adaptación a normas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historias_clinicas`
--

CREATE TABLE `historias_clinicas` (
  `cod_historia` int(10) UNSIGNED NOT NULL,
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_psicologa` int(10) UNSIGNED NOT NULL,
  `cod_derivacion_origen` int(10) UNSIGNED DEFAULT NULL,
  `cod_cita_origen` int(10) UNSIGNED DEFAULT NULL,
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
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historia_familiares`
--

CREATE TABLE `historia_familiares` (
  `cod_familiar` int(10) UNSIGNED NOT NULL,
  `cod_historia` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `edad` tinyint(3) UNSIGNED DEFAULT NULL,
  `relacion` varchar(100) DEFAULT NULL,
  `profesion` varchar(150) DEFAULT NULL,
  `ocupacion` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historia_opciones`
--

CREATE TABLE `historia_opciones` (
  `cod_historia` int(10) UNSIGNED NOT NULL,
  `cod_opcion` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informes`
--

CREATE TABLE `informes` (
  `cod_informe` int(10) UNSIGNED NOT NULL,
  `numero_ficha` varchar(30) NOT NULL,
  `cod_elaborado_por` int(10) UNSIGNED NOT NULL,
  `tipo` enum('Mensual','Trimestral','Anual','Individual','General') NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `conclusiones` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `estado` enum('Borrador','Finalizado') NOT NULL DEFAULT 'Borrador',
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informes_secuencia`
--

CREATE TABLE `informes_secuencia` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `ultimo` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `informes_secuencia`
--

INSERT INTO `informes_secuencia` (`id`, `ultimo`) VALUES
(1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informe_individual`
--

CREATE TABLE `informe_individual` (
  `cod_informe` int(10) UNSIGNED NOT NULL,
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_historia` int(10) UNSIGNED DEFAULT NULL,
  `cod_derivacion` int(10) UNSIGNED DEFAULT NULL,
  `cod_seguimiento` int(10) UNSIGNED DEFAULT NULL,
  `numero_atenciones` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `referido_por` varchar(150) DEFAULT NULL,
  `tipo_atencion` text DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `aspecto_cognitivo` text DEFAULT NULL,
  `aspectos_afectivos` text DEFAULT NULL,
  `diagnostico_acuerdos` text DEFAULT NULL,
  `recibido_por` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informe_resumen`
--

CREATE TABLE `informe_resumen` (
  `cod_informe` int(10) UNSIGNED NOT NULL,
  `total_estudiantes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_derivaciones` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `derivaciones_pendientes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `derivaciones_atendidas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_citas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `citas_atendidas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_historias` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

CREATE TABLE `inscripciones` (
  `cod_inscripcion` int(10) UNSIGNED NOT NULL,
  `cod_estudiante` int(10) UNSIGNED NOT NULL,
  `cod_seccion` int(10) UNSIGNED NOT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado` enum('Activo','Retirado','Finalizado') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `instituciones`
--

CREATE TABLE `instituciones` (
  `cod_institucion` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `instituciones`
--

INSERT INTO `instituciones` (`cod_institucion`, `nombre`, `codigo`, `direccion`, `telefono`, `estado`) VALUES
(1, 'U.E. Cañada Pailita B', 'UE-CPB', NULL, NULL, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `cod_materia` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`cod_materia`, `nombre`, `estado`) VALUES
(1, 'Ciencias Sociales', 'Activo'),
(2, 'Matemática', 'Activo'),
(3, 'Biología', 'Activo'),
(4, 'Inglés', 'Activo'),
(5, 'Lenguaje', 'Activo'),
(6, 'Educación Física', 'Activo'),
(7, 'Física', 'Activo'),
(8, 'Química', 'Activo'),
(9, 'Tecnología', 'Activo'),
(10, 'Religión', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `cod_modulo` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `ruta` varchar(100) DEFAULT NULL,
  `icono` varchar(60) DEFAULT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`cod_modulo`, `nombre`, `ruta`, `icono`, `orden`, `estado`) VALUES
(1, 'Dashboard', '/index.php', 'bi-speedometer2', 1, 'Activo'),
(2, 'Estudiantes', '/estudiantes/index.php', 'bi-people', 2, 'Activo'),
(3, 'Derivaciones', '/derivaciones/listar.php', 'bi-send', 3, 'Activo'),
(4, 'Citas', '/citas/listar.php', 'bi-calendar-check', 4, 'Activo'),
(5, 'Historias clinicas', '/historias_clinicas/listar.php', 'bi-folder2-open', 5, 'Activo'),
(6, 'Informes', '/informes/listar.php', 'bi-file-earmark-bar-graph', 6, 'Activo'),
(7, 'Usuarios', '/usuarios/listar.php', 'bi-person-gear', 7, 'Activo'),
(8, 'Roles y permisos', '/roles/listar.php', 'bi-shield-lock', 8, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opciones_historia`
--

CREATE TABLE `opciones_historia` (
  `cod_opcion` int(10) UNSIGNED NOT NULL,
  `cod_grupo` int(10) UNSIGNED NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `opciones_historia`
--

INSERT INTO `opciones_historia` (`cod_opcion`, `cod_grupo`, `descripcion`, `estado`) VALUES
(1, 1, 'Se distrae mirando a cualquier lado', 'Activo'),
(2, 1, 'Se olvida sus carpetas o tareas', 'Activo'),
(3, 1, 'Habla constantemente', 'Activo'),
(4, 2, 'Se mueve constantemente en su asiento', 'Activo'),
(5, 3, 'Falta de interés por aprender', 'Activo'),
(6, 4, 'Conversación con el tutor/a a cargo', 'Activo'),
(7, 4, 'Mayor supervisión', 'Activo'),
(8, 2, 'Se para y se sienta constantemente', 'Activo'),
(9, 4, 'Ninguna', 'Activo'),
(10, 3, 'No participa en las actividades', 'Activo'),
(11, 1, 'Se equivoca por descuido', 'Activo'),
(12, 1, 'No termina de hacer tareas, se atrasa', 'Activo'),
(13, 1, 'Lenguaje inapropiado / conducta obscena', 'Activo'),
(14, 3, 'Se le observa triste, deprimido, desganado', 'Activo'),
(15, 3, 'Cambia de humor frecuentemente', 'Activo'),
(16, 3, 'Prefiere estar solo(a)', 'Activo'),
(17, 3, 'Se calla y aguanta si lo molestan', 'Activo'),
(18, 3, 'Tartamudea / habla nerviosamente', 'Activo'),
(19, 3, 'Se muerde / come las uñas', 'Activo'),
(20, 3, 'Duda al expresar su opinión', 'Activo'),
(21, 3, 'Se mantiene callado', 'Activo'),
(22, 4, 'Conversación con él/ella', 'Activo'),
(23, 4, 'Llamadas de atención', 'Activo'),
(24, 4, 'Premios y sanciones', 'Activo'),
(25, 5, 'Conducta delictiva', 'Activo'),
(26, 5, 'Pandillaje', 'Activo'),
(27, 5, 'Sospecha de consumo de alcohol/drogas', 'Activo'),
(28, 5, 'Problema sexual', 'Activo'),
(29, 6, 'No obedece el reglamento del Colegio', 'Activo'),
(30, 6, 'Le es difícil seguir indicaciones - protesta', 'Activo'),
(31, 6, 'No cumple normas establecidas', 'Activo'),
(32, 6, 'Propicia el desorden', 'Activo'),
(33, 6, 'Miente', 'Activo'),
(34, 6, 'Agrede de manera verbal', 'Activo'),
(35, 6, 'Agrede de manera física', 'Activo'),
(36, 6, 'Llama por apodos / Bullying', 'Activo'),
(37, 6, 'Sus compañeros lo rechazan', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paralelos`
--

CREATE TABLE `paralelos` (
  `cod_paralelo` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paralelos`
--

INSERT INTO `paralelos` (`cod_paralelo`, `nombre`, `estado`) VALUES
(1, 'A', 'Activo'),
(2, 'B', 'Activo'),
(3, 'C', 'Activo'),
(4, 'D', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `cod_permiso` int(10) UNSIGNED NOT NULL,
  `cod_rol` int(10) UNSIGNED NOT NULL,
  `cod_modulo` int(10) UNSIGNED NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear` tinyint(1) NOT NULL DEFAULT 0,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`cod_permiso`, `cod_rol`, `cod_modulo`, `puede_ver`, `puede_crear`, `puede_editar`, `puede_eliminar`) VALUES
(1, 1, 1, 1, 1, 1, 1),
(2, 1, 2, 1, 1, 1, 1),
(3, 1, 3, 1, 1, 1, 1),
(4, 1, 4, 1, 1, 1, 1),
(5, 1, 5, 1, 1, 1, 1),
(6, 1, 6, 1, 1, 1, 1),
(7, 1, 7, 1, 1, 1, 1),
(8, 1, 8, 1, 1, 1, 1),
(9, 2, 1, 1, 0, 0, 0),
(10, 2, 2, 1, 1, 1, 0),
(11, 2, 3, 1, 0, 1, 0),
(12, 2, 4, 1, 1, 1, 0),
(13, 2, 5, 1, 1, 1, 0),
(14, 2, 6, 1, 1, 1, 0),
(15, 3, 1, 1, 0, 0, 0),
(16, 3, 2, 1, 0, 0, 0),
(17, 3, 3, 1, 1, 1, 0),
(18, 4, 1, 1, 0, 0, 0),
(19, 4, 6, 1, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas`
--

CREATE TABLE `personas` (
  `cod_persona` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `ci` varchar(20) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `personas`
--

INSERT INTO `personas` (`cod_persona`, `nombres`, `apellidos`, `ci`, `correo`, `telefono`, `fecha_registro`) VALUES
(1, 'Administrador', 'Sistema', NULL, 'admin@local.test', NULL, '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `responsables`
--

CREATE TABLE `responsables` (
  `cod_responsable` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(150) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `cod_rol` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`cod_rol`, `nombre`, `descripcion`, `estado`, `fecha_registro`) VALUES
(1, 'Administrador', 'Acceso total al sistema', 'Activo', '2026-10-06 05:12:57'),
(2, 'Psicóloga', 'Gestiona derivaciones, citas, historias clínicas, seguimientos e informes', 'Activo', '2026-10-06 05:12:57'),
(3, 'Docente', 'Consulta estudiantes y registra derivaciones', 'Activo', '2026-10-06 05:12:57'),
(4, 'Director', 'Consulta informes y estadísticas autorizadas', 'Activo', '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones`
--

CREATE TABLE `secciones` (
  `cod_seccion` int(10) UNSIGNED NOT NULL,
  `cod_institucion` int(10) UNSIGNED NOT NULL,
  `cod_curso` int(10) UNSIGNED NOT NULL,
  `cod_paralelo` int(10) UNSIGNED NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `gestion` year(4) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `secciones`
--

INSERT INTO `secciones` (`cod_seccion`, `cod_institucion`, `cod_curso`, `cod_paralelo`, `turno`, `gestion`, `estado`) VALUES
(1, 1, 1, 1, 'Mañana', '2026', 'Activo'),
(2, 1, 1, 1, 'Tarde', '2026', 'Activo'),
(3, 1, 1, 2, 'Mañana', '2026', 'Activo'),
(4, 1, 1, 2, 'Tarde', '2026', 'Activo'),
(5, 1, 1, 3, 'Mañana', '2026', 'Activo'),
(6, 1, 1, 3, 'Tarde', '2026', 'Activo'),
(7, 1, 1, 4, 'Mañana', '2026', 'Activo'),
(8, 1, 1, 4, 'Tarde', '2026', 'Activo'),
(9, 1, 2, 1, 'Mañana', '2026', 'Activo'),
(10, 1, 2, 1, 'Tarde', '2026', 'Activo'),
(11, 1, 2, 2, 'Mañana', '2026', 'Activo'),
(12, 1, 2, 2, 'Tarde', '2026', 'Activo'),
(13, 1, 2, 3, 'Mañana', '2026', 'Activo'),
(14, 1, 2, 3, 'Tarde', '2026', 'Activo'),
(15, 1, 2, 4, 'Mañana', '2026', 'Activo'),
(16, 1, 2, 4, 'Tarde', '2026', 'Activo'),
(17, 1, 3, 1, 'Mañana', '2026', 'Activo'),
(18, 1, 3, 1, 'Tarde', '2026', 'Activo'),
(19, 1, 3, 2, 'Mañana', '2026', 'Activo'),
(20, 1, 3, 2, 'Tarde', '2026', 'Activo'),
(21, 1, 3, 3, 'Mañana', '2026', 'Activo'),
(22, 1, 3, 3, 'Tarde', '2026', 'Activo'),
(23, 1, 3, 4, 'Mañana', '2026', 'Activo'),
(24, 1, 3, 4, 'Tarde', '2026', 'Activo'),
(25, 1, 4, 1, 'Mañana', '2026', 'Activo'),
(26, 1, 4, 1, 'Tarde', '2026', 'Activo'),
(27, 1, 4, 2, 'Mañana', '2026', 'Activo'),
(28, 1, 4, 2, 'Tarde', '2026', 'Activo'),
(29, 1, 4, 3, 'Mañana', '2026', 'Activo'),
(30, 1, 4, 3, 'Tarde', '2026', 'Activo'),
(31, 1, 4, 4, 'Mañana', '2026', 'Activo'),
(32, 1, 4, 4, 'Tarde', '2026', 'Activo'),
(33, 1, 5, 1, 'Mañana', '2026', 'Activo'),
(34, 1, 5, 1, 'Tarde', '2026', 'Activo'),
(35, 1, 5, 2, 'Mañana', '2026', 'Activo'),
(36, 1, 5, 2, 'Tarde', '2026', 'Activo'),
(37, 1, 5, 3, 'Mañana', '2026', 'Activo'),
(38, 1, 5, 3, 'Tarde', '2026', 'Activo'),
(39, 1, 5, 4, 'Mañana', '2026', 'Activo'),
(40, 1, 5, 4, 'Tarde', '2026', 'Activo'),
(41, 1, 6, 1, 'Mañana', '2026', 'Activo'),
(42, 1, 6, 1, 'Tarde', '2026', 'Activo'),
(43, 1, 6, 2, 'Mañana', '2026', 'Activo'),
(44, 1, 6, 2, 'Tarde', '2026', 'Activo'),
(45, 1, 6, 3, 'Mañana', '2026', 'Activo'),
(46, 1, 6, 3, 'Tarde', '2026', 'Activo'),
(47, 1, 6, 4, 'Mañana', '2026', 'Activo'),
(48, 1, 6, 4, 'Tarde', '2026', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimientos`
--

CREATE TABLE `seguimientos` (
  `cod_seguimiento` int(10) UNSIGNED NOT NULL,
  `cod_historia` int(10) UNSIGNED NOT NULL,
  `cod_psicologa` int(10) UNSIGNED NOT NULL,
  `cod_cita` int(10) UNSIGNED DEFAULT NULL,
  `fecha` date NOT NULL,
  `descripcion` text NOT NULL,
  `tecnicas_aplicadas` text DEFAULT NULL,
  `acuerdos` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `proxima_sesion` date DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `cod_usuario` int(10) UNSIGNED NOT NULL,
  `cod_persona` int(10) UNSIGNED NOT NULL,
  `usuario` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `cod_rol` int(10) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`cod_usuario`, `cod_persona`, `usuario`, `password`, `cod_rol`, `estado`, `ultimo_acceso`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 1, 'admin', '$2y$10$FdwXgTBRFXMgmS89Q50zEexUysvoaLGBIeNgWJeOzsQyANXXm6D8.', 1, 'Activo', NULL, '2026-10-06 05:12:57', '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_citas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_citas` (
`cod_cita` int(10) unsigned
,`cod_estudiante` int(10) unsigned
,`cod_psicologa` int(10) unsigned
,`cod_derivacion` int(10) unsigned
,`fecha` date
,`hora` time
,`estado` enum('Pendiente','Atendida','Reprogramada','Cancelada')
,`observaciones` text
,`motivo` varchar(500)
,`fecha_registro` timestamp
,`fecha_actualizacion` timestamp
,`estudiante` varchar(161)
,`psicologa` varchar(161)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_derivaciones`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_derivaciones` (
`cod_derivacion` int(10) unsigned
,`fecha` date
,`cod_estudiante` int(10) unsigned
,`cod_docente` int(10) unsigned
,`cod_materia` int(10) unsigned
,`materia` varchar(100)
,`motivo` text
,`observaciones` text
,`prioridad` enum('Alta','Media','Baja')
,`estado` enum('Pendiente','En seguimiento','Atendido')
,`solicitar_cita` tinyint(1)
,`cod_psicologa_solicitada` int(10) unsigned
,`fecha_registro` timestamp
,`fecha_actualizacion` timestamp
,`estudiante` varchar(161)
,`curso` varchar(50)
,`paralelo` varchar(10)
,`turno` enum('Mañana','Tarde')
,`docente` varchar(161)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_estudiantes`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_estudiantes` (
`cod_estudiante` int(10) unsigned
,`codigo` varchar(20)
,`ci` varchar(20)
,`rude` varchar(30)
,`nombres` varchar(80)
,`apellidos` varchar(80)
,`fecha_nacimiento` date
,`lugar_nacimiento` varchar(100)
,`sexo` enum('M','F')
,`direccion` varchar(255)
,`telefono` varchar(25)
,`estado` enum('Activo','Retirado')
,`cod_seccion` int(10) unsigned
,`cod_curso` int(10) unsigned
,`curso` varchar(50)
,`cod_paralelo` int(10) unsigned
,`paralelo` varchar(10)
,`turno` enum('Mañana','Tarde')
,`gestion` year(4)
,`cod_institucion` int(10) unsigned
,`institucion` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_citas`
--
DROP TABLE IF EXISTS `vista_citas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_citas`  AS SELECT `c`.`cod_cita` AS `cod_cita`, `c`.`cod_estudiante` AS `cod_estudiante`, `c`.`cod_psicologa` AS `cod_psicologa`, `c`.`cod_derivacion` AS `cod_derivacion`, `c`.`fecha` AS `fecha`, `c`.`hora` AS `hora`, `c`.`estado` AS `estado`, `c`.`observaciones` AS `observaciones`, `c`.`motivo` AS `motivo`, `c`.`fecha_registro` AS `fecha_registro`, `c`.`fecha_actualizacion` AS `fecha_actualizacion`, concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`, concat(`pp`.`nombres`,' ',`pp`.`apellidos`) AS `psicologa` FROM (((`citas` `c` join `estudiantes` `e` on(`e`.`cod_estudiante` = `c`.`cod_estudiante`)) join `usuarios` `u` on(`u`.`cod_usuario` = `c`.`cod_psicologa`)) join `personas` `pp` on(`pp`.`cod_persona` = `u`.`cod_persona`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_derivaciones`
--
DROP TABLE IF EXISTS `vista_derivaciones`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_derivaciones`  AS SELECT `d`.`cod_derivacion` AS `cod_derivacion`, `d`.`fecha` AS `fecha`, `d`.`cod_estudiante` AS `cod_estudiante`, `d`.`cod_docente` AS `cod_docente`, `d`.`cod_materia` AS `cod_materia`, `m`.`nombre` AS `materia`, `d`.`motivo` AS `motivo`, `d`.`observaciones` AS `observaciones`, `d`.`prioridad` AS `prioridad`, `d`.`estado` AS `estado`, `d`.`solicitar_cita` AS `solicitar_cita`, `d`.`cod_psicologa_solicitada` AS `cod_psicologa_solicitada`, `d`.`fecha_registro` AS `fecha_registro`, `d`.`fecha_actualizacion` AS `fecha_actualizacion`, concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`, `ve`.`curso` AS `curso`, `ve`.`paralelo` AS `paralelo`, `ve`.`turno` AS `turno`, concat(`pd`.`apellidos`,' ',`pd`.`nombres`) AS `docente` FROM (((((`derivaciones` `d` join `estudiantes` `e` on(`e`.`cod_estudiante` = `d`.`cod_estudiante`)) join `docentes` `doc` on(`doc`.`cod_docente` = `d`.`cod_docente`)) join `personas` `pd` on(`pd`.`cod_persona` = `doc`.`cod_persona`)) left join `materias` `m` on(`m`.`cod_materia` = `d`.`cod_materia`)) left join `vista_estudiantes` `ve` on(`ve`.`cod_estudiante` = `e`.`cod_estudiante`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_estudiantes`
--
DROP TABLE IF EXISTS `vista_estudiantes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_estudiantes`  AS SELECT `e`.`cod_estudiante` AS `cod_estudiante`, `e`.`codigo` AS `codigo`, `e`.`ci` AS `ci`, `e`.`rude` AS `rude`, `e`.`nombres` AS `nombres`, `e`.`apellidos` AS `apellidos`, `e`.`fecha_nacimiento` AS `fecha_nacimiento`, `e`.`lugar_nacimiento` AS `lugar_nacimiento`, `e`.`sexo` AS `sexo`, `e`.`direccion` AS `direccion`, `e`.`telefono` AS `telefono`, `e`.`estado` AS `estado`, `s`.`cod_seccion` AS `cod_seccion`, `c`.`cod_curso` AS `cod_curso`, `c`.`nombre` AS `curso`, `p`.`cod_paralelo` AS `cod_paralelo`, `p`.`nombre` AS `paralelo`, `s`.`turno` AS `turno`, `s`.`gestion` AS `gestion`, `i`.`cod_institucion` AS `cod_institucion`, `i`.`nombre` AS `institucion` FROM (((((`estudiantes` `e` left join `inscripciones` `ins` on(`ins`.`cod_estudiante` = `e`.`cod_estudiante` and `ins`.`estado` = 'Activo')) left join `secciones` `s` on(`s`.`cod_seccion` = `ins`.`cod_seccion`)) left join `cursos` `c` on(`c`.`cod_curso` = `s`.`cod_curso`)) left join `paralelos` `p` on(`p`.`cod_paralelo` = `s`.`cod_paralelo`)) left join `instituciones` `i` on(`i`.`cod_institucion` = `s`.`cod_institucion`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`cod_auditoria`),
  ADD KEY `idx_auditoria_usuario` (`cod_usuario`),
  ADD KEY `idx_auditoria_fecha` (`fecha`);

--
-- Indices de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  ADD PRIMARY KEY (`cod_categoria`),
  ADD UNIQUE KEY `uk_categorias_derivacion_nombre` (`nombre`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`cod_cita`),
  ADD UNIQUE KEY `uk_citas_psicologa_horario` (`cod_psicologa`,`fecha`,`hora`,`turno_reservado`),
  ADD KEY `idx_citas_estudiante` (`cod_estudiante`,`fecha`),
  ADD KEY `idx_citas_derivacion` (`cod_derivacion`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`cod_curso`),
  ADD UNIQUE KEY `uk_cursos_nombre_nivel` (`nombre`,`nivel`),
  ADD UNIQUE KEY `uk_cursos_nivel_orden` (`nivel`,`orden`);

--
-- Indices de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD PRIMARY KEY (`cod_derivacion`),
  ADD KEY `idx_derivaciones_estudiante` (`cod_estudiante`),
  ADD KEY `idx_derivaciones_docente` (`cod_docente`),
  ADD KEY `idx_derivaciones_materia` (`cod_materia`),
  ADD KEY `idx_derivaciones_estado` (`estado`,`prioridad`,`fecha`),
  ADD KEY `idx_derivaciones_psicologa` (`cod_psicologa_solicitada`);

--
-- Indices de la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD PRIMARY KEY (`cod_derivacion`,`cod_categoria`),
  ADD KEY `idx_derivacion_categorias_categoria` (`cod_categoria`);

--
-- Indices de la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  ADD PRIMARY KEY (`cod_evidencia`),
  ADD KEY `idx_evidencia_derivacion` (`cod_derivacion`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`cod_docente`),
  ADD UNIQUE KEY `uk_docentes_persona` (`cod_persona`);

--
-- Indices de la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  ADD PRIMARY KEY (`cod_docente`,`cod_materia`),
  ADD KEY `idx_docente_materias_materia` (`cod_materia`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`cod_estudiante`),
  ADD UNIQUE KEY `uk_estudiantes_codigo` (`codigo`),
  ADD UNIQUE KEY `uk_estudiantes_ci` (`ci`),
  ADD UNIQUE KEY `uk_estudiantes_rude` (`rude`),
  ADD KEY `idx_estudiantes_nombre` (`apellidos`,`nombres`);

--
-- Indices de la tabla `estudiante_responsables`
--
ALTER TABLE `estudiante_responsables`
  ADD PRIMARY KEY (`cod_estudiante`,`cod_responsable`,`parentesco`),
  ADD KEY `idx_est_responsable` (`cod_responsable`);

--
-- Indices de la tabla `grupos_opciones_historia`
--
ALTER TABLE `grupos_opciones_historia`
  ADD PRIMARY KEY (`cod_grupo`),
  ADD UNIQUE KEY `uk_grupos_opciones_codigo` (`codigo`);

--
-- Indices de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD PRIMARY KEY (`cod_historia`),
  ADD UNIQUE KEY `uk_historia_estudiante` (`cod_estudiante`),
  ADD KEY `idx_historias_psicologa` (`cod_psicologa`),
  ADD KEY `idx_historias_derivacion` (`cod_derivacion_origen`),
  ADD KEY `idx_historias_cita` (`cod_cita_origen`);

--
-- Indices de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD PRIMARY KEY (`cod_familiar`),
  ADD KEY `idx_historia_familiares_historia` (`cod_historia`);

--
-- Indices de la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD PRIMARY KEY (`cod_historia`,`cod_opcion`),
  ADD KEY `idx_historia_opciones_opcion` (`cod_opcion`);

--
-- Indices de la tabla `informes`
--
ALTER TABLE `informes`
  ADD PRIMARY KEY (`cod_informe`),
  ADD UNIQUE KEY `uk_informes_numero_ficha` (`numero_ficha`),
  ADD KEY `idx_informes_elaborado_por` (`cod_elaborado_por`);

--
-- Indices de la tabla `informes_secuencia`
--
ALTER TABLE `informes_secuencia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `informe_individual`
--
ALTER TABLE `informe_individual`
  ADD PRIMARY KEY (`cod_informe`),
  ADD KEY `idx_informe_individual_estudiante` (`cod_estudiante`),
  ADD KEY `fk_informe_individual_historia` (`cod_historia`),
  ADD KEY `fk_informe_individual_derivacion` (`cod_derivacion`),
  ADD KEY `fk_informe_individual_seguimiento` (`cod_seguimiento`);

--
-- Indices de la tabla `informe_resumen`
--
ALTER TABLE `informe_resumen`
  ADD PRIMARY KEY (`cod_informe`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`cod_inscripcion`),
  ADD UNIQUE KEY `uk_inscripcion_estudiante_seccion` (`cod_estudiante`,`cod_seccion`),
  ADD KEY `idx_inscripciones_seccion` (`cod_seccion`,`estado`);

--
-- Indices de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  ADD PRIMARY KEY (`cod_institucion`),
  ADD UNIQUE KEY `uk_instituciones_nombre` (`nombre`),
  ADD UNIQUE KEY `uk_instituciones_codigo` (`codigo`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`cod_materia`),
  ADD UNIQUE KEY `uk_materias_nombre` (`nombre`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`cod_modulo`),
  ADD UNIQUE KEY `uk_modulos_nombre` (`nombre`);

--
-- Indices de la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  ADD PRIMARY KEY (`cod_opcion`),
  ADD UNIQUE KEY `uk_opcion_grupo_descripcion` (`cod_grupo`,`descripcion`);

--
-- Indices de la tabla `paralelos`
--
ALTER TABLE `paralelos`
  ADD PRIMARY KEY (`cod_paralelo`),
  ADD UNIQUE KEY `uk_paralelos_nombre` (`nombre`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`cod_permiso`),
  ADD UNIQUE KEY `uk_permiso_rol_modulo` (`cod_rol`,`cod_modulo`),
  ADD KEY `idx_permisos_modulo` (`cod_modulo`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`cod_persona`),
  ADD UNIQUE KEY `uk_personas_ci` (`ci`),
  ADD UNIQUE KEY `uk_personas_correo` (`correo`),
  ADD KEY `idx_personas_nombre` (`apellidos`,`nombres`);

--
-- Indices de la tabla `responsables`
--
ALTER TABLE `responsables`
  ADD PRIMARY KEY (`cod_responsable`),
  ADD KEY `idx_responsables_nombre` (`nombres`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`cod_rol`),
  ADD UNIQUE KEY `uk_roles_nombre` (`nombre`);

--
-- Indices de la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD PRIMARY KEY (`cod_seccion`),
  ADD UNIQUE KEY `uk_seccion` (`cod_institucion`,`cod_curso`,`cod_paralelo`,`turno`,`gestion`),
  ADD KEY `idx_secciones_curso` (`cod_curso`),
  ADD KEY `idx_secciones_paralelo` (`cod_paralelo`);

--
-- Indices de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD PRIMARY KEY (`cod_seguimiento`),
  ADD KEY `idx_seguimientos_historia` (`cod_historia`,`fecha`),
  ADD KEY `idx_seguimientos_psicologa` (`cod_psicologa`),
  ADD KEY `idx_seguimientos_cita` (`cod_cita`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`cod_usuario`),
  ADD UNIQUE KEY `uk_usuarios_usuario` (`usuario`),
  ADD UNIQUE KEY `uk_usuarios_persona` (`cod_persona`),
  ADD KEY `idx_usuarios_rol` (`cod_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `cod_auditoria` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  MODIFY `cod_categoria` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `cod_cita` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `cod_curso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  MODIFY `cod_derivacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  MODIFY `cod_evidencia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `cod_docente` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `cod_estudiante` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos_opciones_historia`
--
ALTER TABLE `grupos_opciones_historia`
  MODIFY `cod_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  MODIFY `cod_historia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  MODIFY `cod_familiar` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `informes`
--
ALTER TABLE `informes`
  MODIFY `cod_informe` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `cod_inscripcion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  MODIFY `cod_institucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `cod_materia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `cod_modulo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  MODIFY `cod_opcion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `paralelos`
--
ALTER TABLE `paralelos`
  MODIFY `cod_paralelo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `cod_permiso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `personas`
--
ALTER TABLE `personas`
  MODIFY `cod_persona` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `responsables`
--
ALTER TABLE `responsables`
  MODIFY `cod_responsable` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `cod_rol` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `secciones`
--
ALTER TABLE `secciones`
  MODIFY `cod_seccion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  MODIFY `cod_seguimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `cod_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_usuarios` FOREIGN KEY (`cod_usuario`) REFERENCES `usuarios` (`cod_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_citas_derivaciones` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD CONSTRAINT `fk_derivaciones_docentes` FOREIGN KEY (`cod_docente`) REFERENCES `docentes` (`cod_docente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_materias` FOREIGN KEY (`cod_materia`) REFERENCES `materias` (`cod_materia`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_psicologa` FOREIGN KEY (`cod_psicologa_solicitada`) REFERENCES `usuarios` (`cod_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD CONSTRAINT `fk_derivacion_categorias_categoria` FOREIGN KEY (`cod_categoria`) REFERENCES `categorias_derivacion` (`cod_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivacion_categorias_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  ADD CONSTRAINT `fk_evidencia_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD CONSTRAINT `fk_docentes_personas` FOREIGN KEY (`cod_persona`) REFERENCES `personas` (`cod_persona`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  ADD CONSTRAINT `fk_docente_materias_docente` FOREIGN KEY (`cod_docente`) REFERENCES `docentes` (`cod_docente`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_docente_materias_materia` FOREIGN KEY (`cod_materia`) REFERENCES `materias` (`cod_materia`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `estudiante_responsables`
--
ALTER TABLE `estudiante_responsables`
  ADD CONSTRAINT `fk_est_resp_estudiante` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_est_resp_responsable` FOREIGN KEY (`cod_responsable`) REFERENCES `responsables` (`cod_responsable`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD CONSTRAINT `fk_historias_cita` FOREIGN KEY (`cod_cita_origen`) REFERENCES `citas` (`cod_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_derivacion` FOREIGN KEY (`cod_derivacion_origen`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD CONSTRAINT `fk_historia_familiares_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD CONSTRAINT `fk_historia_opciones_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historia_opciones_opcion` FOREIGN KEY (`cod_opcion`) REFERENCES `opciones_historia` (`cod_opcion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `informes`
--
ALTER TABLE `informes`
  ADD CONSTRAINT `fk_informes_elaborado_por` FOREIGN KEY (`cod_elaborado_por`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `informe_individual`
--
ALTER TABLE `informe_individual`
  ADD CONSTRAINT `fk_informe_individual_derivacion` FOREIGN KEY (`cod_derivacion`) REFERENCES `derivaciones` (`cod_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_estudiante` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_historia` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_informe` FOREIGN KEY (`cod_informe`) REFERENCES `informes` (`cod_informe`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_seguimiento` FOREIGN KEY (`cod_seguimiento`) REFERENCES `seguimientos` (`cod_seguimiento`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `informe_resumen`
--
ALTER TABLE `informe_resumen`
  ADD CONSTRAINT `fk_informe_resumen_informe` FOREIGN KEY (`cod_informe`) REFERENCES `informes` (`cod_informe`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `fk_inscripciones_estudiantes` FOREIGN KEY (`cod_estudiante`) REFERENCES `estudiantes` (`cod_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscripciones_secciones` FOREIGN KEY (`cod_seccion`) REFERENCES `secciones` (`cod_seccion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  ADD CONSTRAINT `fk_opciones_historia_grupo` FOREIGN KEY (`cod_grupo`) REFERENCES `grupos_opciones_historia` (`cod_grupo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `fk_permisos_modulos` FOREIGN KEY (`cod_modulo`) REFERENCES `modulos` (`cod_modulo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_permisos_roles` FOREIGN KEY (`cod_rol`) REFERENCES `roles` (`cod_rol`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD CONSTRAINT `fk_secciones_cursos` FOREIGN KEY (`cod_curso`) REFERENCES `cursos` (`cod_curso`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_secciones_instituciones` FOREIGN KEY (`cod_institucion`) REFERENCES `instituciones` (`cod_institucion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_secciones_paralelos` FOREIGN KEY (`cod_paralelo`) REFERENCES `paralelos` (`cod_paralelo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD CONSTRAINT `fk_seguimientos_citas` FOREIGN KEY (`cod_cita`) REFERENCES `citas` (`cod_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguimientos_historias` FOREIGN KEY (`cod_historia`) REFERENCES `historias_clinicas` (`cod_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguimientos_psicologa` FOREIGN KEY (`cod_psicologa`) REFERENCES `usuarios` (`cod_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_personas` FOREIGN KEY (`cod_persona`) REFERENCES `personas` (`cod_persona`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`cod_rol`) REFERENCES `roles` (`cod_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
