<?php

 * @package    Max

class MAX_FileScanner
{
	var $_files;
	var $_allowedFileMask;
	var $_allowedFileTypes;

	var $_lastMatch;
	var $_sorted;


	function __construct()
	{
		$this->_allowedFileTypes = array();
		$this->_allowedFileMask = null;
		$this->reset();
	}

	function reset()
	{
		$this->_files = array();
		$this->_lastMatch = null;
	}


	 * @param string

	function addFile($file)
	{
		$this->_sorted = false;
		if ($this->isAllowedFile($file)) {
		    if (!in_array($file, $this->_files)) {
		        $key = $this->buildKey($file);
		        if (empty($key)) {
		            $this->_files[] = $file;
		        } else {
		            $this->_files[$key] = $file;
		        }
		    }
		}
	}


	 * @param string
	 * @param integer|boolean

	function addDir($dir, $recursive = false)
	{
        if ($recursive) {
		    return $this->_addRecursiveDir($dir, $recursive);
		}
	    if ($handle = opendir($dir)) {
            while ($file = readdir($handle)) {
                if (is_dir($dir.'/'.$file)) {
                    continue;
                }
                $this->addFile($dir.'/'.$file);
            }
            closedir($handle);
        }
	}


	 * @param string
	 * @param integer|boolean

	function _addRecursiveDir($dir, $recursive = true)
	{
	    if ($recursive !== true) {
		    if ($recursive < 0) {
		        return;
		    }
		    $recursive--;
		}

		if (!is_dir($dir)) {
		    return;
		}
	    if ($handle = opendir($dir)) {
            while ($file = readdir($handle)) {
                if (is_dir($dir.'/'.$file) && $file != '.' && $file != '..') {
                    $this->_addRecursiveDir($dir.'/'.$file, $recursive);
                    continue;
                }
                $this->addFile($dir.'/'.$file);
            }
            closedir($handle);
        }
	}


	function getAllFiles()
	{
		if (!$this->_sorted) {
			$this->_sorted = true;
			if (!empty($this->_allowedFileMask)) {
				asort($this->_files, SORT_STRING);
	    	} else {
				sort($this->_files, SORT_STRING);
	    	}
		}
		return $this->_files;
	}


	 * @param string

	function setFileMask($fileMask)
	{
	    $this->_allowedFileMask = $fileMask;
	}


	 * @param array
	 * @return boolean

	function addFileTypes($fileTypes)
	{
		if (!is_array($fileTypes)) {
		    $fileTypes = array($fileTypes);
		}
	    $modified = false;
        if (is_array($fileTypes)) {
		    foreach ($fileTypes as $fileType) {
		        if (!in_array($fileType, $this->_allowedFileTypes)) {
		            $this->_allowedFileTypes[] = $fileType;
		            $modified = true;
		        }
		    }
		}
		return $modified;
	}


	 * @param string
	 * @return boolean

	function isAllowedFile($fileName)
	{
	    if (!empty($this->_allowedFileTypes)) {

    	    $ext = $this->getFileExtension($fileName);

            if (!in_array(strtolower($ext), $this->_allowedFileTypes)) {
                return false;
            }
	    }

        if (!empty($this->_allowedFileMask)) {
        	$matches = null;
            if (!preg_match($this->_allowedFileMask, $fileName, $matches)) {
                return false;
            } else {
                $this->_lastMatch = $matches;
            }
	    }
	    return true;
	}


	 * @param string
	 * @return string
	 * @static

	function getFileExtension($fileName)
	{
        return substr($fileName, strrpos($fileName, '.')+1, strlen($fileName));
	}


	 * @param string
	 * @return string
	 * @static

	function getFileName($fileName)
	{
	   return substr($fileName, strrpos($fileName, '/')+1, strlen($fileName));
	}

	 * @param string
	 * @return string
	 
	function buildKey($fileName)
	{
	    if (empty($this->_allowedFileMask)) {
    	    return null;
    	}
	    if (!empty($this->_lastMatch)) {
    	    $matches = $this->_lastMatch;
    	} else {
    		$matches = null;
    	    preg_match($this->_allowedFileMask, $fileName, $matches);
    	}
	    if (is_array($matches) && count($matches) == 4) {
            $key = $matches[2].':'.$matches[3];
            return $key;
        }
        return null;
	}

}

?>
