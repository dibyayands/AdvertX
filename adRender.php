<?php

 * @package
 * @subpackage

 * @param array   $aBanner
 * @param int     $zoneId
 * @param string  $source
 * @param string  $target
 * @param string  $ct0
 * @param boolean $withtext
 * @param string  $charset
 * @param bookean $logClick
 * @param boolean $logView
 * @param boolean $richMedia
 * @param string  $loc
 * @param string  $referer
 * @param array   $context
 * @return string

function MAX_adRender(&$aBanner, $zoneId=0, $source='', $target='', $ct0='', $withText=false, $charset = '', $logClick=true, $logView=true, $richMedia=true, $loc='', $referer='', &$context = array())
{
    $conf = $GLOBALS['_MAX']['CONF'];

    if (empty($target)) {
        $target = !empty($aBanner['target']) ? $aBanner['target'] : '_blank';
    }
    $target = htmlspecialchars($target, ENT_QUOTES);
    $source = htmlspecialchars($source, ENT_QUOTES);
	$aBanner['bannerContent'] = "";

	OX_Delivery_Common_hook('preAdRender', array(&$aBanner, &$zoneId, &$source, &$ct0, &$withText, &$logClick, &$logView, null, &$richMedia, &$loc, &$referer));

	$functionName = _getAdRenderFunction($aBanner, $richMedia);
	$code = OX_Delivery_Common_hook('adRender', array(&$aBanner, &$zoneId, &$source, &$ct0, &$withText, &$logClick, &$logView, null, &$richMedia, &$loc, &$referer), $functionName);

    list($usec, $sec) = explode(' ', microtime());
    $time = (float)$usec + (float)$sec;
    $random = MAX_getRandomNumber();
    global $cookie_random;
    $cookie_random = $random;
    $clickUrl = _adRenderBuildClickUrl($aBanner, $zoneId, $source, $ct0, $logClick, true);
    $urlPrefix = rtrim(MAX_commonGetDeliveryUrl(), '/');
    $imgUrlPrefix = rtrim(_adRenderBuildImageUrlPrefix(), '/');

    $code = str_replace('{clickurl}', $clickUrl, $code);

    if (strpos($code, '{logurl}') !== false) {
        $logUrl = _adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&');
        $code = str_replace('{logurl}', $logUrl, $code);
    }
    if (strpos($code, '{logurl_enc}') !== false) {
        $logUrl_enc = urlencode(_adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&'));
        $code = str_replace('{logurl_enc}', $logUrl_enc, $code);
    }
    if (strpos($code, '{imgurl}') !== false) {
        $imgUrl = _adRenderBuildImageUrlPrefix();
        $code = str_replace('{imgurl}', $imgUrl, $code);
    }
    if (strpos($code, '{imgurl_enc}') !== false) {
        $imgUrl_enc = urlencode(_adRenderBuildImageUrlPrefix());
        $code = str_replace('{imgurl_enc}', $logUrl, $code);
    }
    if (strpos($code, '{clickurlparams}')) {
        $maxparams = _adRenderBuildParams($aBanner, $zoneId, $source, urlencode($ct0), $logClick, true);
        $code = str_replace('{clickurlparams}', $maxparams, $code);
    }
    $search = array('{timestamp}','{random}','{target}','{url_prefix}','{img_url_prefix}','{bannerid}','{zoneid}','{source}', '{pageurl}', '{width}', '{height}', '{websiteid}', '{campaignid}', '{advertiserid}', '{referer}');
    $locReplace = isset($GLOBALS['loc']) ? $GLOBALS['loc'] : '';
    $websiteid = (!empty($aBanner['affiliate_id'])) ? $aBanner['affiliate_id'] : '0';
    $replace = array($time, $random, $target, $urlPrefix, $imgUrlPrefix, $aBanner['ad_id'], $zoneId, $source, urlencode($locReplace), $aBanner['width'], $aBanner['height'], $websiteid, $aBanner['campaign_id'], $aBanner['client_id'], $referer);

    preg_match_all('#{([a-zA-Z0-9_]*?)(_enc)?}#', $code, $macros);
    for ($i=0;$i<count($macros[1]);$i++) {
        if (!in_array($macros[0][$i], $search) && isset($_REQUEST[$macros[1][$i]])) {
            $search[] = $macros[0][$i];
            $replace[] = (!empty($macros[2][$i])) ? urlencode(stripslashes($_REQUEST[$macros[1][$i]])) : htmlspecialchars(stripslashes($_REQUEST[$macros[1][$i]]), ENT_QUOTES);
        }
    }
    $componentParams =  OX_Delivery_Common_hook('addUrlParams', array($aBanner));
    if (!empty($componentParams) && is_array($componentParams)) {
        foreach ($componentParams as $params) {
            if (!empty($params) && is_array($params)) {
                foreach ($params as $key => $value) {
                    $search[]  = '{' . $key . '}';
                    $replace[] = urlencode($value);
                }
            }
        }
    }
    $code = str_replace($search, $replace, $code);

    $clickUrl = str_replace($search, $replace, $clickUrl);
    $aBanner['clickUrl'] = $clickUrl;

    if (strpos($code, '{clickurl_enc}') !== false) {
        $code = str_replace('{clickurl_enc}', urlencode($clickUrl), $code);
    }

    $logUrl = _adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&');
    $logUrl = str_replace($search, $replace, $logUrl);
    $aBanner['logUrl'] = $logUrl;

    $aBanner['aSearch']  = $search;
    $aBanner['aReplace'] = $replace;

	OX_Delivery_Common_hook('postAdRender', array(&$code, $aBanner, &$context));

    return MAX_commonConvertEncoding($code, $charset);
}


 * @param string
 * @param string
 * @param array
 * @return string

function MAX_adRenderImageBeacon($logUrl, $beaconId = 'beacon', $userAgent = null)
{
    if (!isset($userAgent) && isset($_SERVER['HTTP_USER_AGENT'])) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
    }
    $beaconId .= '_{random}';
    if (isset($userAgent) && preg_match("#Mozilla/(1|2|3|4)#", $userAgent)
        && !preg_match("#compatible#", $userAgent)) {
        $div = "<layer id='{$beaconId}' width='0' height='0' border='0' visibility='hide'>";
        $style = '';
        $divEnd = '</layer>';
    } else {
        $div = "<div id='{$beaconId}' style='position: absolute; left: 0px; top: 0px; visibility: hidden;'>";
        $style = " style='width: 0px; height: 0px;'";
        $divEnd = '</div>';
    }
        $beacon = "$div<img src='".htmlspecialchars($logUrl, ENT_QUOTES)."' width='0' height='0' alt=''{$style} />{$divEnd}";
        return $beacon;
}


 * @param int
 * @param string
 * @param string
 * @param string
 * @return string

