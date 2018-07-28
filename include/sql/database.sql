-- MySQL dump 10.16  Distrib 10.1.26-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: autoqueuer
-- ------------------------------------------------------
-- Server version	10.1.26-MariaDB-0+deb9u1

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

CREATE DATABASE IF NOT EXISTS `autoqueuer`;

USE `autoqueuer`

--
-- Table structure for table `clientpumps`
--

DROP TABLE IF EXISTS `clientpumps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientpumps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` tinytext NOT NULL,
  `password` tinytext NOT NULL,
  `host` tinytext NOT NULL,
  `port` int(11) NOT NULL,
  `type` tinytext NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  `searched` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `searches` bigint(20) NOT NULL DEFAULT '0',
  `queuedfiles` bigint(20) NOT NULL DEFAULT '0',
  `path_incoming` text NOT NULL,
  `updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collections`
--

DROP TABLE IF EXISTS `collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collections` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `host` text NOT NULL,
  `hostpath` text NOT NULL,
  `username` text NOT NULL,
  `password` text NOT NULL,
  `rootpath` text NOT NULL,
  `url` tinytext NOT NULL,
  `enabled` int(11) NOT NULL DEFAULT '1',
  `updated` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_clientpumps` int(11) NOT NULL,
  `id_collections` bigint(20) NOT NULL DEFAULT '0',
  `id_searches` int(11) NOT NULL DEFAULT '0',
  `name` tinytext,
  `path` tinytext,
  `ed2khash` varchar(32) DEFAULT NULL,
  `size` bigint(20) NOT NULL DEFAULT '0',
  `verified` int(11) NOT NULL DEFAULT '1',
  `existing` int(11) NOT NULL DEFAULT '0',
  `moved` int(11) NOT NULL DEFAULT '0',
  `fakecheck` int(11) NOT NULL,
  `redownload` int(11) NOT NULL DEFAULT '0',
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `ed2khash` (`ed2khash`)
) ENGINE=MyISAM AUTO_INCREMENT=36305 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files_unfinished`
--

DROP TABLE IF EXISTS `files_unfinished`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files_unfinished` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_clientpumps` bigint(20) NOT NULL,
  `name` tinytext NOT NULL,
  `size` bigint(20) NOT NULL,
  `renewable` int(11) NOT NULL DEFAULT '0',
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=359 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logmessages`
--

DROP TABLE IF EXISTS `logmessages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logmessages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_logmessages_parent` bigint(20) NOT NULL DEFAULT '0',
  `id_files` int(11) NOT NULL DEFAULT '0',
  `type` int(11) NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `updated` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15649 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `moverules`
--

DROP TABLE IF EXISTS `moverules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moverules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nickname` tinytext NOT NULL,
  `regex` text NOT NULL,
  `movetopath` text NOT NULL,
  `movetochgrp` tinytext NOT NULL,
  `movetochmod` varchar(4) NOT NULL,
  `matches` int(11) NOT NULL DEFAULT '0',
  `cmdaftermove` tinytext NOT NULL,
  `filessincelastmail` int(11) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parameters`
--

DROP TABLE IF EXISTS `parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parameters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parameter` tinytext NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searches`
--

DROP TABLE IF EXISTS `searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_clientpumps` int(11) NOT NULL DEFAULT '0',
  `nickname` tinytext NOT NULL,
  `search` tinytext NOT NULL,
  `type` tinytext NOT NULL,
  `sizemin` bigint(20) NOT NULL DEFAULT '0',
  `sizemax` bigint(20) NOT NULL DEFAULT '0',
  `extension` tinytext NOT NULL,
  `method` tinytext NOT NULL,
  `executiontimeout` bigint(20) NOT NULL DEFAULT '0',
  `executiontimeoutbase` bigint(20) NOT NULL DEFAULT '0',
  `executiontimeoutrandbase` bigint(20) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT '0',
  `executions` bigint(20) NOT NULL DEFAULT '0',
  `resultscans` int(11) NOT NULL DEFAULT '0',
  `queuedfiles` int(11) NOT NULL DEFAULT '0',
  `filessincelastmail` int(11) NOT NULL DEFAULT '0',
  `movetopath` text NOT NULL,
  `movetochgrp` tinytext NOT NULL,
  `movetochmod` varchar(4) NOT NULL,
  `executed` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=29 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_visum` int(11) NOT NULL,
  `nickname` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `password` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `username` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `gender` enum('0','1','2') COLLATE utf8_unicode_ci NOT NULL,
  `birth` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-07-28 15:09:05
