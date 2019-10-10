<?php

// Required files
require_once MAX_PATH . '/lib/OA/DB.php';
require_once MAX_PATH . '/lib/OA/DB/Table/Core.php';

class Max_Admin_DB
{

     * @param string $type
     * @return boolean

    function tableTypeIsSupported($type)
    {
        // Assume MySQL always supports MyISAM table types
        if ($type == 'MYISAM') {
            return true;
        } else {
            $oDbh =& OA_DB::singleton();
            $rc = $oDbh->query('SHOW VARIABLES');
            while ($row = $rc->fetchRow(DB_FETCHMODE_ORDERED)) {
                if ($type == 'BDB' && $row[0] == 'have_bdb' && $row[1] == 'YES') {
                    return true;
                }
                if ($type == 'GEMINI' && $row[0] == 'have_gemini' && $row[1] == 'YES') {
                    return true;
                }
                if ($type == 'INNODB' && $row[0] == 'have_innodb' && $row[1] == 'YES') {
                    return true;
                }
            }
        }
        return false;
    }


     * @return array

    function getTableTypes()
    {
        $types['MYISAM'] = 'MyISAM';
        $types['BDB'] = 'Berkeley DB';
        $types['GEMINI'] = 'NuSphere Gemini';
        $types['INNODB'] = 'InnoDB';
        $types[' '] = 'PostgreSQL';
        return $types;
    }


     * @return array

    function getServerTypes()
    {

        $types['mysql'] = 'mysql';
        $types['mysqli'] = 'mysqli';
        return $types;
    }


     * @param array
     * @return boolean
     
    function checkDatabaseExists($installvars)
    {
        $oDbh =& OA_DB::singleton();
        $oTable = OA_DB_Table_Core::singleton();
        $aTables = OA_DB_Table::listOATablesCaseSensitive();
        $result = false;
        foreach ($oTable->tables as $k => $v) {
            if (is_array($aTables) && in_array($installvars['table_prefix'] . $k, $aTables)) {
                // Table exists
                $result = true;
                break;
            }
        }
        return $result;
    }
}

?>
