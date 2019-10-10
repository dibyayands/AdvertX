<?php

 * @package    MaxDelivery
 * @subpackage adSelect
 *
 * Array
 *   (
 *       [ad_id] => 123
 *       [placement_id] => 4
 *       [active] => t
 *       [name] => Web Flash (With backup)
 *       [type] => web
 *       [contenttype] => swf
 *       [pluginversion] => 6
 *       [filename] => banner_468x60.swf
 *       [imageurl] =>
 *       [htmltemplate] =>
 *       [htmlcache] =>
 *       [width] => 468
 *       [height] => 60
 *       [weight] => 1
 *       [seq] => 0
 *       [target] => _blank
 *       [alt] =>
 *       [status] =>
 *       [bannertext] =>
 *       [adserver] =>
 *       [block] => 0
 *       [capping] => 0
 *       [session_capping] => 0
 *       [compiledlimitation] =>
 *       [acl_plugins] =>
 *       [append] =>
 *       [appendtype] => 0
 *       [bannertype] => 0
 *       [alt_filename] => backup_banner_468x60.gif
 *       [alt_imageurl] =>
 *       [alt_contenttype] => gif
 *       [campaign_priority] => 5
 *       [campaign_weight] => 0
 *       [campaign_companion] => 0
 *       [priority] => 0.10989010989
 *       [zoneid] => 567
 *       [bannerid] => 123
 *       [storagetype] => web
 *       [campaignid] => 4
 *       [zone_companion] =>
 *       [prepend] =>
 *   )
 *


require_once MAX_PATH . '/lib/max/Delivery/limitations.php';
require_once MAX_PATH . '/lib/max/Delivery/adRender.php';
require_once MAX_PATH . '/lib/max/Delivery/cache.php';

define ("PRI_ECPM_FROM", 6);
define ("PRI_ECPM_TO", 9);

 * @var int

$GLOBALS['OX_adSelect_SkipOtherPriorityLevels'] = -1;


 * @param string
 * @param string
 * @param string
 * @param string
 * @param int
 * @param array
 * @param boolean
 * @param string
 * @param string
 * @param string
 * @return array

