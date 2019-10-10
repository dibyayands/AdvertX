<?php

require_once RV_PATH . '/lib/RV.php';

require_once MAX_PATH . '/lib/OA.php';
require_once OX_PATH . '/lib/pear/PEAR.php';

 * @package

class MAX
{
     * @param integer
     * @return string

    public static function errorConstantToString($errorCode)
    {
        $aErrorCodes = array(
            MAX_ERROR_INVALIDARGS           => 'invalid arguments',
            MAX_ERROR_INVALIDCONFIG         => 'invalid config',
            MAX_ERROR_NODATA                => 'no data',
            MAX_ERROR_NOCLASS               => 'no class',
            MAX_ERROR_NOMETHOD              => 'no method',
            MAX_ERROR_NOAFFECTEDROWS        => 'no affected rows',
            MAX_ERROR_NOTSUPPORTED          => 'not supported',
            MAX_ERROR_INVALIDCALL           => 'invalid call',
            MAX_ERROR_INVALIDAUTH           => 'invalid auth',
            MAX_ERROR_EMAILFAILURE          => 'email failure',
            MAX_ERROR_DBFAILURE             => 'db failure',
            MAX_ERROR_DBTRANSACTIONFAILURE  => 'db transaction failure',
            MAX_ERROR_BANNEDUSER            => 'banned user',
            MAX_ERROR_NOFILE                => 'no file',
            MAX_ERROR_INVALIDFILEPERMS      => 'invalid file perms',
            MAX_ERROR_INVALIDSESSION        => 'invalid session',
            MAX_ERROR_INVALIDPOST           => 'invalid post',
            MAX_ERROR_INVALIDTRANSLATION    => 'invalid translation',
            MAX_ERROR_FILEUNWRITABLE        => 'file unwritable',
            MAX_ERROR_INVALIDREQUEST        => 'invalid request',
            MAX_ERROR_INVALIDTYPE           => 'invalid type',
        );
        if (in_array($errorCode, array_keys($aErrorCodes))) {
            return strtoupper($aErrorCodes[$errorCode]);
        } else {
            return 'PEAR';
        }
    }

     * @param PEAR_Error
     * @param string
     * @return string

    public static function errorObjToString($oError, $additionalInfo = null)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        $message = htmlspecialchars($oError->getMessage());
        $debugInfo = htmlspecialchars($oError->getDebugInfo());
        $additionalInfo = htmlspecialchars($additionalInfo);
        $level = $oError->getCode();
        $errorType = MAX::errorConstantToString($level);
        $img = MAX::constructURL(MAX_URL_IMAGE, 'errormessage.gif');
        // Message
        $output = <<<EOF
<br />
<div class="errormessage">
    <img class="errormessage" src="$img" align="absmiddle">
    <span class='tab-r'>$errorType Error</span>
    <br />
    <br />$message
    <br /><pre>$debugInfo</pre>
    $additionalInfo
</div>
<br />
<br />
EOF;
        return $output;
    }


     * @static
     * @param mixed
     * @param integer
     * @param integer
     * @return PEAR_Error

    public static function raiseError($message, $type = null, $behaviour = null)
    {

        if ($behaviour == PEAR_ERROR_DIE) {

            $errorType = MAX::errorConstantToString($type);
            if (!is_string($message)) $message = print_r($message, true);
            OA::debug($type . ' :: ' . $message, PEAR_LOG_EMERG);
            exit();
        }
        $error = PEAR::raiseError($message, $type, $behaviour);
        return $error;
    }


     * @param integer
     * @param string
     * @return string

    public static function constructURL($type, $file = null)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        // Prepare the base URL
        if ($type == MAX_URL_ADMIN) {
            $path = $aConf['webpath']['admin'];
        } elseif ($type == MAX_URL_IMAGE) {
            return OX::assetPath("/images/" . $file);
        } else {
            return null;
        }

        $path .= '/';

        if ($aConf['openads']['sslPort'] != 443) {
            if ($GLOBALS['_MAX']['HTTP'] == 'https://') {
                $path = preg_replace('#/#', ':' . $aConf['openads']['sslPort'] . '/', $path, 1);
            }
        }

        return $GLOBALS['_MAX']['HTTP'] . $path . $file;
    }
}


 * @static
 * @param PEAR_Error

function pearErrorHandler($oError)
{
    $aConf = $GLOBALS['_MAX']['CONF'];

    $message = $oError->getMessage();
    $debugInfo = $oError->getDebugInfo();
    OA::debug('PEAR' . " :: $message : $debugInfo", PEAR_LOG_ERR);

    $msg = '';
    if (empty($aConf['debug']['production'])) {
        $GLOBALS['_MAX']['ERRORS'][] = $oError;
    }

    if (!empty($aConf['debug']['showBacktrace'])) {
        $msg .= 'PEAR backtrace: <div onClick="if (this.style.height) {this.style.height = null;this.style.width = null;} else {this.style.height = \'8px\'; this.style.width=\'8px\'}"';
        $msg .= 'style="float:left; cursor: pointer; border: 1px dashed #FF0000; background-color: #EFEFEF; height: 8px; width: 8px; overflow: hidden; margin-bottom: 2px;">';
        $msg .= '<pre wrap style="margin: 5px; background-color: #EFEFEF">';

        ob_start();
        print_r($oError->getBacktrace());
        $msg .= ob_get_clean();

        $msg .= '<hr></pre></div>';
        $msg .= '<div style="clear:both"></div>';
    }
    if (defined('TEST_ENVIRONMENT_RUNNING')) {

        echo nl2br("Message: $message\ndebugInfo: $debugInfo\nbackTrace: $msg");
        exit(1);
    } elseif (defined('OA_WEBSERVICES_API_XMLRPC')) {

        $oResponse = new XML_RPC_Response('', 99999, $message);
        echo $oResponse->serialize();
        exit;
    } else {

        echo MAX::errorObjToString($oError, $msg);
    }
}

$oPEAR = new PEAR();
$oPEAR->setErrorHandling(PEAR_ERROR_CALLBACK, 'pearErrorHandler');
$clientCache = array();
$campaignCache = array();
$bannerCache = array();
$zoneCache = array();
$affiliateCache = array();

?>
