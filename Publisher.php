<?php

require_once MAX_PATH . '/lib/max/Admin/Invocation.php';

class MAX_Admin_Invocation_Publisher extends MAX_Admin_Invocation {


     * @var array

    var $defaultOptionValues = array('comments' => 0);


     * @param array
     * @param boolean
     * @return string

    function placeInvocationForm($extra = '', $zone_invocation = false, $aParams = null)
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $pref = $GLOBALS['_MAX']['PREF'];

        $globalVariables = array(
            'affiliateid', 'codetype', 'size', 'text', 'dest'
        );

        $buffer = '';

        $this->zone_invocation = $zone_invocation;

        foreach($globalVariables as $makeMeGlobal) {
            global $$makeMeGlobal;

            $this->$makeMeGlobal =& $$makeMeGlobal;
        }

        $invocationTypes =& OX_Component::getComponents('invocationTags');
        foreach($invocationTypes as $pluginKey => $invocationType) {
            if (!empty($invocationType->publisherPlugin)) {
                $available[$pluginKey] = $invocationType->publisherPlugin;
                $names[$pluginKey] = $invocationType->getName();
                if (!empty($invocationType->default)) {
                    $defaultPublisherPlugin = $pluginKey;
                }
            }
        }

        $affiliateid = $this->affiliateid;

        if (count($available) == 1) {

            $codetype = $defaultPublisherPlugin;
        } elseif (count($available) > 1) {

            if (is_null($codetype)) {
                $codetype = $defaultPublisherPlugin;
            }

	        echo "<form name='generate' method='POST' onSubmit='return max_formValidate(this);'>\n";

            echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
            echo "<input type='hidden' name='affiliateid' value='{$affiliateid}'>";
            echo "<tr><td height='25' colspan='3'><b>". $GLOBALS['strChooseTypeOfInvocation'] ."</b></td></tr>";
            echo "<tr><td height='35'>";
            echo "<select name='codetype' onChange=\"this.form.submit()\" accesskey=".$GLOBALS['keyList']." tabindex='".($tabindex++)."'>";

            foreach($names as $pluginKey => $invocationTypeName) {
                echo "<option value='".$pluginKey."'".($codetype == $pluginKey ? ' selected' : '').">".$invocationTypeName."</option>";
            }

            echo "</select>";
            echo "&nbsp;<input type='image' src='" . OX::assetPath() . "/images/".$GLOBALS['phpAds_TextDirection']."/go_blue.gif' border='0'>";
            echo "</td></tr></table>";

			echo "</form>";

            echo phpAds_ShowBreak($print = false);
            echo "<br />";
        } else {
            $code = 'Error: No publisher invocation plugins available';
            return;
        }
        if (!empty($codetype)) {
            $invocationTag = OX_Component::factoryByComponentIdentifier($codetype);
            if($invocationTag === false) {
                OA::debug('Error while factory invocationTag plugin');
                exit();
            }
            $code = $this->generateInvocationCode($invocationTag);
        }

        $previewURL = MAX::constructURL(MAX_URL_ADMIN, "affiliate-preview.php?affiliateid={$affiliateid}&codetype={$codetype}");
        foreach ($invocationTag->defaultOptionValues as $feature => $value) {
            if ($invocationTag->maxInvocation->$feature != $value) {
                $previewURL .= "&{$feature}=" . rawurlencode($invocationTag->maxInvocation->$feature);
            }
        }
        foreach ($this->defaultOptionValues as $feature => $value) {
            if ($this->$feature != $value) {
                $previewURL .= "&{$feature}=" . rawurlencode($this->$feature);
            }
        }

        echo "<form name='generate' action='".$previewURL."' method='get' target='_blank'>\n";
		echo "<input type='hidden' name='codetype' value='" . $codetype . "' />";

        echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
        echo "<tr><td height='25' colspan='3'><img src='" . OX::assetPath() . "/images/icon-overview.gif' align='absmiddle'>&nbsp;<b>".$GLOBALS['strParameters']."</b></td></tr>";
        echo "<tr height='1'><td width='30'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='30'></td>";
        echo "<td width='200'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='200'></td>";
        echo "<td width='100%'><img src='" . OX::assetPath() . "/images/break.gif' height='1' width='100%'></td></tr>";

        echo $invocationTag->generateOptions($this);

        echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
        echo "</table>";

        echo "<input type='hidden' name='affiliateid' value='{$affiliateid}' />";

        echo "<input type='submit' value='".$GLOBALS['strGenerate']."' name='submitbutton' tabindex='".($tabindex++)."'>";
        echo "</form>";
    }

     * @return array
     
    function getDefaultOptionsList()
    {
        return array('comments'  => MAX_PLUGINS_INVOCATION_TAGS_STANDARD);
    }
}

?>