function MAX_adRenderBlankBeacon($zoneId, $source, $loc, $referer)
{
    $logUrl = _adRenderBuildLogURL(array(
        'ad_id' => 0,
        'placement_id' => 0,
    ), $zoneId, $source, $loc, $referer, '&');

    return str_replace('{random}', MAX_getRandomNumber(), MAX_adRenderImageBeacon($logUrl));
}


 * @param array
 * @param int
 * @param string
 * @param string
 * @param int
 * @param boolean
 * @param boolean
 * @param boolean
 * @param boolean
 * @param string
 * @param string
 * @param boolean
 * @return string

function _adRenderImage(&$aBanner, $zoneId=0, $source='', $ct0='', $withText=false, $logClick=true, $logView=true, $useAlt=false, $richMedia=true, $loc='', $referer='', $context=array(), $useAppend=true)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $aBanner['bannerContent'] = $imageUrl = _adRenderBuildFileUrl($aBanner, $useAlt);

    if (!$richMedia) {
        return _adRenderBuildFileUrl($aBanner, $useAlt);
    }
    $prepend = (!empty($aBanner['prepend']) && $useAppend) ? $aBanner['prepend'] : '';
    $append = (!empty($aBanner['append']) && $useAppend) ? $aBanner['append'] : '';

    $clickUrl = _adRenderBuildClickUrl($aBanner, $zoneId, $source, $ct0, $logClick);
    if (!empty($clickUrl)) {  // There is a link
        $status = _adRenderBuildStatusCode($aBanner);

        $clickTag = "<a href='".htmlspecialchars($clickUrl, ENT_QUOTES)."' target='{target}'$status>";
        $clickTagEnd = '</a>';
    } else {
        $clickTag = '';
        $clickTagEnd = '';
    }
    // Create the image tag..
    if (!empty($imageUrl)) {
        $imgStatus = empty($clickTag) && !empty($status) ? $status : '';
        $width = !empty($aBanner['width']) ? $aBanner['width'] : 0;
        $height = !empty($aBanner['height']) ? $aBanner['height'] : 0;
        $alt = !empty($aBanner['alt']) ? htmlspecialchars($aBanner['alt'], ENT_QUOTES) : '';
        $imageTag = "$clickTag<img src='".htmlspecialchars($imageUrl, ENT_QUOTES)."' width='$width' height='$height' alt='$alt' title='$alt' border='0'$imgStatus />$clickTagEnd";
    } else {
        $imageTag = '';
    }

    $bannerText = $withText && !empty($aBanner['bannertext']) ? "<br />$clickTag" . htmlspecialchars($aBanner['bannertext'], ENT_QUOTES) . "$clickTagEnd" : '';
    // Get the image beacon...
    $beaconTag = ($logView && $conf['logging']['adImpressions']) ? _adRenderImageBeacon($aBanner, $zoneId, $source, $loc, $referer) : '';
    return $prepend . $imageTag . $bannerText . $beaconTag . $append;
}

 * @param array
 * @param int
 * @param string
 * @param string
 * @param int
 * @param boolean
 * @param boolean
 * @param boolean
 * @param string
 * @param string
 * @return string

