<?php

 * @package    Max

require_once 'pre-check.php';
require_once 'init-parse.php';
require_once 'variables.php';
require_once 'constants.php';

function init()
{
    // Prevent _MAX from being read from the request string (if register globals is on)
    unset($GLOBALS['_MAX']);
    unset($GLOBALS['_OX']);

    if (!function_exists("ctype_alnum")) {
       function ctype_alnum($text) { return preg_match("/^[A-Za-z\d\300-\377]+$/", $text); }
       function ctype_alpha($text) { return preg_match("/^[a-zA-Z\300-\377]+$/", $text); }
       function ctype_digit($text) { return preg_match("/^\d+$/", $text); }
       function ctype_xdigit($text){ return preg_match("/^[a-fA-F0-9]+$/", $text); }
       function ctype_cntrl($text) { return preg_match("/^[\000-\037]+$/", $text); }
       function ctype_space($text) { return preg_match("/^\s+$/", $text); }
       function ctype_upper($text) { return preg_match("/^[A-Z\300-\337]+$/", $text); }
       function ctype_lower($text) { return preg_match("/^[a-z\340-\377]+$/", $text); }
       function ctype_graph($text) { return preg_match("/^[\041-\176\241-\377]+$/", $text); }
       function ctype_punct($text) { return preg_match("/^[^0-9A-Za-z\000-\040\177-\240\300-\377]+$/", $text); }
       function ctype_print($text) { return ctype_punct($text) && ctype_graph($text); }
    }

    // Set up server variables
    setupServerVariables();

    // Set up the UI constants
    setupConstants();

    // Set up the common configuration variables
    setupConfigVariables();

    require MAX_PATH.'/lib/vendor/autoload.php';
    $GLOBALS['_MAX']['DI'] = new \RV\Container($GLOBALS['_MAX']['CONF']);

    error_reporting(E_ALL & ~(E_NOTICE | E_WARNING | E_DEPRECATED | E_STRICT));

    if ( (!isset($GLOBALS['_MAX']['CONF']['openads']['installed'])) || (!$GLOBALS['_MAX']['CONF']['openads']['installed']) )
    {
        define('OA_INSTALLATION_STATUS',    OA_INSTALLATION_STATUS_NOTINSTALLED);
    }
    else if ($GLOBALS['_MAX']['CONF']['openads']['installed'] && file_exists(MAX_PATH.'/var/UPGRADE'))
    {
        define('OA_INSTALLATION_STATUS',    OA_INSTALLATION_STATUS_UPGRADING);
    }
    else if ($GLOBALS['_MAX']['CONF']['openads']['installed'] && file_exists(MAX_PATH.'/var/INSTALLED'))
    {
        define('OA_INSTALLATION_STATUS',    OA_INSTALLATION_STATUS_INSTALLED);
    }

    global $installing;
    if ((!$installing) && (PHP_SAPI != 'cli')) {
        $scriptName = basename($_SERVER['SCRIPT_NAME']);
        if ($scriptName != 'install.php' && PHP_SAPI != 'cli')
        {

            if (OA_INSTALLATION_STATUS !== OA_INSTALLATION_STATUS_INSTALLED)
            {
                if ($scriptName == 'maintenance.php' || $scriptName == 'maintenance-distributed.php') {
                    exit;
                }

                $path = dirname($_SERVER['SCRIPT_NAME']);
                if ($path == DIRECTORY_SEPARATOR)
                {
                    $path = '';
                }
                if (defined('ROOT_INDEX'))
                {
                    $location = 'Location: ' . $GLOBALS['_MAX']['HTTP'] .
                           OX_getHostNameWithPort() . $path . '/www/admin/install.php';
                    header($location);
                } elseif (defined('WWW_INDEX'))
                {
                    $location = 'Location: ' . $GLOBALS['_MAX']['HTTP'] .
                           OX_getHostNameWithPort() . $path . '/admin/install.php';
                    header($location);
                } else
                {
                    $location = 'Location: ' . $GLOBALS['_MAX']['HTTP'] .
                           OX_getHostNameWithPort() . $path . '/install.php';
                    header($location);
                }
                exit();
            }
        }
    }

    $conf = $GLOBALS['_MAX']['CONF'];
    include_once MAX_PATH . '/lib/max/ErrorHandler.php';
    $eh = new MAX_ErrorHandler();
    $eh->startHandler();

    $GLOBALS['_OX']['ORIGINAL_MEMORY_LIMIT'] = OX_getMemoryLimitSizeInBytes();

    OX_increaseMemoryLimit(OX_getMinimumRequiredMemory());
}

init();

require_once 'PEAR.php';

$conf = $GLOBALS['_MAX']['CONF'];

?>
