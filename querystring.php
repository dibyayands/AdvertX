<?php

 * @package
 * @subpackage


function MAX_querystringConvertParams()
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $qs = $_SERVER['QUERY_STRING'];
    $dest = false;
    $destStr = $conf['var']['dest'] . '=';
    $pos = strpos($qs, $destStr);
    if ($pos === false) {
        $destStr = 'dest=';
        $pos = strpos($qs, $destStr);
    }
    if ($pos !== false) {
        $dest = urldecode(substr($qs, $pos + strlen($destStr)));
        $qs = substr($qs, 0, $pos);
    }
    $aGet = array();
    $paramStr = $conf['var']['params'] . '=';
    $paramPos = strpos($qs, $paramStr);
    if (is_numeric($paramPos)) {
        $qs = urldecode(substr($qs, $paramPos + strlen($paramStr)));
        $delim = $qs[0];
        if (is_numeric($delim)) {
            $delim = substr($qs, 1, $delim);
        }
        $qs = substr($qs, strlen($delim) + 1);
        MAX_querystringParseStr($qs, $aGet, $delim);


        $qPos = isset($aGet[$conf['var']['dest']]) ? strpos($aGet[$conf['var']['dest']], '?') : false;
        $aPos = isset($aGet[$conf['var']['dest']]) ? strpos($aGet[$conf['var']['dest']], '&') : false;
        if ($aPos && !$qPos) {
            $desturl = substr($aGet[$conf['var']['dest']], 0, $aPos);
            $destparams = substr($aGet[$conf['var']['dest']], $aPos+1);
            $aGet[$conf['var']['dest']] = $desturl . '?' . $destparams;
        }
    } else {
        parse_str($qs, $aGet);
    }
    if ($dest !== false) {
        $aGet[$conf['var']['dest']] = $dest;
    }

    $n = isset($_GET[$conf['var']['n']]) ? $_GET[$conf['var']['n']] : '';
    if (empty($n)) {

        $n = isset($aGet[$conf['var']['n']]) ? $aGet[$conf['var']['n']] : '';
    }
    if (!empty($n) && !empty($_COOKIE[$conf['var']['vars']][$n])) {
        $aVars = json_decode($_COOKIE[$conf['var']['vars']][$n], true);
        if (is_array($aVars)) {
            foreach ($aVars as $name => $value) {
                if (!isset($_GET[$name])) {
                    $aGet[$name] = $value;
                }
            }
        }
    }
    $_GET = $aGet;
    $_REQUEST = $_GET + $_POST + $_COOKIE;
}


 * @param integer
 * @return string

function MAX_querystringGetDestinationUrl($adId = null)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $dest = isset($_REQUEST[$conf['var']['dest']]) ? $_REQUEST[$conf['var']['dest']] : '';
    if (empty($dest) && !empty($adId)) {

        $aAd = MAX_cacheGetAd($adId);
        if (!empty($aAd)) {
            $dest = $aAd['url'];
        }
    }

    if (empty($dest)) {
        return;
    }
    $aVariables = array();
    $aValidVariables = array_values($conf['var']);

    $componentParams =  OX_Delivery_Common_hook('addUrlParams', array(array('bannerid' => $adId)));
    if (!empty($componentParams) && is_array($componentParams)) {
        foreach ($componentParams as $params) {
            if (!empty($params) && is_array($params)) {
                foreach ($params as $key => $value) {
                    $aValidVariables[] = $key;
                }
            }
        }
    }

    $destParams = parse_url($dest);
    if (!empty($destParams['query'])) {
        $destQuery = explode('&', $destParams['query']);
        if (!empty($destQuery)) {
            foreach ($destQuery as $destPair) {
                list($destName, $destValue) = explode('=', $destPair);
                $aValidVariables[] = $destName;
            }
        }
    }

    foreach ($_GET as $name => $value) {
        if (!in_array($name, $aValidVariables)) {
            $aVariables[] = $name . '=' . $value;
        }
    }
    foreach ($_POST as $name => $value) {
        if (!in_array($name, $aValidVariables)) {
            $aVariables[] = $name . '=' . $value;
        }
    }
    if (!empty($aVariables)) {
        $dest .= ((strpos($dest, '?') > 0) ? '&' : '?') . implode('&', $aVariables);
    }
    return $dest;
}


 * @param string
 * @param string
 * @param string

function MAX_querystringParseStr($qs, &$aArr, $delim = '&')
{
    $aArr = $_GET;
    $aElements = explode($delim, $qs);
    foreach($aElements as $element) {
        $len = strpos($element, '=');
        if ($len !== false) {
            $name = substr($element, 0, $len);
            $value = substr($element, $len+1);
            $aArr[$name] = urldecode($value);
        }
    }
}

?>
