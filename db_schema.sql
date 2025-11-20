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
) ENGINE=InnoDB AUTO_INCREMENT=290 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','consulta') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

