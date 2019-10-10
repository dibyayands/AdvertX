<?php

 * @package    OpenXDelivery

require_once 'init-delivery-parse.php';
require_once 'memory.php';
require_once 'variables.php';

// Increase the PHP memory_limit value to the minimum required value, if necessery
OX_increaseMemoryLimit(OX_getMinimumRequiredMemory());

if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 0);
}

setupServerVariables();
setupDeliveryConfigVariables();
$conf = $GLOBALS['_MAX']['CONF'];

include MAX_PATH.'/lib/vendor/autoload.php';

$GLOBALS['_OA']['invocationType'] = array_search(basename($_SERVER['SCRIPT_FILENAME']), $conf['file']);

if (!empty($conf['debug']['production'])) {
    error_reporting(E_ALL & ~(E_NOTICE | E_WARNING | E_DEPRECATED | E_STRICT));
} else {

    error_reporting(E_ALL & ~(E_DEPRECATED | E_STRICT));
}

require_once MAX_PATH . '/lib/max/Delivery/common.php';
require_once MAX_PATH . '/lib/max/Delivery/cache.php';

OX_Delivery_logMessage('starting delivery script: ' . basename($_SERVER['REQUEST_URI']), 7);
if (!empty($_REQUEST[$conf['var']['trace']])) {
    OX_Delivery_logMessage('trace enabled: ' . $_REQUEST[$conf['var']['trace']], 7);
}

// Set the viewer's remote information used in logging and delivery limitation evaluation
MAX_remotehostSetInfo();

// Set common delivery parameters in the global scope
MAX_commonInitVariables();

// Load cookie data from client/plugin
MAX_cookieLoad();

// Unpack the packed capping cookies
MAX_cookieUnpackCapping();


if (empty($GLOBALS['_OA']['invocationType']) || $GLOBALS['_OA']['invocationType'] != 'xmlrpc') {
    OX_Delivery_Common_hook('postInit');
}

?>
