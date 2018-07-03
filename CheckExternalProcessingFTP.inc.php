<?php

/**
 * @file plugins/generic/externalProcessing/CheckExternalProcessingFTP.inc.php
 *
 * Copyright (c) 2011 Center for Digital Research and Scholarship, Columbia University
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CheckExternalProcessingFTP
 * @ingroup plugins_generic_externalProcessing
 *
 * @brief Class to check the ExternalProcessing FTP server for items to download and process
 */

// $Id$


import('lib.pkp.classes.scheduledTask.ScheduledTask');

class CheckExternalProcessingFTP extends ScheduledTask 
{

	/**
	 * Constructor.
	 */
	function CheckExternalProcessingFTP() 
	{
		$this->ScheduledTask();
	}

	function execute() 
	{
		$externalProcessingPlugin =& PluginRegistry::getPlugin("generic", "externalProcessingplugin");
                
                // Check FTP site
		$result = $externalProcessingPlugin->checkExternalProcessingFTP();
		echo(date("Y-m-d H:i:s") . " >> " . ((is_numeric($result) && $result >= 0) ? 'Downloaded '.$result.' items from ExternalProcessing' : $result) . "\r\n");
                
                // Extract the archive
                
                // Spawn additional images
                
                
                
			
	}
}

?>
