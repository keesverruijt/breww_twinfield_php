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

    require __DIR__ . '/vendor/autoload.php';

    function generateSalt($length = 10){
        //set up random characters
        $chars='1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
        //get the length of the random characters
        $char_len = strlen($chars)-1;
        //store output
        $output = '';
        //iterate over $chars
        while (strlen($output) < $length) {
            /* get random characters and append to output till the length of the output
             is greater than the length provided */
            $output .= $chars[ rand(0, $char_len) ];
        }
        //return the result
        return $output;
    }

    $config_file = $_SERVER['DOCUMENT_ROOT'] . '/.breww_twinfield_php.toml';
    $config = \Yosymfony\Toml\Toml::ParseFile($config_file);
    $twinfield_auth = $config['twinfield'];
    # print_r($twinfield_auth);

    $client_id = $twinfield_auth['clientId'];
    $redirect_uri = $twinfield_auth['redirectUri'];
    $nonce = generateSalt(16);

    $url = "https://login.twinfield.com/auth/authentication/connect/authorize?client_id=" . $client_id . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=openid+offline_access+twf.organisation+twf.organisationUser+twf.user&nonce=" . $nonce . "&state=tstate" . $nonce;
  ?>
    <p>
    Follow the following steps to create a Twinfield Developer Client:
    <nl>
    <li> Go to <a href="https://developers.twinfield.com/">Twinfield Developer Portal</a> (and login using a Developer account.)</li>
    <li> Create a new client named "<code><?php echo $twinfield_auth['clientId'] ?></code>". Choose <i>Authorization code</i> for the
       Authorization flow and keep the Access token type set to <i>JWT</i>, and select <i>Ignore single sign on</i>.</li>
    <li> During creation of the client, generate a new secret (press Generate new) and store this in
       "<?php echo $config_file; ?>" in the <code>clientSecret</code> setting.</li>
    <li> Set the Redirect URL to "<code><?php echo $redirect_uri; ?></code>".</li>
    <li> Save the page then click the link below. This will generate a token, store this in 
       "<?php echo $config_file; ?>" in the <code>refreshToken</code> setting.</li>
    </nl>
    ... and then test the synchronization.
    </p>

    Click here to ask for permission:

    echo "<a href='" . $url . "'>Ask permission for $client_id</a>";
  ?>

  </body>
</html>