function MAX_adSelect($what, $campaignid = '', $target = '', $source = '', $withtext = 0, $charset = '', $context = array(), $richmedia = true, $ct0 = '', $loc = '', $referer = '')
{
    $conf = $GLOBALS['_MAX']['CONF'];

    if (empty($GLOBALS['source'])) {
        $GLOBALS['source'] = $source;
    }
    if (empty($GLOBALS['loc'])) {
        $GLOBALS['loc'] = $loc;
    }

    $originalZoneId = null;
    if (strpos($what,'zone:') === 0) {
        $originalZoneId = intval(substr($what,5));
    } elseif (strpos($what,'campaignid:') === 0) {
        $originalCampaignId = intval(substr($what,11));
    } elseif (strpos($what, 'bannerid:') === 0) {
        $originalBannerId = intval(substr($what,9));
    }
    $userid = MAX_cookieGetUniqueViewerId();
    MAX_cookieAdd($conf['var']['viewerId'], $userid, _getTimeYearFromNow());
    $outputbuffer = '';
    // Set flag
    $found = false;
    // Reset followed zone chain
    $GLOBALS['_MAX']['followedChain'] = array();
    $GLOBALS['_MAX']['adChain'] = array();

    // Reset considered ads set
    $GLOBALS['_MAX']['considered_ads'] = array();

    $first = true;
    global $g_append, $g_prepend;
    $g_append = '';
    $g_prepend = '';
    if(!empty($what)) {
	    while ($first || ($what != '' && $found == false)) {
	        $first = false;
	        $ix = strpos($what, '|');
	        if ($ix === false) {
	            $remaining = '';
	        } else {
	            $remaining = substr($what, $ix+1);
	            $what = substr($what, 0, $ix);
	        }
	        if (strpos($what, 'zone:') === 0) {
	            $zoneId  = intval(substr($what,5));
	            $row = _adSelectZone($zoneId, $context, $source, $richmedia);
	        } else {
	            // Expand paths to regular statements
	            if (strpos($what, '/') > 0) {
	                if (strpos($what, '@') > 0) {
	                    list ($what, $append) = explode ('@', $what);
	                } else {
	                    $append = '';
	                }

	                $separate  = explode ('/', $what);
	                $expanded  = '';
	                $collected = array();

	                reset($separate);
	                while (list(,$v) = each($separate)) {
	                    $expanded .= ($expanded != '' ? ',+' : '') . $v;
	                    $collected[] = $expanded . ($append != '' ? ',+'.$append : '');
	                }

	                $what = strtok(implode('|', array_reverse ($collected)), '|');
	                $remaining = strtok('').($remaining != '' ? '|'.$remaining : '');
	            }

	            $row = _adSelectDirect($what, $campaignid, $context, $source, $richmedia, $remaining == '');
	        }
	        if (is_array($row) && empty($row['default'])) {
	            // Log the ad request
	            MAX_Delivery_log_logAdRequest($row['bannerid'], $row['zoneid'], $row);
	            if (($row['adserver'] == 'max' || $row['adserver'] == '3rdPartyServers:ox3rdPartyServers:max')
	                && preg_match("#{$conf['webpath']['delivery']}.*zoneid=([0-9]+)#", $row['htmltemplate'], $matches) && !stristr($row['htmltemplate'], $conf['file']['popup'])) {
	                $GLOBALS['_MAX']['adChain'][] = $row;
	                $found = false;
	                $what = "zone:{$matches[1]}";
	            } else {
	                $found = true;
	            }
	        } else {
                    // Log the ad request
                    MAX_Delivery_log_logAdRequest(null, $originalZoneId, null);
                    $what  = $remaining;
	        }
	    }
    }

    // Return the banner information
    if ($found) {
        $zoneId = empty($row['zoneid']) ? 0 : $row['zoneid'];
        if (!empty($GLOBALS['_MAX']['adChain'])) {
            foreach ($GLOBALS['_MAX']['adChain'] as $index => $ad) {
                if (($ad['ad_id'] != $row['ad_id']) && !empty($ad['append'])) {
                    $row['append'] .= $ad['append'];
                }
            }
        }
        $outputbuffer = MAX_adRender($row, $zoneId, $source, $target, $ct0, $withtext, $charset, true, true, $richmedia, $loc, $referer, $context);
        $output = array(
            'html'          => $outputbuffer,
            'bannerid'      => $row['bannerid'],
            'contenttype'   => $row['contenttype'],
            'alt'           => $row['alt'],
            'width'         => $row['width'],
            'height'        => $row['height'],
            'url'           => $row['url'],
            'campaignid'    => $row['campaignid'],
            'clickUrl'      => $row['clickUrl'],
            'logUrl'        => $row['logUrl'],
            'aSearch'       => $row['aSearch'],
            'aReplace'      => $row['aReplace'],
            'bannerContent' => $row['bannerContent'],
            'clickwindow'   => $row['clickwindow'],
            'aRow'          => $row,
            'context'       => _adSelectBuildContext($row, $context),
            'iframeFriendly' => (bool)$row['iframe_friendly'],
        );

        $row += array(
            'block_ad'             => 0,
            'cap_ad'               => 0,
            'session_cap_ad'       => 0,
            'block_campaign'       => 0,
            'cap_campaign'         => 0,
            'session_cap_campaign' => 0,
            'block_zone'           => 0,
            'cap_zone'             => 0,
            'session_cap_zone'     => 0,
        );

        if (MAX_Delivery_cookie_cappingOnRequest()) {
            if ($row['block_ad'] > 0 || $row['cap_ad'] > 0 || $row['session_cap_ad'] > 0) {
                MAX_Delivery_cookie_setCapping('Ad', $row['bannerid'], $row['block_ad'], $row['cap_ad'], $row['session_cap_ad']);
            }
            if ($row['block_campaign'] > 0 || $row['cap_campaign'] > 0 || $row['session_cap_campaign'] > 0) {
                MAX_Delivery_cookie_setCapping('Campaign', $row['campaign_id'], $row['block_campaign'], $row['cap_campaign'], $row['session_cap_campaign']);
            }
            if ($row['block_zone'] > 0 || $row['cap_zone'] > 0 || $row['session_cap_zone'] > 0) {
                MAX_Delivery_cookie_setCapping('Zone', $row['zoneid'], $row['block_zone'], $row['cap_zone'], $row['session_cap_zone']);
            }

            MAX_Delivery_log_setLastAction(0, array($row['bannerid']), array($zoneId), array($row['viewwindow']));
        }
    } else {

        if (!empty($zoneId)) {

            $g_append = MAX_adRenderBlankBeacon($zoneId, $source, $loc, $referer).$g_append;


            $outputbuffer = join("\n", OX_Delivery_Common_hook('blankAdSelect', array($zoneId, $context, $source, $richmedia)) ?: []);
        }

        if (!empty($outputbuffer)) {

            $outputbuffer = $g_prepend . $outputbuffer . $g_append;
            $output = array('html' => $outputbuffer, 'bannerid' => '' );
        } elseif (!empty($row['default'])) {

            if (empty($target)) {
                $target = '_blank';
            }
            $outputbuffer = $g_prepend . '<a href=\'' . $row['default_banner_destination_url'] . '\' target=\'' .
                            $target . '\'><img src=\'' . $row['default_banner_image_url'] .
                            '\' border=\'0\' alt=\'\'></a>' . $g_append;
            $output = array('html' => $outputbuffer, 'bannerid' => '', 'default_banner_image_url' => $row['default_banner_image_url'] );
        } elseif (!empty($conf['defaultBanner']['imageUrl'])) {

            if (empty($target)) {
                $target = '_blank';
            }
            $outputbuffer = "{$g_prepend}<img src='{$conf['defaultBanner']['imageUrl']}' border='0' alt=''>{$g_append}";
            $output = array('html' => $outputbuffer, 'bannerid' => '', 'default_banner_image_url' => $conf['defaultBanner']['imageUrl']);
        } else {

            $outputbuffer = $g_prepend . $g_append;
            $output = array('html' => $outputbuffer, 'bannerid' => '' );
        }
    }


    OX_Delivery_Common_hook('postAdSelect', array(&$output));

    return $output;
}


 * @param string
 * @param string
 * @param array
 * @param string
 * @param boolean
 * @param boolean
 * @return array|false

