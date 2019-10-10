<?php

require_once RV_PATH . '/lib/RV.php';

require_once MAX_PATH . '/lib/Max.php';
require_once MAX_PATH . '/lib/max/FileScanner.php';

require_once OX_PATH . '/lib/OX.php';
require_once OX_PATH . '/lib/pear/Cache/Lite.php';
require_once OX_PATH . '/lib/pear/Config.php';

define('MAX_PLUGINS_VAR_WRITE_MODE', 0755);

define('MAX_PLUGINS_EXTENSION', '.plugin.php');
define('MAX_PLUGINS_PATH', '/plugins/');

 * @static
 * @package

class MAX_Plugin
{


     * @static
     * @param string
     * @param string
     * @param string
     * @return mixed

    function &factory($module, $package, $name = null)
    {
        if ($name === null) {
            $name = $package;
        }
        if (!MAX_Plugin::_isEnabledPlugin($module, $package, $name))
        {
            return false;
        }
        if (!MAX_Plugin::_includePluginFile($module, $package, $name))
        {
            return false;
        }
        $className = MAX_Plugin::_getPluginClassName($module, $package, $name);
        $obj = new $className($module, $package, $name);
        $obj->module  = $module;
        $obj->package = $package;
        $obj->name    = $name;
        return $obj;
    }

    function _isEnabledPlugin($module, $package, $name)
    {
        $aRefactoredModules = array('deliveryLimitations', 'bannerTypeHtml', 'bannerTypeText');
        if (in_array($module, $aRefactoredModules))
        {
            $aConf = $GLOBALS['_MAX']['CONF'];
            if (empty($aConf['pluginGroupComponents'][$package]))
            {
                return false;
            }
            if (!$aConf['pluginGroupComponents'][$package])
            {
                return false;
            }
        }
        return true;
    }


     * @static
     * @access private
     * @param string
     * @param string
     * @param string
     * @return boolean

    function _includePluginFile($module, $package, $name = null)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        if ($name === null) {
            $name = $package;
        }
        $packagePath = empty($package) ? "" : $package."/";

