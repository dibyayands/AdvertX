<?php

require_once RV_PATH . '/lib/RV.php';

require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/max/Admin_DA.php';
if(!isset($GLOBALS['_MAX']['FILES']['/lib/max/Delivery/common.php'])) {
    require_once MAX_PATH . '/lib/max/Delivery/common.php';
}
require_once MAX_PATH . '/lib/max/language/Loader.php';
require_once MAX_PATH . '/lib/max/other/lib-io.inc.php';
require_once LIB_PATH . '/Plugin/Component.php';
require_once MAX_PATH . '/www/admin/lib-zones.inc.php';

Language_Loader::load('invocation');


class MAX_Admin_Invocation {

    var $defaultOptionValues = array(
        'thirdPartyServer' => 0,
        'cacheBuster'      => 1,
    );

    function getAllowedVariables()
    {
        $aVariables = array(
            // IDs
            'affiliateid', 'bannerid', 'clientid', 'campaignid', 'zoneid',
            // Special vars
            'codetype', 'submitbutton',
            // Others
            'bannerUrl',
            'block',
            'blockcampaign',
            'cachebuster',
            'charset',
            'comments',
            'delay',
            'delay_type',
            'domains_table',
            'extra',
            'frame_width',
            'frame_height',
            'height',
            'hostlanguage',
            'iframetracking',
            'ilayer',
            'layerstyle',
            'left',
            'location',
            'menubar',
            'noscript',
            'parameters',
            'popunder',
            'raw',
            'refresh',
            'resizable',
            'resize',
            'scrollbars',
            'source',
            'ssl',
            'status',
            'target',
            'template',
            'thirdpartytrack',
            'timeout',
            'toolbars',
            'top',
            'transparent',
            'uniqueid',
            'website',
            'what',
            'width',
            'withtext',
            'xmlrpcproto',
            'xmlrpctimeout',
        );

        if (isset($invocationTag->defaultOptionValues)) {
            foreach($invocationTag->defaultOptionValues as $key => $default) {
                $aVariables[] = $key;
            }
        }

        return $aVariables;
    }

     * @param array

    function assignVariables($aParams = null)
    {

        $globalVariables = $this->getAllowedVariables();

        if (!isset($aParams)) {
            // Register globals
            call_user_func_array('phpAds_registerGlobal', $globalVariables);

            foreach($globalVariables as $makeMeGlobal) {
                global $$makeMeGlobal;
                if (isset($$makeMeGlobal)) {
                    if (isset($invocationTag->defaultOptionValues[$makeMeGlobal])) {
                        $$makeMeGlobal = $invocationTag->defaultOptionValues[$makeMeGlobal];
                    } else if (isset($this->defaultOptionValues[$makeMeGlobal])) {
                        $$makeMeGlobal = $this->defaultOptionValues[$makeMeGlobal];
                    }
                }
                $this->$makeMeGlobal =& $$makeMeGlobal;
            }
        } else {

            foreach($globalVariables as $makeMeGlobal) {
                if (isset($aParams[$makeMeGlobal])) {
                    $this->$makeMeGlobal = $aParams[$makeMeGlobal];
                }
            }
        }

    }


     * @param object
     * @param array
     * @return string

    function generateInvocationCode(&$invocationTag, $aParams = null)
    {
        $conf = $GLOBALS['_MAX']['CONF'];

        $this->assignVariables($aParams);

        if($invocationTag === null) {
            $invocationTag = OX_Component::factoryByComponentIdentifier($this->codetype);
        }
        if($invocationTag === false) {
            OA::debug('Error while factory invocationTag plugin '.$this->codetype);
            exit();
        }

        $invocationTag->setInvocation($this);

        return $invocationTag->generateInvocationCode();
    }

     * @return string

    function generateTrackerCode($trackerId)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        global $trackerid;

        $variablesComment = '';
        $variablesQuerystring = '';

        $variables = Admin_DA::getVariables(array('trackerid' => $trackerId), true);

        $name = PRODUCT_NAME;
        if (!empty($GLOBALS['_MAX']['CONF']['ui']['applicationName'])) {
            $name = $GLOBALS['_MAX']['CONF']['ui']['applicationName'];
        }
        $buffer = "

