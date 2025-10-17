-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-10-2025 a las 20:28:16
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
-- Base de datos: `sistema_pedidos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `nombre_corto` varchar(255) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `proveedor` varchar(255) NOT NULL,
  `cuenta` varchar(255) NOT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_llegada` date NOT NULL,
  `forma_pago` varchar(100) DEFAULT NULL,
  `usuario` varchar(100) NOT NULL,
  `estado` enum('Solicitud','EnTransito','Recibido','EntregadoTecnico','PorDevolver','Devuelto','Cancelado') NOT NULL,
  `observaciones` text DEFAULT NULL,
  `ods` varchar(100) DEFAULT NULL,
  `ods_id` int(11) DEFAULT NULL,
  `factura` enum('Si','No') NOT NULL DEFAULT 'No',
  `recibio` enum('Si','No') NOT NULL DEFAULT 'No',
  `motivo` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `foto` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `nombre_corto`, `cantidad`, `precio`, `subtotal`, `proveedor`, `cuenta`, `fecha_compra`, `fecha_llegada`, `forma_pago`, `usuario`, `estado`, `observaciones`, `ods`, `ods_id`, `factura`, `recibio`, `motivo`, `fecha_creacion`, `fecha_actualizacion`, `foto`, `url`) VALUES
(3, 'Display', 2, 1500.00, 3000.00, 'ML arturo', '', '2025-09-17', '2025-09-22', 'Mercado Pago', '', 'EnTransito', 'nin', '7899', 7899, 'No', 'Si', 'lkkkk', '2025-09-17 16:41:37', '2025-09-22 17:12:19', 'uploads/WhatsApp_Image_2025-09-08_at_1.51.16_PM_20250918_034044_e62d1588.jpeg', 'https://es.scribd.com/document/652884845/4-4-1-1-Packet-Tracer-Configuring-a-Zone-Based-Policy-Firewall-ZPF'),
(4, 'Teclado', 1, 1200.00, 1200.00, 'AliExpress', '', '2025-09-17', '2025-09-26', 'Efectivo', '', 'PorDevolver', '', '13827', 13827, 'Si', 'No', 'mmmmq12', '2025-09-17 16:50:09', '2025-09-19 15:39:12', 'uploads/logo_20250918_031717_3ff1be8c.jpg', 'https://www.uaeh.edu.mx/'),
(5, 'equis', 1, 1111.00, 1111.00, 'AliExpress arturo', '', '2025-09-17', '2025-09-01', 'Transferencia', '', 'EnTransito', 'loco', '16787', 16787, 'No', 'No', '', '2025-09-17 16:56:19', '2025-09-18 20:32:04', NULL, ''),
(8, 'Display2', 2, 2000.00, 4000.00, 'Mercado Libre', '', '2025-09-09', '2025-09-19', 'Efectivo', '', 'Cancelado', '', '14449', 14449, 'Si', 'No', 'ningunooo', '2025-09-18 01:19:00', '2025-09-22 17:50:52', NULL, ''),
(12, 'ventilador', 2, 1234.00, 2468.00, 'Mercado Libre', '', '2025-09-16', '2025-09-20', 'Mercado Pago', '', 'EnTransito', '', '10000', 0, 'No', 'No', '', '2025-09-19 20:56:35', '2025-09-19 20:56:35', NULL, ''),
(52, 'Display', 2, 1500.00, 3000.00, 'ML arturo', '', '2025-09-17', '2025-09-22', 'Mercado Pago', '', 'EnTransito', 'nin', '7899', 7899, 'No', 'Si', 'lkkkk', '2025-09-17 16:41:37', '2025-09-22 17:12:19', 'uploads/WhatsApp_Image_2025-09-08_at_1.51.16_PM_20250918_034044_e62d1588.jpeg', 'https://es.scribd.com/document/652884845/4-4-1-1-Packet-Tracer-Configuring-a-Zone-Based-Policy-Firewall-ZPF');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
