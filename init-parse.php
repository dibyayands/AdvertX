<?php

 * @package    ReviveAdserver

 * @param string $configPath
 * @param string $configFile
 * @param boolean $sections
 * @param string  $type

 * @return mixed

function parseIniFile($configPath = null, $configFile = null, $sections = true, $type = '.php')
{
    $fixMysqli = function($conf) {
        if ('mysql' === $conf['database']['type'] && !extension_loaded('mysql') && extension_loaded('mysqli')) {
            $conf['database']['type'] = 'mysqli';
        } elseif ('mysqli' === $conf['database']['type']) {
            if (empty($conf['table']['type'])) {
                $conf['table']['type'] = 'InnoDB';
            }
            if (!extension_loaded('mysqli') && extension_loaded('mysql')) {
                $conf['database']['type'] = 'mysql';
            }
        }

        return $conf;
    };

    // Set up the configuration .ini file path location
    if (is_null($configPath)) {
        $configPath = MAX_PATH . '/var';
    }
    // Set up the configuration .ini file type name
    if (!is_null($configFile)) {
        $configFile = '.' . $configFile;
    }

    if (is_null($configFile) && !isset($_SERVER['SERVER_NAME'])) {

        if (defined('TEST_ENVIRONMENT_RUNNING')) {
            $_SERVER['HTTP_HOST'] = 'test';
        } else {
            if (!isset($GLOBALS['argv'][1])) {
                echo PRODUCT_NAME . " was called via the command line, but had no host as a parameter.\n";
                exit(1);
            }
            $_SERVER['HTTP_HOST'] = trim($GLOBALS['argv'][1]);
        }
    }

    $host = OX_getHostName();


    if (is_null($configFile) && defined('TEST_ENVIRONMENT_RUNNING') && empty($GLOBALS['override_TEST_ENVIRONMENT_RUNNING'])) {
        // Does the test environment config exist?
        $testFilePath = $configPath . '/test.conf' . $type;
        if (file_exists($testFilePath)) {
            return @parse_ini_file($testFilePath, $sections);
        } else {

            define('TEST_ENVIRONMENT_NO_CONFIG', true);
            return array();
        }
    }
    // Is the .ini file for the hostname being used directly accessible?
    if (file_exists($configPath . '/' . $host . $configFile . '.conf' . $type)) {
        // Parse the configuration file
        $conf = @parse_ini_file($configPath . '/' . $host . $configFile . '.conf' . $type, $sections);
        // Is this a real config file?
        if (!isset($conf['realConfig'])) {
            // Yes, return the parsed configuration file
            return $fixMysqli($conf);
        }
        // Parse and return the real configuration .ini file
        if (file_exists($configPath . '/' . $conf['realConfig'] . $configFile . '.conf' . $type)) {
            $realConfig = @parse_ini_file(MAX_PATH . '/var/' . $conf['realConfig'] . '.conf' . $type, true);
            $mergedConf = mergeConfigFiles($realConfig, $conf);
            // if not multiple levels of configs
            if (!isset($mergedConf['realConfig'])) {
                return $fixMysqli($mergedConf);
            }
        }
    } elseif ($configFile === '.plugin') {
        // For plugins, if no configuration file is found, return the sane default values
        $pluginType = basename($configPath);
        $defaultConfig = MAX_PATH . '/plugins/' . $pluginType . '/default.plugin.conf' . $type;
        if (file_exists($defaultConfig)) {
            return parse_ini_file($defaultConfig, $sections);
        } else {
            echo PRODUCT_NAME . " could not read the default configuration file for the {$pluginType} plugin";
            exit(1);
        }
    }
    // Check for a default.conf.php file...
    if (file_exists($configPath . '/default' . $configFile . '.conf' . $type)) {
        // Parse the configuration file
        $conf = @parse_ini_file($configPath . '/default' . $configFile . '.conf' . $type, $sections);
        // Is this a real config file?
        if (!isset($conf['realConfig'])) {
            // Yes, return the parsed configuration file
            return $fixMysqli($conf);
        }
        // Parse and return the real configuration .ini file
        if (file_exists($configPath . '/' . $conf['realConfig'] . $configFile . '.conf' . $type)) {
            $realConfig = @parse_ini_file(MAX_PATH . '/var/' . $conf['realConfig'] . '.conf' . $type, true);
            $mergedConf = mergeConfigFiles($realConfig, $conf);
            // if not multiple levels of configs
            if (!isset($mergedConf['realConfig'])) {
                return $fixMysqli($mergedConf);
            }
        }
    }

    global $installing;
    if ($installing)
    {
        if (file_exists($configPath . '/' . $host . $configFile . '.conf.ini'))
        {
            return parseIniFile($configPath, $configFile, $sections, '.ini');
        }
        if (!$configFile)
        {

            return @parse_ini_file(MAX_PATH . '/etc/dist.conf.php', $sections);
        }

    }

    if (file_exists(MAX_PATH . '/var/INSTALLED'))
    {

        if (file_exists($configPath . '/' . $host . $configFile . '.conf.ini'))
        {
            return parseIniFile($configPath, $configFile, $sections, '.ini');
        }
        echo PRODUCT_NAME . " has been installed, but no configuration file ".$configPath . '/' . $host . $configFile . '.conf.php'." was found.\n";
        exit(1);
    }
    return @parse_ini_file(MAX_PATH . '/etc/dist.conf.php', $sections);
}

?>