        if (!empty($variables)) {
            $buffer .= "
            $variablesQuerystring = '';
            foreach ($variables as $variable) {
                $variablesQuerystring .= "&amp;{$variable['name']}=%%" . strtoupper($variable['name']) . "_VALUE%%";
            }
        }
        $buffer .= "

" . $this->_generateTrackerImageBeacon($trackerId);
        $buffer .= "\n";
        return $buffer;
    }

     * @param array
     * @param boolean
     * @param array
     * @return string

    function placeInvocationForm($extra = '', $zone_invocation = false, $aParams = null)
    {
        $this->tabindex = 1;
        global $phpAds_TextDirection;

        $conf = $GLOBALS['_MAX']['CONF'];
        $pref = $GLOBALS['_MAX']['PREF'];

        $buffer = '';
        $this->zone_invocation = $zone_invocation;

        // register all the variables
        $this->assignVariables($aParams);
        if (is_array($extra)) {
            $this->assignVariables($extra);
        }

        // Check if affiliate is on the same server as the delivery code
        if (!empty($extra['website'])) {
            $server_max      = parse_url('http://' . $conf['webpath']['delivery'] . '/');
            $server_affilate = parse_url($extra['website']);
            $this->server_same     = (@gethostbyname($server_max['host']) == @gethostbyname($server_affilate['host']));
        } else {
            $this->server_same = true;
        }

        if (!is_array($extra) || !isset($extra['zoneadvanced']) || !$extra['zoneadvanced']) {
            $buffer .= "<form id='generate' name='generate' method='POST' onSubmit='return max_formValidate(this) && disableTextarea();'>\n";
        }

        // Invocation type selection
        if (!is_array($extra) || (isset($extra['delivery']) && ($extra['delivery']!=phpAds_ZoneInterstitial) && ($extra['delivery']!=phpAds_ZonePopup)) && ($extra['delivery']!=MAX_ZoneEmail)) {

            $invocationTags =& OX_Component::getComponents('invocationTags');

            $allowed = array();
            foreach($invocationTags as $pluginKey => $invocationTag) {
                if ($invocationTag->isAllowed($extra, $this->server_same)) {
                    $aOrderedComponents[$invocationTag->getOrder()] =
                        array(
                            'pluginKey' => $pluginKey,
                            'isAllowed' => $invocationTag->isAllowed($extra, $this->server_same),
                            'name' => $invocationTag->getName()
                        );
                }
            }

            ksort($aOrderedComponents);
            foreach ($aOrderedComponents as $order => $aComponent) {
                $allowed[$aComponent['pluginKey']] = $aComponent['isAllowed'];
            }

            if (!isset($this->codetype) || $allowed[$this->codetype] == false) {
                foreach ($allowed as $codetype => $isAllowed) {
                    $this->codetype = $codetype;
                    break;
                }

            $buffer .= "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
            $buffer .= "<tr><td height='25' width='350'><b>". $GLOBALS['strChooseTypeOfBannerInvocation'] ."</b>";
            if ($this->codetype=="invocationTags:oxInvocationTags:adview"){
                $buffer .= "";
            }

            $buffer .= "</td></tr><tr><td height='35' valign='top'>";
            $buffer .= "<select name='codetype' onChange=\"disableTextarea();this.form.submit()\" accesskey=".$GLOBALS['keyList']." tabindex='".($this->tabindex++)."'>";

            $invocationTagsNames = array();
            foreach ($aOrderedComponents as $order => $aComponent) {
                $invocationTagsNames[$aComponent['pluginKey']] = $aComponent['name'];
            }
            foreach($invocationTagsNames as $pluginKey => $invocationTagName) {
                $buffer .= "<option value='".$pluginKey."'".($this->codetype == $pluginKey ? ' selected' : '').">".$invocationTagName."</option>";
            }
            $buffer .= "</select>";
            $buffer .= "&nbsp;<input type='image' src='" . OX::assetPath() . "/images/".$phpAds_TextDirection."/go_blue.gif' border='0'></td>";
        } else {
            $invocationTags =& OX_Component::getComponents('invocationTags');
            foreach($invocationTags as $invocationCode => $invocationTag) {
                if(isset($invocationTag->defaultZone) && $extra['delivery'] == $invocationTag->defaultZone) {
                    $this->codetype = $invocationCode;
                    break;
                }
            }
            if (!isset($this->codetype)) {
                $this->codetype = '';
            }
        }
        if ($this->codetype != '') {
            $invocationTag = OX_Component::factoryByComponentIdentifier($this->codetype);
            if($invocationTag === false) {
                OA::debug('Error while factory invocationTag plugin');
                exit();
            }
            $invocationTag->setInvocation($this);

            $buffer .= "</td></tr></table>";

            $buffer .= $invocationTag->getHeaderHtml( $this, $extra );
            $buffer .= $this->getTextAreaAndOptions($invocationTag, $extra);
        }
        if (is_array($extra)) {
            reset($extra);
            while (list($k, $v) = each($extra)) {
                $buffer .= "<input type='hidden' value='".htmlspecialchars($v,ENT_QUOTES)."' name='$k'>";
            }
        }
        if (!is_array($extra) || !isset($extra['zoneadvanced']) || !$extra['zoneadvanced']) {
            $buffer .= "</form><br /><br />";
        }

        $buffer .= "<script type='text/javascript'>
            function disableTextarea() {
                var form = findObj('generate');
                if (typeof(form.bannercode) != 'undefined') {
                    form.bannercode.disabled = true;
                }
                form.submit();
            }
            </script>
        ";

        return $buffer;
    }

    public function getTextAreaAndOptions($invocationTag, $extra)
    {
        if(!$invocationTag->displayTextAreaAndOptions) {
            return '';
        }
        $buffer = "<hr />";

        $buffer .= "<div id='div-zone-invocation'>";
        $buffer .= "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tr>";
        $buffer .= "<td width='40'>&nbsp;</td><td><br />";

        $buffer .= "<br />";

        if ($invocationTag->canGenerate()) {
            $buffer .= "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
            $buffer .= "<tr><td height='25'>";

            if ($this->codetype == 'invocationTags:oxInvocationTags:xmlrpc') {
                $buffer .= "
                    <div class='errormessage'><img class='errormessage' src='" . OX::assetPath() . "/images/warning.gif' align='absmiddle'>
                        {$GLOBALS['strIABNoteXMLRPCInvocation']}
                    </div>";
            }

            if ($this->codetype == 'invocationTags:oxInvocationTags:local' && !$this->server_same) {
                $buffer .= "
                    <div class='errormessage'><img class='errormessage' src='" . OX::assetPath() . "/images/warning.gif' align='absmiddle'>
                        {$GLOBALS['strWarningLocalInvocation']}
                        <br><p>{$GLOBALS['strIABNoteLocalInvocation']}</p>
                    </div>";
            }
            else if ($this->codetype == 'invocationTags:oxInvocationTags:local' && $this->server_same) {
                $buffer .= "
                    <div class='errormessage'><img class='errormessage' src='" . OX::assetPath() . "/images/warning.gif' align='absmiddle'>
                {$GLOBALS['strIABNoteLocalInvocation']}
                    </div>";
            }

            if (empty($invocationTag->suppressTextarea)) {
                $buffer .= "<img src='" . OX::assetPath() . "/images/icon-generatecode.gif' align='absmiddle'>&nbsp;<b>".$GLOBALS['strBannercode']."</b></td>";

                if (strpos ($_SERVER['HTTP_USER_AGENT'], 'MSIE') > 0 &&
                    strpos ($_SERVER['HTTP_USER_AGENT'], 'Opera') < 1) {
                    $buffer .= "<td height='25' align='right'><img src='" . OX::assetPath() . "/images/icon-clipboard.gif' align='absmiddle'>&nbsp;";
                    $buffer .= "<a href='javascript:max_CopyClipboard(\"bannercode\");'>".$GLOBALS['strCopyToClipboard']."</a></td></tr>";
                } else {
                    $buffer .= "<td>&nbsp;</td>";
                }
                $buffer .= "<tr height='1'><td colspan='2' bgcolor='#888888'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='100%'></td></tr>";
                $buffer .= "<tr><td colspan='2'>";

                $buffer .= "<textarea id='bannercode' name='bannercode' class='code-gray' rows='15' cols='80' style='width:95%; border: 1px solid black' readonly>";
                $buffer .= htmlspecialchars($this->generateInvocationCode($invocationTag));
                $buffer .= "</textarea>";

                $buffer .= "
                    <script type='text/javascript'>
                    <!--
                    $(document).ready(function() {
                        $('#bannercode').selectText();
                    });
                    //-->
                    </script>";
            } else {
                $buffer .= $this->generateInvocationCode($invocationTag);
            }
            $buffer .= "</td></tr>";
            $buffer .= "</table><br />";
            $buffer .= phpAds_ShowBreak($print = false);
            $buffer .= "<br />";


            $generated = true;
        } else {
            $generated = false;
        }

        if (!(is_array($extra) && isset($extra['zoneadvanced']) && $extra['zoneadvanced'])) {
            // Header
            // Parameters Section
            $buffer .= "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
            $buffer .= "<tr><td height='25' colspan='3'><img src='" . OX::assetPath() . "/images/icon-overview.gif' align='absmiddle'>&nbsp;<b>".$GLOBALS['strParameters']."</b></td></tr>";
            $buffer .= "<tr height='1'><td width='30'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='30'></td>";
            $buffer .= "<td width='200'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='200'></td>";
            $buffer .= "<td width='100%'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='100%'></td></tr>";
        }

        $buffer .= $invocationTag->generateOptions($this);

        if (!(is_array($extra) && isset($extra['zoneadvanced']) && $extra['zoneadvanced'])) {

            $buffer .= "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
            $buffer .= "<tr height='1'><td colspan='3' bgcolor='#888888'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='100%'></td></tr>";
            $buffer .= "</table>";
            $buffer .= "<br /><br />";
            $buffer .= "<input type='hidden' value='".($generated ? 1 : 0)."' name='generate'>";
            if ($generated) {
                $buffer .= "<input type='submit' value='".$GLOBALS['strRefresh']."' name='submitbutton' tabindex='".($this->tabindex++)."'>";
            } else {
                $buffer .= "<input type='submit' value='".$GLOBALS['strGenerate']."' name='submitbutton' tabindex='".($this->tabindex++)."'>";
            }
        }

        $buffer .= "</td></tr></table>";
        $buffer .= "</div>";
        return $buffer;
    }


     * @return string

    function getDefaultOptionsList()
    {
        $options = array (
            'spacer'                    => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'thirdPartyServer'          => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'cacheBuster'               => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'comments'                  => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
        );
        return $options;
    }

    function generateJavascriptTrackerCode($trackerId, $append = false)
    {
        $conf = $GLOBALS['_MAX']['CONF'];

        $variablemethod = 'default';
        $trackers = Admin_DA::getTrackers(array('tracker_id' => $trackerId), true);
        if (count($trackers)) {
            $variablemethod = $trackers[$trackerId]['variablemethod'];
        }

        $variables = Admin_DA::getVariables(array('trackerid' => $trackerId), true);
        $variablesQuerystring = '';

        $name = PRODUCT_NAME;
        if (!empty($GLOBALS['_MAX']['CONF']['ui']['applicationName'])) {
            $name = $GLOBALS['_MAX']['CONF']['ui']['applicationName'];
        }
        $buffer = "<!--/*
        $varbuffer = '';
        if (!empty($variables)) {
            foreach ($variables as $id => $variable) {
                if (($variablemethod == 'default' || $variablemethod == 'js') && $variable['variablecode']) {
                    $varcode    = stripslashes($variable['variablecode']);
                    $varbuffer .= "    {$varcode};\n";
                }
                $variablesQuerystring .= "&amp;{$variable['name']}=%%" . strtoupper($variable['name']) . "_VALUE%%";
            }
        }

        if (!empty($varbuffer)) {
            $varprefix = $conf['var']['prefix'];
            $buffer .= "

<script type='text/javascript'><!--
";
            $buffer .= $varbuffer;
            $buffer .= "
";
        }

        $buffer  .= "

<script type='text/javascript'><!--
    var {$varprefix}p = (location.protocol=='https:'?'https:".MAX_commonConstructPartialDeliveryUrl($conf['file']['conversionjs'], true)."':'http:".MAX_commonConstructPartialDeliveryUrl($conf['file']['conversionjs'])."');\n
    var {$varprefix}r=Math.floor(Math.random()*999999);
    document.write (\"<\" + \"script language='JavaScript' \");
    document.write (\"type='text/javascript' src='\"+{$varprefix}p);
    document.write (\"?trackerid={$trackerId}";

        if ($append == true) {
            $buffer .= "&amp;append=1";
        } else {
            $buffer .= "&amp;append=0";
        }

        $buffer .= "&amp;r=\"+{$varprefix}r+\"'><\" + \"\\/script>\");
//]]>--></script><noscript>" . $this->_generateTrackerImageBeacon($trackerId) . "</noscript>";
        $buffer .= "\n";
        return $buffer;
    }

    function _generateTrackerImageBeacon($trackerId)
    {
        $conf = $GLOBALS['_MAX']['CONF'];

        $variables = Admin_DA::getVariables(array('trackerid' => $trackerId), true);
        $beacon  = "<div id='m3_tracker_{$trackerId}' style='position: absolute; left: 0px; top: 0px; visibility: hidden;'>";
        $beacon .= "<img src='" . MAX_commonConstructDeliveryUrl($conf['file']['conversion']) . "?trackerid={$trackerId}";
        foreach ($variables as $variable) {
            $beacon .= "&amp;{$variable['name']}=%%" . strtoupper($variable['name']) . "_VALUE%%";
        }
        $beacon .= "&amp;cb=%%RANDOM_NUMBER%%' width='0' height='0' alt='' /></div>";
        return $beacon;
    }
}

?>