function _adRenderFlash(&$aBanner, $zoneId=0, $source='', $ct0='', $withText=false, $logClick=true, $logView=true, $useAlt=false, $richMedia=true, $loc='', $referer='', $context=array())
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $prepend = !empty($aBanner['prepend']) ? $aBanner['prepend'] : '';
    $append = !empty($aBanner['append']) ? $aBanner['append'] : '';
    $width = !empty($aBanner['width']) ? $aBanner['width'] : 0;
    $height = !empty($aBanner['height']) ? $aBanner['height'] : 0;
    $pluginVersion = !empty($aBanner['pluginversion']) ? _adRenderGetRealPluginVersion($aBanner['pluginversion']) : '4';
    $logURL = _adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&');

    if (!empty($aBanner['alt_filename']) || !empty($aBanner['alt_imageurl'])) {
        $altImageAdCode = _adRenderImage($aBanner, $zoneId, $source, $ct0, false, $logClick, false, true, true, $loc, $referer, $context, false);
        $fallBackLogURL = _adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&', true);
    } else {
        $alt = !empty($aBanner['alt']) ? htmlspecialchars($aBanner['alt'], ENT_QUOTES) : '';
        $altImageAdCode = "<img src='" . _adRenderBuildImageUrlPrefix() . '/1x1.gif' . "' alt='".$alt."' title='".$alt."' border='0' />";

        if ($zoneId) {
            // Log a blank impression instead
            $fallBackLogURL = _adRenderBuildLogURL(array(
                    'ad_id' => 0,
                    'placement_id' => 0,
                ), $zoneId, $source, $loc, $referer, '&', true);
        } else {
            // No zone, skip logging
            $fallBackLogURL = false;
        }
    }

    // Create the anchor tag..
    $clickUrl = _adRenderBuildClickUrl($aBanner, $zoneId, $source, $ct0, $logClick);
    if (!empty($clickUrl)) {  // There is a link
        $status = _adRenderBuildStatusCode($aBanner);
        $target = !empty($aBanner['target']) ? $aBanner['target'] : '_blank';
        $swfParams = array('clickTARGET' => $target, 'clickTAG' => $clickUrl);
        $clickTag = "<a href='".htmlspecialchars($clickUrl, ENT_QUOTES)."' target='$target'$status>";
        $clickTagEnd = '</a>';
    } else {
        $swfParams = array();
        $clickTag = '';
        $clickTagEnd = '';
    }

    if (!empty($aBanner['parameters'])) {
        $aAdParams = unserialize($aBanner['parameters']);
        if (isset($aAdParams['swf']) && is_array($aAdParams['swf'])) {

            $swfParams = array();
            $aBannerSwf = $aBanner;

            $aBannerSwf['noClickTag'] = true;
            foreach ($aAdParams['swf'] as $iKey => $aSwf) {
                $aBannerSwf['url'] = $aSwf['link'];
                $swfParams["alink{$iKey}"] = _adRenderBuildClickUrl($aBannerSwf, $zoneId, $source, $ct0, $logClick);
                $swfParams["atar{$iKey}"]  = $aSwf['tar'];
            }
        }
    }
    $fileUrl = _adRenderBuildFileUrl($aBanner, false);
    $id = 'rv_swf_{random}';

    $swfId = (!empty($aBanner['alt']) ? $aBanner['alt'] : 'Advertisement');
    $swfId = 'id-' . preg_replace('/[a-z0-1]+/', '', strtolower($swfId));

    $code = "
