<?php 

/**
 * @defgroup plugins_generic_externalProcessing
 */
 
/**
 * @file plugins/generic/externalProcessing/index.php
 *
 * Copyright (c) 2011 Center for Digital Research and Scholarship, Columbia University
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_externalProcessing
 * @brief Wrapper for ExternalProcessing plugin
 *
 */

// $Id$


require_once('ExternalProcessingPlugin.inc.php');

return new ExternalProcessingPlugin(); 

?> 