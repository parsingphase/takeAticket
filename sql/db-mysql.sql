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
  startTime INT DEFAULT NULL
);
-- startTime is unix epoch seconds

DROP TABLE IF EXISTS songs;

CREATE TABLE songs (
  id         INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  artist     TEXT,
  title      TEXT,
  source     TEXT DEFAULT NULL,
  hasHarmony INT  DEFAULT 0,
  hasKeys    INT  DEFAULT 0,
  duration   INT  DEFAULT NULL,
  inRb3      INT  DEFAULT 0,
  inRb4      INT  DEFAULT 0,
  codeNumber TEXT
);

DROP TABLE IF EXISTS performers;

CREATE TABLE performers (
  id   INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name TEXT
);

DROP TABLE IF EXISTS tickets_x_performers;

CREATE TABLE tickets_x_performers (
  ticketId    INT  NOT NULL,
  performerId INT  NOT NULL,
  instrument  TEXT NOT NULL
);