<?php

require_once MAX_PATH . '/lib/max/language/Loader.php';


 * @package    Max
 * @static

class MAX_Admin_Cache
{


     * @return array 

    function AvailableCachingModes()
    {
        Language_Loader::load('default');
        $modes = array();
        $modes['none'] = $GLOBALS['strNone'];
        if (is_writable(MAX_PATH . '/var/cache')) {
            $modes['file'] = $GLOBALS['strCacheFiles'];
        }
        return $modes;
    }

}

?>
