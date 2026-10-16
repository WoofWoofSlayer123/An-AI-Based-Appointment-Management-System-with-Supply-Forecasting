-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: health_center_system
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `health_center_id` int NOT NULL,
  `service` varchar(150) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Confirmed','In consultation','Rejected','Cancelled','Completed','No-show') NOT NULL DEFAULT 'Pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `assigned_doctor` varchar(50) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `attendance_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `attendance_confirmed_at` datetime DEFAULT NULL,
  `reminder_count` tinyint NOT NULL DEFAULT '0',
  `last_reminder_date` date DEFAULT NULL,
  `confirm_token` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_confirm_token` (`confirm_token`),
  KEY `username` (`username`),
  KEY `health_center_id` (`health_center_id`),
  KEY `assigned_doctor` (`assigned_doctor`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`health_center_id`) REFERENCES `health_centers` (`id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`assigned_doctor`) REFERENCES `users` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,'fangedleon23',2,'General Consultation','2026-10-02','14:00:00','Completed','2026-09-30 20:39:59','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(2,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-13','09:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(3,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-05','11:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(4,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-16','10:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(5,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-06','10:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(6,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-17','11:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(7,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-13','13:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(8,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-30','09:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(9,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-07','14:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(10,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-24','09:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(11,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-25','11:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(12,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-11','09:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(13,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-04','14:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(14,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-18','14:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(15,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-29','10:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(16,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-21','10:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(17,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-14','09:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(18,'fangedleon23',1,'[SEED TEST] General Consultation','2026-09-18','12:00:00','Completed','2026-10-01 12:31:29','doctortest5',NULL,NULL,0,NULL,0,NULL,NULL),(19,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-20','13:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(20,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-19','08:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(21,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-20','12:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(22,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-04','08:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(23,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-14','11:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(24,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-22','10:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(25,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-28','12:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(26,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-12','15:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(27,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-03','16:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(28,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-17','13:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(29,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-15','10:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(30,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-22','16:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(31,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-17','15:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(32,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-22','09:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(33,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-21','10:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(34,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-05','13:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(35,'fangedleon23',2,'[SEED TEST] General Consultation','2026-09-02','10:00:00','Completed','2026-10-01 12:31:29','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(36,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-19','14:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(37,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-05','14:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(38,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-29','12:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(39,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-29','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(40,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-11','08:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(41,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-08','16:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(42,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-07','13:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(43,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-13','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(44,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-20','14:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(45,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-13','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(46,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-29','08:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(47,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-27','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(48,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-15','13:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(49,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-21','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(50,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-06','15:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(51,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-27','09:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(52,'fangedleon23',3,'[SEED TEST] General Consultation','2026-09-21','15:00:00','Completed','2026-10-01 12:31:29','doctortest4',NULL,NULL,0,NULL,0,NULL,NULL),(53,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-10','15:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(54,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-15','09:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(55,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-21','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(56,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-08','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(57,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-19','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(58,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-05','14:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(59,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-04','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(60,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-26','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(61,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-21','11:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(62,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-12','16:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(63,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-13','15:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(64,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-02','11:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(65,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-03','10:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(66,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-19','11:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(67,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-17','12:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(68,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-08','16:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(69,'fangedleon23',4,'[SEED TEST] General Consultation','2026-09-13','08:00:00','Completed','2026-10-01 12:31:29','doctortest2',NULL,NULL,0,NULL,0,NULL,NULL),(70,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-03','14:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(71,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-24','14:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(72,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-19','08:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(73,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-12','13:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(74,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-12','10:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(75,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-17','12:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(76,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-07','10:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(77,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-30','08:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(78,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-28','09:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(79,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-13','10:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(80,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-09','14:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(81,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-05','13:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(82,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-05','10:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(83,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-26','10:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(84,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-15','09:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(85,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-14','14:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(86,'fangedleon23',5,'[SEED TEST] General Consultation','2026-09-15','12:00:00','Completed','2026-10-01 12:31:29','doctortest1',NULL,NULL,0,NULL,0,NULL,NULL),(87,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-05','08:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(88,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-02','16:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(89,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-05','11:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(90,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-27','15:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(91,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-16','13:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(92,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-18','10:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(93,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-06','09:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(94,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-13','15:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(95,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-24','13:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(96,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-08','10:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(97,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-23','16:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(98,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-09','15:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(99,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-28','11:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(100,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-28','15:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(101,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-09','09:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(102,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-07','16:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(103,'fangedleon23',6,'[SEED TEST] General Consultation','2026-09-28','11:00:00','Completed','2026-10-01 12:31:29','doctortest3',NULL,NULL,0,NULL,0,NULL,NULL),(104,'fangedleon23',2,'General Consultation','2026-10-23','22:11:00','Cancelled','2026-10-15 20:14:36',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(105,'fangedleon23',2,'Family Planning Services','2026-10-15','21:26:00','No-show','2026-10-15 20:26:54','fangedleon1',NULL,NULL,0,NULL,0,NULL,NULL),(106,'fangedleon23',2,'Family Planning Services','2026-10-16','15:46:00','Pending','2026-10-15 21:46:30',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(107,'fangedleon23',4,'TB Follow Up','2026-10-15','22:35:00','Pending','2026-10-15 22:35:11',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(108,'fangedleon23',5,'Family Planning Services','2026-10-19','11:30:00','Pending','2026-10-16 08:17:23',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(109,'fangedleon23',2,'General Consultation','2026-10-16','10:30:00','Pending','2026-10-16 08:51:06',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(110,'fangedleon23',2,'General Consultation','2026-10-16','09:00:00','Pending','2026-10-16 08:51:36',NULL,NULL,NULL,0,NULL,0,NULL,NULL),(111,'fangedleon23',5,'Family Planning Services','2026-10-16','09:00:00','Pending','2026-10-16 08:58:14',NULL,NULL,NULL,0,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diagnoses`
--

DROP TABLE IF EXISTS `diagnoses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnoses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `staff_username` varchar(50) NOT NULL,
  `diagnosis` text NOT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `username` (`username`),
  KEY `staff_username` (`staff_username`),
  CONSTRAINT `diagnoses_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diagnoses_ibfk_2` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  CONSTRAINT `diagnoses_ibfk_3` FOREIGN KEY (`staff_username`) REFERENCES `users` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnoses`
--

LOCK TABLES `diagnoses` WRITE;
/*!40000 ALTER TABLE `diagnoses` DISABLE KEYS */;
INSERT INTO `diagnoses` VALUES (1,1,'fangedleon23','fangedleon1','test','test','2026-10-01 09:15:09'),(2,2,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(3,3,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(4,4,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(5,5,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(6,6,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(7,7,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(8,8,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(9,9,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(10,10,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(11,11,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(12,12,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(13,13,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(14,14,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(15,15,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(16,16,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(17,17,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(18,18,'fangedleon23','doctortest5','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(19,19,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(20,20,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(21,21,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(22,22,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(23,23,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(24,24,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(25,25,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(26,26,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(27,27,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(28,28,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(29,29,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(30,30,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(31,31,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(32,32,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(33,33,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(34,34,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(35,35,'fangedleon23','fangedleon1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(36,36,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(37,37,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(38,38,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(39,39,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(40,40,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(41,41,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(42,42,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(43,43,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(44,44,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(45,45,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(46,46,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(47,47,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(48,48,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(49,49,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(50,50,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(51,51,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(52,52,'fangedleon23','doctortest4','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(53,53,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(54,54,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(55,55,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(56,56,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(57,57,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(58,58,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(59,59,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(60,60,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(61,61,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(62,62,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(63,63,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(64,64,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(65,65,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(66,66,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(67,67,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(68,68,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(69,69,'fangedleon23','doctortest2','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(70,70,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(71,71,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(72,72,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(73,73,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(74,74,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(75,75,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(76,76,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(77,77,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(78,78,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(79,79,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(80,80,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(81,81,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(82,82,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(83,83,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(84,84,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(85,85,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(86,86,'fangedleon23','doctortest1','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(87,87,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(88,88,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(89,89,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(90,90,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(91,91,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(92,92,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(93,93,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(94,94,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(95,95,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(96,96,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(97,97,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(98,98,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(99,99,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(100,100,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(101,101,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(102,102,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29'),(103,103,'fangedleon23','doctortest3','Seed test visit — no real clinical content.','Seed test data — safe to delete','2026-10-01 12:31:29');
/*!40000 ALTER TABLE `diagnoses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diagnosis_addenda`
--

DROP TABLE IF EXISTS `diagnosis_addenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnosis_addenda` (
  `id` int NOT NULL AUTO_INCREMENT,
  `diagnosis_id` int NOT NULL,
  `staff_username` varchar(50) NOT NULL,
  `addendum` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `diagnosis_id` (`diagnosis_id`),
  KEY `staff_username` (`staff_username`),
  CONSTRAINT `diagnosis_addenda_ibfk_1` FOREIGN KEY (`diagnosis_id`) REFERENCES `diagnoses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diagnosis_addenda_ibfk_2` FOREIGN KEY (`staff_username`) REFERENCES `users` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnosis_addenda`
--

LOCK TABLES `diagnosis_addenda` WRITE;
/*!40000 ALTER TABLE `diagnosis_addenda` DISABLE KEYS */;
/*!40000 ALTER TABLE `diagnosis_addenda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_center_status`
--

DROP TABLE IF EXISTS `health_center_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_center_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `health_center_id` int NOT NULL,
  `status_date` date NOT NULL,
  `status` enum('Open','Closed','Event') NOT NULL DEFAULT 'Open',
  `custom_hours` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_center_date` (`health_center_id`,`status_date`),
  CONSTRAINT `health_center_status_ibfk_1` FOREIGN KEY (`health_center_id`) REFERENCES `health_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_center_status`
--

LOCK TABLES `health_center_status` WRITE;
/*!40000 ALTER TABLE `health_center_status` DISABLE KEYS */;
/*!40000 ALTER TABLE `health_center_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_centers`
--

DROP TABLE IF EXISTS `health_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_centers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `operating_hours` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_centers`
--

LOCK TABLES `health_centers` WRITE;
/*!40000 ALTER TABLE `health_centers` DISABLE KEYS */;
INSERT INTO `health_centers` VALUES (1,'Tugatog Health Center',NULL,NULL,'Mon–Fri, 8:00 AM – 5:00 PM'),(2,'Potrero Super Health Center','12 Durian St. Barangay Potrero Malabon City','','Mon–Fri, 8:00 AM – 5:00 PM'),(3,'Tonsuya Super Health Center','Ortega St. Tonsuya, Malabon City','(02)-8351-8038','Mon–Fri, 8:00 AM – 5:00 PM'),(4,'Longos Super Health Center','66 Hito St., Longos, Malabon City','','Mon–Fri, 8:00 AM – 5:00 PM'),(5,'Acacia Health Center','Ma. Clara Street Acacia, Malabon','282825022','Mon–Fri, 8:00 AM – 5:00 PM'),(6,'Tinajeros Health Center','66 Crispin St. Tinajeros, Malabon City','282828811','Mon–Fri, 8:00 AM – 5:00 PM');
/*!40000 ALTER TABLE `health_centers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `health_center_id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'unit',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `reorder_threshold` int NOT NULL DEFAULT '10',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `health_center_id` (`health_center_id`),
  CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`health_center_id`) REFERENCES `health_centers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
INSERT INTO `medicines` VALUES (1,1,'Paracetamol 500mg','tablet',50,10,'2026-10-01 12:31:29'),(2,1,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(3,1,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(4,1,'ORS Sachet','sachet',80,16,'2026-10-01 12:31:29'),(5,2,'Paracetamol 500mg','tablet',160,10,'2026-10-03 08:20:52'),(6,2,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(7,2,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(8,2,'ORS Sachet','sachet',100,16,'2026-10-03 08:21:22'),(9,3,'Paracetamol 500mg','tablet',50,10,'2026-10-01 12:31:29'),(10,3,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(11,3,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(12,3,'ORS Sachet','sachet',80,16,'2026-10-01 12:31:29'),(13,4,'Paracetamol 500mg','tablet',50,10,'2026-10-01 12:31:29'),(14,4,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(15,4,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(16,4,'ORS Sachet','sachet',80,16,'2026-10-01 12:31:29'),(17,5,'Paracetamol 500mg','tablet',50,10,'2026-10-01 12:31:29'),(18,5,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(19,5,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(20,5,'ORS Sachet','sachet',80,16,'2026-10-01 12:31:29'),(21,6,'Paracetamol 500mg','tablet',50,10,'2026-10-01 12:31:29'),(22,6,'Amoxicillin 500mg','capsule',100,20,'2026-10-01 12:31:29'),(23,6,'Cetirizine 10mg','tablet',300,60,'2026-10-01 12:31:29'),(24,6,'ORS Sachet','sachet',80,16,'2026-10-01 12:31:29');
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'fangedleon23',1,'Your appointment request for General Consultation on Oct 2, 2026 has been submitted and is awaiting confirmation.',1,'2026-09-30 20:39:59'),(2,'fangedleon23',1,'Your General Consultation appointment on Oct 2, 2026 has been confirmed with Dr. Staff1.',1,'2026-09-30 21:03:20'),(3,'fangedleon23',1,'Your visit summary from Oct 2, 2026 is ready. Log in to view your diagnosis.',1,'2026-10-01 09:15:09'),(4,'fangedleon23',104,'Your appointment request for General Consultation on Oct 23, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-15 20:14:36'),(5,'fangedleon23',104,'You cancelled your General Consultation appointment on Oct 23, 2026.',1,'2026-10-15 20:14:54'),(6,'fangedleon23',105,'Your appointment request for Family Planning Services on Oct 15, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-15 20:26:54'),(7,'fangedleon23',105,'Your Family Planning Services appointment on Oct 15, 2026 has been confirmed with Dr. Staff1.',1,'2026-10-15 20:27:18'),(8,'fangedleon1',105,'You\'ve been assigned a new patient: Co, Earl Vincent for Family Planning Services on Oct 15, 2026 at 9:26 PM.',1,'2026-10-15 20:27:18'),(9,'fangedleon23',106,'Your appointment request for Family Planning Services on Oct 16, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-15 21:46:30'),(10,'fangedleon23',107,'Your appointment request for TB Follow Up on Oct 15, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-15 22:35:11'),(11,'fangedleon23',108,'Your appointment request for Family Planning Services on Oct 19, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-16 08:17:23'),(12,'fangedleon23',109,'Your appointment request for General Consultation on Oct 16, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-16 08:51:06'),(13,'fangedleon23',110,'Your appointment request for General Consultation on Oct 16, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-16 08:51:36'),(14,'fangedleon23',111,'Your appointment request for Family Planning Services on Oct 16, 2026 has been submitted and is awaiting confirmation.',1,'2026-10-16 08:58:14'),(15,'fangedleon23',105,'You were marked as a no-show for your Family Planning Services appointment on Oct 15, 2026. Please contact the health center to reschedule.',0,'2026-10-16 11:42:23');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,'princevincent0123@gmail.com','403191','2026-09-19 05:00:50','2026-09-19 10:45:50'),(2,'princevincent0123@gmail.com','679389','2026-09-19 05:06:28','2026-09-19 10:51:28'),(3,'princevincent0123@gmail.com','151518','2026-09-19 05:06:42','2026-09-19 10:51:42'),(4,'princevincent0123@gmail.com','547501','2026-10-16 05:09:01','2026-10-16 10:54:01');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `patient_id` varchar(20) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `health_center_id` int DEFAULT NULL,
  `pending_staff` tinyint(1) NOT NULL DEFAULT '0',
  `staff_position` varchar(100) DEFAULT NULL,
  `staff_claimed_center_id` int DEFAULT NULL,
  `staff_employee_id` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `health_center_id` (`health_center_id`),
  KEY `staff_claimed_center_id` (`staff_claimed_center_id`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`health_center_id`) REFERENCES `health_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`staff_claimed_center_id`) REFERENCES `health_centers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'fangedleon23','Co, Earl Vincent','PT-00001','0928843043',NULL,0,NULL,NULL,NULL,'2005-08-06','Male','143 University Avenue Potrero, Malabon City','Sonia Co','09424577721'),(2,'Vinceeent','Earl Vincent Co','PT-00002','0912456789',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,'test1','test','PT-00003','0913456789',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,'test2','test2','PT-00005','0912456789',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(5,'test4','test4','PT-00006','0913456789',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,'fangedleon1','Staff1','PT-00007','0913456789',2,0,'Barangay Health Staff',2,'HC-2023-1',NULL,NULL,NULL,NULL,NULL),(7,'admintest1','Co, Earl Vincent','PT-00008','092388430434',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(8,'stafftest1','Co, Earl Vincent','PT-00009','09238843042',2,0,'Health Center Staff',2,'',NULL,NULL,NULL,NULL,NULL),(9,'doctortest1','Juan Dela Cruz','PT-00010','091234567890',5,0,'Doctor',5,'',NULL,NULL,NULL,NULL,NULL),(10,'doctortest2','Jobert Cruz','PT-00011','091234567800',4,0,'Doctor',4,'',NULL,NULL,NULL,NULL,NULL),(11,'doctortest3','Sean Reyes','PT-00012','091234567801',6,0,'Doctor',6,'',NULL,NULL,NULL,NULL,NULL),(12,'doctortest4','Timothy','PT-00013','091234567812',3,0,'Doctor',3,'',NULL,NULL,NULL,NULL,NULL),(13,'doctortest5','Jericho','PT-00014','091234567823',1,0,'Doctor',1,'',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `diagnosis_id` int NOT NULL,
  `medicine_id` int NOT NULL,
  `quantity` int NOT NULL,
  `instructions` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `prescribed_by` varchar(100) DEFAULT NULL,
  `prescribed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `diagnosis_id` (`diagnosis_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`diagnosis_id`) REFERENCES `diagnoses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
INSERT INTO `prescriptions` VALUES (1,2,1,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(2,3,1,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(3,4,1,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(4,5,1,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(5,6,1,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(6,7,2,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(7,8,2,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(8,9,2,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(9,10,2,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(10,11,2,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(11,12,3,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(12,13,3,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(13,14,3,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(14,15,3,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(15,16,3,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(16,17,4,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(17,18,4,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(18,19,5,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(19,20,5,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(20,21,5,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(21,22,5,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(22,23,5,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(23,24,6,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(24,25,6,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(25,26,6,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(26,27,6,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(27,28,6,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(28,29,7,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(29,30,7,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(30,31,7,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(31,32,7,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(32,33,7,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(33,34,8,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(34,35,8,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(35,36,9,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(36,37,9,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(37,38,9,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(38,39,9,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(39,40,9,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(40,41,10,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(41,42,10,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(42,43,10,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(43,44,10,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(44,45,10,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(45,46,11,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(46,47,11,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(47,48,11,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(48,49,11,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(49,50,11,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(50,51,12,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(51,52,12,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(52,53,13,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(53,54,13,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(54,55,13,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(55,56,13,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(56,57,13,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(57,58,14,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(58,59,14,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(59,60,14,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(60,61,14,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(61,62,14,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(62,63,15,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(63,64,15,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(64,65,15,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(65,66,15,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(66,67,15,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(67,68,16,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(68,69,16,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(69,70,17,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(70,71,17,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(71,72,17,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(72,73,17,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(73,74,17,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(74,75,18,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(75,76,18,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(76,77,18,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(77,78,18,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(78,79,18,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(79,80,19,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(80,81,19,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(81,82,19,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(82,83,19,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(83,84,19,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(84,85,20,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(85,86,20,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(86,87,21,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(87,88,21,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(88,89,21,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(89,90,21,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(90,91,21,50,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(91,92,22,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(92,93,22,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(93,94,22,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(94,95,22,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(95,96,22,60,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(96,97,23,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(97,98,23,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(98,99,23,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(99,100,23,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(100,101,23,30,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(101,102,24,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48'),(102,103,24,10,'Seed test data','2026-10-01 12:31:29',NULL,'2026-10-16 12:20:48');
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_tokens`
--

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('patient','staff','doctor','admin') NOT NULL DEFAULT 'patient',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'fangedleon23','princevincent0123@gmail.com','$2y$10$GHy3YoI0OXflEi0/C04k6.dHCrumQBAWBsHRPJfAXeb448J3hBI1y','patient','2026-09-19 10:20:14'),(2,'Vinceeent','test123@gmail.com','$2y$10$GQMLxfPivw0tTkEqy1j.w.0k.fc188agxH5A/DZ6OL/YOTBH1vEP6','patient','2026-09-19 10:28:31'),(3,'test1','test1@gmail.com','$2y$10$0s1Ih4.5tjbardQO9h4er.SHus6uU0ViRVGDsiLMoXRHpZ3VA9PH6','patient','2026-09-19 10:30:59'),(5,'test2','test2@gmail.ccom','$2y$10$1kHgen6XqsYXYWSVboWzYOoaNEIgVHV.dOMsGTwMtd0R3SYBwdX0.','patient','2026-09-19 10:38:06'),(6,'test4','test4@gmail.com','$2y$10$kP5UoPA9/zYZ3qYWsYqbEuCn6ZnYofQcVUR4sZ.3iGh6YxrAygQUq','patient','2026-09-19 10:43:35'),(7,'fangedleon1','Staff@gmail.com','$2y$10$sGLsOswWHe3Hv5XuR91ZbemwHpx0MQiiyGplpg6lB6u3g1z1ueTB6','doctor','2026-09-26 08:14:31'),(8,'admintest1','admintest123@gmail.com','$2y$10$pFUbaSXY8ZT65bLIbrYfmecPJIS4bOBan/1rzHDWcTvDYDFSj4Hzu','admin','2026-09-30 14:25:59'),(9,'stafftest1','stafftest@gmail.com','$2y$10$LAURBmbbGKPUMeHfrTOAeetyi2OZABYzxCFWyEHOnNlAsqMfUwtRq','staff','2026-09-30 14:30:00'),(10,'doctortest1','doctortest1@gmail.com','$2y$10$j.ZscrUbWmBbEhJtFv./bueWMKjALwQF1Oo7gkyTqgzPVHZEJOHrS','doctor','2026-10-01 12:13:33'),(11,'doctortest2','doctortest2@gmail.com','$2y$10$MXo/vmCoB3HqBqHOffid2eRplrtgjSNt4c8YxdOwDGizZ1BWw5/GW','doctor','2026-10-01 12:14:10'),(12,'doctortest3','doctortest3@gmail.com','$2y$10$.vxciCMkyqQx3vcn8vaM9esCPfi9kMZYPFzcnVj4LVyPhqvysjUaa','doctor','2026-10-01 12:14:37'),(13,'doctortest4','doctortest4@gmail.com','$2y$10$vsXrjkLPkxbermXMH0IBLOlGku82mmlUxXCJV49ZX1NC9wUMp79YS','doctor','2026-10-01 12:15:16'),(14,'doctortest5','doctortest5@gmail.com','$2y$10$288q0pz7lznHoiM13n3CneDiTHZsR8CKnfZy2deG/O.D8uoWNbnZq','doctor','2026-10-01 12:15:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 13:53:42
