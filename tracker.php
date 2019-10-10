<?php

 * @package
 * @subpackage

 * @param int
 * @param array
 * @param unknown_type
 * @return string

function MAX_trackerbuildJSVariablesScript($trackerid, $conversionInfo, $trackerJsCode = null)
{
    $conf = $GLOBALS['_MAX']['CONF'];
    $buffer = '';
    $url = MAX_commonGetDeliveryUrl($conf['file']['conversionvars']);
    $tracker = MAX_cacheGetTracker($trackerid);
    $variables = MAX_cacheGetTrackerVariables($trackerid);
    $variableQuerystring = '';
    if (empty($trackerJsCode)) {
        $trackerJsCode = md5(uniqid('', true));
    } else {

        $tracker['variablemethod'] = 'default';
    }
    if (!empty($variables)) {
        if ($tracker['variablemethod'] == 'dom') {
            $buffer .= "
    function MAX_extractTextDom(o)
    {
        var txt = '';

        if (o.nodeType == 3) {
            txt = o.data;
        } else {
            for (var i = 0; i < o.childNodes.length; i++) {
                txt += MAX_extractTextDom(o.childNodes[i]);
            }
        }

        return txt;
    }

    function MAX_TrackVarDom(id, v)
    {
        if (max_trv[id][v]) { return; }
        var o = document.getElementById(v);
        if (o) {
            max_trv[id][v] = escape(o.tagName == 'INPUT' ? o.value : MAX_extractTextDom(o));
        }
    }";
            $funcName = 'MAX_TrackVarDom';
        } elseif ($tracker['variablemethod'] == 'default') {
            $buffer .= "
    function MAX_TrackVarDefault(id, v)
    {
        if (max_trv[id][v]) { return; }
        if (typeof(window[v]) == undefined) { return; }
        max_trv[id][v] = window[v];
    }";
            $funcName = 'MAX_TrackVarDefault';
        } else {
            $buffer .= "
    function MAX_TrackVarJs(id, v, c)
    {
        if (max_trv[id][v]) { return; }
        if (typeof(window[v]) == undefined) { return; }
        if (typeof(c) != 'undefined') {
            eval(c);
        }
        max_trv[id][v] = window[v];
    }";
            $funcName = 'MAX_TrackVarJs';
        }

        $buffer .= "
    if (!max_trv) { var max_trv = new Array(); }
    if (!max_trv['{$trackerJsCode}']) { max_trv['{$trackerJsCode}'] = new Array(); }";

        foreach($variables as $key => $variable) {
            $variableQuerystring .= "&{$variable['name']}=\"+max_trv['{$trackerJsCode}']['{$variable['name']}']+\"";
            if ($tracker['variablemethod'] == 'custom') {
                $buffer .= "
    {$funcName}('{$trackerJsCode}', '{$variable['name']}', '".addcslashes($variable['variablecode'], "'")."');";
            } else {
                $buffer .= "
    {$funcName}('{$trackerJsCode}', '{$variable['name']}');";
            }
        }
        if (!empty($variableQuerystring)) {

            foreach ($conversionInfo as $plugin => $pluginData) {
                $conversionInfoParams = array();
                if (is_array($pluginData)) {
                    foreach ($pluginData as $key => $value) {
                        $conversionInfoParams[] = $key . '=' . urlencode($value);
                    }
                }
                $conversionInfoParams = '&' . implode('&', $conversionInfoParams);
                $buffer .= "
    document.write (\"<\" + \"script language='JavaScript' type='text/javascript' src='\");
    document.write (\"$url?trackerid=$trackerid&plugin={$plugin}{$conversionInfoParams}{$variableQuerystring}'\");";
                $buffer .= "\n\tdocument.write (\"><\\/scr\"+\"ipt>\");";
            }
        }
    }
    if(!empty($tracker['appendcode'])) {

        $tracker['appendcode'] = preg_replace('/("\?trackerid=\d+&amp;inherit)=1/', '$1='.$trackerJsCode, $tracker['appendcode']);

        $jscode = MAX_javascriptToHTML($tracker['appendcode'], "MAX_{$trackerid}_appendcode");


        $jscode = preg_replace("/\{m3_trackervariable:(.+?)\}/", "\"+max_trv['{$trackerJsCode}']['$1']+\"", $jscode);

        $buffer .= "\n".preg_replace('/^/m', "\t", $jscode)."\n";
    }
    if (empty($buffer)) {
        $buffer = "document.write(\"\");";
    }
    return $buffer;
}


 * @param integer
 * @return mixed

function MAX_trackerCheckForValidAction($trackerid)
{

    $aTrackerLinkedAds = MAX_cacheGetTrackerLinkedCreatives($trackerid);

    if (empty($aTrackerLinkedAds)) {
        return false;
    }

    $aPossibleActions = _getActionTypes();

    $now = MAX_commonGetTimeNow();
    $aConf = $GLOBALS['_MAX']['CONF'];
    $aMatchingActions = array();

    foreach ($aTrackerLinkedAds as $creativeId => $aLinkedInfo) {

        foreach ($aPossibleActions as $actionId => $action) {

            if (!empty($aLinkedInfo[$action . '_window']) && !empty($_COOKIE[$aConf['var']['last' . ucfirst($action)]][$creativeId])) {

                if (stristr($_COOKIE[$aConf['var']['last' . ucfirst($action)]][$creativeId], ' ')) {
                    list($value, $extra) = explode(' ', $_COOKIE[$aConf['var']['last' . ucfirst($action)]][$creativeId], 2);
                    $_COOKIE[$aConf['var']['last' . ucfirst($action)]][$creativeId] = $value;
                } else {
                    $extra = '';
                }
                list($lastAction, $zoneId) = explode('-', $_COOKIE[$aConf['var']['last' . ucfirst($action)]][$creativeId]);

                $lastAction = MAX_commonUnCompressInt($lastAction);


                $lastSeenSecondsAgo = $now - $lastAction;

                if ($lastSeenSecondsAgo <= $aLinkedInfo[$action . '_window'] && $lastSeenSecondsAgo > 0) {

                    $aMatchingActions[$lastSeenSecondsAgo] = array(
                        'action_type'   => $actionId,
                        'tracker_type'  => $aLinkedInfo['tracker_type'],
                        'status'        => $aLinkedInfo['status'],
                        'cid'           => $creativeId,
                        'zid'           => $zoneId,
                        'dt'            => $lastAction,
                        'window'        => $aLinkedInfo[$action . '_window'],
                        'extra'         => $extra,
                    );
                }
            }
        }
    }


    if (empty($aMatchingActions)) {
        return false;
    }

    ksort($aMatchingActions);

    return array_shift($aMatchingActions);
}

function _getActionTypes()
{
    return array(0 => 'view', 1 => 'click');
}

function _getTrackerTypes()
{
    return array(1 => 'sale', 2 => 'lead', 3 => 'signup');
}

?>
