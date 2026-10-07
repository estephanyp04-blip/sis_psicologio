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
  `id_auditoria` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
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
  `id_categoria` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_derivacion`
--

INSERT INTO `categorias_derivacion` (`id_categoria`, `nombre`, `estado`) VALUES
(1, 'Rendimiento Académico', 'Activo'),
(2, 'Acoso escolar / Acoso', 'Activo'),
(3, 'Conducta en Aula', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int(10) UNSIGNED NOT NULL,
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_psicologa` int(10) UNSIGNED NOT NULL,
  `id_derivacion` int(10) UNSIGNED DEFAULT NULL,
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
  `id_curso` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Secundaria',
  `orden` tinyint(3) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre`, `nivel`, `orden`, `estado`) VALUES
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
  `id_derivacion` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_docente` int(10) UNSIGNED NOT NULL,
  `id_materia` int(10) UNSIGNED DEFAULT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `estado` enum('Pendiente','En seguimiento','Atendido') NOT NULL DEFAULT 'Pendiente',
  `solicitar_cita` tinyint(1) NOT NULL DEFAULT 0,
  `id_psicologa_solicitada` int(10) UNSIGNED DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_categorias`
--

CREATE TABLE `derivacion_categorias` (
  `id_derivacion` int(10) UNSIGNED NOT NULL,
  `id_categoria` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_evidencias`
--