        $fileName = MAX_PATH . MAX_PLUGINS_PATH . $module . "/". $packagePath . $name . MAX_PLUGINS_EXTENSION;
        if (!file_exists($fileName)) {
            MAX::raiseError("Unable to include the file $fileName.");
            return false;
        } else {
            include_once $fileName;
        }
        $className = MAX_Plugin::_getPluginClassName($module, $package, $name);
        if (!class_exists($className)) {
            MAX::raiseError("Plugin file included but class '$className' does not exist.");
            return false;
        } else {
            return true;
        }
    }


     * @static
     * @access private
     * @param string
     * @param string
     * @param string
     * @return string

    function _getPluginClassName($module, $package, $name = null)
    {
        if ($name === null) {
            $name = $package;
        }
        $className = 'Plugins_' . ucfirst($module) . '_' . ucfirst($package) . '_' . ucfirst($name);
        return $className;
    }


     * @static
     * @param string
     * @param string
     * @param boolean
     * @param mixed
     * @return array

    function &getPlugins($module, $package = null, $onlyPluginNameAsIndex = true, $recursive = 1)
    {
        $plugins = array();
        $pluginFiles = MAX_Plugin::_getPluginsFiles($module, $package, $recursive);
        foreach ($pluginFiles as $key => $pluginFile) {
            $pluginInfo = explode(':', $key);
            if (count($pluginInfo) > 1) {
                $plugin = MAX_Plugin::factory($module, $pluginInfo[0], $pluginInfo[1]);
                if ($plugin !== false) {
                    if ($onlyPluginNameAsIndex) {
                        $plugins[$pluginInfo[1]] = $plugin ;
                    } else {
                        $plugins[$key] = $plugin;
                    }
                }
            }
        }
        return $plugins;
    }


     * @static
     * @access private
     * @param string
     * @param string
     * @param mixed
     * @return array

    function _getPluginsFiles($module, $package = null, $recursive = 1)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        $pluginsDir = MAX_PATH . MAX_PLUGINS_PATH;

        if (!empty($package)) {
            $dir = $pluginsDir . '/' . $module . '/' . $package;
        } else {
            $dir = $pluginsDir . '/' . $module;
        }
        return MAX_Plugin::_getPluginsFilesFromDirectory($dir, $recursive);
    }


     * @static
     * @access private
     * @param string
     * @param mixed
     * @return array

    function _getPluginsFilesFromDirectory($directory, $recursive = 1)
    {
        if (is_readable($directory)) {
            $fileMask = self::_getFileMask();
            $oFileScanner = new MAX_FileScanner();
            $oFileScanner->addFileTypes(array('php','inc'));
            $oFileScanner->setFileMask($fileMask);
            $oFileScanner->addDir($directory, $recursive);
            return $oFileScanner->getAllFiles();
        } else {
            return array();
        }
    }

    static function _getFileMask()
    {
        return '#^.*'.
            preg_quote(MAX_PLUGINS_PATH, '#').
            '/?([a-zA-Z0-9\-_]*)/?([a-zA-Z0-9\-_]*)?/([a-zA-Z0-9\-_]*)'.
            preg_quote(MAX_PLUGINS_EXTENSION, '#').
            '$#';
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param string
     * @param array
     * @return mixed

    function &callStaticMethod($module, $package, $name = null, $staticMethod, $aParams = null)
    {
        if ($name === null) {
            $name = $package;
        }
        if (!MAX_Plugin::_isEnabledPlugin($module, $package, $name))
        {
            return false;
        }
        if (!MAX_Plugin::_includePluginFile($module, $package, $name)) {
            return false;
        }
        $className = MAX_Plugin::_getPluginClassName($module, $package, $name);


        $aClassMethods = array_map(strtolower, (get_class_methods($className)));
        if (!$aClassMethods) {
            $aClassMethods = array();
        }
        if (!in_array(strtolower($staticMethod), $aClassMethods)) {
            MAX::raiseError("Method '$staticMethod()' not defined in class '$className'.", MAX_ERROR_INVALIDARGS);
            return false;
        }
        if (is_null($aParams)) {
            return call_user_func(array($className, $staticMethod));
        } else {
            return call_user_func_array(array($className, $staticMethod), $aParams);
        }
    }


     * @static
     * @param array
     * @param string
     * @param array
     * @return mixed

    function &callOnPlugins(&$aPlugins, $methodName, $aParams = null)
    {
        if (!is_array($aPlugins)) {
            MAX::raiseError('Bad argument: Not an array of plugins.', MAX_ERROR_INVALIDARGS);
            return false;
        }
        foreach ($aPlugins as $key => $oPlugin) {
            if (!is_a($oPlugin, 'MAX_Plugin_Common')) {
                MAX::raiseError('Bad argument: Not an array of plugins.', MAX_ERROR_INVALIDARGS);
                return false;
            }
        }
        $aReturn = array();
        foreach ($aPlugins as $key => $oPlugin) {

            if (!is_callable(array($oPlugin, $methodName))) {
                $message = "Method '$methodName()' not defined in class '" .
                            MAX_Plugin::_getPluginClassName($oPlugin->module, $oPlugin->package, $oPlugin->name) . "'.";
                MAX::raiseError($message, MAX_ERROR_INVALIDARGS);
                return false;
            }
            if (is_null($aParams)) {
                $aReturn[$key] = call_user_func(array($aPlugins[$key], $methodName));
            } else {
                $aReturn[$key] = call_user_func_array(array($aPlugins[$key], $methodName), $aParams);
            }
        }
        return $aReturn;
    }

     * @static
     * @param string
     * @param string
     * @param string
     * @return mixed

    function &factoryPluginByModuleConfig($module, $configKey = 'type', $omit = 'none')
    {

        $conf = MAX_Plugin::getConfig($module);

        if (!isset($conf[$configKey])) {
            return false;
        } else {
            $packageName = explode(':', $conf[$configKey]);
            if (count($packageName) > 1) {
                $package = $packageName[0];
                $name = $packageName[1];
            } else {
                $package = $conf[$configKey];
                $name = null;
            }
        }

        if ($package == $omit) {
            $r = null;
            return $r;
        }

        if (!empty($module) && !empty($package)) {
            return MAX_Plugin::factory($module, $package, $name);
        }

        return false;
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param boolean
     * @param boolean
     * @return mixed

    function getConfig($module, $package = null, $name = null, $processSections = false, $copyDefaultIfNotExists = true)
    {

        $conf = isset($GLOBALS['_MAX']['CONF'][$module]) ? $GLOBALS['_MAX']['CONF'][$module] : false;
        if (!empty($package)) {
            $conf = isset($conf[$package]) ? $conf[$package] : false;
        }

        if ($conf === false) {
            $configFileName = MAX_Plugin::getConfigFileName($module, $package, $name);
            $conf = MAX_Plugin::getConfigByFileName($configFileName, $processSections, false);
        }

        if ($conf !== false) {
            return $conf;
        }
        if ($copyDefaultIfNotExists) {
            MAX_Plugin::copyDefaultConfig($module, $package, $name);
            $defaultConfigFileName = MAX_Plugin::getConfigFileName($module, $package, $name, true);
            return MAX_Plugin::getConfigByFileName($defaultConfigFileName, $processSections, false);
        }
        OA::debug("Config for $package/$module/$name does not exist.", MAX_ERROR_NOFILE);
        return false;
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param boolean
     * @param string
     * @return string

    function getConfigFileName($module, $package = null, $name = null, $defaultConfig = false, $host = null)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        if ($defaultConfig) {
            if (is_null($host)) {
                $host = 'default';
            }
            $startPath  = MAX_PATH . '/plugins/';
        } else {
            if (is_null($host)) {
                $host = OX_getHostName();
            }
            $startPath  = MAX_PATH . $aConf['pluginPaths']['var'] . 'config/';
        }
        $configName = $host.'.plugin.conf.php';
        if ($package === null) {
            $configPath = $module . '/';
        } elseif ($name === null) {
            $configPath = $module . '/' . $package.'/';
        } else {
            $configPath = $module . '/' . $package . '/' . $name . '.';
        }

        return $startPath . $configPath . $configName;
    }


     * @static
     * @param string
     * @param boolean
     * @param boolean
     * @return mixed

    function getConfigByFileName($configFileName, $processSections = false, $raiseErrors = true)
    {
        if (!file_exists($configFileName)) {
            if ($raiseErrors) {
                MAX::raiseError("Config file '{$configFileName}' does not exist.", MAX_ERROR_NOFILE);
            }
            return false;
        }
        $conf = parse_ini_file($configFileName, $processSections);
        if (isset($conf['realConfig'])) {
            if (preg_match('#.*\/(.*)\.plugin\.conf\.php#D', $configFileName, $match = null)) {
                $configFileName = str_replace($match[1], $conf['realConfig'], $configFileName);
                return MAX_Plugin::getConfigByFileName($configFileName, $processSections, $raiseErrors);
            } else {
                return false;
            }
        }
        if (is_array($conf)) {
            return $conf;
        } else {
            return false;
        }
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @return boolean

    function copyDefaultConfig($module, $package = null, $name = null)
    {
        $configFileName = MAX_Plugin::getConfigFileName($module, $package, $name);
        $defaultConfigFileName = MAX_Plugin::getConfigFileName($module, $package, $name, $default = true);
        if (file_exists($defaultConfigFileName)) {

            MAX_Plugin::_mkDirRecursive(dirname($configFileName), MAX_PLUGINS_VAR_WRITE_MODE);

            $ret = @copy($defaultConfigFileName, $configFileName);
            return $ret;
        }
        return false;
    }


     * @static
     * @param array
     * @param string
     * @param string
     * @param string
     * @return boolean

    function writePluginConfig($aConfig, $module, $package = null, $name = null)
    {
        $conf = $GLOBALS['_MAX']['CONF'];

        $url = @parse_url('http://' . $conf['webpath']['delivery']);
        if (!isset($url['host'])) {
            return false;
        }
        $deliveryHost = $url['host'];
        $configFileName = MAX_Plugin::getConfigFileName($module, $package, $name, false, $deliveryHost);
        if (!file_exists($configFileName)) {
            MAX_Plugin::copyDefaultConfig($module, $package, $name);
        }

        $oConfig = new Config();
        $oConfig->parseConfig($aConfig, 'phpArray');
        $result = $oConfig->writeConfig($configFileName, 'inifile');
        if ($result == false || PEAR::isError($result)) {
            return false;
        }

        $url = @parse_url('http://' . $conf['webpath']['admin']);
        if (isset($url['host'])) {
            $adminHost = $url['host'];
            if ($adminHost != $deliveryHost) {

                $configFileName = MAX_Plugin::getConfigFileName($module, $package, $name, false, $adminHost);
                $aConfig = array('realConfig' => $deliveryHost);
                $oConfig = new Config();
                $oConfig->parseConfig($aConfig, 'phpArray');
                if (!$oConfig->writeConfig($configFileName, 'inifile')) {
                    return false;
                }
            }
        }
        $url = @parse_url('http://' . $conf['webpath']['deliverySSL']);
        if (isset($url['host'])) {
            $deliverySslHost = $url['host'];
            if ($deliverySslHost != $deliveryHost) {
                $configFileName = MAX_Plugin::getConfigFileName($module, $package, $name, false, $deliverySslHost);
                $aConfig = array('realConfig' => $deliveryHost);
                $oConfig = new Config();
                $oConfig->parseConfig($aConfig, 'phpArray');
                if (!$oConfig->writeConfig($configFileName, 'inifile')) {
                    return false;
                }
            }
        }
        return true;
    }

     * @static
     * @access private
     * @param string
     * @param int
     * @return boolean

    function _mkDirRecursive($directory, $mode = null)
    {
        if (substr($directory, 0, strlen(MAX_PATH)) != MAX_PATH) {
            $directory = MAX_PATH . $directory;
        }
        if (is_dir($directory)) {
            return true;
        } else {
            if (is_null($mode)) {
                $mode = MAX_PLUGINS_VAR_WRITE_MODE;
            }
            $ret1 = MAX_Plugin::_mkDirRecursive(dirname($directory));
            $ret2 = @mkdir($directory, $mode);
            return $ret1 && $ret2;
        }
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param integer
     * @return mixed

    function prepareCacheOptions($module, $package, $cacheDir = null, $cacheExpire = 3600)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];

        if (is_null($cacheDir)) {
            $cacheDir = MAX_PATH . $aConf['pluginPaths']['var'] . 'cache/' . $module . '/' . $package . '/';
        }
        $aOptions = array(
            'cacheDir' => $cacheDir,
            'lifeTime' => $cacheExpire,
            'automaticSerialization' => true
        );
        if (!is_dir($aOptions['cacheDir'])) {
            if (!MAX_Plugin::_mkDirRecursive($aOptions['cacheDir'], MAX_PLUGINS_VAR_WRITE_MODE)) {
                MAX::raiseError('Folder: "' . $aOptions['cacheDir'] . '" is not writeable.', PEAR_LOG_ERR);
                return false;
            }
        }
        return $aOptions;
    }


     * @static
     * @param mixed
     * @param string
     * @param string
     * @param string
     * @param string
     * @param array
     * @return boolean

    function saveCacheForPlugin($data, $id, $module, $package, $name = null, $aOptions = null)
    {
        if (is_null($name)) {
            $name = $package;
        }
        if (is_null($aOptions)) {
            $aOptions = MAX_Plugin::prepareCacheOptions($module, $package);
        }
        $cache = new Cache_Lite($aOptions);
        return $cache->save($data, $id, $name);
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param string
     * @param boolean
     * @param array
     * @return mixed

    function getCacheForPluginById($id, $module, $package, $name = null, $doNotTestCacheValidity = true, $aOptions = null)
    {
        if (is_null($name)) {
            $name = $package;
        }
        if (is_null($aOptions)) {
            $aOptions = MAX_Plugin::prepareCacheOptions($module, $package);
        }
        $cache = new Cache_Lite($aOptions);
        return $cache->get($id, $name, $doNotTestCacheValidity);
    }


     * @static
     * @param string
     * @param string
     * @param string
     * @param string
     * @param array
     * @return boolean
    
    function cleanPluginCache($module, $package, $name = null, $mode = 'ingroup', $aOptions = null)
    {
        if (is_null($name)) {
            $name = $package;
        }
        if (is_null($aOptions)) {
            $aOptions = MAX_Plugin::prepareCacheOptions($module, $package);
        }
        $oCache = new Cache_Lite($aOptions);
        return $oCache->clean($name, $mode);
    }

}

?>
