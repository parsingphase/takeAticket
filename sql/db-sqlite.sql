-- Keep one empty line between each command - these are used in loading data for tests
-- Comments at start of line only please
DROP TABLE IF EXISTS tickets;

CREATE TABLE tickets (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
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
  id         INT PRIMARY KEY,
  artist     TEXT,
  title      TEXT,
  source     INT DEFAULT NULL,
  duration   INT DEFAULT NULL,
  codeNumber TEXT
);

DROP TABLE IF EXISTS performers;

CREATE TABLE performers (
  id   INT PRIMARY KEY,
  name TEXT
);

DROP TABLE IF EXISTS tickets_x_performers;

CREATE TABLE tickets_x_performers (
  ticketId    INT  NOT NULL,
  performerId INT  NOT NULL,
  instrumentId  INT NOT NULL
);

DROP TABLE IF EXISTS settings;

CREATE TABLE settings (
  id      INT PRIMARY KEY,
  settingKey TEXT,
  settingValue TEXT
);

DROP TABLE IF EXISTS instruments;

CREATE TABLE instruments (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  name    TEXT,
  abbreviation TEXT,
  iconHtml TEXT
);

DROP TABLE IF EXISTS platforms;

CREATE TABLE platforms (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  name    TEXT
);

DROP TABLE IF EXISTS sources;

CREATE TABLE sources (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  name    TEXT
);

DROP TABLE IF EXISTS songs_x_instruments;

CREATE TABLE songs_x_instruments (
  songId    INT  NOT NULL,
  instrumentId  INT NOT NULL
);

DROP TABLE IF EXISTS songs_x_platforms;

CREATE TABLE songs_x_platforms (
  songId    INT  NOT NULL,
  platformId  INT NOT NULL
);
