<?php

require_once MAX_PATH . '/lib/max/Plugin.php';
require_once MAX_PATH . '/lib/max/language/Loader.php';


 * @package
 * @static

class MAX_Admin_Geotargeting
{

     * @return array
     
    function AvailableGeotargetingModes()
    {
        Language_Loader::load('default');

        $plugins = &MAX_Plugin::getPlugins('geotargeting');
        $modes['none'] = $GLOBALS['strNone'];
        $pluginModes = MAX_Plugin::callOnPlugins($plugins, 'getModuleInfo');
        foreach($pluginModes as $key => $pluginMode) {
            $modes[$key] = $pluginMode;
        }
        return $modes;
    }

}

?>