<div id='{$id}' style='display: inline;'>$altImageAdCode</div>
<script type='text/javascript'><!--/"."/ <![CDATA[
    var ox_swf = new FlashObject('{$fileUrl}', '{$swfId}', '{$width}', '{$height}', '{$pluginVersion}');\n";
    foreach ($swfParams as $key => $value) {

        $code .= "    ox_swf.addVariable('{$key}', '" . preg_replace('#%7B(.*?)%7D#', '{$1}', urlencode($value)) . "');\n";
    }
    if (!empty($aBanner['transparent'])) {
        $code .= "    ox_swf.addParam('wmode','transparent');\n";
    } else {
        $code .= "    ox_swf.addParam('wmode','opaque');\n";
    }
    $code .= "    ox_swf.addParam('allowScriptAccess','always');\n";

    if ($logView && $conf['logging']['adImpressions']) {

        $code .= "    ox_swf.write('{$id}', ".json_encode($logURL).", ".json_encode($fallBackLogURL).");\n";
    } else {
        $code .= "    ox_swf.write('{$id}');\n";
    }

    $code .= "/"."/ ]]> --></script>";
    if ($fallBackLogURL) {
        $code .= '<noscript>' . _adRenderImageBeacon($aBanner, $zoneId, $source, $loc, $referer, $fallBackLogURL) . '</noscript>';
    }
    $bannerText = $withText && !empty($aBanner['bannertext']) ? "<br />{$clickTag}{$aBanner['bannertext']}{$clickTagEnd}" : '';

    return $prepend . $code . $bannerText . $append;
}

 * @param array
 * @param int
 * @param string
 * @param string
 * @param int
 * @param boolean
 * @param boolean
 * @param boolean
 * @param boolean
 * @param string
 * @param string
 * @return string

function _adRenderHtml(&$aBanner, $zoneId=0, $source='', $ct0='', $withText=false, $logClick=true, $logView=true, $useAlt=false, $richMedia=true, $loc='', $referer='', $context=array())
{

    if (!function_exists('Plugin_BannerTypeHtml_delivery_adRender')) {
        _includeDeliveryPluginFile('/lib/OX/Extension/bannerTypeHtml/bannerTypeHtmlDelivery.php');
    }

    return Plugin_BannerTypeHtml_delivery_adRender($aBanner, $zoneId, $source, $ct0, $withText, $logClick, $logView, $useAlt, $richMedia, $loc, $referer);
}


 * @param array
 * @param int
 * @param string
 * @param string
 * @param int
 * @param boolean
 * @param boolean
 * @param boolean
 * @param boolean
 * @param string
 * @param string
 * @return string

function _adRenderText(&$aBanner, $zoneId=0, $source='', $ct0='', $withText=false, $logClick=true, $logView=true, $useAlt=false, $richMedia=false, $loc='', $referer='', $context=array())
{

    $aConf = $GLOBALS['_MAX']['CONF'];
    if (!function_exists('Plugin_BannerTypeText_delivery_adRender')) {
        @include LIB_PATH . '/Extension/bannerTypeText/bannerTypeTextDelivery.php';
    }
    return Plugin_BannerTypeText_delivery_adRender($aBanner, $zoneId, $source, $ct0, $withText, $logClick, $logView, $useAlt, $richMedia, $loc, $referer);
}


 * @param array
 * @param boolean
 * @param string
 * @return string

function _adRenderBuildFileUrl($aBanner, $useAlt = false, $params = '')
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $fileUrl = '';
    if ($aBanner['type'] == 'url') {
        $fileUrl = $useAlt ? $aBanner['alt_imageurl'] : $aBanner['imageurl'];
        if (!empty($params)) {
            $fileUrl .= "?{$params}";
        }
    } else {
        $fileName = $useAlt ? $aBanner['alt_filename'] : $aBanner['filename'];
        $params = !empty($params) ? $params : '';
        if (!empty($fileName)) {
            if ($aBanner['type'] == 'web') {
                $fileUrl = _adRenderBuildImageUrlPrefix() . "/{$fileName}";
                if (!empty($params)) {
                    $fileUrl .= "?{$params}";
                }
            } elseif ($aBanner['type'] == 'sql') {
                $fileUrl = MAX_commonGetDeliveryUrl($conf['file']['image']) . "?filename={$fileName}&contenttype={$aBanner['contenttype']}";
                if (!empty($params)) {
                    $fileUrl .= "&{$params}";
                }
            }
        }
    }
    return $fileUrl;
}


 * @return string

