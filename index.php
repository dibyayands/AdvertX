<?php

define('ROOT_INDEX', true);

// Require the initialisation file
require_once 'init.php';

// Required files
require_once LIB_PATH . '/Admin/Redirect.php';

if (OA_INSTALLATION_STATUS == OA_INSTALLATION_STATUS_INSTALLED)
{
    OX_Admin_Redirect::redirect();
}

?>
