<?php

print_r($_REQUEST);
print_r($_POST);

require __DIR__ . '/vendor/autoload.php';

$config = \Yosymfony\Toml\Toml::ParseFile('./.breww_twinfield_php.toml');
$twinfield_auth = $config['twinfield'];
print_r($twinfield_auth);

$provider    = new \PhpTwinfield\Secure\Provider\OAuthProvider([
    'clientId'     => $twinfield_auth['clientId'],
    'clientSecret' => $twinfield_auth['clientSecret'],
    'redirectUri'  => $twinfield_auth['redirectUri']
]);
$accessToken  = $provider->getAccessToken("authorization_code", ["code" => $_REQUEST['code']]);
print_r($accessToken);
?>

