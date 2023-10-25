<?php

  $db_file = '.url.db';
  if (!file_exists($db_file)) {
    $database = new SQLite3('.url.db');
    $database->close();
    $db = new PDO('sqlite:' . $db_file);
    $db->exec('CREATE TABLE IF NOT EXISTS url
      (
      hash CHAR(12) PRIMARY KEY NOT NULL,
      url  CHAR(255) NOT NULL)
    ');
    $db->close();
  }
  $db = new PDO('sqlite:' . $db_file);
?>
