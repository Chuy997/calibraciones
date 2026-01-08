/*M!999999\- enable the sandbox mode */ 

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
DROP TABLE IF EXISTS `assets_hw_audit_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_hw_audit_items` (
  `DetailID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `AuditID` int(10) unsigned NOT NULL,
  `AssetsHWID` varchar(64) NOT NULL,
  `PhysicalCheck` tinyint(1) NOT NULL DEFAULT 1,
  `ConditionCheck` enum('Good','Damage','Scrap','Missing') NOT NULL DEFAULT 'Good',
  `Notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`DetailID`),
  KEY `idx_audit_items_audit` (`AuditID`),
  KEY `idx_audit_items_golden` (`AssetsHWID`)
) ENGINE=InnoDB AUTO_INCREMENT=3460 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets_hw_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_hw_audits` (
  `AuditID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `AuditDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Auditor` varchar(100) NOT NULL,
  `Status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `TotalItems` int(11) NOT NULL DEFAULT 0,
  `TotalMissing` int(11) NOT NULL DEFAULT 0,
  `TotalDamaged` int(11) NOT NULL DEFAULT 0,
  `Comments` text DEFAULT NULL,
  PRIMARY KEY (`AuditID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets_hw_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_hw_history` (
  `HistoryID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `AssetsHWID` varchar(64) NOT NULL,
  `Action` enum('create','update','scrap','restore') NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT NULL,
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap','Out of use','Pending') NOT NULL DEFAULT 'Activo',
  `Comments` text DEFAULT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`HistoryID`),
  KEY `idx_ingenieria` (`AssetsHWID`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets_hw_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_hw_items` (
  `ID` varchar(64) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT 'Ingenieria',
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap','Out of use','Pending') NOT NULL DEFAULT 'Activo',
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_status` (`Status`),
  KEY `idx_location` (`Location`),
  KEY `idx_department` (`Department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calibrationhistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calibrationhistory` (
  `HistoryID` int(11) NOT NULL AUTO_INCREMENT,
  `InstrumentID` varchar(50) DEFAULT NULL,
  `CalDate` date DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `CertificateNo` varchar(255) DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`HistoryID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calibrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calibrations` (
  `calibrationID` int(11) NOT NULL AUTO_INCREMENT,
  `torqueID` varchar(50) DEFAULT NULL,
  `empleadoID` varchar(50) NOT NULL,
  `valor1` float NOT NULL,
  `valor2` float NOT NULL,
  `valor3` float NOT NULL,
  `valor4` float NOT NULL,
  `promedio` float NOT NULL,
  `resultado` enum('aprobado','fuera de tolerancia') DEFAULT NULL,
  `fechaCalibracion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`calibrationID`),
  KEY `idx_calibrations_torqueID` (`torqueID`),
  KEY `idx_calibrations_fecha` (`fechaCalibracion`),
  CONSTRAINT `fk_calibrations_torqueID` FOREIGN KEY (`torqueID`) REFERENCES `torques` (`torqueID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1072 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `golden_audit_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `golden_audit_items` (
  `DetailID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `AuditID` int(10) unsigned NOT NULL,
  `GoldenID` varchar(64) NOT NULL,
  `PhysicalCheck` tinyint(1) NOT NULL DEFAULT 1,
  `ConditionCheck` enum('Good','Damage','Scrap','Missing') NOT NULL DEFAULT 'Good',
  `Notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`DetailID`),
  KEY `idx_audit_items_audit` (`AuditID`),
  KEY `idx_audit_items_golden` (`GoldenID`)
) ENGINE=InnoDB AUTO_INCREMENT=44601 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `golden_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `golden_audits` (
  `AuditID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `AuditDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Auditor` varchar(100) NOT NULL,
  `Status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `TotalItems` int(11) NOT NULL DEFAULT 0,
  `TotalMissing` int(11) NOT NULL DEFAULT 0,
  `TotalDamaged` int(11) NOT NULL DEFAULT 0,
  `Comments` text DEFAULT NULL,
  PRIMARY KEY (`AuditID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `golden_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `golden_history` (
  `HistoryID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `GoldenID` varchar(64) NOT NULL,
  `Action` enum('create','update','scrap','restore') NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT NULL,
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap') DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`HistoryID`),
  KEY `idx_golden` (`GoldenID`),
  CONSTRAINT `fk_golden_history_items` FOREIGN KEY (`GoldenID`) REFERENCES `golden_items` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=305 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `golden_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `golden_items` (
  `ID` varchar(64) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT 'Testing',
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap') NOT NULL DEFAULT 'Activo',
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_status` (`Status`),
  KEY `idx_location` (`Location`),
  KEY `idx_department` (`Department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `golden_scrap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `golden_scrap` (
  `ScrapID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `GoldenID` varchar(64) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT NULL,
  `Owner` varchar(120) DEFAULT NULL,
  `ReasonForScrap` varchar(255) NOT NULL,
  `DateScrapped` datetime NOT NULL DEFAULT current_timestamp(),
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  PRIMARY KEY (`ScrapID`),
  KEY `idx_golden` (`GoldenID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `history` (
  `historyID` int(11) NOT NULL AUTO_INCREMENT,
  `torqueID` varchar(50) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`historyID`),
  KEY `idx_history_torqueID` (`torqueID`),
  KEY `idx_history_date` (`date`),
  CONSTRAINT `fk_history_torqueID` FOREIGN KEY (`torqueID`) REFERENCES `torques` (`torqueID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1073 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `dt` date NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`dt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingenieria_audit_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingenieria_audit_items` (
  `DetailID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `AuditID` int(10) unsigned NOT NULL,
  `GoldenID` varchar(64) NOT NULL,
  `PhysicalCheck` tinyint(1) NOT NULL DEFAULT 1,
  `ConditionCheck` enum('Good','Damage','Scrap','Missing') NOT NULL DEFAULT 'Good',
  `Notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`DetailID`),
  KEY `idx_audit_items_audit` (`AuditID`),
  KEY `idx_audit_items_golden` (`GoldenID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingenieria_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingenieria_audits` (
  `AuditID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `AuditDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Auditor` varchar(100) NOT NULL,
  `Status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `TotalItems` int(11) NOT NULL DEFAULT 0,
  `TotalMissing` int(11) NOT NULL DEFAULT 0,
  `TotalDamaged` int(11) NOT NULL DEFAULT 0,
  `Comments` text DEFAULT NULL,
  PRIMARY KEY (`AuditID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingenieria_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingenieria_history` (
  `HistoryID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IngenieriaID` varchar(64) NOT NULL,
  `Action` enum('create','update','scrap','restore') NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT NULL,
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap') DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`HistoryID`),
  KEY `idx_ingenieria` (`IngenieriaID`),
  CONSTRAINT `fk_ingenieria_history_items` FOREIGN KEY (`IngenieriaID`) REFERENCES `ingenieria_items` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingenieria_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingenieria_items` (
  `ID` varchar(64) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Brand` varchar(120) DEFAULT NULL,
  `Model` varchar(120) DEFAULT NULL,
  `SerialNumber` varchar(120) DEFAULT NULL,
  `Pedimento` varchar(255) DEFAULT NULL,
  `Location` varchar(120) DEFAULT NULL,
  `Department` varchar(120) DEFAULT 'Ingenieria',
  `Owner` varchar(120) DEFAULT NULL,
  `Status` enum('Activo','Scrap') NOT NULL DEFAULT 'Activo',
  `Picture` varchar(255) DEFAULT NULL,
  `Document` varchar(255) DEFAULT NULL,
  `Comments` text DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_status` (`Status`),
  KEY `idx_location` (`Location`),
  KEY `idx_department` (`Department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `PdfPath` varchar(255) DEFAULT NULL,
  `CertificateNo` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `instrumentsoutofuse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `Picture` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `linpu_calibrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `linpu_calibrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(100) NOT NULL,
  `slot` tinyint(3) unsigned NOT NULL COMMENT '1, 2, 3...',
  `channel` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Puerto dentro del slot (1-4)',
  `wavelength_nm` int(11) NOT NULL,
  `reference_reading_dbm` decimal(6,3) NOT NULL,
  `dut_reading_dbm` decimal(6,3) NOT NULL,
  `deviation_db` decimal(5,3) GENERATED ALWAYS AS (abs(`reference_reading_dbm` - `dut_reading_dbm`)) STORED,
  `tolerance_db` decimal(3,2) NOT NULL COMMENT '0.20 dB según IEC 61315:2017 (Tabla 1, Clase 1, 1310/1550 nm); ITU-T L.52 recomienda ±0.25 dB máx.',
  `result` enum('aprobado','fuera de tolerancia') NOT NULL,
  `operator` varchar(100) NOT NULL,
  `calibration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `certificate_path` varchar(255) DEFAULT NULL,
  `picture_path` varchar(255) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `turno` tinyint(4) NOT NULL,
  `posicion` varchar(10) NOT NULL,
  `particulas_0_5_um` int(11) NOT NULL,
  `particulas_5_0_um` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fecha_pos` (`fecha`,`posicion`)
) ENGINE=InnoDB AUTO_INCREMENT=2636 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `torques` (
  `torqueID` varchar(50) NOT NULL,
  `fechaAlta` date NOT NULL,
  `foto` varchar(255) NOT NULL,
  `torque` float NOT NULL,
  `SN` varchar(50) NOT NULL,
  `status` enum('activo','fuera de uso','calibracion fallida') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`torqueID`),
  KEY `idx_torques_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `updatehistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `updatehistory` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
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
  `Picture` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `userID` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','operator') NOT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vw_expected_dates_last3m`;
/*!50001 DROP VIEW IF EXISTS `vw_expected_dates_last3m`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vw_expected_dates_last3m` AS SELECT
 1 AS `expected_date` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vw_missing_calibrations_last3m`;
/*!50001 DROP VIEW IF EXISTS `vw_missing_calibrations_last3m`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vw_missing_calibrations_last3m` AS SELECT
 1 AS `torqueID`,
  1 AS `expected_date` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `vw_expected_dates_last3m`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jmuro`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_expected_dates_last3m` AS with recursive rng(`d`,`n`) as (select curdate() - interval 93 day AS `d`,1 AS `n` union all select `rng`.`d` + interval 1 day AS `d + INTERVAL 1 DAY`,`rng`.`n` + 1 AS `n + 1` from `rng` where `rng`.`n` < 93)select `rng`.`d` AS `expected_date` from `rng` where dayofweek(`rng`.`d`) = 2 and !(`rng`.`d` in (select `holidays`.`dt` from `holidays`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_missing_calibrations_last3m`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`jmuro`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_missing_calibrations_last3m` AS select `t`.`torqueID` AS `torqueID`,`e`.`expected_date` AS `expected_date` from ((`torques` `t` join `vw_expected_dates_last3m` `e`) left join `calibrations` `c` on(`c`.`torqueID` = `t`.`torqueID` and cast(`c`.`fechaCalibracion` as date) = `e`.`expected_date`)) where `t`.`status` = 'activo' and `c`.`calibrationID` is null order by `e`.`expected_date`,`t`.`torqueID` */;
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

