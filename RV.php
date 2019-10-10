<?php

require_once RV_PATH . '/lib/pear/PEAR.php';
 * @package

class RV
{
     * @static

    static function disableErrorHandling()
    {
        PEAR::pushErrorHandling(null);
    }

     * @static

    static function enableErrorHandling()
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        list($mode, $options) = $stack[sizeof($stack) - 1];
        if (is_null($mode) && is_null($options)) {
            PEAR::popErrorHandling();
        }
    }

     * @static
     * @return array

    static function getAppConfig() {
        return $GLOBALS['_MAX']['CONF'];
    }


     * @static
     * @param string
     * @param array
     * @return string
     
    static function stripVersion($version, $aAllow = null)
    {
        $allow = is_null($aAllow) ? '' : '|'.join('|', $aAllow);
        return preg_replace('/^v?(\d+.\d+.\d+(?:-(?:beta(?:-rc\d+)?|rc\d+'.$allow.'))?).*$/i', '$1', $version);
    }

}

?>