function _adSelectDirect($what, $campaignid = '', $context = array(), $source = '', $richMedia = true, $lastpart = true)
{
    $aDirectLinkedAdInfos = MAX_cacheGetLinkedAdInfos($what, $campaignid, $lastpart);

    $GLOBALS['_MAX']['DIRECT_SELECTION'] = true;

    $aLinkedAd = _adSelectCommon($aDirectLinkedAdInfos, $context, $source, $richMedia);

    if (is_array($aLinkedAd)) {
        $aLinkedAd['zoneid'] = 0;
        $aLinkedAd['bannerid'] = $aLinkedAd['ad_id'];
        $aLinkedAd['storagetype'] = $aLinkedAd['type'];
        $aLinkedAd['campaignid'] = $aLinkedAd['placement_id'];

        return $aLinkedAd;
    }

    if (!empty($aDirectLinkedAdInfos['default_banner_image_url'])) {
        return array(
           'default'                        => true,
           'default_banner_image_url'       => $aDirectLinkedAdInfos['default_banner_image_url'],
           'default_banner_destination_url' => $aDirectLinkedAdInfos['default_banner_destination_url']
        );
    }

    return false;
}



 * @param int
 * @param array
 * @return int

function _getNextZone($zoneId, $arrZone)
{
    if (!empty($arrZone['chain']) && (substr($arrZone['chain'],0,5) == 'zone:')) {
        return intval(substr($arrZone['chain'],5));
    }
    else {
        return $zoneId;
    }
}




 * @param int
 * @param array
 * @param string
 * @param boolean
 * @return array|false

