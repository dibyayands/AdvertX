<?php

 * @package
 * @subpackage


$file = '/lib/max/Delivery/cookie.php';

if(isset($GLOBALS['_MAX']['FILES'][$file])) {
    return;
}

$GLOBALS['_MAX']['FILES'][$file] = true;

$GLOBALS['_MAX']['COOKIE']['LIMITATIONS']['arrCappingCookieNames'] = array();


if (!is_callable('MAX_cookieSet')) {
    if (!empty($conf['cookie']['plugin']) && is_readable(MAX_PATH . "/plugins/cookieStorage/{$conf['cookie']['plugin']}.delivery.php")) {
        include MAX_PATH . "/plugins/cookieStorage/{$conf['cookie']['plugin']}.delivery.php";
    } else {
        function MAX_cookieSet($name, $value, $expire, $path = '/', $domain = null) { return MAX_cookieClientCookieSet($name, $value, $expire, $path, $domain); }
        function MAX_cookieUnset($name) { return MAX_cookieClientCookieUnset($name); }
        function MAX_cookieFlush() { return MAX_cookieClientCookieFlush(); }
        function MAX_cookieLoad() { return true; }
    }
}


 * @param string
 * @param string
 * @param string

function MAX_cookieAdd($name, $value, $expire = 0)
{
    if (!isset($GLOBALS['_MAX']['COOKIE']['CACHE'])) {
        $GLOBALS['_MAX']['COOKIE']['CACHE'] = array();
    }
    $GLOBALS['_MAX']['COOKIE']['CACHE'][$name] = array($value, $expire);
}


 * @param string

function MAX_cookieSetViewerIdAndRedirect($viewerId) {
    $aConf = $GLOBALS['_MAX']['CONF'];

    if (!empty($aConf['privacy']['disableViewerId'])) {
        return;
    }

    MAX_cookieAdd($aConf['var']['viewerId'], $viewerId, _getTimeYearFromNow());
    MAX_cookieFlush();

    if ($GLOBALS['_MAX']['SSL_REQUEST']) {
        $url = MAX_commonConstructSecureDeliveryUrl(basename($_SERVER['SCRIPT_NAME']));
    } else {
        $url = MAX_commonConstructDeliveryUrl(basename($_SERVER['SCRIPT_NAME']));
    }
    $url .= "?{$aConf['var']['cookieTest']}=1&" . $_SERVER['QUERY_STRING'];
    MAX_header("Location: {$url}");

    if(empty($GLOBALS['is_simulation']) && !defined('TEST_ENVIRONMENT_RUNNING')) {
        exit;
    }

}

function _getTimeThirtyDaysFromNow()
{
	return MAX_commonGetTimeNow() + 2592000;
}

function _getTimeYearFromNow()
{
	return MAX_commonGetTimeNow() + 31536000;
}

function _getTimeYearAgo()
{
    return MAX_commonGetTimeNow() - 31536000;
}


function MAX_cookieUnpackCapping()
{
    $conf = $GLOBALS['_MAX']['CONF'];

    $cookieNames = $GLOBALS['_MAX']['COOKIE']['LIMITATIONS']['arrCappingCookieNames'];

	if (!is_array($cookieNames))
		return;

    foreach ($cookieNames as $cookieName) {
        if (!empty($_COOKIE[$cookieName])) {
            if (!is_array($_COOKIE[$cookieName])) {
                $output = array();
                $data = explode('_', $_COOKIE[$cookieName]);
                foreach ($data as $pair) {
                    list($name, $value) = explode('.', $pair);
                    $output[$name] = $value;
                }
                $_COOKIE[$cookieName] = $output;
            }
        }
        if (!empty($_COOKIE['_' . $cookieName]) && is_array($_COOKIE['_' . $cookieName])) {
            foreach ($_COOKIE['_' . $cookieName] as $adId => $cookie) {
                if (_isBlockCookie($cookieName)) {
                    $_COOKIE[$cookieName][$adId] = $cookie;
                } else {
                    if (isset($_COOKIE[$cookieName][$adId])) {
                        $_COOKIE[$cookieName][$adId] += $cookie;
                    } else {
                        $_COOKIE[$cookieName][$adId] = $cookie;
                    }
                }

                MAX_cookieUnset("_{$cookieName}[{$adId}]");
            }
        }
    }
}

 * @param string
 * @return boolean

