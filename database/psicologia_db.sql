-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-09-2026 a las 03:44:16
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

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
-- Estructura de tabla para la tabla `categorias_derivacion`
--

CREATE TABLE `categorias_derivacion` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_derivacion`
--

INSERT INTO `categorias_derivacion` (`id_categoria`, `nombre`, `descripcion`) VALUES
(1, 'Rendimiento Académico', 'Baja de notas, falta de atención o dificultades de aprendizaje.'),
(2, 'Conducta en Aula', 'Indisciplina, agresividad o interrupciones.'),
(3, 'Social / Emocional', 'Aislamiento, tristeza, llanto recurrente o ansiedad.'),
(4, 'Dinámica Familiar', 'Problemas en el hogar, negligencia o conflictos familiares.'),
(5, 'Acoso escolar / Acoso', 'Víctima o agresor de bullying o ciberacoso.'),
(6, 'Otro', 'Situación no especificada anteriormente.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL,
  `id_derivacion` int(11) DEFAULT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('Pendiente','Atendida','Cancelada','Reprogramada') NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivaciones`
--

CREATE TABLE `derivaciones` (
  `id_derivacion` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_profesional_asignado` int(11) DEFAULT NULL,
  `materia` varchar(150) NOT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `estado` enum('Pendiente','En seguimiento','En atención','Atendido','Finalizado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `solicita_cita` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `categorias` text DEFAULT NULL,
  `solicitar_cita` tinyint(1) NOT NULL DEFAULT 0,
  `id_profesional` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_categorias`
--

CREATE TABLE `derivacion_categorias` (
  `id_derivacion` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `derivacion_evidencias`
--

CREATE TABLE `derivacion_evidencias` (
  `id_evidencia` int(11) NOT NULL,
  `id_derivacion` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(50) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `id_docente` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombres` varchar(60) NOT NULL,
  `apellidos` varchar(60) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `materia` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `ci` varchar(20) NOT NULL,
  `nombres` varchar(60) NOT NULL,
  `apellidos` varchar(60) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `lugar_nacimiento` varchar(100) DEFAULT NULL,
  `sexo` enum('M','F') DEFAULT NULL,
  `curso` varchar(20) NOT NULL,
  `paralelo` varchar(5) NOT NULL,
  `turno` enum('Mañana','Tarde') NOT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `nombre_tutor` varchar(100) DEFAULT NULL,
  `telefono_tutor` varchar(20) DEFAULT NULL,
  `estado` enum('Activo','Retirado') NOT NULL DEFAULT 'Activo',
  `genero` varchar(20) DEFAULT NULL,
  `padre` varchar(150) DEFAULT NULL,
  `madre` varchar(150) DEFAULT NULL,
  `tutor` varchar(150) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historias_clinicas`
--

CREATE TABLE `historias_clinicas` (
  `id_historia` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_derivacion` int(11) DEFAULT NULL,
  `fecha_apertura` date NOT NULL,
  `nombre_completo` varchar(130) DEFAULT NULL,
  `fecha_nacimiento_estudiante` date DEFAULT NULL,
  `lugar_nacimiento` varchar(100) DEFAULT NULL,
  `celular_estudiante` varchar(20) DEFAULT NULL,
  `padre_madre` varchar(100) DEFAULT NULL,
  `derivado_por` varchar(120) DEFAULT NULL,
  `fecha_derivacion` date DEFAULT NULL,
  `materia_derivacion` varchar(150) DEFAULT NULL,
  `prioridad_derivacion` enum('Alta','Media','Baja') DEFAULT NULL,
  `observaciones_derivacion` text DEFAULT NULL,
  `tutor_curso` varchar(120) DEFAULT NULL,
  `situacion_escolar` enum('Muy buena','Buena','Regular','Deficiente') DEFAULT NULL,
  `cursos_repetidos` varchar(255) DEFAULT NULL,
  `dificultad_escolar` text DEFAULT NULL,
  `materia_agrada` varchar(100) DEFAULT NULL,
  `materia_desagrada` varchar(100) DEFAULT NULL,
  `relacion_escolar` text DEFAULT NULL,
  `talla` varchar(20) DEFAULT NULL,
  `peso` varchar(20) DEFAULT NULL,
  `valoracion` varchar(255) DEFAULT NULL,
  `enfermedades_actuales` text DEFAULT NULL,
  `motivo_consulta` text DEFAULT NULL,
  `conductas_riesgo` text DEFAULT NULL,
  `atencion_distraccion` text DEFAULT NULL,
  `actividad_motora` text DEFAULT NULL,
  `adaptacion_normas` text DEFAULT NULL,
  `dificultades_socioemocionales` text DEFAULT NULL,
  `estrategias_previas` text DEFAULT NULL,
  `antecedentes` text DEFAULT NULL,
  `familiares` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`familiares`)),
  `valoracion_familiar` enum('Muy buena','Buena','Regular','Conflictiva') DEFAULT NULL,
  `contexto_familiar` text DEFAULT NULL,
  `impresion_diagnostica` text DEFAULT NULL,
  `plan_intervencion` text DEFAULT NULL,
  `evolucion_caso` tinyint(4) DEFAULT NULL,
  `estado` enum('Activa','En seguimiento','Cerrada') NOT NULL DEFAULT 'Activa',
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `evaluacion_inicial` text DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historia_familiares`
--

CREATE TABLE `historia_familiares` (
  `id_familiar` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `relacion` varchar(100) DEFAULT NULL,
  `profesion` varchar(150) DEFAULT NULL,
  `ocupacion` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historia_opciones`
--

CREATE TABLE `historia_opciones` (
  `id_opcion` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `grupo` varchar(80) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informes`
--

CREATE TABLE `informes` (
  `id_informe` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `elaborado_por` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `resumen` text NOT NULL,
  `recomendaciones` text DEFAULT NULL,
  `numero_ficha` varchar(30) NOT NULL DEFAULT '',
  `numero_atenciones` int(11) NOT NULL DEFAULT 0,
  `referido_por` varchar(150) DEFAULT NULL,
  `id_derivacion` int(11) DEFAULT NULL,
  `tipo_atencion` text DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `aspecto_cognitivo` text DEFAULT NULL,
  `aspectos_afectivos` text DEFAULT NULL,
  `diagnostico_acuerdos` text DEFAULT NULL,
  `recibido_por` varchar(150) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Borrador',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(4, 'Director'),
(3, 'Docente'),
(2, 'Psicóloga');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimientos`
--

CREATE TABLE `seguimientos` (
  `id_seguimiento` int(11) NOT NULL,
  `id_historia` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `evolucion` text NOT NULL,
  `recomendaciones` text DEFAULT NULL,
  `proxima_cita` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `usuario` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `id_rol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `fk_citas_derivaciones` (`id_derivacion`),
  ADD KEY `idx_citas_fecha` (`fecha`,`hora`),
  ADD KEY `idx_citas_estudiante` (`id_estudiante`),
  ADD KEY `idx_citas_usuario` (`id_usuario`),
  ADD KEY `idx_citas_estado` (`estado`);

--
-- Indices de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD PRIMARY KEY (`id_derivacion`),
  ADD KEY `idx_derivaciones_estado` (`estado`,`prioridad`,`fecha`),
  ADD KEY `idx_derivaciones_estudiante` (`id_estudiante`),
  ADD KEY `idx_derivaciones_docente` (`id_docente`),
  ADD KEY `idx_derivaciones_profesional` (`id_profesional_asignado`),
  ADD KEY `idx_derivaciones_fecha` (`fecha`),
  ADD KEY `idx_derivaciones_materia` (`materia`);

--
-- Indices de la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD PRIMARY KEY (`id_derivacion`,`id_categoria`),
  ADD KEY `fk_derivacion_categoria_categoria` (`id_categoria`);

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
  ADD UNIQUE KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_docentes_materia` (`materia`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_estudiantes_curso` (`curso`,`paralelo`,`turno`),
  ADD KEY `idx_estudiantes_apellidos` (`apellidos`),
  ADD KEY `idx_estudiantes_estado` (`estado`);

--
-- Indices de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD PRIMARY KEY (`id_historia`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `fk_historias_usuarios` (`id_usuario`),
  ADD KEY `idx_historias_estado` (`estado`),
  ADD KEY `idx_historias_fecha` (`fecha_apertura`),
  ADD KEY `idx_historias_derivacion` (`id_derivacion`);

--
-- Indices de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD PRIMARY KEY (`id_familiar`),
  ADD KEY `idx_familiares_historia` (`id_historia`);

--
-- Indices de la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD PRIMARY KEY (`id_opcion`),
  ADD KEY `idx_opciones_historia` (`id_historia`);

--
-- Indices de la tabla `informes`
--
ALTER TABLE `informes`
  ADD PRIMARY KEY (`id_informe`),
  ADD KEY `fk_informes_usuarios` (`elaborado_por`),
  ADD KEY `idx_informes_estudiante` (`id_estudiante`),
  ADD KEY `idx_informes_historia` (`id_historia`),
  ADD KEY `idx_informes_fecha` (`fecha`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD PRIMARY KEY (`id_seguimiento`),
  ADD KEY `fk_seguimientos_usuarios` (`id_usuario`),
  ADD KEY `idx_seguimientos_historia` (`id_historia`),
  ADD KEY `idx_seguimientos_fecha` (`fecha`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `idx_usuarios_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias_derivacion`
--
ALTER TABLE `categorias_derivacion`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  MODIFY `id_derivacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  MODIFY `id_evidencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  MODIFY `id_historia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  MODIFY `id_familiar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `informes`
--
ALTER TABLE `informes`
  MODIFY `id_informe` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  MODIFY `id_seguimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_citas_derivaciones` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivaciones`
--
ALTER TABLE `derivaciones`
  ADD CONSTRAINT `fk_derivaciones_docentes` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivaciones_profesional` FOREIGN KEY (`id_profesional_asignado`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_categorias`
--
ALTER TABLE `derivacion_categorias`
  ADD CONSTRAINT `fk_derivacion_categoria_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_derivacion` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_derivacion_categoria_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `derivacion_evidencias`
--
ALTER TABLE `derivacion_evidencias`
  ADD CONSTRAINT `fk_evidencia_derivacion` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD CONSTRAINT `fk_docentes_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historias_clinicas`
--
ALTER TABLE `historias_clinicas`
  ADD CONSTRAINT `fk_historias_derivaciones` FOREIGN KEY (`id_derivacion`) REFERENCES `derivaciones` (`id_derivacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historias_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historia_familiares`
--
ALTER TABLE `historia_familiares`
  ADD CONSTRAINT `fk_familiares_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historia_opciones`
--
ALTER TABLE `historia_opciones`
  ADD CONSTRAINT `fk_opciones_historia` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `informes`
--
ALTER TABLE `informes`
  ADD CONSTRAINT `fk_informes_estudiantes` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informes_historias` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_informes_usuarios` FOREIGN KEY (`elaborado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD CONSTRAINT `fk_seguimientos_historias` FOREIGN KEY (`id_historia`) REFERENCES `historias_clinicas` (`id_historia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguimientos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
