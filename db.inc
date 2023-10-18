<?php

  $database = new SQLite3('.url.db');
  $database->close();

  $db = new PDO('sqlite:.url.db');

  $db->exec('CREATE TABLE IF NOT EXISTS url
    (
    salt CHAR(10) PRIMARY KEY NOT NULL,
    url CHAR(255) NOT NULL)
  ');
?>