function _adSelectZone($zoneId, $context = array(), $source = '', $richMedia = true)
{

    if ($zoneId === 0) { return false; }

    global $g_append, $g_prepend;
    while (!in_array($zoneId, $GLOBALS['_MAX']['followedChain'])) {
        $GLOBALS['_MAX']['followedChain'][] = $zoneId;
        $appendedThisZone = false;

        $aZoneInfo = MAX_cacheGetZoneInfo($zoneId);

        if (empty($aZoneInfo)) {

            return false;
        }

        if ($zoneId != 0 && MAX_limitationsIsZoneForbidden($zoneId, $aZoneInfo)) {
            $zoneId = _getNextZone($zoneId, $aZoneInfo);
            continue;
        }

        $aZoneLinkedAdInfos = MAX_cacheGetZoneLinkedAdInfos ($zoneId);

        if (is_array($aZoneInfo)) {
            if (isset($aZoneInfo['forceappend']) && $aZoneInfo['forceappend'] == 't') {
                $g_prepend .= $aZoneInfo['prepend'];
                $g_append = $aZoneInfo['append'] . $g_append;
                $appendedThisZone = true;
            }

            $aZoneLinkedAdInfos += $aZoneInfo;

            $aLinkedAd = _adSelectCommon($aZoneLinkedAdInfos, $context, $source, $richMedia);

            if (is_array($aLinkedAd)) {
                $aLinkedAd['zoneid'] = $zoneId;
                $aLinkedAd['bannerid'] = $aLinkedAd['ad_id'];
                $aLinkedAd['storagetype'] = $aLinkedAd['type'];
                $aLinkedAd['campaignid'] = $aLinkedAd['placement_id'];
                $aLinkedAd['zone_companion'] = $aZoneLinkedAdInfos['zone_companion'];
                $aLinkedAd['block_zone'] = @$aZoneInfo['block_zone'];
                $aLinkedAd['cap_zone'] = @$aZoneInfo['cap_zone'];
                $aLinkedAd['session_cap_zone'] = @$aZoneInfo['session_cap_zone'];
                $aLinkedAd['affiliate_id'] = @$aZoneInfo['publisher_id'];

                if (!$appendedThisZone) {
                    $aLinkedAd['append'] .= @$aZoneInfo['append'] . $g_append;
                    $aLinkedAd['prepend'] = $g_prepend . @$aZoneInfo['prepend'] . $aLinkedAd['prepend'];
                } else {
                    $aLinkedAd['append'] .= $g_append;
                    $aLinkedAd['prepend'] = $g_prepend . $aLinkedAd['prepend'];
                }
                return ($aLinkedAd);
            }

            $zoneId = _getNextZone($zoneId, $aZoneInfo);
        }
    }
    if (!empty($aZoneInfo['default_banner_image_url'])) {
        return array(
           'default'                        => true,
           'default_banner_image_url'       => $aZoneInfo['default_banner_image_url'],
           'default_banner_destination_url' => $aZoneInfo['default_banner_destination_url']
        );
    }

    return false;
}



 * @param string
 * @param array
 * @param string
 * @param boolean
 * @return array|false

function _adSelectCommon($aAds, $context, $source, $richMedia)
{

    OX_Delivery_Common_hook('preAdSelect', array(&$aAds, &$context, &$source, &$richMedia));

    if (!empty($aAds['ext_adselection'])) {
        $adSelectFunction = OX_Delivery_Common_getFunctionFromComponentIdentifier($aAds['ext_adselection'], 'adSelect');
    }
    if (empty($adSelectFunction) || !function_exists($adSelectFunction)) {
        $adSelectFunction = '_adSelect';
    }

    if (!empty($aAds['count_active'])) {

        if (isset($aAds['zone_companion']) && isset($context)) {
            foreach ($context as $contextEntry) {
                if (isset($contextEntry['==']) && preg_match('/^companionid:/', $contextEntry['=='])) {
                    if ($aLinkedAd = _adSelectInnerLoop($adSelectFunction, $aAds, $context, $source, $richMedia, true)) {
                        return $aLinkedAd;
                    }
                }
            }
        }
        $aLinkedAd = _adSelectInnerLoop($adSelectFunction, $aAds, $context, $source, $richMedia);
        if (is_array($aLinkedAd)) {
            return $aLinkedAd;
        }
    }
    return false;
}


 * @param callback
 * @param string
 * @param array
 * @param string
 * @param boolean
 * @param boolean
 * @return array|false

