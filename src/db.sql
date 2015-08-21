CREATE TABLE tickets (
  id      INT PRIMARY KEY,
  offset  INT,
  title   TEXT,
  songId INT DEFAULT NULL,
  used    INT DEFAULT 0,
  deleted INT DEFAULT 0
);

CREATE TABLE songs (
  id         INT PRIMARY KEY,
  artist     TEXT,
  title      TEXT,
  source     TEXT DEFAULT NULL,
  hasHarmony INT    DEFAULT 0,
  hasKeys    INT    DEFAULT 0,
  codeNumber TEXT
);