function _adRenderBuildImageUrlPrefix()
{
    $conf = $GLOBALS['_MAX']['CONF'];
    return $GLOBALS['_MAX']['SSL_REQUEST'] ? 'https://' . $conf['webpath']['imagesSSL'] : 'http://' .  $conf['webpath']['images'];
}


 * @param array
 * @param int
 * @param string
 * @param string
 * @param string
 * @param string
 * @return string

function _adRenderBuildLogURL($aBanner, $zoneId = 0, $source = '', $loc = '', $referer = '', $amp = '&amp;', $fallBack = false)
{
    $conf = $GLOBALS['_MAX']['CONF'];

    $delimiter = $GLOBALS['_MAX']['MAX_DELIVERY_MULTIPLE_DELIMITER'];

    $logLastAction = (!empty($aBanner['viewwindow']) && !empty($aBanner['tracker_status'])) ? '1' : '';

    if (!empty($GLOBALS['_MAX']['adChain'])) {
        foreach ($GLOBALS['_MAX']['adChain'] as $index => $ad) {
            $aBanner['ad_id'] .= $delimiter . $ad['ad_id'];
            $aBanner['placement_id'] .= $delimiter . $ad['placement_id'];
            $zoneId .= $delimiter . $ad['zoneid'];
            $aBanner['block_ad'] .= $delimiter . $ad['block_ad'];
            $aBanner['cap_ad'] .= $delimiter . $ad['cap_ad'];
            $aBanner['session_cap_ad'] .= $delimiter . $ad['session_cap_ad'];
            $aBanner['block_campaign'] .= $delimiter . $ad['block_campaign'];
            $aBanner['cap_campaign'] .= $delimiter . $ad['cap_campaign'];
            $aBanner['session_cap_campaign'] .= $delimiter . $ad['session_cap_campaign'];
            $aBanner['block_zone'] .= $delimiter . $ad['block_zone'];
            $aBanner['cap_zone'] .= $delimiter . $ad['cap_zone'];
            $aBanner['session_cap_zone'] .= $delimiter . $ad['session_cap_zone'];
            $logLastAction .= $delimiter . (!empty($ad['viewwindow']) && !empty($ad['tracker_status'])) ? '1' : '0';
        }
    }
    $url = MAX_commonGetDeliveryUrl($conf['file']['log']);
    $url .= "?" . $conf['var']['adId'] . "=" . $aBanner['ad_id'];
    $url .= $amp . $conf['var']['campaignId'] . "=" . $aBanner['placement_id'];
    $url .= $amp . $conf['var']['zoneId'] . "=" . $zoneId;
    if (!empty($source)) $url .= $amp . $conf['var']['channel'] . "=" . $source;
    if (!empty($aBanner['block_ad'])) $url .= $amp . $conf['var']['blockAd'] . "=" . $aBanner['block_ad'];
    if (!empty($aBanner['cap_ad'])) $url .= $amp . $conf['var']['capAd'] . "=" . $aBanner['cap_ad'];
    if (!empty($aBanner['session_cap_ad'])) $url .= $amp . $conf['var']['sessionCapAd'] . "=" . $aBanner['session_cap_ad'];
    if (!empty($aBanner['block_campaign'])) $url .= $amp . $conf['var']['blockCampaign'] . "=" . $aBanner['block_campaign'];
    if (!empty($aBanner['cap_campaign'])) $url .= $amp . $conf['var']['capCampaign'] . "=" . $aBanner['cap_campaign'];
    if (!empty($aBanner['session_cap_campaign'])) $url .= $amp . $conf['var']['sessionCapCampaign'] . "=" . $aBanner['session_cap_campaign'];
    if (!empty($aBanner['block_zone'])) $url .= $amp . $conf['var']['blockZone'] . "=" . $aBanner['block_zone'];
    if (!empty($aBanner['cap_zone'])) $url .= $amp . $conf['var']['capZone'] . "=" . $aBanner['cap_zone'];
    if (!empty($aBanner['session_cap_zone'])) $url .= $amp . $conf['var']['sessionCapZone'] . "=" . $aBanner['session_cap_zone'];
    if (!empty($logLastAction)) $url .= $amp . $conf['var']['lastView'] . "=" . $logLastAction;
    if (!empty($loc)) $url .= $amp . "loc=" . urlencode($loc);
    if (!empty($referer)) $url .= $amp . "referer=" . urlencode($referer);
    if (!empty($fallBack)) $url .= $amp . $conf['var']['fallBack'] . '=1';
    $url .= $amp . "cb={random}";

    // addUrlParams hook for plugins to add key=value pairs to the log/click URLs
    $componentParams =  OX_Delivery_Common_hook('addUrlParams', array($aBanner));
    if (!empty($componentParams) && is_array($componentParams)) {
        foreach ($componentParams as $params) {
            if (!empty($params) && is_array($params)) {
                foreach ($params as $key => $value) {
                    $url .= $amp . urlencode($key) . '=' . urlencode($value);
                }
            }
        }
    }
    return $url;
}


 * @param array
 * @param int
 * @param string
 * @param string
 * @param string
 * @param string
 * @return string