function _adSelectInnerLoop($adSelectFunction, $aAds, $context, $source, $richMedia, $companion = false)
{
    // Array of campaign types sorted by priority
    $aCampaignTypes = array(
        'xAds' => false,
        'ads'  => array(10, 9, 8, 7, 6, 5, 4, 3, 2, 1),
        'lAds' => false,
        'eAds' => array(-2),
    );

    $GLOBALS['_MAX']['considered_ads'][] = &$aAds;

    foreach ($aCampaignTypes as $type => $aPriorities) {
        if ($aPriorities) {
            $ad_picked = false;
            foreach ($aPriorities as $pri) {

                if (!$ad_picked) {
                    $aLinkedAd = OX_Delivery_Common_hook('adSelect',
                            array(&$aAds, &$context, &$source, &$richMedia, $companion, $type, $pri), $adSelectFunction);

                    if (is_array($aLinkedAd)) {
                        $ad_picked = true;
                    }

                    if ($aLinkedAd == $GLOBALS['OX_adSelect_SkipOtherPriorityLevels']) {
                        $ad_picked = true;
                    }
                }
                else
                {
                    if (!empty($aAds[$type][$pri])) {

                        $aContext = _adSelectBuildContextArray($aAds[$type][$pri], $type, $context);
                        _adSelectDiscardNonMatchingAds($aAds[$type][$pri], $aContext, $source, $richMedia);
                    }
                }
            }
            if ($ad_picked && is_array ($aLinkedAd)) {
                return $aLinkedAd;
            }
        } else {
            $aLinkedAd = OX_Delivery_Common_hook('adSelect', array(&$aAds, &$context, &$source, &$richMedia, $companion, $type), $adSelectFunction);

    		if (is_array($aLinkedAd)) {
      			return $aLinkedAd;
    		}
    	}
    }
    return false;
}



 * @param array
 * @param array
 * @param string
 * @param boolean
 * @param boolean
 * @param string
 * @param integer
 * @return array|void

