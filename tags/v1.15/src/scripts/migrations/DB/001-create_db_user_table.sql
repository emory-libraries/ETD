-- Create database for ETD data
--Create table for etd admins
-- Create a standard user to access the data

-- Replace <DB NAME> with databae name based on enviroment (etd_dev, etd_staging, etd_prod)
-- Replace <PASSWORD> with password you want to use



CREATE DATABASE `<DB NAME>` ;

USE <DB NAME>;

CREATE TABLE IF NOT EXISTS `etd_admins` (
  `netid` varchar(20) NOT NULL,
  `schoolid` varchar(20) NOT NULL,
  `programid` varchar(20) NOT NULL,
  UNIQUE KEY `etd_admins_unique` (`netid`,`schoolid`,`programid`)
);



CREATE USER 'etdusr'@'%' IDENTIFIED BY '<PASSWORD>';
GRANT USAGE ON * . * TO 'etdusr'@'%' IDENTIFIED BY '<PASSWORD>' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0 ;
GRANT ALL PRIVILEGES ON `<DB NAME>` . * TO 'etdusr'@'%';

CREATE USER 'etdusr'@'localhost' IDENTIFIED BY '<PASSWORD>';
GRANT USAGE ON * . * TO 'etdusr'@'localhost' IDENTIFIED BY '<PASSWORD>' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0 ;
GRANT ALL PRIVILEGES ON `<DB NAME>` . * TO 'etdusr'@'localhost';
