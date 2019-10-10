<?php

/**
 * @package    OpenXDelivery
 */

 * @param $configPath The path to the config file
 * @param $configFile Optional - The suffix of the config file
 * @param $sections Optional - process sections to get a multidimensional array
 *
 * @return mixed The array resulting from the call to parse_ini_file(), with
 *               the appropriate .ini file for the installation.
 */
function parseDeliveryIniFile($configPath = null, $configFile = null, $sections = true)
{
    $fixMysqli = function($conf) {
        if ('mysql' === $conf['database']['type'] && !extension_loaded('mysql') && extension_loaded('mysqli')) {
            $conf['database']['type'] = 'mysqli';
        } elseif ('mysqli' === $conf['database']['type'] && !extension_loaded('mysqli') && extension_loaded('mysql')) {
            $conf['database']['type'] = 'mysql';
        }

        return $conf;
    };

    if (!$configPath) {
        $configPath = MAX_PATH . '/var';
    }
    if ($configFile) {
        $configFile = '.' . $configFile;
    }
    $host = OX_getHostName();
    $configFileName = $configPath . '/' . $host . $configFile . '.conf.php';
    $conf = @parse_ini_file($configFileName, $sections);
    if (isset($conf['realConfig'])) {
        // added for backward compatibility - realConfig points to different config
        $realconf = @parse_ini_file(MAX_PATH . '/var/' . $conf['realConfig'] . '.conf.php', $sections);
        $conf = mergeConfigFiles($realconf, $conf);
    }
    if (!empty($conf)) {
        return $fixMysqli($conf);
    } elseif ($configFile === '.plugin') {
        // For plugins, if no configuration file is found, return the sane default values
        $pluginType = basename($configPath);
        $defaultConfig = MAX_PATH . '/plugins/' . $pluginType . '/default.plugin.conf.php';
        $conf = @parse_ini_file($defaultConfig, $sections);
        if ($conf !== false) {
            // check for false here - it's possible file doesn't exist
            return $conf;
        }
        echo "AdvertX could not read the default configuration file for the {$pluginType} plugin";
        exit(1);
    }
    $configFileName = $configPath . '/default' . $configFile . '.conf.php';
    $conf = @parse_ini_file($configFileName, $sections);
    if (isset($conf['realConfig'])) {
        $conf = @parse_ini_file(MAX_PATH . '/var/' . $conf['realConfig'] . '.conf.php', $sections);
    }
    if (!empty($conf)) {
        return $fixMysqli($conf);
    }
    // Check to ensure Max hasn't been installed
    if (file_exists(MAX_PATH . '/var/INSTALLED')) {
        echo "AdvertX has been installed, but no configuration file was found.\n";
        exit(1);
    }
    
}

if (!function_exists('mergeConfigFiles'))
{
    function mergeConfigFiles($realConfig, $fakeConfig)
    {
        foreach ($fakeConfig as $key => $value) {
            if (is_array($value)) {
                if (!isset($realConfig[$key])) {
                    $realConfig[$key] = array();
                }
                $realConfig[$key] = mergeConfigFiles($realConfig[$key], $value);
            } else {
                if (isset($realConfig[$key]) && is_array($realConfig[$key])) {
                    $realConfig[$key][0] = $value;
                } else {
                    if (isset($realConfig) && !is_array($realConfig)) {
                        $temp = $realConfig;
                        $realConfig = array();
                        $realConfig[0] = $temp;
                    }
                    $realConfig[$key] = $value;
                }
            }
        }
        unset($realConfig['realConfig']);
        return $realConfig;
    }
}

?>