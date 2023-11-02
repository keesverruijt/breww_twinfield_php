<?php

include 'functions.inc';
include 'db.inc';

function get_by_hash($hash) {
  global $db;

  $sth = $db->prepare('select url from url where hash = ?');
  $sth->execute(array($hash));
  $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
  if (count($rows) > 0) {
    return $rows[0]['url'];
  }
  return null;
}

if (str_starts_with($_SERVER['REQUEST_URI'], '/url?'))
{

  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);

  if (array_key_exists('new', $query)) {
    $url = $query['new'];

    $full_hash = generateHash($url);
    for ($len = 10; $len <= 40; $len += 2) {
      $hash = substr($full_hash, 0, $len);
      $stored_url = get_by_hash($hash);
      
      if ($url == $stored_url) 
      {
	break;
      }
      if ($stored_url == '') {
	try {
	  $sth = $db->prepare('insert into url (hash, url) values (?, ?)');
	  $r = $sth->execute(array($hash, $url));
	  if (!$r)
	  {
	    http_response_code(501);
	    echo $db->errorInfo();
	    return;
	  }
	} catch (Exception $e) {
	  http_response_code(501);
	  echo $e->getMessage();
	  return;
	}
	break;
      }
      $hash = null;
    }

    if (!isset($hash)) {
      http_response_code(501);
      echo "No unique value could be generated";
      return;
    }

    header('Content-Type: text/plain');
    echo $hash;
    return;
  }
}
else if (str_starts_with($_SERVER['REQUEST_URI'], '/url/'))
{
  $hash = substr($_SERVER['REQUEST_URI'], 5);
  $url = get_by_hash($hash);
  if (isset($url)) {
    header('Location: ' . $url, true, 302);
    return;
  }
  http_response_code(404);
  echo "<html><body>Cannot lookup $hash</body></html>";
  return;
}

?>

<html>
<body>

<?php
try {
  $config = get_config();
?>
<button>
  <a href="synchronize.php">Synchronize transactions</a>
</button>
<?php
}
catch (Exception $e) {
?>
<a href="twinfield/index.php">Set-up link to Twinfield</a>
<?php
}
?>

</body>
</html>

