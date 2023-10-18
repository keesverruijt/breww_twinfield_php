<html>
  <head>
  </head>
  <body>
    <h1>Breww to Twinfield integration</h1>

    This page asks for access to the 'Brew_Twinfield_php' application.

    Click here to ask for permission:

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

    $config = \Yosymfony\Toml\Toml::ParseFile('./.breww_twinfield_php.toml');
    $twinfield_auth = $config['twinfield'];
    print_r($twinfield_auth);

    $client_id = $twinfield_auth['clientId'];
    $redirect_uri = $twinfield_auth['redirectUri'];
    $nonce = generateSalt(16);

    $url = "https://login.twinfield.com/auth/authentication/connect/authorize?client_id=" . $client_id . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=openid+offline_access+twf.organisation+twf.organisationUser+twf.user&nonce=" . $nonce . "&state=tstate" . $nonce;

    echo "<a href='" . $url . "'>Ask permission for $client_id</a>";
  ?>

  </body>
</html>
