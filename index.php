<?php

if (str_starts_with($_SERVER['REQUEST_URI'], '/url?'))
{
  include 'salt.inc';
  include 'db.inc';

  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);

  if (array_key_exists('new', $query)) {
    $url = $query['new'];
    $salt = generateSalt(12);

    try {
      $sth = $db->prepare('insert into url (salt, url) values (?, ?)');
      $r = $sth->execute(array($salt, $url));
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

    header('Content-Type: text/plain');
    echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $path . '/' . $salt;
    return;
  }
}
else if (str_starts_with($_SERVER['REQUEST_URI'], '/url/'))
{
  include 'db.inc';

  $salt = substr($_SERVER['REQUEST_URI'], 5);
  $sth = $db->prepare('select url from url where salt = ?');
  $sth->execute(array($salt));
  $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
  if (count($rows) > 0) {
    header('Location: ' . $rows[0]['url'], true, 301);
    return;
  }
  http_response_code(404);
  echo "<html><body>Cannot lookup $salt</body></html>";
  return;
}

?>

<html>
<body>
<pre>

<?php
  var_dump($_REQUEST);
  var_dump($_SERVER);
?>

</pre>
</body>
</html>