CREATE TABLE `derivacion_evidencias` (
  `id_evidencia` int(10) UNSIGNED NOT NULL,
  `id_derivacion` int(10) UNSIGNED NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `id_docente` int(10) UNSIGNED NOT NULL,
  `id_persona` int(10) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_materias`
--

CREATE TABLE `docente_materias` (
  `id_docente` int(10) UNSIGNED NOT NULL,
  `id_materia` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(10) UNSIGNED NOT NULL,
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
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_responsable` int(10) UNSIGNED NOT NULL,
  `parentesco` enum('Padre','Madre','Tutor','Otro') NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos_opciones_historia`
--

CREATE TABLE `grupos_opciones_historia` (
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grupos_opciones_historia`
--

INSERT INTO `grupos_opciones_historia` (`id_grupo`, `codigo`, `nombre`) VALUES
(1, 'atencion_distraccion', 'Atención y distracción'),
(2, 'actividad_motora', 'Actividad motora'),
(3, 'dificultades_socioemocionales', 'Dificultades socioemocionales'),
(4, 'estrategias_previas', 'Estrategias previas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historias_clinicas`
--

CREATE TABLE `historias_clinicas` (
  `id_historia` int(10) UNSIGNED NOT NULL,
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_psicologa` int(10) UNSIGNED NOT NULL,
  `id_derivacion_origen` int(10) UNSIGNED DEFAULT NULL,
  `id_cita_origen` int(10) UNSIGNED DEFAULT NULL,
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
  `id_familiar` int(10) UNSIGNED NOT NULL,
  `id_historia` int(10) UNSIGNED NOT NULL,
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
  `id_historia` int(10) UNSIGNED NOT NULL,
  `id_opcion` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informes`
--

CREATE TABLE `informes` (
  `id_informe` int(10) UNSIGNED NOT NULL,
  `numero_ficha` varchar(30) NOT NULL,
  `id_elaborado_por` int(10) UNSIGNED NOT NULL,
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
  `id_informe` int(10) UNSIGNED NOT NULL,
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_historia` int(10) UNSIGNED DEFAULT NULL,
  `id_derivacion` int(10) UNSIGNED DEFAULT NULL,
  `id_seguimiento` int(10) UNSIGNED DEFAULT NULL,
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
  `id_informe` int(10) UNSIGNED NOT NULL,
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
  `id_inscripcion` int(10) UNSIGNED NOT NULL,
  `id_estudiante` int(10) UNSIGNED NOT NULL,
  `id_seccion` int(10) UNSIGNED NOT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado` enum('Activo','Retirado','Finalizado') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `instituciones`
--

CREATE TABLE `instituciones` (
  `id_institucion` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `instituciones`
--

INSERT INTO `instituciones` (`id_institucion`, `nombre`, `codigo`, `direccion`, `telefono`, `estado`) VALUES
(1, 'U.E. Cañada Pailita B', 'UE-CPB', NULL, NULL, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id_materia`, `nombre`, `estado`) VALUES
(1, 'Ciencias Sociales', 'Activo'),
(2, 'Matemática', 'Activo'),
(3, 'Biología', 'Activo'),
(4, 'Inglés', 'Activo'),
(5, 'Lenguaje', 'Activo'),
(6, 'Educación Física', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id_modulo` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `ruta` varchar(100) DEFAULT NULL,
  `icono` varchar(60) DEFAULT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id_modulo`, `nombre`, `ruta`, `icono`, `orden`, `estado`) VALUES
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
  `id_opcion` int(10) UNSIGNED NOT NULL,
  `id_grupo` int(10) UNSIGNED NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `opciones_historia`
--

INSERT INTO `opciones_historia` (`id_opcion`, `id_grupo`, `descripcion`, `estado`) VALUES
(1, 1, 'Se distrae mirando a cualquier lado', 'Activo'),
(2, 1, 'Se olvida sus carpetas o tareas', 'Activo'),
(3, 1, 'Habla constantemente', 'Activo'),
(4, 2, 'Se mueve constantemente en su asiento', 'Activo'),
(5, 3, 'Falta de interés por aprender', 'Activo'),
(6, 4, 'Conversación con el tutor/a a cargo', 'Activo'),
(7, 4, 'Mayor supervisión', 'Activo'),
(8, 2, 'Se para y se sienta constantemente', 'Activo'),
(9, 4, 'Ninguna', 'Activo'),
(10, 3, 'No participa en las actividades', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paralelos`
--

CREATE TABLE `paralelos` (
  `id_paralelo` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paralelos`
--

INSERT INTO `paralelos` (`id_paralelo`, `nombre`, `estado`) VALUES
(1, 'A', 'Activo'),
(2, 'B', 'Activo'),
(3, 'C', 'Activo'),
(4, 'D', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int(10) UNSIGNED NOT NULL,
  `id_rol` int(10) UNSIGNED NOT NULL,
  `id_modulo` int(10) UNSIGNED NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear` tinyint(1) NOT NULL DEFAULT 0,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_permiso`, `id_rol`, `id_modulo`, `puede_ver`, `puede_crear`, `puede_editar`, `puede_eliminar`) VALUES
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
  `id_persona` int(10) UNSIGNED NOT NULL,
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

INSERT INTO `personas` (`id_persona`, `nombres`, `apellidos`, `ci`, `correo`, `telefono`, `fecha_registro`) VALUES
(1, 'Administrador', 'Sistema', NULL, 'admin@local.test', NULL, '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `responsables`
--

CREATE TABLE `responsables` (
  `id_responsable` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(150) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `estado`, `fecha_registro`) VALUES
(1, 'Administrador', 'Acceso total al sistema', 'Activo', '2026-10-06 05:12:57'),
(2, 'Psicóloga', 'Gestiona derivaciones, citas, historias clínicas, seguimientos e informes', 'Activo', '2026-10-06 05:12:57'),
(3, 'Docente', 'Consulta estudiantes y registra derivaciones', 'Activo', '2026-10-06 05:12:57'),
(4, 'Director', 'Consulta informes y estadísticas autorizadas', 'Activo', '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones`
--

CREATE TABLE `secciones` (
  `id_seccion` int(10) UNSIGNED NOT NULL,
  `id_institucion` int(10) UNSIGNED NOT NULL,
  `id_curso` int(10) UNSIGNED NOT NULL,
  `id_paralelo` int(10) UNSIGNED NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `gestion` year(4) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `secciones`
--

INSERT INTO `secciones` (`id_seccion`, `id_institucion`, `id_curso`, `id_paralelo`, `turno`, `gestion`, `estado`) VALUES
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
  `id_seguimiento` int(10) UNSIGNED NOT NULL,
  `id_historia` int(10) UNSIGNED NOT NULL,
  `id_psicologa` int(10) UNSIGNED NOT NULL,
  `id_cita` int(10) UNSIGNED DEFAULT NULL,
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
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_persona` int(10) UNSIGNED NOT NULL,
  `usuario` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(10) UNSIGNED NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_persona`, `usuario`, `password`, `id_rol`, `estado`, `ultimo_acceso`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 1, 'admin', '$2y$12$yNjW5ahkIFUDxm0bdij8LeM0AFthoNOtDSOFGD2wEDQeQ76x0zwSu', 1, 'Activo', NULL, '2026-10-06 05:12:57', '2026-10-06 05:12:57');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_citas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_citas` (
`id_cita` int(10) unsigned
,`id_estudiante` int(10) unsigned
,`id_psicologa` int(10) unsigned
,`id_derivacion` int(10) unsigned
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
`id_derivacion` int(10) unsigned
,`fecha` date
,`id_estudiante` int(10) unsigned
,`id_docente` int(10) unsigned
,`id_materia` int(10) unsigned
,`materia` varchar(100)
,`motivo` text
,`observaciones` text
,`prioridad` enum('Alta','Media','Baja')
,`estado` enum('Pendiente','En seguimiento','Atendido')
,`solicitar_cita` tinyint(1)
,`id_psicologa_solicitada` int(10) unsigned
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
`id_estudiante` int(10) unsigned
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
,`id_seccion` int(10) unsigned
,`id_curso` int(10) unsigned
,`curso` varchar(50)
,`id_paralelo` int(10) unsigned
,`paralelo` varchar(10)
,`turno` enum('Mañana','Tarde')
,`gestion` year(4)
,`id_institucion` int(10) unsigned
,`institucion` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_citas`
--
DROP TABLE IF EXISTS `vista_citas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_citas`  AS SELECT `c`.`id_cita` AS `id_cita`, `c`.`id_estudiante` AS `id_estudiante`, `c`.`id_psicologa` AS `id_psicologa`, `c`.`id_derivacion` AS `id_derivacion`, `c`.`fecha` AS `fecha`, `c`.`hora` AS `hora`, `c`.`estado` AS `estado`, `c`.`observaciones` AS `observaciones`, `c`.`motivo` AS `motivo`, `c`.`fecha_registro` AS `fecha_registro`, `c`.`fecha_actualizacion` AS `fecha_actualizacion`, concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`, concat(`pp`.`nombres`,' ',`pp`.`apellidos`) AS `psicologa` FROM (((`citas` `c` join `estudiantes` `e` on(`e`.`id_estudiante` = `c`.`id_estudiante`)) join `usuarios` `u` on(`u`.`id_usuario` = `c`.`id_psicologa`)) join `personas` `pp` on(`pp`.`id_persona` = `u`.`id_persona`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_derivaciones`
--
DROP TABLE IF EXISTS `vista_derivaciones`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_derivaciones`  AS SELECT `d`.`id_derivacion` AS `id_derivacion`, `d`.`fecha` AS `fecha`, `d`.`id_estudiante` AS `id_estudiante`, `d`.`id_docente` AS `id_docente`, `d`.`id_materia` AS `id_materia`, `m`.`nombre` AS `materia`, `d`.`motivo` AS `motivo`, `d`.`observaciones` AS `observaciones`, `d`.`prioridad` AS `prioridad`, `d`.`estado` AS `estado`, `d`.`solicitar_cita` AS `solicitar_cita`, `d`.`id_psicologa_solicitada` AS `id_psicologa_solicitada`, `d`.`fecha_registro` AS `fecha_registro`, `d`.`fecha_actualizacion` AS `fecha_actualizacion`, concat(`e`.`apellidos`,' ',`e`.`nombres`) AS `estudiante`, `ve`.`curso` AS `curso`, `ve`.`paralelo` AS `paralelo`, `ve`.`turno` AS `turno`, concat(`pd`.`apellidos`,' ',`pd`.`nombres`) AS `docente` FROM (((((`derivaciones` `d` join `estudiantes` `e` on(`e`.`id_estudiante` = `d`.`id_estudiante`)) join `docentes` `doc` on(`doc`.`id_docente` = `d`.`id_docente`)) join `personas` `pd` on(`pd`.`id_persona` = `doc`.`id_persona`)) left join `materias` `m` on(`m`.`id_materia` = `d`.`id_materia`)) left join `vista_estudiantes` `ve` on(`ve`.`id_estudiante` = `e`.`id_estudiante`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_estudiantes`
--
DROP TABLE IF EXISTS `vista_estudiantes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_estudiantes`  AS SELECT `e`.`id_estudiante` AS `id_estudiante`, `e`.`codigo` AS `codigo`, `e`.`ci` AS `ci`, `e`.`rude` AS `rude`, `e`.`nombres` AS `nombres`, `e`.`apellidos` AS `apellidos`, `e`.`fecha_nacimiento` AS `fecha_nacimiento`, `e`.`lugar_nacimiento` AS `lugar_nacimiento`, `e`.`sexo` AS `sexo`, `e`.`direccion` AS `direccion`, `e`.`telefono` AS `telefono`, `e`.`estado` AS `estado`, `s`.`id_seccion` AS `id_seccion`, `c`.`id_curso` AS `id_curso`, `c`.`nombre` AS `curso`, `p`.`id_paralelo` AS `id_paralelo`, `p`.`nombre` AS `paralelo`, `s`.`turno` AS `turno`, `s`.`gestion` AS `gestion`, `i`.`id_institucion` AS `id_institucion`, `i`.`nombre` AS `institucion` FROM (((((`estudiantes` `e` left join `inscripciones` `ins` on(`ins`.`id_estudiante` = `e`.`id_estudiante` and `ins`.`estado` = 'Activo')) left join `secciones` `s` on(`s`.`id_seccion` = `ins`.`id_seccion`)) left join `cursos` `c` on(`c`.`id_curso` = `s`.`id_curso`)) left join `paralelos` `p` on(`p`.`id_paralelo` = `s`.`id_paralelo`)) left join `instituciones` `i` on(`i`.`id_institucion` = `s`.`id_institucion`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `idx_auditoria_usuario` (`id_usuario`),
  ADD KEY `idx_auditoria_fecha` (`fecha`);

--
-- Indices de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `uk_categorias_derivacion_nombre` (`nombre`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD UNIQUE KEY `uk_citas_psicologa_horario` (`id_psicologa`,`fecha`,`hora`,`turno_reservado`),
  ADD KEY `idx_citas_estudiante` (`id_estudiante`,`fecha`),
  ADD KEY `idx_citas_derivacion` (`id_derivacion`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`),
  ADD UNIQUE KEY `uk_cursos_nombre_nivel` (`nombre`,`nivel`),
  ADD UNIQUE KEY `uk_cursos_nivel_orden` (`nivel`,`orden`);

--
-- Indices de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD PRIMARY KEY (`id_derivacion`),
  ADD KEY `idx_derivaciones_estudiante` (`id_estudiante`),
  ADD KEY `idx_derivaciones_docente` (`id_docente`),
  ADD KEY `idx_derivaciones_materia` (`id_materia`),
  ADD KEY `idx_derivaciones_estado` (`estado`,`prioridad`,`fecha`),
  ADD KEY `idx_derivaciones_psicologa` (`id_psicologa_solicitada`);

--
-- Indices de la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD PRIMARY KEY (`id_derivacion`,`id_categoria`),
  ADD KEY `idx_derivacion_categorias_categoria` (`id_categoria`);

--
-- Indices de la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  ADD PRIMARY KEY (`id_evidencia`),
  ADD KEY `idx_evidencia_derivacion` (`id_derivacion`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`id_docente`),
  ADD UNIQUE KEY `uk_docentes_persona` (`id_persona`);

--
-- Indices de la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  ADD PRIMARY KEY (`id_docente`,`id_materia`),
  ADD KEY `idx_docente_materias_materia` (`id_materia`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `uk_estudiantes_codigo` (`codigo`),
  ADD UNIQUE KEY `uk_estudiantes_ci` (`ci`),
  ADD UNIQUE KEY `uk_estudiantes_rude` (`rude`),
  ADD KEY `idx_estudiantes_nombre` (`apellidos`,`nombres`);

--
-- Indices de la tabla `estudiante_responsables`
--
ALTER TABLE `estudiante_responsables`
  ADD PRIMARY KEY (`id_estudiante`,`id_responsable`,`parentesco`),
  ADD KEY `idx_est_responsable` (`id_responsable`);

--
-- Indices de la tabla `grupos_opciones_historia`
--
ALTER TABLE `grupos_opciones_historia`
  ADD PRIMARY KEY (`id_grupo`),
  ADD UNIQUE KEY `uk_grupos_opciones_codigo` (`codigo`);

--
-- Indices de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD PRIMARY KEY (`id_historia`),
  ADD UNIQUE KEY `uk_historia_estudiante` (`id_estudiante`),
  ADD KEY `idx_historias_psicologa` (`id_psicologa`),
  ADD KEY `idx_historias_derivacion` (`id_derivacion_origen`),
  ADD KEY `idx_historias_cita` (`id_cita_origen`);

--
-- Indices de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD PRIMARY KEY (`id_familiar`),
  ADD KEY `idx_historia_familiares_historia` (`id_historia`);

--
-- Indices de la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD PRIMARY KEY (`id_historia`,`id_opcion`),
  ADD KEY `idx_historia_opciones_opcion` (`id_opcion`);

--
-- Indices de la tabla `informes`
--
ALTER TABLE `informes`
  ADD PRIMARY KEY (`id_informe`),
  ADD UNIQUE KEY `uk_informes_numero_ficha` (`numero_ficha`),
  ADD KEY `idx_informes_elaborado_por` (`id_elaborado_por`);

--
-- Indices de la tabla `informes_secuencia`
--
ALTER TABLE `informes_secuencia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `informe_individual`
--
ALTER TABLE `informe_individual`
  ADD PRIMARY KEY (`id_informe`),
  ADD KEY `idx_informe_individual_estudiante` (`id_estudiante`),
  ADD KEY `fk_informe_individual_historia` (`id_historia`),
  ADD KEY `fk_informe_individual_derivacion` (`id_derivacion`),
  ADD KEY `fk_informe_individual_seguimiento` (`id_seguimiento`);

--
-- Indices de la tabla `informe_resumen`
--
ALTER TABLE `informe_resumen`
  ADD PRIMARY KEY (`id_informe`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id_inscripcion`),
  ADD UNIQUE KEY `uk_inscripcion_estudiante_seccion` (`id_estudiante`,`id_seccion`),
  ADD KEY `idx_inscripciones_seccion` (`id_seccion`,`estado`);

--
-- Indices de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  ADD PRIMARY KEY (`id_institucion`),
  ADD UNIQUE KEY `uk_instituciones_nombre` (`nombre`),
  ADD UNIQUE KEY `uk_instituciones_codigo` (`codigo`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`),
  ADD UNIQUE KEY `uk_materias_nombre` (`nombre`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id_modulo`),
  ADD UNIQUE KEY `uk_modulos_nombre` (`nombre`);

--
-- Indices de la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  ADD PRIMARY KEY (`id_opcion`),
  ADD UNIQUE KEY `uk_opcion_grupo_descripcion` (`id_grupo`,`descripcion`);

--
-- Indices de la tabla `paralelos`
--
ALTER TABLE `paralelos`
  ADD PRIMARY KEY (`id_paralelo`),
  ADD UNIQUE KEY `uk_paralelos_nombre` (`nombre`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`),
  ADD UNIQUE KEY `uk_permiso_rol_modulo` (`id_rol`,`id_modulo`),
  ADD KEY `idx_permisos_modulo` (`id_modulo`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`id_persona`),
  ADD UNIQUE KEY `uk_personas_ci` (`ci`),
  ADD UNIQUE KEY `uk_personas_correo` (`correo`),
  ADD KEY `idx_personas_nombre` (`apellidos`,`nombres`);

--
-- Indices de la tabla `responsables`
--
ALTER TABLE `responsables`
  ADD PRIMARY KEY (`id_responsable`),
  ADD KEY `idx_responsables_nombre` (`nombres`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uk_roles_nombre` (`nombre`);

--
-- Indices de la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD PRIMARY KEY (`id_seccion`),
  ADD UNIQUE KEY `uk_seccion` (`id_institucion`,`id_curso`,`id_paralelo`,`turno`,`gestion`),
  ADD KEY `idx_secciones_curso` (`id_curso`),
  ADD KEY `idx_secciones_paralelo` (`id_paralelo`);

--
-- Indices de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD PRIMARY KEY (`id_seguimiento`),
  ADD KEY `idx_seguimientos_historia` (`id_historia`,`fecha`),
  ADD KEY `idx_seguimientos_psicologa` (`id_psicologa`),
  ADD KEY `idx_seguimientos_cita` (`id_cita`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uk_usuarios_usuario` (`usuario`),
  ADD UNIQUE KEY `uk_usuarios_persona` (`id_persona`),
  ADD KEY `idx_usuarios_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  MODIFY `id_categoria` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  MODIFY `id_derivacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  MODIFY `id_evidencia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `id_docente` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `grupos_opciones_historia`
--
ALTER TABLE `grupos_opciones_historia`
  MODIFY `id_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  MODIFY `id_historia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  MODIFY `id_familiar` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `informes`
--
ALTER TABLE `informes`
  MODIFY `id_informe` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id_inscripcion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  MODIFY `id_institucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id_modulo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  MODIFY `id_opcion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `paralelos`
--
ALTER TABLE `paralelos`
  MODIFY `id_paralelo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `personas`
--
ALTER TABLE `personas`
  MODIFY `id_persona` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `responsables`
--
ALTER TABLE `responsables`
  MODIFY `id_responsable` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `secciones`
--
ALTER TABLE `secciones`
  MODIFY `id_seccion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  MODIFY `id_seguimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_citas_derivaciones` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_psicologa` FOREIGN KEY (`id_psicologa`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD CONSTRAINT `fk_derivaciones_docentes` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_materias` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_psicologa` FOREIGN KEY (`id_psicologa_solicitada`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD CONSTRAINT `fk_derivacion_categorias_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_derivacion` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivacion_categorias_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  ADD CONSTRAINT `fk_evidencia_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD CONSTRAINT `fk_docentes_personas` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  ADD CONSTRAINT `fk_docente_materias_docente` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_docente_materias_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `estudiante_responsables`
--
ALTER TABLE `estudiante_responsables`
  ADD CONSTRAINT `fk_est_resp_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_est_resp_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsables` (`id_responsable`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD CONSTRAINT `fk_historias_cita` FOREIGN KEY (`id_cita_origen`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_derivacion` FOREIGN KEY (`id_derivacion_origen`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_psicologa` FOREIGN KEY (`id_psicologa`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD CONSTRAINT `fk_historia_familiares_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD CONSTRAINT `fk_historia_opciones_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historia_opciones_opcion` FOREIGN KEY (`id_opcion`) REFERENCES `opciones_historia` (`id_opcion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `informes`
--
ALTER TABLE `informes`
  ADD CONSTRAINT `fk_informes_elaborado_por` FOREIGN KEY (`id_elaborado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `informe_individual`
--
ALTER TABLE `informe_individual`
  ADD CONSTRAINT `fk_informe_individual_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_informe` FOREIGN KEY (`id_informe`) REFERENCES `informes` (`id_informe`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informe_individual_seguimiento` FOREIGN KEY (`id_seguimiento`) REFERENCES `seguimientos` (`id_seguimiento`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `informe_resumen`
--
ALTER TABLE `informe_resumen`
  ADD CONSTRAINT `fk_informe_resumen_informe` FOREIGN KEY (`id_informe`) REFERENCES `informes` (`id_informe`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `fk_inscripciones_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscripciones_secciones` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id_seccion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `opciones_historia`
--
ALTER TABLE `opciones_historia`
  ADD CONSTRAINT `fk_opciones_historia_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos_opciones_historia` (`id_grupo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `fk_permisos_modulos` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_permisos_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD CONSTRAINT `fk_secciones_cursos` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_secciones_instituciones` FOREIGN KEY (`id_institucion`) REFERENCES `instituciones` (`id_institucion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_secciones_paralelos` FOREIGN KEY (`id_paralelo`) REFERENCES `paralelos` (`id_paralelo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD CONSTRAINT `fk_seguimientos_citas` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguimientos_historias` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguimientos_psicologa` FOREIGN KEY (`id_psicologa`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_personas` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id_persona`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
