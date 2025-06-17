-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2024 at 11:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `calibraciones`
--

-- --------------------------------------------------------

--
-- Table structure for table `calibrationhistory`
--

CREATE TABLE `calibrationhistory` (
  `HistoryID` int(11) NOT NULL,
  `InstrumentID` varchar(50) DEFAULT NULL,
  `CalDate` date DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `CertificateNo` varchar(255) DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calibrationhistory`
--

INSERT INTO `calibrationhistory` (`HistoryID`, `InstrumentID`, `CalDate`, `DueDate`, `CertificateNo`, `UpdatedAt`) VALUES
(1, '30170', '2024-05-24', '2025-05-24', '83935', '2024-06-12 22:34:51'),
(2, '30170', '2024-05-27', '2025-05-27', '83935', '2024-06-12 22:44:38');

-- --------------------------------------------------------

--
-- Table structure for table `instruments`
--

CREATE TABLE `instruments` (
  `ID` varchar(50) NOT NULL,
  `Picture` varchar(255) DEFAULT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Brand` varchar(255) DEFAULT NULL,
  `Model` varchar(255) DEFAULT NULL,
  `SerialNumber` varchar(255) DEFAULT NULL,
  `HW_ZL` varchar(255) DEFAULT NULL,
  `CalDate` date DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `DaysCounter` int(11) DEFAULT NULL,
  `Status` enum('fuera de calibracion','calibrado','en proceso de calibracion') NOT NULL DEFAULT 'fuera de calibracion',
  `Comments` varchar(255) DEFAULT NULL,
  `PdfPath` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instruments`
--

INSERT INTO `instruments` (`ID`, `Picture`, `Description`, `Brand`, `Model`, `SerialNumber`, `HW_ZL`, `CalDate`, `DueDate`, `DaysCounter`, `Status`, `Comments`, `PdfPath`) VALUES
('107755', 'uploads/107755.jfif', 'Three-path diode power sensor', 'Rohde & Schwarz', 'NRPS8', '107755', NULL, '2024-09-09', '2025-09-09', NULL, 'calibrado', 'ONT Line', 'uploads/107755.pdf'),
('30165', NULL, 'SCALE', 'BRAUNKER', 'ZM301', '163750313', NULL, '2024-04-08', '2025-04-08', NULL, 'calibrado', 'Linea 2 POC', 'uploads/MICRO (2).pdf'),
('30169', NULL, 'TORQUE ANALIZER', 'HIOS', 'HP-100', '917431', 'HW0303011', '2024-01-23', '2025-01-23', 225, 'calibrado', 'ATO', 'uploads/30169.pdf'),
('30170', 'uploads/dustFluke.png', 'DUST COUNTER', 'FLUKE', 'FLUKE 985', '1609994506', 'HW0103004', '2024-05-27', '2025-05-27', 350, 'calibrado', 'ATO', 'uploads/FlukeDustCounter.pdf'),
('30178', NULL, 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035462', 'HW0103021', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC', 'uploads/30178.pdf'),
('30179', NULL, 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035461', 'WH0103020', '2023-11-17', '2024-11-17', 179, 'calibrado', 'POC', 'uploads/30179.pdf'),
('30182', NULL, 'SCALE', 'AVERY WEIGHT-TRONIX', 'ZM303', '181750560', 'WH0103015', '2024-04-08', '2025-04-08', 301, 'calibrado', 'Linea 3 POC', 'uploads/MICRO.pdf'),
('30189', NULL, 'CABLE METER', 'EMKO', 'EZM-4931', '30189', 'HW0101016', '2023-11-17', '2024-11-17', 158, 'calibrado', 'Corte de cable', 'uploads/DH5928.pdf'),
('30190', NULL, 'MOISTURE AND TEMPERATURE SENSOR', 'EXTECH', '445815', '3090', 'WH0103001', '2024-04-12', '2025-04-12', 301, 'calibrado', 'ATO', 'uploads/MICRO (5).pdf'),
('30191', NULL, 'MOISTURE AND TEMPERATURE SENSOR', 'EXTECH', '445815', '3091', 'WH0103003', '2024-04-12', '2025-04-12', 301, 'calibrado', 'ATO', 'uploads/MICRO (4).pdf'),
('DH5927', NULL, 'SCALE', 'AVERY WEIGHT-TRONIX', 'ZM301', '500204410597', 'WH0103022', '2024-04-08', '2025-04-08', 301, 'calibrado', 'Corte de cable', 'uploads/MICRO (1).pdf'),
('DH5928', NULL, 'CABLE METER', 'EMKO', 'EZM-4931', 'DH5928', 'HW0303015', '2023-11-17', '2024-11-17', 107, 'calibrado', 'Corte de cable', 'uploads/DH5928_.pdf'),
('DP7251', NULL, 'SCALE', 'INTERTEK', 'IT3', '1908910', 'ZL0103016', '2023-11-17', '2024-11-17', 158, 'calibrado', '.', 'uploads/DP7251.pdf'),
('DP7252B', NULL, 'SCALE', 'ETI', 'IT3', '1908914', 'ZL0103007', '2023-11-17', '2024-11-17', 158, 'calibrado', '.', 'uploads/DP7252B .pdf'),
('DT1348', NULL, 'CABLE METER', 'COPOSE', 'N/A', 'N/A', 'ZL0101001', '2023-11-17', '2024-11-17', 158, 'calibrado', 'Corte de cable', 'uploads/DT1348.pdf'),
('DT3967', NULL, 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18229999', 'ZL0103018', '2024-08-19', '2025-08-19', 49, 'calibrado', 'Calidad', 'uploads/DT3967_2024.pdf'),
('DT3968', 'uploads/30180.jpeg', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18220001', 'HW0303007', '2024-07-03', '2025-07-03', -6, 'calibrado', '.', 'uploads/DT3968.pdf'),
('DU7310', 'uploads/DU7310.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7310.pdf'),
('DU7311', 'uploads/DU7311.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7311.pdf'),
('DU7312', 'uploads/DU7312.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7312.pdf'),
('DU7313', 'uploads/DU7313.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7313.pdf'),
('DU7314', 'uploads/DU7314.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7314.pdf'),
('DU7315', 'uploads/DU7315.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7315.pdf'),
('DU7316', 'uploads/DU7316.jpeg', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '0', '2024-07-03', '2025-07-03', -6, 'calibrado', 'All areas', 'uploads/DU7316.pdf'),
('DV4620', NULL, 'ESD TESTER', 'OHM METRICS', 'PDT700', '2010-0077', 'HW0303009', '2024-01-18', '2025-01-18', 220, 'calibrado', 'ATO', 'uploads/DV4620.pdf'),
('DV4622', NULL, 'ESD TESTER', 'OHM METRICS', 'PDT700', '2010-0067', 'HW0303010', '2024-01-18', '2025-01-18', 220, 'calibrado', 'ATO', 'uploads/DV4622.pdf'),
('DV6914', 'uploads/dv6914.jfif', 'ELECTRO-FIELDMETER', 'KLEINWACHTER', 'EFM 022', '93540719', '0', '2024-09-09', '2025-09-09', 45, 'calibrado', 'ATO', 'uploads/DV6914_2024.pdf'),
('DV6916', NULL, 'CABLE REFERENCE', 'N/A', 'N/A', 'N/A', 'N/A', '2024-01-23', '2025-01-23', 225, 'calibrado', 'ATO', 'uploads/DV6916.pdf'),
('DW3665', NULL, 'SURFACE RESISTIVITY METER', 'ACL STATICIDE', 'ACL380', '54660', '0', '2024-01-18', '2025-01-18', 220, 'calibrado', 'ATO', 'uploads/DW3665.pdf'),
('EC7809', NULL, 'SCALE', 'BRAUNKER', 'GSE 350', '48538', '#N/A', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC Line 1', 'uploads/EC7809.pdf'),
('EC7810', NULL, 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155025463', '0', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC', 'uploads/30178.pdf'),
('EC7811', NULL, 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', 'ZTHO-3488117', '#N/A', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC Line 2', 'uploads/EC7811.pdf'),
('ED4709', NULL, 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G8I8', 'WH0103024', '2024-04-11', '2025-04-11', 301, 'calibrado', 'POC', 'uploads/ED4709.pdf'),
('ED4712', NULL, 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G8I8', 'WH0103024', '2024-04-09', '2025-04-09', 301, 'calibrado', 'POC', 'uploads/ED4712.pdf'),
('ED4714', NULL, 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G9P3', 'WH0103024', '2024-04-09', '2025-04-09', 301, 'calibrado', 'POC', 'uploads/ED4714.pdf'),
('ED4983', NULL, 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', '800001180102AK36', '#N/A', '2024-04-08', '2025-04-08', 301, 'calibrado', 'Linea 3 POC', 'uploads/MICRO (3).pdf'),
('ED4984', NULL, 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', '800001184911B5PW', '#N/A', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC Line 1', 'uploads/ED4984.pdf'),
('EG3410', NULL, 'SCALE', 'MONOLITHLOT', 'DWS6080', 'SDWS105107FF4808', 'EG3410', '2023-11-17', '2024-11-17', 158, 'calibrado', 'Z Line 1', 'uploads/EG3410.pdf'),
('EG3411', NULL, 'SCALE', 'MONOLITHLOT', 'DWS6080', 'SDWS105107FF4871', 'EG3411', '2023-11-17', '2024-11-17', 158, 'calibrado', 'Z Line 2', 'uploads/EG3411.pdf'),
('EG3412', 'uploads/defender.png', 'Wifi Scale', 'Ohaus', 'Defender T32XW	', 'C148059438', NULL, '2024-07-03', '2025-07-03', NULL, 'calibrado', 'Edificio 6', 'uploads/EG3412.pdf'),
('EG3413', NULL, 'SCALE', 'Ohaus', 'Defender T32XW', 'C139661196', 'EG3413', '2024-01-18', '2025-01-18', 220, 'calibrado', '.', 'uploads/EG3413.pdf'),
('FL0101', NULL, 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000283C30001', 'WH0103023', '2024-03-01', '2025-03-01', 260, 'calibrado', 'POC', 'uploads/FL0101.pdf'),
('FL0102', NULL, 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000283C30002', 'WH0103024', '2024-03-01', '2025-03-01', 260, 'calibrado', 'POC', 'uploads/FL0102.pdf'),
('new1', NULL, 'LINPU Optical Power Meter', 'LINPU', 'F1200', 'C023000012111160007', '#N/A', '0000-00-00', '0000-00-00', 0, '', 'in process to find supplier', NULL),
('new2', NULL, 'LINPU Optical Power Meter', 'LINPU', 'F1200', 'C023000012111160008', '#N/A', '0000-00-00', '0000-00-00', 0, '', 'in process to find supplier', NULL),
('SO5462', NULL, 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035462', 'WH0103021', '2024-02-27', '2025-02-27', 260, 'calibrado', 'POC', 'uploads/SO5462.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `instrumentsoutofuse`
--

CREATE TABLE `instrumentsoutofuse` (
  `ID` varchar(255) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Brand` varchar(255) DEFAULT NULL,
  `Model` varchar(255) DEFAULT NULL,
  `SerialNumber` varchar(255) DEFAULT NULL,
  `CalDate` date DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `Status` varchar(255) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `ReasonForRemoval` varchar(255) DEFAULT NULL,
  `DateRemoved` date DEFAULT NULL,
  `PdfPath` varchar(255) DEFAULT NULL,
  `Picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instrumentsoutofuse`
--

INSERT INTO `instrumentsoutofuse` (`ID`, `Description`, `Brand`, `Model`, `SerialNumber`, `CalDate`, `DueDate`, `Status`, `Comments`, `ReasonForRemoval`, `DateRemoved`, `PdfPath`, `Picture`) VALUES
('30176', 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035460', '2023-11-17', '2024-11-17', 'fuera de calibracion', 'Fuera de calibracion', 'No Funciona', '2024-06-17', 'uploads/30176 2023.pdf', 'uploads/30176.png'),
('30192', 'MOISTURE AND TEMPERATURE SENSOR', 'N/A', 'N/A', '3092', '2023-01-01', '2024-01-01', '', 'Fuera de uso', 'Fuera de Calibración', '2024-06-17', NULL, 'uploads/30192.png'),
('DP7252A', 'SCALE', 'BRAUNKER', 'GSE 350', '1908914', '2023-11-17', '2024-11-17', 'calibrado', 'Scarp', 'Obsoleto', '2024-06-17', 'uploads/Fuera de uso.pdf', 'uploads/Braunker GSE350.png');

-- --------------------------------------------------------

--
-- Table structure for table `updatehistory`
--

CREATE TABLE `updatehistory` (
  `ID` int(11) NOT NULL,
  `InstrumentID` varchar(50) DEFAULT NULL,
  `UpdatedColumn` varchar(50) DEFAULT NULL,
  `OldValue` text DEFAULT NULL,
  `NewValue` text DEFAULT NULL,
  `UpdateTimestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Description` varchar(255) NOT NULL,
  `Brand` varchar(255) NOT NULL,
  `Model` varchar(255) NOT NULL,
  `SerialNumber` varchar(255) NOT NULL,
  `CalDate` date NOT NULL,
  `DueDate` date NOT NULL,
  `CertificateNo` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Comments` text NOT NULL,
  `PdfPath` varchar(255) DEFAULT NULL,
  `Picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `updatehistory`
--

INSERT INTO `updatehistory` (`ID`, `InstrumentID`, `UpdatedColumn`, `OldValue`, `NewValue`, `UpdateTimestamp`, `UpdatedAt`, `Description`, `Brand`, `Model`, `SerialNumber`, `CalDate`, `DueDate`, `CertificateNo`, `Status`, `Comments`, `PdfPath`, `Picture`) VALUES
(5, '107755', NULL, NULL, NULL, '2024-06-13 19:15:49', '2024-06-13 19:15:49', 'Three-path diode power sensor', 'Rohde & Schwarz', 'NRPS8', '107755', '2023-07-27', '2024-07-27', '5523631030283850', 'Calibrado', 'ONT Line', 'uploads/107755 2023.pdf', NULL),
(6, '30182', NULL, NULL, NULL, '2024-06-13 19:22:50', '2024-06-13 19:22:50', 'SCALE', 'AVERY WEIGHT-TRONIX', 'ZM303', '181750560', '2024-04-08', '2025-04-08', '5523631030853495', 'Calibrado', 'Linea 3 POC', 'uploads/MICRO.pdf', NULL),
(7, 'DH5927', NULL, NULL, NULL, '2024-06-13 19:37:18', '2024-06-13 19:37:18', 'SCALE', 'AVERY WEIGHT-TRONIX', 'ZM301', '500204410597', '2024-04-08', '2025-04-08', '5523631030853518', 'Calibrado', 'Corte de cable', 'uploads/MICRO (1).pdf', NULL),
(8, '30165', NULL, NULL, NULL, '2024-06-13 19:40:12', '2024-06-13 19:40:12', 'SCALE', 'BRAUNKER', 'ZM301', '163750313', '2024-04-08', '2025-04-08', '5523631030853536', 'Calibrado', 'Linea 2 POC', 'uploads/MICRO (2).pdf', NULL),
(9, 'ED4983', NULL, NULL, NULL, '2024-06-13 19:43:16', '2024-06-13 19:43:16', 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', '800001180102AK36', '2024-04-08', '2025-04-08', '5523631030853569', 'Calibrado', 'Linea 3 POC', 'uploads/MICRO (3).pdf', NULL),
(10, '30191', NULL, NULL, NULL, '2024-06-13 19:47:02', '2024-06-13 19:47:02', 'MOISTURE AND TEMPERATURE SENSOR', 'EXTECH', '445815', '3091', '2024-04-12', '2025-04-12', '5523631030848116', 'Calibrado', 'ATO', 'uploads/MICRO (4).pdf', NULL),
(11, '30190', NULL, NULL, NULL, '2024-06-13 19:48:31', '2024-06-13 19:48:31', 'MOISTURE AND TEMPERATURE SENSOR', 'EXTECH', '445815', '3090', '2024-04-12', '2025-04-12', '5523631030848115', 'Calibrado', 'ATO', 'uploads/MICRO (5).pdf', NULL),
(12, 'ED4709', NULL, NULL, NULL, '2024-06-13 19:56:44', '2024-06-13 19:56:44', 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G8I8', '2024-04-11', '2025-04-11', '5523631030848119', 'Calibrado', 'POC', 'uploads/ED4709.pdf', NULL),
(13, 'ED4712', NULL, NULL, NULL, '2024-06-13 19:58:43', '2024-06-13 19:58:43', 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G8I8', '2024-04-09', '2025-04-09', '5523631030848120', 'Calibrado', 'POC', 'uploads/ED4712.pdf', NULL),
(14, 'ED4714', NULL, NULL, NULL, '2024-06-13 20:00:30', '2024-06-13 20:00:30', 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000285118G9P3', '2024-04-09', '2025-04-09', '5523631030848121', 'Calibrado', 'POC', 'uploads/ED4714.pdf', NULL),
(15, '30169', NULL, NULL, NULL, '2024-06-13 21:28:06', '2024-06-13 21:28:06', 'TORQUE ANALIZER', 'HIOS', 'HP-100', '917431', '2024-01-23', '2025-01-23', '5523631030639779', 'Calibrado', 'ATO', 'uploads/30169.pdf', NULL),
(16, '30178', NULL, NULL, NULL, '2024-06-13 21:33:01', '2024-06-13 21:33:01', 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035462', '2024-02-27', '2025-02-27', '5523631030748891', 'Calibrado', 'POC', 'uploads/30178.pdf', NULL),
(17, '30179', NULL, NULL, NULL, '2024-06-13 21:34:43', '2024-06-13 21:34:43', 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035461', '2023-11-17', '2024-11-17', '5523631030543325', 'Calibrado', 'POC', 'uploads/30179.pdf', NULL),
(18, '30180B', NULL, NULL, NULL, '2024-06-13 21:36:38', '2024-06-13 21:36:38', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18220001', '2023-06-06', '2024-06-06', '5523631030114115', 'Calibrado', '.', 'uploads/30180.pdf', NULL),
(19, '30189', NULL, NULL, NULL, '2024-06-13 21:51:47', '2024-06-13 21:51:47', 'CABLE METER', 'EMKO', 'EZM-4931', '30189', '2023-11-17', '2024-11-17', '5523631030543243', 'Calibrado', 'Corte de cable', 'uploads/DH5928.pdf', NULL),
(20, 'DP7251', NULL, NULL, NULL, '2024-06-13 21:55:38', '2024-06-13 21:55:38', 'SCALE', 'INTERTEK', 'IT3', '1908910', '2023-11-17', '2024-11-17', '5523631030546450', 'Calibrado', '.', 'uploads/DP7251.pdf', NULL),
(21, 'SO5462', NULL, NULL, NULL, '2024-06-13 22:40:16', '2024-06-13 22:40:16', 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155035462', '2024-02-27', '2025-02-27', '5523631030748920', 'Calibrado', 'POC', 'uploads/SO5462.pdf', NULL),
(22, 'EC7810', NULL, NULL, NULL, '2024-06-13 22:46:08', '2024-06-13 22:46:08', 'PALLET JACK SCALE', 'FAYA', 'ECS-550S', '181155025463', '2024-02-27', '2025-02-27', '5523631030748891', 'Calibrado', 'POC', 'uploads/30178.pdf', NULL),
(23, 'FL0102', NULL, NULL, NULL, '2024-06-14 15:38:08', '2024-06-14 15:38:08', 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000283C30002', '2024-03-01', '2025-03-01', '5523631030737582', 'Calibrado', 'POC', 'uploads/FL0102.pdf', NULL),
(24, 'FL0101', NULL, NULL, NULL, '2024-06-14 15:40:09', '2024-06-14 15:40:09', 'FLEXOMETER WIFI', 'MONOLITHLOT', 'MIT- 3033', '800000283C30001', '2024-03-01', '2025-03-01', '5523631030737594', 'Calibrado', 'POC', 'uploads/FL0101.pdf', NULL),
(25, 'EG3413', NULL, NULL, NULL, '2024-06-14 15:41:24', '2024-06-14 15:41:24', 'SCALE', 'Ohaus', 'Defender T32XW', 'C139661196', '2024-01-18', '2025-01-18', '5523631030640192', 'Calibrado', '.', 'uploads/EG3413.pdf', NULL),
(26, 'EG3411', NULL, NULL, NULL, '2024-06-14 15:43:29', '2024-06-14 15:43:29', 'SCALE', 'MONOLITHLOT', 'DWS6080', 'SDWS105107FF4871', '2023-11-17', '2024-11-17', '5523631030543381', 'Calibrado', 'Z Line 2', 'uploads/EG3411.pdf', NULL),
(27, 'EG3410', NULL, NULL, NULL, '2024-06-14 15:44:25', '2024-06-14 15:44:25', 'SCALE', 'MONOLITHLOT', 'DWS6080', 'SDWS105107FF4808', '2023-11-17', '2024-11-17', '5523631030543388', 'Calibrado', 'Z Line 1', 'uploads/EG3410.pdf', NULL),
(28, 'ED4984', NULL, NULL, NULL, '2024-06-14 15:45:32', '2024-06-14 15:45:32', 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', '800001184911B5PW', '2024-02-27', '2025-02-27', '5523631030748916', 'Calibrado', 'POC Line 1', 'uploads/ED4984.pdf', NULL),
(29, 'EC7811', NULL, NULL, NULL, '2024-06-14 15:48:31', '2024-06-14 15:48:31', 'WIFI SCALE', 'MONOLITHLOT', 'MTS-6000Plus', 'ZTHO-3488117', '2024-02-27', '2025-02-27', '5523631030748913', 'Calibrado', 'POC Line 2', 'uploads/EC7811.pdf', NULL),
(30, 'EC7809', NULL, NULL, NULL, '2024-06-14 15:50:16', '2024-06-14 15:50:16', 'SCALE', 'BRAUNKER', 'GSE 350', '48538', '2024-02-27', '2025-02-27', '5523631030748909', 'Calibrado', 'POC Line 1', 'uploads/EC7809.pdf', NULL),
(31, 'DW3665', NULL, NULL, NULL, '2024-06-14 15:51:12', '2024-06-14 15:51:12', 'SURFACE RESISTIVITY METER', 'ACL STATICIDE', 'ACL380', '54660', '2024-01-18', '2025-01-18', '5523631030640180', 'Calibrado', 'ATO', 'uploads/DW3665.pdf', NULL),
(32, 'DV6916', NULL, NULL, NULL, '2024-06-14 15:52:15', '2024-06-14 15:52:15', 'CABLE REFERENCE', 'N/A', 'N/A', 'N/A', '2024-01-23', '2025-01-23', '5523631030641633', 'Calibrado', 'ATO', 'uploads/DV6916.pdf', NULL),
(33, 'DV6914', NULL, NULL, NULL, '2024-06-14 15:53:07', '2024-06-14 15:53:07', 'ELECTRO-FIELDMETER', 'KLEINWACHTER', 'EFM 022', '93540719', '2023-07-27', '2024-07-27', '5523631030235536', 'Calibrado', 'ATO', 'uploads/DV6914.pdf', NULL),
(34, 'DV4622', NULL, NULL, NULL, '2024-06-14 15:53:53', '2024-06-14 15:53:53', 'ESD TESTER', 'OHM METRICS', 'PDT700', '2010-0067', '2024-01-18', '2025-01-18', '5523631030640176', 'Calibrado', 'ATO', 'uploads/DV4622.pdf', NULL),
(35, 'DV4620', NULL, NULL, NULL, '2024-06-14 15:55:10', '2024-06-14 15:55:10', 'ESD TESTER', 'OHM METRICS', 'PDT700', '2010-0077', '2024-01-18', '2025-01-18', '5523631030640170', 'Calibrado', 'ATO', 'uploads/DV4620.pdf', NULL),
(36, 'DU7316', NULL, NULL, NULL, '2024-06-14 15:58:29', '2024-06-14 15:58:29', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114293', 'Calibrado', 'All areas', 'uploads/DU7316.pdf', NULL),
(37, 'DU7315', NULL, NULL, NULL, '2024-06-14 15:59:35', '2024-06-14 15:59:35', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114272', 'Calibrado', 'All areas', 'uploads/DU7315.pdf', NULL),
(38, 'DU7314', NULL, NULL, NULL, '2024-06-14 16:00:04', '2024-06-14 16:00:04', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114250', 'Calibrado', 'All areas', 'uploads/DU7314.pdf', NULL),
(39, 'DU7313', NULL, NULL, NULL, '2024-06-14 16:00:48', '2024-06-14 16:00:48', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114241', 'Calibrado', 'All areas', 'uploads/DU7313.pdf', NULL),
(40, 'DU7312', NULL, NULL, NULL, '2024-06-14 16:01:35', '2024-06-14 16:01:35', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114209', 'Calibrado', 'All areas', 'uploads/DU7312.pdf', NULL),
(41, 'DU7311', NULL, NULL, NULL, '2024-06-14 16:02:06', '2024-06-14 16:02:06', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114190', 'Calibrado', 'All areas', 'uploads/DU7311.pdf', NULL),
(42, 'DU7310', NULL, NULL, NULL, '2024-06-14 16:02:36', '2024-06-14 16:02:36', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2023-06-06', '2024-06-06', '5523631030114148', 'Calibrado', 'All areas', 'uploads/DU7310.pdf', NULL),
(43, 'DT1348', NULL, NULL, NULL, '2024-06-14 16:03:29', '2024-06-14 16:03:29', 'CABLE METER', 'COPOSE', 'N/A', 'N/A', '2023-11-17', '2024-11-17', '5523631030543292', 'Calibrado', 'Corte de cable', 'uploads/DT1348.pdf', NULL),
(44, 'DT3967', NULL, NULL, NULL, '2024-06-14 16:07:00', '2024-06-14 16:07:00', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18229999', '2023-07-31', '2024-07-31', '5523631030242220', 'Calibrado', 'Calidad', 'uploads/DT3967 2023.pdf', NULL),
(45, 'DP7252B', NULL, NULL, NULL, '2024-06-14 16:09:06', '2024-06-14 16:09:06', 'SCALE', 'ETI', 'IT3', '1908914', '2023-11-17', '2024-11-17', '5523631030546457', 'Calibrado', '.', 'uploads/DP7252B .pdf', NULL),
(46, 'DH5928', NULL, NULL, NULL, '2024-06-14 16:11:14', '2024-06-14 16:11:14', 'CABLE METER', 'EMKO', 'EZM-4931', 'DH5928', '2023-11-17', '2024-11-17', '5523631030543243', 'Calibrado', 'Corte de cable', 'uploads/DH5928_.pdf', NULL),
(47, 'DT3968', NULL, NULL, NULL, '2024-06-14 16:18:20', '2024-06-14 16:18:20', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18220001', '2023-06-06', '2024-06-06', '5523631030114115', 'Calibrado', 'Calidad', 'uploads/DT3968 2023.pdf', NULL),
(48, '30170', NULL, NULL, NULL, '2024-06-17 19:35:14', '2024-06-17 19:35:14', 'DUST COUNTER', 'FLUKE', 'FLUKE 985', '1609994506', '2024-05-27', '2025-05-27', '', 'Calibrado', 'ATO', 'uploads/FlukeDustCounter.pdf', 'uploads/dustFluke.png'),
(49, 'DT3968', NULL, NULL, NULL, '2024-07-19 21:30:16', '2024-07-19 21:30:16', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18220001', '2024-07-03', '2025-07-03', '', 'Calibrado', '.', 'uploads/DT3968.pdf', 'uploads/30180.jpeg'),
(50, 'DU7310', NULL, NULL, NULL, '2024-07-19 21:31:34', '2024-07-19 21:31:34', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7310.pdf', 'uploads/DU7310.jpeg'),
(51, 'DU7311', NULL, NULL, NULL, '2024-07-19 21:32:26', '2024-07-19 21:32:26', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7311.pdf', 'uploads/DU7311.jpeg'),
(52, 'DU7312', NULL, NULL, NULL, '2024-07-19 21:33:09', '2024-07-19 21:33:09', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7312.pdf', 'uploads/DU7312.jpeg'),
(53, 'DU7313', NULL, NULL, NULL, '2024-07-19 21:34:31', '2024-07-19 21:34:31', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7313.pdf', 'uploads/DU7313.jpeg'),
(54, 'DU7314', NULL, NULL, NULL, '2024-07-19 21:35:35', '2024-07-19 21:35:35', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7314.pdf', 'uploads/DU7314.jpeg'),
(55, 'DU7315', NULL, NULL, NULL, '2024-07-19 21:36:07', '2024-07-19 21:36:07', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7315.pdf', 'uploads/DU7315.jpeg'),
(56, 'DU7316', NULL, NULL, NULL, '2024-07-19 21:36:38', '2024-07-19 21:36:38', 'STANDARD WEIGHT', 'REMEX', 'CLASS M2', 'N/A', '2024-07-03', '2025-07-03', '', 'Calibrado', 'All areas', 'uploads/DU7316.pdf', 'uploads/DU7316.jpeg'),
(57, 'EG3412', NULL, NULL, NULL, '2024-07-19 22:12:36', '2024-07-19 22:12:36', 'Wifi Scale', 'Ohaus', 'Defender T32XW	', 'C148059438', '2024-07-03', '2025-07-03', '', 'Calibrado', 'Edificio 6', 'uploads/EG3412.pdf', 'uploads/defender.png'),
(58, 'DT3967', NULL, NULL, NULL, '2024-09-23 14:47:51', '2024-09-23 14:47:51', 'VERNIER', 'MITUTOYO', 'CD-8\"ASX', 'A18229999', '2024-08-19', '2025-08-19', '', 'Calibrado', 'Calidad', 'uploads/DT3967_2024.pdf', NULL),
(61, '107755', NULL, NULL, NULL, '2024-09-27 16:51:52', '2024-09-27 16:51:52', 'Three-path diode power sensor', 'Rohde & Schwarz', 'NRPS8', '107755', '2024-09-09', '2025-09-09', '', 'Calibrado', 'ONT Line', 'uploads/107755.pdf', 'uploads/107755.jfif'),
(64, 'DV6914', NULL, NULL, NULL, '2024-09-27 16:59:54', '2024-09-27 16:59:54', 'ELECTRO-FIELDMETER', 'KLEINWACHTER', 'EFM 022', '93540719', '2024-09-09', '2025-09-09', '', 'Calibrado', 'ATO', 'uploads/DV6914_2024.pdf', 'uploads/dv6914.jfif');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','consulta') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$YLFbBF5rPS2yT99tjs.YHezGwnlBrK.hlq06O4wkPysa.aXgMNU6S', 'admin'),
(5, 'consulta', '$2y$10$VGVEV1LPY0W3QTM/.jHsR.47BzNn40XFMIO2TVOa21udRMJ8m3vPm', 'consulta');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `calibrationhistory`
--
ALTER TABLE `calibrationhistory`
  ADD PRIMARY KEY (`HistoryID`);

--
-- Indexes for table `instruments`
--
ALTER TABLE `instruments`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `instrumentsoutofuse`
--
ALTER TABLE `instrumentsoutofuse`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `updatehistory`
--
ALTER TABLE `updatehistory`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `calibrationhistory`
--
ALTER TABLE `calibrationhistory`
  MODIFY `HistoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `updatehistory`
--
ALTER TABLE `updatehistory`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
