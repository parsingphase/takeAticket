-- Keep one empty line between each command - these are used in loading data for tests
-- Comments at start of line only please
DROP TABLE IF EXISTS tickets;

CREATE TABLE tickets (
  id      INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  offset  INT,
  title   TEXT,
  songId  INT DEFAULT NULL,
  used    INT DEFAULT 0,
  deleted INT DEFAULT 0,
  private INT DEFAULT 0,
  blocking INT DEFAULT 0,
  createdBy INT DEFAULT NULL,
  startTime INT DEFAULT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- startTime is unix epoch seconds

DROP TABLE IF EXISTS songs;

CREATE TABLE songs (
  id         INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  artist     TEXT,
  title      TEXT,
  sourceId   INT DEFAULT NULL,
  duration   INT  DEFAULT NULL,
  codeNumber TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS performers;

CREATE TABLE performers (
  id   INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS tickets_x_performers;

CREATE TABLE tickets_x_performers (
  ticketId    INT  NOT NULL,
  performerId INT  NOT NULL,
  instrumentId  INT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS settings;

CREATE TABLE settings (
  id      INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  settingKey TEXT,
  settingValue TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS instruments;

CREATE TABLE instruments (
  id      INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name    TEXT,
  abbreviation TEXT,
  iconHtml TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS platforms;

CREATE TABLE platforms (
id      INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
name    TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS sources;

CREATE TABLE sources (
  id      INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name    TEXT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS songs_x_instruments;

CREATE TABLE songs_x_instruments (
  songId    INT  NOT NULL,
  instrumentId  INT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS songs_x_platforms;

CREATE TABLE songs_x_platforms (
  songId    INT  NOT NULL,
  platformId  INT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
