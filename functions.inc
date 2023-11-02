<?php
require_once __DIR__ . '/vendor/autoload.php';

function generateSalt($length = 10) {
  $chars='1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
  $char_len = strlen($chars)-1;
  $output = '';
  while (strlen($output) < $length) {
    /* get random characters and append to output till the length of the output
     is greater than the length provided */
    $output .= $chars[ rand(0, $char_len) ];
  }
  return $output;
}

function generateHash($data) {
  return hash('sha256', $data);
}

function keyvalue($array, $key, $default = '') : string {
  if (!is_array($array)) {
    return $default;
  }
  if (array_key_exists($key, $array)) {
    return $array[$key];
  }
  return $default;
}

function url_origin( $s, $use_forwarded_host = false )
{
    $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
    $sp       = strtolower( $s['SERVER_PROTOCOL'] );
    $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
    $port     = $s['SERVER_PORT'];
    $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
    $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
    $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
    return $protocol . '://' . $host;
}

function full_url( $s, $use_forwarded_host = false )
{
    return url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
}

function get_config_str() : string {
  return 
    "The configuration file should contain the following:\n"
    . "\n"
    . "[settings]\n"
    . "  url          = \"<where-this-is-running>\"\n"
    . "\n"
    . "[twinfield]\n"
    . "  clientId     = \"<application_id>\"\n"
    . "  clientSecret = \"<application_secret>\"\n"
    . "  redirectUri  = \"<redirect_uri>\"\n"
    . "  refreshToken = \"<token>\"\n"
    . "  officeCode   = \"<twinfield_office_code>\"\n"
    . "  salesCode    = \"VRK\"\n"
    . "  # Map BREWW tax_rate_decimal to Twinfield taxrate codes. No provision for EU/NonEU rates yet\n"
    . "  vatCodes     = { \"0.00\" = \"VN\", \"0.09\" = \"VL\", \"0.21\" = \"VH\" }\n"
    . "\n"
    . "[breww]\n"
    . "  token           = \"<breww_application_token>\"\n"
    . "  dimension_regex = \"GBTF-(\\d{4})(-|$)\"\n"
    . "  dimension_group = \"1\"\n";
}

$config_file = $_SERVER['DOCUMENT_ROOT'] . '/.breww_twinfield_php.toml';
function get_config()
{
  global $config_file;

  try {
    if (!file_exists($config_file)) {
      throw new Exception("There is no config file $config_file");
    }
    $config = \Yosymfony\Toml\Toml::ParseFile($config_file);

    if (!array_key_exists('settings', $config))
    {
      $config['settings'] = array();
    }
    if (!array_key_exists('url', $config['settings']) && array_key_exists('SERVER_PROTOCOL', $_SERVER))
    {
      $config['settings']['url'] = url_origin($_SERVER);
    }

    if (!array_key_exists('twinfield', $config))
    {
      $config['twinfield'] = array();
    }
    if (!array_key_exists('redirectUri', $config['twinfield']) && array_key_exists('SERVER_PROTOCOL', $_SERVER))
    {
      $config['twinfield']['redirectUri'] = url_origin($_SERVER) . '/twinfield_setup/redirect.php';
    }

    return $config;
  } 
  catch (Exception $e) {
    if (array_key_exists('SERVER_PROTOCOL')) {
       echo "<pre>" . get_config_str() . "</pre>";
       exit(0);
    }
    $log->info(get_config_str());

    throw $e;
  }
}

?>
