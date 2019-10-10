<?php

class ArrayUtils
{
     * @param array
     * @param object
     
    function unsetIfKeyNumeric(&$aValues, $oValue)
    {
        $key = array_search($oValue, $aValues);
        if (is_numeric($key)) {
            unset($aValues[$key]);
        }
    }
}

?>