function _isBlockCookie($cookieName)
{
    return in_array($cookieName, array(
        $GLOBALS['_MAX']['CONF']['var']['blockAd'],
        $GLOBALS['_MAX']['CONF']['var']['blockCampaign'],
        $GLOBALS['_MAX']['CONF']['var']['blockZone'],
        $GLOBALS['_MAX']['CONF']['var']['lastView'],
        $GLOBALS['_MAX']['CONF']['var']['lastClick'],
        $GLOBALS['_MAX']['CONF']['var']['blockLoggingClick'],
    ));
}


 * @param bool
 * @return string

function MAX_cookieGetUniqueViewerId($create = true)
{
    static $uniqueViewerId = null;

    if (!defined('TEST_ENVIRONMENT_RUNNING')) {

        if (null !== $uniqueViewerId) {
            return $uniqueViewerId;
        }

    }


    $conf = $GLOBALS['_MAX']['CONF'];

    $privacyViewerId = empty($conf['privacy']['disableViewerId']) ? null : '01000111010001000101000001010010';

    if (isset($_COOKIE[$conf['var']['viewerId']])) {
        $uniqueViewerId = $privacyViewerId ?: $_COOKIE[$conf['var']['viewerId']];
    } elseif ($create) {
        $uniqueViewerId = $privacyViewerId ?: md5(uniqid('', true));
        $GLOBALS['_MAX']['COOKIE']['newViewerId'] = true;
    } else {
        $uniqueViewerId = null;
    }

    return $uniqueViewerId;
}

 * @return string

function MAX_cookieGetCookielessViewerID()
{
    if (empty($_SERVER['REMOTE_ADDR']) || empty($_SERVER['HTTP_USER_AGENT'])) {
        return '';
    }
    $cookiePrefix = $GLOBALS['_MAX']['MAX_COOKIELESS_PREFIX'];
    return $cookiePrefix . substr(md5($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']), 0, 32-(strlen($cookiePrefix)));
}


 * @return boolean

function MAX_Delivery_cookie_cappingOnRequest()
{

    if (isset($GLOBALS['_OA']['invocationType']) &&
        ($GLOBALS['_OA']['invocationType'] == 'xmlrpc' || $GLOBALS['_OA']['invocationType'] == 'view')
    ) {

        return true;
    }

    return !$GLOBALS['_MAX']['CONF']['logging']['adImpressions'];
}


 * @param string
 * @param integer
 * @param integer
 * @param integer
 * @param integer

function MAX_Delivery_cookie_setCapping($type, $id, $block = 0, $cap = 0, $sessionCap = 0)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $setBlock = false;

    if ($cap > 0) {

        $expire = MAX_commonGetTimeNow() + $conf['cookie']['permCookieSeconds'];

        if (!isset($_COOKIE[$conf['var']['cap' . $type]][$id])) {
            $value = 1;
            $setBlock = true;
        } else if ($_COOKIE[$conf['var']['cap' . $type]][$id] >= $cap) {
            $value = -$_COOKIE[$conf['var']['cap' . $type]][$id]+1;

            $setBlock = true;
        } else {
            $value = 1;
        }
        MAX_cookieAdd("_{$conf['var']['cap' . $type]}[{$id}]", $value, $expire);
    }
    if ($sessionCap > 0) {

        if (!isset($_COOKIE[$conf['var']['sessionCap' . $type]][$id])) {
            $value = 1;
            $setBlock = true;
        } else if ($_COOKIE[$conf['var']['sessionCap' . $type]][$id] >= $sessionCap) {
            $value = -$_COOKIE[$conf['var']['sessionCap' . $type]][$id]+1;

             $setBlock = true;
        } else {
            $value = 1;
        }
        MAX_cookieAdd("_{$conf['var']['sessionCap' . $type]}[{$id}]", $value, 0);
    }
    if ($block > 0 || $setBlock) {

        MAX_cookieAdd("_{$conf['var']['block' . $type]}[{$id}]", MAX_commonGetTimeNow(), _getTimeThirtyDaysFromNow());
    }
}


 * @param string
 * @param mixed
 * @param int
 * @param string
 * @param string
 * @return null

function MAX_cookieClientCookieSet($name, $value, $expire, $path = '/', $domain = null)
{

    if(empty($GLOBALS['is_simulation']) && !defined('TEST_ENVIRONMENT_RUNNING')) {

        if (isset($GLOBALS['_OA']['invocationType']) && $GLOBALS['_OA']['invocationType'] == 'xmlrpc') {
            if (!isset($GLOBALS['_OA']['COOKIE']['XMLRPC_CACHE'])) {
                $GLOBALS['_OA']['COOKIE']['XMLRPC_CACHE'] = array();
            }
            $GLOBALS['_OA']['COOKIE']['XMLRPC_CACHE'][$name] = array($value, $expire);
        } else {
            @setcookie($name, $value, $expire, $path, $domain);
        }

    } else {
       $_COOKIE[$name] = $value;
    }

}

function MAX_cookieClientCookieUnset($name)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $domain = (!empty($conf['cookie']['domain'])) ? $conf['cookie']['domain'] : null;
    MAX_cookieSet($name, false, _getTimeYearAgo(), '/', $domain);

    MAX_cookieSet(str_replace('_', '%5F', urlencode($name)), false, _getTimeYearAgo(), '/', $domain);
}


