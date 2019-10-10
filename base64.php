<?php

 * @package    MaxDelivery
 * @subpackage base64


 * @param string
 * @return string

function MAX_base64EncodeUrlSafe($string) {

    $search  = array('+', '/', '=');
    $replace = array('-', '~', '');

    $string  = base64_encode($string);
    return str_replace($search, $replace, $string);
}


 * @param string
 * @return string

function MAX_base64DecodeUrlSafe($string) {
  
    $search  = array('-', '~');
    $replace = array('+', '/');

    $string = str_replace($search, $replace, $string);
    return base64_decode($string);
}

?>