function _adSelect(&$aLinkedAdInfos, $context, $source, $richMedia, $companion, $adArrayVar = 'ads', $cp = null)
{

    if (!is_array($aLinkedAdInfos)) { return; }

    if (!is_null($cp) && isset($aLinkedAdInfos[$adArrayVar][$cp])) {
        $aAds = &$aLinkedAdInfos[$adArrayVar][$cp];
    } elseif (is_null($cp) && isset($aLinkedAdInfos[$adArrayVar])) {
        $aAds = &$aLinkedAdInfos[$adArrayVar];
    } else {
        $aAds = array();
    }

    if (count($aAds) == 0) {
        return;
    }

    $aContext = _adSelectBuildContextArray($aAds, $adArrayVar, $context, $companion);

    _adSelectDiscardNonMatchingAds($aAds, $aContext, $source, $richMedia);

    if (count($aAds) == 0) {
        return;
    }

    global $n;
    mt_srand
        (floor
         ((isset ($n) && strlen ($n) > 5
           ? hexdec ($n[0].$n[2].$n[3].$n[4].$n[5])
           : 1000000) * (double) microtime ()));

    $conf = $GLOBALS['_MAX']['CONF'];

    if ($adArrayVar == 'eAds') {
        if (!empty ($conf['delivery']['ecpmSelectionRate'])) {

            $selection_rate = floatval ($conf['delivery']['ecpmSelectionRate']);

            if (!_controlTrafficEnabled ($aAds) ||
                    (mt_rand (0, $GLOBALS['_MAX']['MAX_RAND']) /
                     $GLOBALS['_MAX']['MAX_RAND']) <= $selection_rate)
            {

                $max_ecpm = 0;
                $top_ecpms = array();
                foreach ($aAds as $key => $ad) {
                    if ($ad['ecpm'] < $max_ecpm) {
                        continue;
                    } elseif ($ad['ecpm'] > $max_ecpm) {
                        $top_ecpms = array();
                        $max_ecpm = $ad['ecpm'];
                    }
                    $top_ecpms[$key] = 1;
                }

                if ($max_ecpm <= 0)
                {
                    $GLOBALS['_MAX']['ECPM_CONTROL'] = 1;
                    $total_priority = _setPriorityFromWeights($aAds);
                } else {

                    $GLOBALS['_MAX']['ECPM_SELECTION'] = 1;
                    $total_priority = count ($top_ecpms);
                    foreach ($aAds as $key => $ad) {
                        if (!empty ($top_ecpms[$key])) {
                            $aAds[$key]['priority'] = 1 / $total_priority;
                        } else {
                            $aAds[$key]['priority'] = 0;
                        }
                    }
                }
            }
            else
            {
                $GLOBALS['_MAX']['ECPM_CONTROL'] = 1;
                $total_priority = _setPriorityFromWeights($aAds);
            }
        }

    } else if (isset($cp)) {

        $used_priority = 0;
        for ($i = 10; $i > $cp; $i--)
        {
            if (isset ($aLinkedAdInfos['priority_used'][$adArrayVar][$i]))
            {
                $used_priority += $aLinkedAdInfos['priority_used'][$adArrayVar][$i];
            }
        }

        if ($used_priority >= 1) {
            return $GLOBALS['OX_adSelect_SkipOtherPriorityLevels'];
        }

        $remaining_priority = 1 - $used_priority;

        $total_priority_orig = 0;
        foreach ($aAds as $ad) {
            $total_priority_orig += $ad['priority'] * $ad['priority_factor'];
        }
        $aLinkedAdInfos['priority_used'][$adArrayVar][$i] = $total_priority_orig;

        if ($total_priority_orig <= 0) {
            return;
        }

        if ($total_priority_orig > $remaining_priority
            // If this ad belongs to a companion campaign that was previously displayed on the page,
            // we scale up the priority factor as we want to ensure that companion ads are
            // displayed together, potentially ignoring their banner weights (refs OX-4853)
            || $companion
            )
        {
            $scaling_denom = $total_priority_orig;

            if ($cp >= PRI_ECPM_FROM &&
                $cp <= PRI_ECPM_TO &&
                !empty ($conf['delivery']['ecpmSelectionRate']))
            {

                $selection_rate = floatval ($conf['delivery']['ecpmSelectionRate']);

                if (!_controlTrafficEnabled ($aAds) ||
                        (mt_rand (0, $GLOBALS['_MAX']['MAX_RAND']) /
                         $GLOBALS['_MAX']['MAX_RAND']) <= $selection_rate)
                {

                    $GLOBALS['_MAX']['ECPM_SELECTION'] = 1;

                    foreach ($aAds as $key => $ad) {
                        $ecpms[] = $ad['ecpm'];
                        $adids[] = $key;
                    }
                    array_multisort ($ecpms, SORT_DESC, $adids);

                    $p_avail = $remaining_priority;
                    $ad_count = count ($aAds);
                    $i = 0;
                    while ($i < $ad_count) {

                        $l = $i;
                        while ($l < $ad_count - 1 &&
                                $ecpms[$l + 1] == $ecpms[$i]) {
                            $l++;
                        }

                        $p_needed = 0;
                        for ($a_idx = $i; $a_idx <= $l; $a_idx++) {
                            $id = $adids[$a_idx];
                            $p_needed += $aAds[$id]['priority'] * $aAds[$id]['priority_factor'];
                        }

                        if ($p_needed > $p_avail) {
                            $scale = $p_avail / $p_needed;

                            for ($a_idx = $i; $a_idx <= $l; $a_idx++) {
                                $id = $adids[$a_idx];
                                $aAds[$id]['priority'] = $aAds[$id]['priority'] * $scale;
                            }
                            $p_avail = 0;

                            for ($a_idx = $l + 1; $a_idx < $ad_count; $a_idx++) {
                                $id = $adids[$a_idx];
                                $aAds[$id]['priority'] = 0;
                            }

                            break;

                        } else {
                            $p_avail -= $p_needed;
                            $i = $l + 1;
                        }
                    }

                    $scaling_denom = $remaining_priority;
                } else {

                    $GLOBALS['_MAX']['ECPM_CONTROL'] = 1;
                }
            }

            $scaling_factor = 1 / $scaling_denom;
        }
        else
        {

            $scaling_factor = 1 / $remaining_priority;
        }

        $total_priority = 0;
        foreach ($aAds as $key => $ad) {
            $newPriority =
                $ad['priority'] * $ad['priority_factor'] * $scaling_factor;

            $aAds[$key]['priority'] = $newPriority;
            $total_priority += $newPriority;
        }

    } else {
        // Rescale priorities by weights
        $total_priority = _setPriorityFromWeights($aAds);
    }

    // Seed the random number generator
    global $n;
    mt_srand
        (floor
         ((isset ($n) && strlen ($n) > 5
           ? hexdec ($n[0].$n[2].$n[3].$n[4].$n[5])
           : 1000000) * (double) microtime ()));

    $conf = $GLOBALS['_MAX']['CONF'];

    // Pick a float random number between 0 and 1, inclusive.
    $random_num =
        mt_rand (0, $GLOBALS['_MAX']['MAX_RAND'])
        / $GLOBALS['_MAX']['MAX_RAND'];


    if (function_exists ('test_mt_rand'))
    {
        $random_num = test_mt_rand (0, $GLOBALS['_MAX']['MAX_RAND'])
        / $GLOBALS['_MAX']['MAX_RAND'];
    }

    if ($random_num > $total_priority) {

        return;
    }

    // Perform selection of an ad, based on the random number
    $low = 0;
    $high = 0;
    foreach($aAds as $aLinkedAd) {
        if (!empty($aLinkedAd['priority'])) {
            $low = $high;
            $high += $aLinkedAd['priority'];
            if ($high > $random_num && $low <= $random_num) {

                if (function_exists ('test_MAX_cacheGetAd'))
                {
                    return test_MAX_cacheGetAd($aLinkedAd['ad_id']);
                }

                $ad = MAX_cacheGetAd($aLinkedAd['ad_id']);
                // Carry over for conversion tracking
                $ad['tracker_status'] = (!empty($aLinkedAd['tracker_status'])) ? $aLinkedAd['tracker_status'] : null;
                // Carry over for ad dimensions for market ads
                if($ad['width'] == $ad['height'] && $ad['width'] == -1) {
                   $ad['width'] = $aLinkedAd['width'];
                   $ad['height'] = $aLinkedAd['height'];
                }
                return $ad;
            }
        }
    }

    return;
}



 * @param unknown_type $aAds

