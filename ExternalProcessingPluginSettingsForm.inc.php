<?php

/**
 * @file TremorPluginSettingsForm.inc.php
 *
 * Copyright (c) 2011 Center for Digital Research and Scholarship, Columbia University
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 * 
 * @class ExternalProcessingPluginSettingsForm
 * @ingroup plugins_generic_externalProcessing
 *
 * @brief Form for journal managers to modify externalProcessing plugin settings
 */

// $Id$


import('lib.pkp.classes.form.Form');

class ExternalProcessingPluginSettingsForm extends Form {

	/** @var $journalId int */
	var $journalId;

	/** @var $plugin object */
	var $plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $journalId int
	 */
	function ExternalProcessingPluginSettingsForm(&$plugin, $journalId) {
		
		$this->journalId = $journalId;
		$this->plugin =& $plugin;

		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$journalId = $this->journalId;
		$plugin =& $this->plugin;

		$this->_data = array(
			'ftphost' => $plugin->getSetting($journalId, 'ftphost'),
			'ftpuser' => $plugin->getSetting($journalId, 'ftpuser'),
			'ftppassword' => $plugin->getSetting($journalId, 'ftppassword'),
			'ftpdirectorysend' => $plugin->getSetting($journalId, 'ftpdirectorysend'),
			'ftpdirectoryreceive' => $plugin->getSetting($journalId, 'ftpdirectoryreceive'),
			'sendarticlefields' => $plugin->getSetting($journalId, 'sendarticlefields'),
			'mkdirmode' => $plugin->getSetting($journalId, 'mkdirmode'),
			'mkdirchgrp' => $plugin->getSetting($journalId, 'mkdirchgrp')
		);

	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('ftphost','ftpuser','ftppassword','ftpdirectorysend','ftpdirectoryreceive','sendarticlefields','mkdirmode','mkdirchgrp'));
	}

	/**
	 * Save settings. 
	 */
	function execute() {
		$plugin =& $this->plugin;
		$journalId = $this->journalId;

		$plugin->updateSetting($journalId, 'ftphost', trim($this->getData('ftphost')), 'string');
		$plugin->updateSetting($journalId, 'ftpuser', trim($this->getData('ftpuser')), 'string');
		$plugin->updateSetting($journalId, 'ftppassword', trim($this->getData('ftppassword')), 'string');
		$plugin->updateSetting($journalId, 'ftpdirectorysend', trim($this->getData('ftpdirectorysend')), 'string');
		$plugin->updateSetting($journalId, 'ftpdirectoryreceive', trim($this->getData('ftpdirectoryreceive')), 'string');
		$plugin->updateSetting($journalId, 'sendarticlefields', trim($this->getData('sendarticlefields')), 'string');
		$plugin->updateSetting($journalId, 'mkdirmode', trim($this->getData('mkdirmode')), 'string');
		$plugin->updateSetting($journalId, 'mkdirchgrp', trim($this->getData('mkdirchgrp')), 'string');
	}
}

?>