function MAX_cookieClientCookieFlush()
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $domain = !empty($conf['cookie']['domain']) ? $conf['cookie']['domain'] : null;

    MAX_cookieSendP3PHeaders();

    if (!empty($GLOBALS['_MAX']['COOKIE']['CACHE'])) {
        // Set cookies
        reset($GLOBALS['_MAX']['COOKIE']['CACHE']);
        while (list($name,$v) = each ($GLOBALS['_MAX']['COOKIE']['CACHE'])) {
            list($value, $expire) = $v;

            if ($name === $conf['var']['viewerId']) {
                MAX_cookieClientCookieSet($name, $value, $expire, '/', !empty($conf['cookie']['viewerIdDomain']) ? $conf['cookie']['viewerIdDomain'] : $domain);
            } else {
                MAX_cookieSet($name, $value, $expire, '/', $domain);
            }
        }

        $GLOBALS['_MAX']['COOKIE']['CACHE'] = array();
    }


    $cookieNames = $GLOBALS['_MAX']['COOKIE']['LIMITATIONS']['arrCappingCookieNames'];

	if (!is_array($cookieNames))
		return;

    $maxCookieSize = !empty($conf['cookie']['maxCookieSize']) ? $conf['cookie']['maxCookieSize'] : 2048;


    foreach ($cookieNames as $cookieName) {

        if (empty($_COOKIE["_{$cookieName}"])) {
            continue;
        }
        switch ($cookieName) {
            case $conf['var']['blockAd']            :
            case $conf['var']['blockCampaign']      :
            case $conf['var']['blockZone']          : $expire = _getTimeThirtyDaysFromNow(); break;
            case $conf['var']['lastClick']          :
            case $conf['var']['lastView']           :
            case $conf['var']['capAd']              :
            case $conf['var']['capCampaign']        :
            case $conf['var']['capZone']            : $expire = _getTimeYearFromNow(); break;
            case $conf['var']['sessionCapCampaign'] :
            case $conf['var']['sessionCapAd']       :
            case $conf['var']['sessionCapZone']     : $expire = 0; break;
        }
        if (!empty($_COOKIE[$cookieName]) && is_array($_COOKIE[$cookieName])) {
            $data = array();
            foreach ($_COOKIE[$cookieName] as $adId => $value) {
                $data[] = "{$adId}.{$value}";
            }

            while (strlen(implode('_', $data)) > $maxCookieSize) {
                $data = array_slice($data, 1);
            }
            MAX_cookieSet($cookieName, implode('_', $data), $expire, '/', $domain);
        }
    }
}


function MAX_cookieSendP3PHeaders() {
    // Send P3P headers
    if ($GLOBALS['_MAX']['CONF']['p3p']['policies']) {
		MAX_header("P3P: ". _generateP3PHeader());
	}
}


 * @access private
 * @return string
 
function _generateP3PHeader()
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $p3p_header = '';
    if ($conf['p3p']['policies']) {
		if ($conf['p3p']['policyLocation'] != '') {
			$p3p_header .= " policyref=\"".$conf['p3p']['policyLocation']."\"";
		}
        if ($conf['p3p']['policyLocation'] != '' && $conf['p3p']['compactPolicy'] != '') {
            $p3p_header .= ", ";
        }
		if ($conf['p3p']['compactPolicy'] != '') {
			$p3p_header .= " CP=\"".$conf['p3p']['compactPolicy']."\"";
		}
    }
    return $p3p_header;
}

?>