function _adRenderImageBeacon($aBanner, $zoneId = 0, $source = '', $loc = '', $referer = '', $logUrl = '')
{
    if (empty($logUrl)) {
        $logUrl = _adRenderBuildLogURL($aBanner, $zoneId, $source, $loc, $referer, '&');
    }
    return MAX_adRenderImageBeacon($logUrl);
}


 * @param array
 * @param int
 * @param string
 * @param string
 * @param boolean
 * @param boolean
 * @return string


function _adRenderBuildParams($aBanner, $zoneId=0, $source='', $ct0='', $logClick=true, $overrideDest=false)
{
    if (isset($aBanner['ad_id']) && empty($aBanner['bannerid'])) {
        $aBanner['bannerid'] = $aBanner['ad_id'];
    }

    $conf = $GLOBALS['_MAX']['CONF'];
    $delimiter = $GLOBALS['_MAX']['MAX_DELIVERY_MULTIPLE_DELIMITER'];

    $logLastClick = (!empty($aBanner['clickwindow'])) ? '1' : '';
    if (!empty($GLOBALS['_MAX']['adChain'])) {
        foreach ($GLOBALS['_MAX']['adChain'] as $index => $ad) {
            $aBanner['bannerid'] .= $delimiter . $ad['bannerid'];
            $aBanner['placement_id'] .= $delimiter . $ad['placement_id'];
            $zoneId .= $delimiter . $ad['zoneid'];
            $logLastClick .= (!empty($aBanner['clickwindow'])) ? '1' : '0';
        }
    }

    $maxparams = '';
    if (!empty($aBanner['url']) || $overrideDest) {
        // There is a link
        $del = $conf['delivery']['ctDelimiter'];
        $delnum = strlen($del);
        $random = "{$del}{$conf['var']['cacheBuster']}={random}";
        $bannerId = !empty($aBanner['bannerid']) ? "{$del}{$conf['var']['adId']}={$aBanner['bannerid']}" : '';
        $zoneId = "{$del}{$conf['var']['zoneId']}={$zoneId}";
        $source = !empty($source) ? "{$del}source=" . urlencode($source) : '';
        $log = $logClick ? '' : "{$del}{$conf['var']['logClick']}=no";
        // Determine the destination
        $dest = !empty($aBanner['url']) ? $aBanner['url'] : '';

        if (!empty($ct0) && strtolower(substr($ct0, 0, 4)) == 'http') {

            $dest = $ct0.preg_replace('/%7B(.*?)%7D/', '{$1}', urlencode($dest));
        }

        $dest = preg_replace('/%7B(.*?)%7D/', '{$1}', urlencode($dest));

        $maxdest = "{$del}{$conf['var']['dest']}={$dest}";

        $log .= (!empty($logLastClick)) ? $del . $conf['var']['lastClick'] . '=' . $logLastClick : '';

        $maxparams = $delnum . $bannerId . $zoneId . $source . $log . $random;

        $componentParams =  OX_Delivery_Common_hook('addUrlParams', array($aBanner));
        if (!empty($componentParams) && is_array($componentParams)) {
            foreach ($componentParams as $params) {
                if (!empty($params) && is_array($params)) {
                    foreach ($params as $key => $value) {
                        $maxparams .= $del . urlencode($key) . '=' . urlencode($value);
                    }
                }
            }
        }
        $maxparams .= $maxdest;
    }
    return $maxparams;
}

 * @param array
 * @param int
 * @param string
 * @param string
 * @param boolean
 * @param boolean
 * @return string

