
-- see also https://github.com/bravecollective/dokuwiki-authbrave/blob/master/schema.sql

CREATE TABLE `ban` (
   `id` bigint(20) NOT NULL,
   `criteria` varchar(45) NOT NULL,
   `comment` varchar(45) DEFAULT NULL,
   PRIMARY KEY (`id`)
);

CREATE TABLE `grp` (
    `id` bigint(20) NOT NULL,
    `grp` varchar(255) NOT NULL,
    `criteria` varchar(255) NOT NULL,
    `comment` varchar(45) DEFAULT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE `session` (
    `sessionid` varchar(45) NOT NULL,
    `charid` bigint(20) NOT NULL,
    `created` bigint(20) NOT NULL,
    PRIMARY KEY (`sessionid`)
);

CREATE TABLE `user` (
    `username` varchar(45) NOT NULL,
    `mail` varchar(45) DEFAULT NULL,
    `groups` varchar(254) DEFAULT NULL,
    `charid` bigint(20) NOT NULL,
    `charname` varchar(45) NOT NULL,
    `corpid` bigint(20) NOT NULL,
    `corpname` varchar(45) NOT NULL,
    `allianceid` bigint(20) DEFAULT NULL,
    `alliancename` varchar(45) DEFAULT NULL,
    `authtoken` varchar(45) NOT NULL,
    `authcreated` bigint(20) NOT NULL,
    `authlast` bigint(20) NOT NULL,
    PRIMARY KEY (`charid`)
);
