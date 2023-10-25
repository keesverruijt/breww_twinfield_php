<html>
  <head>
  </head>
  <body>
    <h1>Breww to Twinfield integration</h1>

    <p>
    This page asks Twinfield for access to the 'Brew_Twinfield_php' application.
    It uses the 'indirect OAuth2' method. 
    </p>

    <?php

    require __DIR__ . '/../vendor/autoload.php';

    require __DIR__ . '/../functions.inc';

    $config_file = $_SERVER['DOCUMENT_ROOT'] . '/.breww_twinfield_php.toml';
    $config = get_config($config_file);

    if (!array_key_exists('twinfield', $config))
    {
      $config['twinfield'] = array();
    }
    $twinfield_auth = $config['twinfield'];

    $client_id = keyvalue($twinfield_auth, 'clientId');
    $client_secret = str_repeat('*', strlen(keyvalue($twinfield_auth, 'clientSecret')));
    $refresh_token = str_repeat('*', strlen(keyvalue($twinfield_auth, 'refreshToken')));
    $redirect_uri = keyvalue($twinfield_auth, 'redirectUri', str_replace('index.php', 'redirect.php', full_url($_SERVER)));
    $nonce = generateSalt(16);

    if ($client_id != '' && $redirect_uri != '')
    {
      $url = "https://login.twinfield.com/auth/authentication/connect/authorize?client_id=" . $client_id . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=openid+offline_access+twf.organisation+twf.organisationUser+twf.user&nonce=" . $nonce . "&state=tstate" . $nonce;
    }

    echo "<p>The config file for this package is: <code>$config_file</code></p>\n";
    echo "<p>Current settings in this file for Twinfield access:</p>\n";
    echo "<table><tr><th>Setting</th><th>Value</th></tr>\n";
    echo "<tr><td>clientId</td><td>$client_id</td></tr>\n";
    echo "<tr><td>clientSecret</td><td>$client_secret</td></tr>\n";
    echo "<tr><td>redirectUri</td><td>$redirect_uri</td></tr>\n";
    echo "<tr><td>refreshToken</td><td>$refresh_token</td></tr>\n";
    echo "</table>\n";

    if ($refresh_token != '' && $client_secret != '' and $client_id != '') {
      echo "<p>All values are filled in. The connection to Twinfield should work already, and you no longer need to do the following...</p>\n";
    }

    if ($client_id == '' || !array_key_exists('redirectUri', $twinfield_auth)) {
      echo "<p>First, you must update the config file and set both <code>clientId</code> and <code>redirectUri</code> settings.</p>";
      if ($client_id == '')
      {
	echo "<p>The <code>clientId</code> should be set to a short text without spaces, for instance 'breww_twinfield_php'.</p>";
      }
      if (!array_key_exists('redirectUri', $twinfield_auth)) {
	echo "<p>The <code>redirectUri</code> should be set '$redirect_uri'.</p>";
      }
      echo "</body></html>";
      return;
    }
?>
    <p>
    Follow the following steps to create a Twinfield Developer Client:
    <nl>
    <li> Go to <a href="https://developers.twinfield.com/">Twinfield Developer Portal</a> (and login using a Developer account.)</li>
    <li>
     <?php echo "Create a new client named \"<code>$client_id</code>\" (this is set as the <code>clientId</code> in my config file.)"; ?>
    </li>
    <li> Choose <i>Authorization code</i> for the Authorization flow and keep the Access token type set to 
         <i>JWT</i>, and select <i>Ignore single sign on</i>.</li>
    <li> During creation of the client, generate a new secret (press Generate new) and store this in the config file 
         in the <code>clientSecret</code> setting.</li>
    <li> Set the Redirect URL to "<code><?php echo $redirect_uri; ?></code>".</li>
    <li> Save the page then click the link below. This will generate a token, store this in 
       the config file in the <code>refreshToken</code> setting.</li>
    </nl>
    ... and then test the synchronization.
    </p>


<?php
    if (isset($url)) {
      echo "Click here to let this code ask Twinfield for a token:";
      echo "<a href='" . $url . "'>Ask Twinfield permission for $client_id</a>";
    } else
    {
      echo "No client ID set in config file; refresh page to check.\n";
    }
?>

  </body>
</html>