function _controlTrafficEnabled (&$aAds)
{
    $control_enabled = true;

    if (empty ($GLOBALS['_MAX']['CONF']['delivery']['enableControlOnPureCPM']))
    {

        $control_enabled = false;
        foreach ($aAds as $ad) {
            if ($ad['revenue_type'] != MAX_FINANCE_CPM)
            {
                $control_enabled = true;
                break;
            }
        }

    }

    return $control_enabled;
}

 * @param array
 * @param array
 * @param string
 * @param bool
 * @return bool

function _adSelectCheckCriteria($aAd, $aContext, $source, $richMedia)
{
    $conf = $GLOBALS['_MAX']['CONF'];


    if (!empty ($aAd['expire_time'])) {
        $expire = strtotime ($aAd['expire_time']);
        $now = MAX_commonGetTimeNow ();
        if ($expire > 0 && $now > $expire) {
            OX_Delivery_logMessage('Campaign has expired for bannerid '.$aAd['ad_id'], 7);
            return false;
        }
    }


    if (isset($aContext['banner']['exclude'][$aAd['ad_id']])) {
        OX_Delivery_logMessage('List of excluded banners list contains bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (isset($aContext['campaign']['exclude'][$aAd['placement_id']])) {

        OX_Delivery_logMessage('List of excluded campaigns contains bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (isset($aContext['client']['exclude'][$aAd['client_id']])) {

        OX_Delivery_logMessage('List of excluded clients contains bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (!empty($aContext['banner']['include']) && !isset($aContext['banner']['include'][$aAd['ad_id']])) {

        OX_Delivery_logMessage('List of included banners does not contain bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (!empty($aContext['campaign']['include']) && !isset($aContext['campaign']['include'][$aAd['placement_id']])) {

        OX_Delivery_logMessage('List of included campaigns does not contain bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (
        $richMedia == false &&
        $aAd['alt_filename'] == '' &&
        !($aAd['contenttype'] == 'jpeg' || $aAd['contenttype'] == 'gif' || $aAd['contenttype'] == 'png') &&
        !($aAd['type'] == 'url' && $aAd['contenttype'] == '')
       ) {
        OX_Delivery_logMessage('No alt image specified for richmedia bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if (MAX_limitationsIsAdForbidden($aAd)) {

        OX_Delivery_logMessage('MAX_limitationsIsAdForbidden = true for bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if ($GLOBALS['_MAX']['SSL_REQUEST'] && $aAd['type'] == 'html' && $aAd['html_ssl_unsafe']) {

        OX_Delivery_logMessage('"http:" on SSL found for html bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if ($GLOBALS['_MAX']['SSL_REQUEST'] && $aAd['type'] == 'url' && $aAd['url_ssl_unsafe']) {

        OX_Delivery_logMessage('"http:" on SSL found in imagurl for url bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    if ($conf['delivery']['acls'] && !MAX_limitationsCheckAcl($aAd, $source)) {

        OX_Delivery_logMessage('MAX_limitationsCheckAcl = false for bannerid '.$aAd['ad_id'], 7);
        return false;
    }

    return true;
}

function _adSelectBuildContextArray(&$aLinkedAds, $adArrayVar, $context, $companion = false)
{
    $aContext = array(
        'campaign' => array('exclude' => array(), 'include' => array()),
        'banner'   => array('exclude' => array(), 'include' => array()),
        'client'   => array('exclude' => array(), 'include' => array()),
    );

    if (is_array($context) && !empty($context)) {
        $cContext = count($context);
        for ($i=0; $i < $cContext; $i++) {
            reset($context[$i]);
            list ($key, $value) = each($context[$i]);

            $valueArray = explode(':', $value);

            if (count($valueArray) == 1) {
                list($value) = $valueArray;
                $type = "";
            } else {
                list($type, $value) = $valueArray;
            }

            if (empty($value)) {
                continue;
            }

            switch($type) {
                case 'campaignid':
                    switch ($key) {
                        case '!=': $aContext['campaign']['exclude'][$value] = true; break;
                        case '==': $aContext['campaign']['include'][$value] = true; break;
                    }
                break;
                case 'clientid':
                    switch ($key) {
                        case '!=': $aContext['client']['exclude'][$value] = true; break;
                        case '==': $aContext['client']['include'][$value] = true; break;
                    }
                break;
                case 'companionid':
                    switch ($key) {
                        case '!=':

                            $aContext['campaign']['exclude'][$value] = true;
                            break;
                        case '==':

                            if ($companion) {
                                $aContext['campaign']['include'][$value] = true;
                            }
                       break;
                    }
                break;
                default:
                    switch ($key) {
                        case '!=': $aContext['banner']['exclude'][$value] = true; break;
                        case '==': $aContext['banner']['include'][$value] = true; break;
                    }
            }
        }
    }

    return $aContext;
}


 * @param array
 * @param array
 * @return array

function _adSelectBuildContext($aBanner, $context = array()) {
    if (!empty($aBanner['zone_companion'])) {

        foreach ($aBanner['zone_companion'] AS $companionCampaign) {
            $value = 'companionid:'.$companionCampaign;
            if ($aBanner['placement_id'] == $companionCampaign) {
                $context[] = array('==' => $value);
            } else {

                $key = array_search(array('==', $value), $context);
                if ($key === false) {

                    $context[] = array('!=' => $value);
                }
            }
        }
    }
    if (isset($aBanner['advertiser_limitation']) && $aBanner['advertiser_limitation'] == '1') {
        $context[] = array('!=' => 'clientid:' . $aBanner['client_id']);
    }
    return $context;
}


 * @param array
 * @param
 * @param unknown_type
 * @param unknown_type
 * @return none

function _adSelectDiscardNonMatchingAds(&$aAds, $aContext, $source, $richMedia)
{
  
    if (empty($GLOBALS['_MAX']['CONF']['delivery']['aclsDirectSelection']) && !empty($GLOBALS['_MAX']['DIRECT_SELECTION'])) {
        return;
    }
    foreach ($aAds as $adId => $aAd) {
        OX_Delivery_logMessage('_adSelectDiscardNonMatchingAds: checking bannerid '.$aAd['ad_id'], 7);
        if (!_adSelectCheckCriteria($aAd, $aContext, $source, $richMedia)) {
            OX_Delivery_logMessage('failed _adSelectCheckCriteria: bannerid '.$aAd['ad_id'], 7);
            unset($aAds[$adId]);
        } else {
            OX_Delivery_logMessage('passed _adSelectCheckCriteria: bannerid '.$aAd['ad_id'], 7);
        }
    }
    return;
}

?>