function _adRenderBuildClickUrl($aBanner, $zoneId=0, $source='', $ct0='', $logClick=true, $overrideDest=false)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $clickUrl = '';
    if (is_string($logClick)) {
        $clickUrl = $logClick;
    } elseif (!empty($aBanner['url']) || $overrideDest) {
        $clickUrl = MAX_commonGetDeliveryUrl($conf['file']['click']) . '?' . $conf['var']['params'] . '=' . _adRenderBuildParams($aBanner, $zoneId, $source, $ct0, $logClick, true);
    }
    return $clickUrl;
}

 * @param array
 * @return string

function _adRenderBuildStatusCode($aBanner)
{
    return !empty($aBanner['status']) ? " onmouseover=\"self.status='" . addslashes($aBanner['status']) . "'; return true;\" onmouseout=\"self.status=''; return true;\"" : '';

}


 * @param int
 * @return string

function _adRenderGetRealPluginVersion($swfVersion)
{
    if ($swfVersion <= 10) {

        $pluginVersion = $swfVersion;
    } elseif ($swfVersion >= 23) {

        $pluginVersion = $swfVersion - 11;
    } elseif ($swfVersion == 11 || $swfVersion == 12) {

        $pluginVersion = 10 + ($swfVersion - 9) / 10;
    } elseif ($swfVersion >= 13 && $swfVersion <= 22) {

        $pluginVersion = 11 + ($swfVersion - 13) / 10;
    }

    return (string)$pluginVersion;
}

function _getAdRenderFunction($aBanner, $richMedia = true)
{
    $functionName = false;
    if (!empty($aBanner['ext_bannertype'])) {
        return OX_Delivery_Common_getFunctionFromComponentIdentifier($aBanner['ext_bannertype'], 'adRender');
    } else {
        switch ($aBanner['contenttype']) {
            case 'gif'  :
            case 'jpeg' :
            case 'png'  :
                $functionName = '_adRenderImage';
                break;
            case 'swf'  :
                if ($richMedia) {
                    $functionName = '_adRenderFlash';
                } else {
                    $functionName = '_adRenderImage';
                }
                break;
            case 'txt'  :
                    $functionName = '_adRenderText';
                break;
            default :
                switch ($aBanner['type']) {
                    case 'html' :
                        $functionName = '_adRenderHtml';
                        break;
                    case 'url' : 
                        $functionName = '_adRenderImage';
                        break;
                    case 'txt' :
                        $functionName = '_adRenderText';
                        break;
                    default:
                        $functionName = '_adRenderHtml';
                        break;
                }
                break;
        }
    }
    return $functionName;
}

?>
