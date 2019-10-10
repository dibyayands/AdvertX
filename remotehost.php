<?php

$file = '/lib/max/Delivery/remotehost.php';

if(isset($GLOBALS['_MAX']['FILES'][$file])) {
    return;
}

$GLOBALS['_MAX']['FILES'][$file] = true;

 * @package
 * @subpackage
 * @param boolean

function MAX_remotehostSetInfo($run = false)
{
    if (empty($GLOBALS['_OA']['invocationType']) || $run || ($GLOBALS['_OA']['invocationType'] != 'xmlrpc')) {
        MAX_remotehostProxyLookup();
        MAX_remotehostAnonymise();
        MAX_remotehostReverseLookup();
        MAX_remotehostSetGeoInfo();
    }
}


function MAX_remotehostProxyLookup()
{
    $conf = $GLOBALS['_MAX']['CONF'];

    if ($conf['logging']['proxyLookup']) {
        OX_Delivery_logMessage('checking remote host proxy', 7);

        $proxy = false;
        if (!empty($_SERVER['HTTP_VIA']) || !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $proxy = true;
        } elseif (!empty($_SERVER['REMOTE_HOST'])) {
            $aProxyHosts = array(
                'proxy',
                'cache',
                'inktomi'
            );
            foreach ($aProxyHosts as $proxyName) {
                if (strpos($_SERVER['REMOTE_HOST'], $proxyName) !== false) {
                    $proxy = true;
                    break;
                }
            }
        }

        if ($proxy) {
            OX_Delivery_logMessage('proxy detected', 7);

            $aHeaders = array(
                'HTTP_FORWARDED',
                'HTTP_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP'
            );
            foreach ($aHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    break;
                }
            }
            if (!empty($ip)) {

                foreach (explode(',', $ip) as $ip) {
                    $ip = trim($ip);

                    if (($ip != 'unknown') && (!MAX_remotehostPrivateAddress($ip))) {

                        $_SERVER['REMOTE_ADDR'] = $ip;
                        $_SERVER['REMOTE_HOST'] = '';
                        $_SERVER['HTTP_VIA']    = '';
                        OX_Delivery_logMessage('real address set to '.$ip, 7);
                        break;
                    }
                }
            }
        }
    }
}


function MAX_remotehostReverseLookup()
{

    if (empty($_SERVER['REMOTE_HOST'])) {
        if ($GLOBALS['_MAX']['CONF']['logging']['reverseLookup']) {
            $_SERVER['REMOTE_HOST'] = @gethostbyaddr($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_HOST'] = $_SERVER['REMOTE_ADDR'];
        }
    }
}


function MAX_remotehostSetGeoInfo()
{
    if (!function_exists('parseDeliveryIniFile')) {
        require_once MAX_PATH . '/init-delivery-parse.php';
    }
    $aConf = $GLOBALS['_MAX']['CONF'];
    $type = (!empty($aConf['geotargeting']['type'])) ? $aConf['geotargeting']['type'] : null;
    if (!is_null($type) && $type != 'none') {
        $aComponent = explode(':', $aConf['geotargeting']['type']);
        if (!empty($aComponent[1]) && (!empty($aConf['pluginGroupComponents'][$aComponent[1]]))) {
            $GLOBALS['_MAX']['CLIENT_GEO'] = OX_Delivery_Common_hook('getGeoInfo', array(), $type);
        }
    }
}


function MAX_remotehostAnonymise()
{
    if (!empty($GLOBALS['_MAX']['CONF']['privacy']['anonymiseIp'])) {
        $_SERVER['REMOTE_ADDR'] = preg_replace('/\d+$/', '0', $_SERVER['REMOTE_ADDR']);
    }
}


 * @param string
 * @return boolean

function MAX_remotehostPrivateAddress($ip)
{
	$ip = ip2long($ip);

	if (!$ip) return false;

	return (MAX_remotehostMatchSubnet($ip, '10.0.0.0', 8) ||
		MAX_remotehostMatchSubnet($ip, '172.16.0.0', 12) ||
		MAX_remotehostMatchSubnet($ip, '192.168.0.0', 16) ||
		MAX_remotehostMatchSubnet($ip, '127.0.0.0', 8)
    );
}

function MAX_remotehostMatchSubnet($ip, $net, $mask)
{
	$net = ip2long($net);

	if (!is_integer($ip)) {
        $ip = ip2long($ip);
    }

	if (!$ip || !$net) {
		return false;
    }

	if (is_integer($mask)) {


		if ($mask > 32 || $mask <= 0)
			return false;
		elseif ($mask == 32)
			$mask = ~0;
		else
			$mask = ~((1 << (32 - $mask)) - 1);
	} elseif (!($mask = ip2long($mask))) {
		return false;
    }

	return ($ip & $mask) == ($net & $mask) ? true : false;
}
