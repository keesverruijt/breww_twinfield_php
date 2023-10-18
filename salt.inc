<?php
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
?>
