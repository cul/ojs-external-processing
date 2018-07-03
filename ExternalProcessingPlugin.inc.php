<?php

/**
 * @file ExternalProcessingPlugin.inc.php
 *
 * Copyright (c) 2011 Center for Digital Research and Scholarship, Columbia University
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ExternalProcessingPlugin
 * @ingroup plugins_generic_externalProcessing
 *
 * @brief This plugin provides functionality for ExternalProcessing to receive/handle OJS content and files
 */
// $Id$

/*
 * NOTES:
* http://digital.lampdev.columbia.edu/tremor/editor/completeFinalCopyedit?articleId=40 --
*
*/


import('lib.pkp.classes.plugins.GenericPlugin');
import('classes.core.Request');
import('classes.article.ArticleDAO');
import('classes.article.ArticleFile');
import('classes.article.SubmissionFileDAO');
import('classes.article.ArticleGalley');
import('classes.article.SuppFileDAO');
import('lib.pkp.classes.file.SubmissionFileManager');
import('classes.journal.JournalDAO');
// import('classes.submission.editAssignment.EditAssignment');
// import('classes.submission.editAssignment.EditAssignmentDAO');
//import('pages.sectionEditor.SubmissionEditHandler');

class ExternalProcessingPlugin extends GenericPlugin {

	// MARK: OJS plugin setup
	public static $SEND_OBJECT_FIELDS = array(
			"author" => array("firstName", "middleName", "lastName", "email")
	);

	/**
	 * Register the plugin
	 * @param unknown $category
	 * @param unknown $path
	 * @return boolean
	*/
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled($mainContextId)) {
				$this->import('ExternalProcessingDAO');
				$this->import('VenderXML');
				$this->import('VenderFTP');
				$externalProcessingDao = new ExternalProcessingDAO($this->getName());
				DAORegistry::registerDAO('ExternalProcessingDAO', $externalProcessingDao);
				HookRegistry::register('LoadHandler', array(&$this, 'loadHandler'));
				HookRegistry::register('TemplateManager::display', array(&$this, 'templateManagerDisplayHandler'));
				HookRegistry::register('SectionEditorAction::setCopyeditFile', array(&$this, 'sendToExternalProcessing'));
			}
			return TRUE;
	}

	/**
	 * Gets the plugin name
	 * @return string
	 */
	function getName() {
		return 'externalProcessingplugin';
	}

	/**
	 * Gets the plugin display name
	 */
	function getDisplayName() {
		return PKPLocale::translate('plugins.generic.externalProcessing.displayname');
	}

	/**
	 * Gets the plugin description
	 */
	function getDescription() {
		return PKPLocale::translate('plugins.generic.externalProcessing.description');
	}

	/**
	 *
	 * @param unknown $hookName
	 * @param unknown $args
	 */
	function loadHandler($hookName, $args) {

	}


	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}


	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

				$templateMgr->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
				$journal = & Request::getJournal();				
				$this->import('ExternalProcessingPluginSettingsForm');
				$form = new ExternalProcessingPluginSettingsForm($this, $journal->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}else{
						$this->setBreadCrumbs(true);
						$form->display();
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}


	/**
	 * Gets the journal ID from the article ID
	 * @param unknown $articleId
	 */
	public static function getJournalIdFromArticleId($articleId) {
		import('classes.article.ArticleDAO');
		$articleDao = & DAORegistry::getDAO('ArticleDAO');
		$article = $articleDao->getArticle($articleId);
		return $article->getJournalId();
	}

	/**
	 * Gets the article ID from the URL
	 * @return unknown
	 */
	public static function getArticleIdFromUrl() {
		$urlParts = explode("/", $_SERVER["REQUEST_URI"]);
		return $urlParts[count($urlParts) - 1];
	}

	/**
	 * Handler for plugin pages
	 * @param unknown $hookName
	 * @param unknown $args
	 */
	function templateManagerDisplayHandler($hookName, $args) {

		$templateMgr = & $args[0]; //TemplateManager::getManager();
		$externalProcessingCss = $templateMgr->get_template_vars('baseUrl') . '/plugins/generic/externalProcessing/externalProcessing.css';
		$templateMgr->addStylesheet('externalProcessingCss', $externalProcessingCss);
		$externalProcessingJs = 'plugins/generic/externalProcessing/js/externalProcessing.js';
		$templateMgr->addJavascript('externalProcessingJs', $externalProcessingJs);
		$templateMgr->register_outputfilter(array('ExternalProcessingPlugin', 'templateOutputFilter'));
	}

	/**
	 * Checks for cases where the send button should be disabled
	 * @param unknown $output
	 * @return unknown
	 */
	private static function disableSendToExternalProcessingButtonIfNeeded($output) {

		// Don't allow more than one round
		if ( self::isItSecondSubmition() ) {
			$output = preg_replace(
					'/<input type="submit" name="setCopyeditFile" value="Send to ExternalProcessing" class="button" \/>/i',
					'<input type="button" name="setCopyeditFile" value="Send to ExternalProcessing" class="disabledButton"
					onClick="alert(\'Second round submission to ExternalProcessing is not allowed. Please provide updated
					files to: tremor.admin@libraries.cul.columbia.edu\')" />',
					$output
			);
			return $output;
		}

		// Don't let it send without a DOI
		if( !self::isDoiCreated() ){
			$output = preg_replace(
					'/<input type="submit" name="setCopyeditFile" value="Send to ExternalProcessing" class="button" \/>/i',
					'<input type="button" name="setCopyeditFile" value="Send to ExternalProcessing" class="disabledButton"
					onClick="alert(\'Files cannot be submitted to ExternalProcessing because a DOI was not created. Please
					contact tremor.admin@libraries.cul.columbia.edu so that this system error can be addressed.\')" />',
					$output
			);

			return $output;
		}

		return $output;
	}

	/**
	 * Checks if a DOI is created for the article
	 * @return boolean
	 */
	private static function isDoiCreated() {

		$articleId = self::getArticleIdFromUrl();
		import('classes.article.ArticleDAO');
		$articleDao = & DAORegistry::getDAO('ArticleDAO');
		$article = $articleDao->getById($articleId);

		if($article && $article->getStoredDOI()){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * Checks if an article is past the first submission
	 * @return boolean
	 */
	private static function isItSecondSubmition() {
			
		$articleId = self::getArticleIdFromUrl();
		import('classes.article.SubmissionFileDAO');
		$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
			
		$sql = "SELECT count(*) as round
		FROM submission_files 
		where submission_id='$articleId' and file_id in 
		(select distinct(file_id) from submission_file_settings 
		WHERE setting_name = 'externalProcessing' and setting_value='sent')";



		$results = & $articleFileDao->retrieve($sql);
		$round = $results->fields["round"];

		if ($round > 0) {
			return true;
		} else{
			return false;
		}
	}

	/**
	 * Modify the template to accomodate the third-party vender 
	 * @param unknown $output
	 * @param unknown $smarty
	 * @return unknown
	 */
	function templateOutputFilter($output, &$smarty) {

		// Remove the proofreading section
		$output = self::replaceInnerHtml($output, 'div', 'id="proofread"', '');

		// Alter Editor Decision section from the default to our custom, vender-specific version
		if (strstr( $_SERVER["REQUEST_URI"], 'editor/submission') && preg_match('/<div id="editorDecision">/i', $output) ) {

			// Change verbage to show it's being send to the third-party vender
			$output = preg_replace(
					'/value="Send to Copyediting"/i',
					'value="Send to ExternalProcessing"',
					$output
			);
			
			// Add the supplementary file selector to the form
			$output = preg_replace(
					'/<div id="editorDecision">(.*?)<form(.*?)action="(.*?)\/editor\/editorReview(.*?)<\/form>(.*?)<\/div>/s',
					'<div id="editorDecision">$1<form$2action="$3/editor/editorReview$4<div id="externalProcessing-specific-send">' . self::getSupplementaryFileCheckboxes() . '</div></form>$5</div>',
					$output
			);
		}

		// Disable the send button in some cases
		$output = self::disableSendToExternalProcessingButtonIfNeeded($output);

		// Add a javascript alert if there's an error
		if (preg_match('/<\/head>/i', $output)) {
			if (isset($_SESSION["externalProcessing_error_message"])) {
				$output = preg_replace(
						'/<\/head>/i', '<script type="text/javascript">jQuery(document).ready(function() { alert("' . $_SESSION["externalProcessing_error_message"] . '"); });</script></head>',
						$output
				);
				unset($_SESSION["externalProcessing_error_message"]);
			}
		}

		// Replace the copyediting section with the third-party copyediting section
		if (preg_match('/<div id="copyedit">/i', $output)) {
			$output = self::replaceInnerHtml($output, 'div', 'id="copyedit"', self::getExternalProcessingCopyeditingSection($smarty));
		}
		
		// Remove the copyediting section
		$output = preg_replace('#<tr>
				<th width="28%" colspan="2">&nbsp;</th>
				<th width="18%" class="heading">Request</th>
				<th width="16%" class="heading">Underway</th>
				<th width="16%" class="heading">Complete</th>
				<th width="22%" colspan="2" class="heading">Acknowledge</th>
				</tr>

				<tr>
				<th colspan="2">
				Layout Version
				</th>
				<td>
				N/A
				</td>
				<td>
				N/A
				</td>
				<td>
				N/A
				</td>
				<td colspan="2">
				N/A
				</td>
				</tr>
				<tr valign="top">
				<th colspan="7">
				File:&nbsp;&nbsp;&nbsp;&nbsp;
				None \(Upload final copyedit version as Layout Version prior to sending request\)
				</th>
				</tr>#', '', $output);

		return $output;
	}

	/**
	 * Gets a UI for selecting supplementary files to send with the article 
	 * @return boolean|string
	 */
	static function getSupplementaryFileCheckboxes() {
		
		// If the sectionEditorSubmissionDAO can't be retrieved, return false
		if( !$sectionEditorSubmissionDao = & DAORegistry::getDAO('SectionEditorSubmissionDAO') ) 
			return false;

		// Get the supplementary files for the submission
		$articleId = self::getArticleIdFromUrl();
		$sectionEditorSubmission = & $sectionEditorSubmissionDao->getSectionEditorSubmission($articleId);
		$suppFiles = $sectionEditorSubmission->getSuppFiles();

		// Build the HTML
		$html = '<div>Select Supplementary Files to Send</div>';
		$html .= '<ul class="externalProcessing-supp-file-selections">';
		
		foreach ($suppFiles as $suppFile) {
			$titles = $suppFile->getTitle();
			$label = $titles[PKPLocale::getLocale()] . ' (' . $suppFile->getFileName() . ')';
			$html .= '<li><input type="checkbox" id="externalProcessing_supp_file_' . $suppFile->getSuppFileId() . '" name="externalProcessing_supp_files[]" value="' . $suppFile->getSuppFileId() . '" /><label for="externalProcessing_supp_file_' . $suppFile->getSuppFileId() . '">' . $label . '</label></li>';
		}
		
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Set the page's breadcrumbs, given the plugin's tree of items to append.
	 * @param $subclass boolean
	 */
	function setBreadcrumbs($isSubclass = false) {
		$templateMgr = & TemplateManager::getManager();
		$pageCrumbs = array(
				array(
						Request::url(null, 'user'),
						'navigation.user'
				),
				array(
						Request::url(null, 'manager'),
						'user.role.manager'
				)
		);
		if ($isSubclass)
			$pageCrumbs[] = array(
					Request::url(null, 'manager', 'plugins'),
					'manager.plugins'
			);

		$templateMgr->assign('pageHierarchy', $pageCrumbs);
	}

	/**
	 * Send the article to the third-party vender
	 * @param unknown $hookName
	 * @param unknown $args
	 * @throws Exception
	 * @return boolean
	 */
	function sendToExternalProcessing($hookName, $args) {
		// $args = &$sectionEditorSubmission, &$fileId, &$revision
		$sectionEditorSubmission = & $args[0];
		$fileId = & $args[1];
		$revision = & $args[2];
		$articleId = & Request::getUserVar('articleId');
		$journal = & Request::getJournal();
		$includeSuppFiles = Request::getUserVar('externalProcessing_supp_files');
		$tempFileFolder = Config::getVar('files', 'files_dir') . '/temp';
		$zipDestination = Config::getVar('files', 'files_dir') . '/journals/' . $journal->getId() . '/articles/' . $articleId . '/externalProcessing';
		$mkdirmode = $this->getSetting($journal->getId(), 'mkdirmode');
		$mkdirchgrp = $this->getSetting($journal->getId(), 'mkdirchgrp');

		// MARK: ### WORKING HERE ON ENHANCING ERROR REPORTING ###

		if (!file_exists($zipDestination)) {
			if (!mkdir($zipDestination))
				self::statusUpdate('report', array('type' => 'error', 'details' => 'Could not make directory ' . $zipDestination));
			else {
				if ($mkdirmode) {
					if (!chmod($zipDestination, octdec($mkdirmode)))
						self::statusUpdate('report', array('type' => 'error', 'details' => 'Could not chmod ' . $zipDestination . ' to ' . $mkdirmode));
				}
				if ($mkdirchgrp) {
					if (!chgrp($zipDestination, $mkdirchgrp))
						self::statusUpdate('report', array('type' => 'error', 'details' => 'Could not chmod ' . $zipDestination . ' to ' . $mkdirchgrp));
				}
			}
		}
		else
			self::statusUpdate('report', array('type' => 'warning', 'details' => 'File ' . $zipDestination . ' already exists. No directory creation has taken place.'));

		$zipDestination .= "/sent";
		/** TODO: Is this bit of code redundant? */
		if (!file_exists($zipDestination)) {
			mkdir($zipDestination);
			if ($mkdirmode) {
				chmod($zipDestination, octdec($mkdirmode));
			}
			if ($mkdirchgrp) {
				chgrp($zipDestination, $mkdirchgrp);
			}
		}


		// If this process fails, we want to do something like below
		// $_SESSION["externalProcessing_error_message"] = "There was an error sending to ExternalProcessing";
		// Request::redirect(null, null, "submissionReview", $articleId);
		// return TRUE;
		// Step 1, determine which primary file to send to externalProcessing, get it
		// Step 2, determine which supplementary files to send along, if any, from Request::getUserVar('externalProcessing_supp_files')
		// Step 3, put together an XML File with all the OJS record info for this article
		// Step 4, put it all together in and zip it up, FTP it to ExternalProcessing
		// Step 5, mark this record as locally "sent to ExternalProcessing", supporting preparation for completing the built-in OJS copy-editing workflow.

		try {

			$zip = new ZipArchive();
			$now = time();

			$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
			$suppFileDao = & DAORegistry::getDAO('SuppFileDAO');
			$articleDao = & DAORegistry::getDAO('ArticleDAO');

			// Determine if this is a send for corrections or an initial send to set round
			$sql = "SELECT MAX(round) as max_round 
			FROM submission_files 
			where submission_id='$articleId' and file_id in 
			(select distinct(file_id) from submission_file_settings 
			WHERE setting_name = 'externalProcessing' and setting_value='sent')";


			$maxRoundResult = & $articleFileDao->retrieve($sql);
			$round = 1;
			if ($maxRoundResult && isset($maxRoundResult->fields) && $maxRoundResult->fields != false) {
				$round = $maxRoundResult->fields["max_round"] + 1;
			}

			$zipFileName = $articleId . '-' . $round . '.zip';
			$zipFilePath = $zipDestination . '/' . $zipFileName;
			if ($zip->open($zipFilePath, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
				throw new Exception("Could not create temporary zip file to send to vender");
			}
			$articleFolder = $articleId . '-' . $round;
			// 1
			$primaryFile = $articleFileDao->getArticleFile($fileId, $revision, $articleId);
			$zip->addFile($primaryFile->getFilePath(), $articleFolder . '/primary/' . $primaryFile->getFileName());

			// 2
			if (isset($includeSuppFiles) && $includeSuppFiles != null) {
				foreach ($includeSuppFiles as $includeSuppFile) {
					$suppFile = $suppFileDao->getSuppFile($includeSuppFile, $articleId);
					$suppFileTitle = $suppFile->getTitle();
					$suppArticleFile = $articleFileDao->getArticleFile($suppFile->getFileId(), null, $articleId);
					$suppFileData[] = array(
							'href' => $suppArticleFile->getFileName(),
							'label' => $suppFileTitle['en_US'],
							'caption' => $suppArticleFile->getOriginalFileName()
					);

					$zip->addFile($suppArticleFile->getFilePath(), $articleFolder . '/supplementary/' . $suppArticleFile->getFileName());
				}
			}

			// 3
			// Generate the article XML
			$venderXml = new VenderXML();
			$sendArticleFields = explode("\n", $this->getSetting($journal->getId(), 'sendarticlefields'));
			$xmlContents = $venderXml->build($articleId, $journal->getId(), $sendArticleFields, $round, $sendArticleFields, $suppFileData);
			if ($xmlContents == false)
				throw new Exception("Could not build the XML file");

			// Output the XML to a file
			$xmlFilePath = $tempFileFolder . '/' . $articleId . '.xml';
			if (!is_dir($xmlFilePath) && !is_dir($tempFileFolder)) {
				mkdir($tempFileFolder, octdec($mkdirmode), true);
				chgrp($tempFileFolder, $mkdirchgrp);
			}

			if (is_file($xmlFilePath))
			if (unlink($xmlFilePath) === FALSE)
				throw new Exception("Removed existing file at " . $xmlFilePath);

			if (file_put_contents($xmlFilePath, $xmlContents) === FALSE)
				throw new Exception("Could not create XML file in " . $xmlFilePath);

			// Add the XML file to the archive
			$zip->addFile($xmlFilePath, $articleFolder . '/ojs-article.xml');


			$zip->close();

			// Tidy up
			if ($mkdirmode)
				chmod($zipFilePath, octdec($mkdirmode));
			if ($mkdirchgrp)
				chgrp($zipFilePath, $mkdirchgrp);
			unlink($xmlFilePath);





			// 4
			$host = $this->getSetting($journal->getId(), 'ftphost');
			$user = $this->getSetting($journal->getId(), 'ftpuser');
			$password = $this->getSetting($journal->getId(), 'ftppassword');
			$directory = $this->getSetting($journal->getId(), 'ftpdirectorysend');
			if (!VenderFTP::ftpSend($host, $user, $password, $directory, $zipFilePath, $zipFileName))
				throw new Exception('Error sending file to vender');

			// 5
			// Store the file we just sent as an article file
			$articleFile = new ArticleFile();
			$articleFile->setArticleId($articleId);
			$articleFile->setFileName($zipFileName);
			$articleFile->setOriginalFileName($zipFileName);
			$articleFile->setFileType("application/zip");
			$articleFile->setFileSize(filesize($zipFilePath));
			$articleFile->setType("externalProcessing/sent");
			$articleFile->setRound($round);
			$articleFile->setDateUploaded(date("Y-m-d H:i:s", $now));
			$articleFile->setDateModified(date("Y-m-d H:i:s", $now));
			$articleFileDao->insertArticleFile($articleFile);
		} catch (Exception $e) {

			$_SESSION["externalProcessing_error_message"] = "Error: " . $e->getMessage();
			Request::redirect(null, null, "submissionReview", $articleId);
			return TRUE;
		}

		return TRUE;
	}

	public static function getExternalProcessingCopyeditingSection(&$smarty) {
		import('classes.article.SubmissionFileDAO');
		$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
		$externalProcessingDao = & DAORegistry::getDAO('ExternalProcessingDAO');
		$articleId = self::getArticleIdFromUrl();
		$journalId = self::getJournalIdFromArticleId($articleId);
		$downloadBaseUrl = $smarty->_tpl_vars["baseUrl"] . "/editor/downloadFile/" . $articleId;

		$html = '<h3>ExternalProcessing Copyediting</h3>';
		$sql = "SELECT * FROM submission_files 
		where submission_id='$articleId' and file_id in 
		(select distinct(file_id) from submission_file_settings 
		WHERE setting_name = 'externalProcessing' and setting_value='sent') order by round";


		$sentResults = & $articleFileDao->retrieve($sql);
		if (!$sentResults->EOF) {
			$html .= '<p>' . PKPLocale::translate('plugins.generic.externalProcessing.sent') . ':</p>';
			while (!$sentResults->EOF) {
				//$articles[] =& $this->_returnArticleFromRow();
				$result = $sentResults->GetRowAssoc(false);


				// Check to see if we've received any response from this particular send
				// TODO: This needs to be moved into the DAO
				$result_file_id = $result["file_id"];
				$sql = "SELECT * FROM submission_files where submission_id='$articleId' 
				and file_id='.$result_file_id.' and file_id in (select file_id from
				submission_file_settings where setting_name='externalProcessing' 
				and setting_value='received')";



				$receivedResult = & $articleFileDao->retrieve($sql);
				$data = array('venderRound' => $result['round'], 'sentDate' => $articleFileDao->dateFromDB($result["date_uploaded"]), 'sentArchive' => $result["file_id"]);
				if (!$receivedResult->EOF) {
					//		$html .= '<ul>';

					$journal = & Request::getJournal();

					$externalProcessingReceivedPath = Config::getVar('files', 'files_dir') . '/journals/' . $journal->getId() . '/articles/' . $articleId . '/externalProcessing/received';

					// MARK: Import Process
					// Process each received archive and log the progress for each
					while (!$receivedResult->EOF) {
						$data = array('venderRound' => $result['round'], 'sentDate' => $articleFileDao->dateFromDB($result["date_uploaded"]), 'sentArchive' => $result["file_id"], 'receivedArchive' => $receivedResult->fields['file_id'], 'receivedDate' => $articleFileDao->dateFromDB($receivedResult->fields['date_uploaded']));
						$result = $receivedResult->GetRowAssoc(false);

						// TODO: Add condition where user can force reimport, overwriting the files on the server and processing them all
						// If the archive has not been processed, throw it through
						if ($externalProcessingDao->archiveProcessed($receivedResult->fields['file_id']) != $receivedResult->fields['file_id']) {
							// MARK: ### GET SOME DATA FOR REPORTING HERE #####
							// Get Import Session
							$data['venderSession'] = ($externalProcessingDao->getLastSessionId() + 1);
							$importSession = self::statusUpdate('createSession', array('vender_import_session_id' => $data['venderSession'], 'archive_id' => $receivedResult->fields['file_id']));

							// Pass the archive to the handler to extract and import the archive files
							// Commented out because extraction is handled when archive is received
							//
							// $archivePath = $externalProcessingReceivedPath . '/' . $result["submission_id"] . '_' . $result["round"] . '.zip';
							$destinationPath = $externalProcessingReceivedPath . '/' . $result["submission_id"] . '_' . $result["round"];
							// $archiveHandlerResults = self::archiveHandler($archivePath, $destinationPath);
							// Import the XML
							// TODO: Get xml importing working properly
							// $xmlImportResult = self::statusUpdate('xmlImport', array('status' => self::updateArticleFromXML($result['round'])));
						}

						// TODO: Overwrite page variable for list of galleys
						// Display results
						$html .= self::displayProcessResults($externalProcessingDao->getLastSessionId(), $data);
						$receivedResult->MoveNext();
					}

					$receivedResult->Close();
				} else {
					$html .= self::displayProcessResults(NULL, $data);
					;
				}

				$receivedResult->Close();
				$sentResults->MoveNext();
			}
		} else {
			$html .= '<p>No files have been sent to ExternalProcessing yet.</p>';
		}
		$sentResults->Close();

		// MARK: Attempting to pull in arbitrary Smarty template files
		$templateMgr = & TemplateManager::getManager();

		//$templateMgr->display('about/contact.tpl');

		return $html;
	}

	public function checkExternalProcessingFTP($articleId = null, $sendTo = 'editor') {
		import('classes.article.ArticleFile');
		import('classes.article.SubmissionFileDAO');
		import('classes.article.SuppFile');
		import('classes.article.SuppFileDAO');
		import('plugins.generic.externalProcessing.VenderExtract');
		import('plugins.generic.externalProcessing.VenderFTP');
		import('plugins.generic.externalProcessing.VenderXmlMetadata');

		$count = 0;
		$now = time();
		echo "\n".date('m/d/Y h:m:s')."3pV $ Checking vender site for completed files.\n";
		$message = "\n";

		if ($articleId == null) {
			// this means we want to check all articles that might be awaiting files from ExternalProcessing
			$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
			$sql = "SELECT * FROM submission_files 	where file_id in 
			(select distinct(file_id) from submission_file_settings 
			WHERE setting_name = 'externalProcessing' and setting_value='sent')
			and	file_id not in 
			(select file_id from submission_file_settings 
			where setting_name='externalProcessing' and setting_value='received')";

			$results = & $articleFileDao->retrieve($sql);


			while (!$results->EOF) { // While we're waiting to receive files
				$result = $results->GetRowAssoc(false);

				$name_base = $result["submission_id"] . '-' . $result["round"];
				// Grab the settings
				$journalId = self::getJournalIdFromArticleId($result["submission_id"]);
				$host = $this->getSetting($journalId, 'ftphost');
				$user = $this->getSetting($journalId, 'ftpuser');
				$password = $this->getSetting($journalId, 'ftppassword');
				$directory = $this->getSetting($journalId, 'ftpdirectoryreceive');
				$saveToDirectory = Config::getVar('files', 'files_dir') . '/journals/' . $journalId . '/articles/' . $result["submission_id"] . '/externalProcessing/received/';
				$fileNamePattern = $name_base . '.zip';
				$mkdirmode = $this->getSetting($journalId, 'mkdirmode');
				$mkdirchgrp = $this->getSetting($journalId, 'mkdirchgrp');

				// If the save to directory doesn't exist, create it
				if (!is_dir($saveToDirectory)) {
					mkdir($saveToDirectory, octdec($mkdirmode), true);
					if ($mkdirchgrp) {
						chgrp($saveToDirectory, $mkdirchgrp);
					}
				}

				// Check the FTP site for each file
				$getFtpResult = VenderFTP::ftpFindRemote($host, $user, $password, $directory, $fileNamePattern, $saveToDirectory);

				if ($getFtpResult == FALSE) {
					echo "$fileNamePattern...Not found\n";
					//                    $results->Close();
				} elseif (preg_match('/' . $fileNamePattern . '/i', $getFtpResult)) {
					echo "$fileNamePattern...Found!\n";
					$message .= $fileNamePattern . " downloaded!\n";
					$count++;

					// So now, let's mark this file as received, and let's process the files w/in the zip
					// Let's get the sent file record

					$sql = "SELECT * FROM submission_files WHERE file_name = '$getFtpResult' AND 
					file_id in (select distinct(file_id) from submission_file_settings 
					WHERE setting_name = 'externalProcessing' and setting_value='sent')";


					$sentResults = & $articleFileDao->retrieve($sql);

					if ($sentResults->EOF) {
						$results->Close();
						$sentResults->Close();
						return "\nCould not find the related sent file for $getFtpResult";
					}

					// Extract the archive
					$filePattern =  $saveToDirectory . '/' . $fileNamePattern;
					$copyeditedDirPath = $saveToDirectory . $name_base;

					echo "Start extract archive: " . $filePattern . " .\n";

					$extractionResult = VenderExtract::dump($filePattern, $saveToDirectory);
					echo "Extract archive finished to: " . $saveToDirectory . " .\n";

					// Import into OJS
					echo "ImportCopyedited started - copyeditedDirPath: " . $saveToDirectory . $name_base . "\n";
					$message .= self::importCopyedited($result["submission_id"], $copyeditedDirPath);
					echo "ImportCopyedited finished";


					echo "Start to extract metadata from xml: " . $saveToDirectory . '/' . $name_base . " .\n" ;
					VenderXmlMetadata::extractMetaDataFromXmlFile($saveToDirectory . '/' . $name_base . "/ojs-article.xml" , $journalId);
					echo "End to extract metadata from xml. \n";

					// Create record for the new file
					while (!$sentResults->EOF) {
						$sentResult = $sentResults->GetRowAssoc(false);
						$articleFile = new ArticleFile();
						$articleFile->setArticleId($result["submission_id"]);
						$articleFile->setFileName($getFtpResult);
						$articleFile->setOriginalFileName($getFtpResult);
						$articleFile->setFileType("application/zip");
						$articleFile->setFileSize(filesize($saveToDirectory . "/" . $getFtpResult));
						$articleFile->setType("externalProcessing/received");
						$articleFile->setRound($sentResult["round"]);
						$articleFile->setAssocId($result["file_id"]);
						$articleFile->setDateUploaded(date("Y-m-d H:i:s", $now));
						$articleFile->setDateModified(date("Y-m-d H:i:s", $now));
						$articleFileDao->insertArticleFile($articleFile);
						$sentResults->MoveNext();

						
						//$this->sendNotificationEmailToEditor($articleFile->getArticleId());
					}

					$sentResults->Close();
				} else {

					if (is_bool($getFtpResult))
						$val = ($getFtpResult) ? 'TRUE' : 'FALSE';
					else
						$val = print_r($getFtpResult, 1);
					$message .= "the unexpected has happened! \n" . $val . "\n";
				}

				$results->MoveNext();
			}
			$results->Close();

			$oldReturn = $count;

			$message .= "\n".date('m/d/Y h:m:s')."3pV $ Finished\n";
			return $message;
		}
	}

	public function getJournalManagers($journalId){
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$journalManagers = $roleDao->getUsersByRoleId(ROLE_ID_JOURNAL_MANAGER, $journalId);
		$journalManagers =& $journalManagers->toArray();

		return $journalManagers;
	}

	public function sendNotificationEmailToManager($articleId, $journalId) {

		$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
		$journalManagers = $this->getJournalManagers($journalId);

		$results = & $articleFileDao->retrieve($sql);


		import('classes.mail.MailTemplate');
		$email = new MailTemplate('EDITOR_NOTIFICATION_TO_PUBLISH');
		$email->assignParams( array('articleId' => $articleId, 'editorName' => 'journal manager') );
		foreach($journalManagers as $manager) {
			$name = $manager->_data['firstName'].' '.$manager->_data['lastName'];
			$email->addRecipient($manager->_data['email'], $name);
		}

		$email->setFrom("support@tremorjournal.org", "Tremor - TOHM"); // email is hardcodded = not good, need to be pulled from DB or some config.
		$email->send();

		echo "Editor notification sent to ".count($journalManagers)." journal managers" . "\n";

	}

	public function sendNotificationEmailToEditor($articleId) {

		$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');

		$sql = "select distinct concat(u.first_name, ' ', u.last_name) as name, u.email from edit_assignments e, users u
		where
		e.editor_id = u.user_id
		and e.submission_id = $articleId";

		// $journalManagers = $roleDao->getUsersByRoleId(ROLE_ID_JOURNAL_MANAGER, $journal->getId());
		// var_dump($journalManagers);


		$results = & $articleFileDao->retrieve($sql);

		$editorName = $results->fields["name"];

		import('classes.mail.MailTemplate');
		$email = new MailTemplate('EDITOR_NOTIFICATION_TO_PUBLISH');
		$email->assignParams( array('articleId' => $articleId, 'editorName' => $editorName) );

		$email->addRecipient($results->fields["email"], $results->fields["name"]);
		$email->setFrom("support@tremorjournal.org", "Tremor - TOHM"); // email is hardcodded = not good, need to be pulled from DB or some config.
		$email->send();
			
		echo "Editor notification sent to: " . $editorName . " <" . $results->fields["email"] . "> .\n";

	}

	public function processVenderArchive($articleId = null) {

	}

	// MARK: ftpSend FORMER RESTING PLACE
	// MARK: ftpFindRemote FORMER RESTING PLACE
	// MARK: XML tools

	public static function getXmlArticleData($article, $fieldName) {
		if (isset($article->$fieldName)) {
			return self::getXmlFromData($article->$fieldName, $fieldName);
		} else {
			return self::getXmlFromData($article->getData($fieldName), $fieldName);
		}
	}

	public static function getXmlFromData($data, $parentName = null) {
		if (is_array($data)) {
			if (isset($data[PKPLocale::getLocale()])) {
				return self::getXmlFromData($data[PKPLocale::getLocale()]);
			} else {
				foreach ($data as $key => $value) {
					if ($parentName != null && is_numeric($key)) {
						$key = substr($parentName, 0, strlen($parentName) - 1);
					}
					return '<' . $key . '>' . self::getXmlFromData($value, $key) . '</' . $key . '>';
				}
			}
		} elseif (is_object($data) && $parentName != null && isset(self::$SEND_OBJECT_FIELDS[$parentName]) && method_exists($data, 'getAllData')) {
			$innerXml = '';
			$objectData = $data->getAllData();
			foreach (self::$SEND_OBJECT_FIELDS[$parentName] as $fieldName) {
				$innerXml .= '<' . $fieldName . '>' . $objectData[$fieldName] . '</' . $fieldName . '>';
			}
			return $innerXml;
		} else {
			return $data;
		}
	}

	public static function replaceInnerHtml($html, $elementName, $elementAttributes, $replaceWith) {

		// Create tags
		$openingTag = '<' . $elementName . ' ' . $elementAttributes . '>';
		$endingTag = '</' . $elementName . '>';

		$regex = '.*' . $openingTag;
		preg_match('/' . $regex . '/s', $html, $matches);
		if (count($matches) == 0)
			return $html;
		$includeAndBefore = $matches[0];
		$beforeOpen = substr($includeAndBefore, 0, strlen($includeAndBefore) - strlen($openingTag));

		$regex = $openingTag . '.*';
		preg_match('/' . $regex . '/s', $html, $matches);
		$includeAndAfter = $matches[0];
		$afterOpen = substr($includeAndAfter, strlen($openingTag));

		// look ahead in the string, search for the end tag </$elementName>, but must note nested tags with same name
		$nestedOpened = 0;
		$pos = 0;
		$sameTag = '<' . $elementName;
		$replaced = false;

		$newHtml = $beforeOpen . $openingTag;

		while ($pos < strlen($afterOpen)) {
			if ($replaced == true) {
				$newHtml .= substr($afterOpen, $pos, 1);
			} elseif (substr($afterOpen, $pos, strlen($sameTag)) == $sameTag) {
				$nestedOpened++;
			} elseif (substr($afterOpen, $pos, strlen($endingTag)) == $endingTag) {
				if ($nestedOpened == 0) {
					// We're there, just append our replacement
					$newHtml .= $replaceWith . '<';
					$replaced = true;
				}
				if ($nestedOpened > 0)
					$nestedOpened--;
			}
			$pos++;
		}

		return $newHtml;
	}

	/** function updateArticleFromXML
	 * Updates the article data from the XML file
	 */
	function updateArticleFromXML($round) {
		// Read the XML into SimpleXML Object
		$journal = & Request::getJournal();
		$articleId = self::getArticleIdFromUrl();
		$externalProcessingDao = & DAORegistry::getDAO('ExternalProcessingDAO');
		// TODO: This line references a file that doesn't exist and needs better reporting of such
		// TODO: The entire path this references is missing. Why?
		// TODO: This path can be generated a lot more cleanly and robust than this
		$xmlPath = Config::getVar('files', 'files_dir') . '/journals/' .
				$journal->getId() . '/articles/' . $articleId . '/externalProcessing/received/' .
				$articleId . '_' . $round . '/article/ojs-article.xml';
		$handle = fopen($xmlPath, "r");

		// If an XML file exists create a SimpleXML object and continue, otherwise return FALSE
		if (file_exists($xmlPath)) {
			$xmlContents = fread($handle, filesize($xmlPath));
			fclose($handle);
			$articleXML = new SimpleXMLElement($xmlContents);
		}
		else
			return FALSE;

		// Update article abstract and title XML
		if (!$externalProcessingDao->updateArticleSettings($articleId, array('abstract' => $articleXML->abstract, 'title' => $articleXML->title, 'cleanTitle' => $articleXML->title)))
			self::trigger_error(PKPLocale::translate('plugins.generic.externalProcessing.xml.import.article.update.failed'), E_USER_WARNING);

		// Update article author names and sequence. The sequence is determined by the order they appear in the XML
		foreach ($articleXML->authors->author as $authorObj) {
			$author = get_object_vars($authorObj);
			static $xmlSeq = 0;
			$author['sequence'] = $xmlSeq;
			$author['articleID'] = $articleId;

			// If the author is there just update the sequence
			// TODO: Make sure all author information is being replaced by XML data, not just additive. This may be done already
			$authorDataObj = $externalProcessingDao->getArticleAuthorData($articleId, $author);
			if (!$authorDataObj->fields('author_id'))
			if (!$externalProcessingDao->updateArticleAuthor(array('sequence' => $authorDataObj->fields('seq'), 'author_id' => $authorDataObj->fields('author_id'))))
				self::trigger_error(PKPLocale::translate('plugins.generic.externalProcessing.xml.import.author.update.failed'), E_USER_WARNING);
			elseif (!$externalProcessingDao->insertArticleAuthor($author))
			self::trigger_error(PKPLocale::translate('plugins.generic.externalProcessing.xml.import.author.insert.failed'), E_USER_WARNING);
			$xmlSeq++;
		}
		// TODO: Remove this line. It's unnecessary since this function should only return FALSE or status and not write to DB directly.
		// self::statusUpdate( 'xmlImport', array('src'=>$xmlPath, 'status'=>TRUE));
		return PKPLocale::translate('plugins.generic.externalProcessing.xml.import.article.update.succeeded');
	}

	// MARK: Archive tools

	/** function archiveHandler
	 * Extracts an archive and recurses through the files to import them into
	 * OJS and prepare them for publishing
	 *
	 * Parameters:
	 *  $file - (string) Archive file
	 *  $path - (string) Path to archive file
	 */
	function archiveHandler($file, $path) {
		// If the directory doesn't exist then decompress the archive, otherwise compare the directory to the archive and extract as needed
		if (!is_dir($path)) {

			// Extract the archive and import the files into OJS writing the result to the db
			//$archiveDecompressResult = self::archiveDecompress($file, $path);
			$archiveDecompressResult = array();

			// Import files in the directory into OJS
			$fileImportResult = self::filesystemToOjs($path);
		} else {

			// Get the list of files from the archive and put it in an array
			ob_start();
			system('unzip -l ' . $file, $sysret);
			$archiveListingRaw = ob_get_contents();
			ob_end_clean();
			$i = 0;
			foreach (explode("\n", $archiveListingRaw) as $record) {
				if (!preg_match('/^\s+\d+\s+\d+-/', $record))
					continue;
				sscanf($record, "%d  %s %s   %s", $size, $date, $time, $filename);
				$archiveListing[$filename] = $size . '::' . $date . ':' . $time;
				$i++;
			}

			// Get the directory listing and compare the archive listing to the directory listing
			$dirListing = self::drillDirectory($path, $path);
			$differences = self::array_xor($archiveListing, $dirListing);

			// If there are differences then extract the needed files
			if (count($differences) > 0) {
				static $archDiffs = array();

				// Rewrite the key names of the files in the differences as values in the archDiffs array
				// TODO: See if this step can be made unnecessary
				foreach ($differences as $file => $value) {
					// If the file exists, add it to the archDiffs array
					if (array_key_exists($file, $archiveListing))
						$archDiffs[] = $file;
				}
				// If there are files listed in the archDiffs array then send the array out to have the specified files decompressed
				if (count($archDiffs) > 0) {
					$archiveDecompressResult = self::archiveDecompress($file, $path, $archDiffs);
					// TODO: This line is probably unnecessary because we do it at the end of the function // $import = self::filesystemToOJS($path);
				}
			}
			self::statusUpdate('archiveDecompress', array('status' => $archiveDecompressResult, 'archiveHandler' => 1));

			// Import files in the directory into OJS
			$fileImportResult = self::filesystemToOjs($path);
		}
	}

	/** function archiveDecompress
	 * Extracts files from a ZIP archive. Extracts all files unless it's
	 * given specific files to take out.
	 *
	 * Parameters:
	 *  $archive - (string) Archive name
	 *  $path - (string) Path to the archive
	 *  (optional) $filesToFetch - (string or array) Specific file or files to extract. Default: NULL
	 */
	function archiveDecompress($archive, $destination, $filesToFetch = NULL) {
		// Make sure the archive exists
		if (file_exists($archive)) {
			// Decompress the proper files
			if ($filesToFetch) {
				if (is_array($filesToFetch))
				foreach ($filesToFetch as $file)
					$toExtract.= $file . ' ';
				else
					$toExtract = $filesToFetch . ' ';
			}
			ob_start();
			system('unzip ' . $archive . ' ' . $toExtract . ' -d ' . $destination, $result);
			ob_end_clean();
		} else
			$result = 9;
		// Report the correct status
		switch ($result) {
			case 0:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => TRUE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionsuccess')));
				return TRUE;
			case 1:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => TRUE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionsuccess')));
				return TRUE;
			case 2:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.genericError')));
				return FALSE;
			case 3:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.badArchive')));
				return FALSE;
			case 4:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.memoryError')));
				return FALSE;
			case 5:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.memoryError')));
				return FALSE;
			case 6:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.memoryErr')));
				return FALSE;
			case 7:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.memoryErr')));
				return FALSE;
			case 9:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.archiveNotFound')));
				return FALSE;
			case 11:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.noMatchFiles')));
				return FALSE;
			case 50:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.diskFull')));
				return FALSE;
			case 51:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.prematureEOA')));
				return FALSE;
			default:
				self::statusUpdate('archiveDecompress', array('src' => $archiveSrc, 'status' => FALSE, 'message' => PKPLocale::translate('plugins.generic.externalProcessing.archive.decompressionfailure.unknown')));
				return FALSE;
		}
	}

	// MARK: File tools

	/**
	 * importCopyedited
	 * @staticvar int $i
	 * @staticvar int $pubFile
	 * @param type $copyeditedDirPath
	 */
	function importCopyedited($articleId, $copyeditedDirPath) {

		$articleDao = & DAORegistry::getDAO('ArticleDAO');
		$galleyDao = & DAORegistry::getDAO('ArticleGalleyDAO');
		$suppfileDao = & DAORegistry::getDAO('SuppFileDAO');

		$articleFileManager = new ArticleFileManager($articleId);
		$articleFileDAO = new SubmissionFileDAO();
		$articleFile = new ArticleFile();

		static $i = 0;
		static $pubFile = 0;
		$articleCopyeditedPath = $copyeditedDirPath;
		$message = '';

		// Get all the files from that copyediting round
		if (!$copyeditedFiles = self::drillDirectory($copyeditedDirPath, $copyeditedDirPath))
			$message .= 'could not import ' . $copyeditedDirPath;


		// Build an array of files to import
		$importFiles = array();
		foreach ($copyeditedFiles as $file => $dateMod) {
			if (stristr($file, $articleCopyeditedPath))
				$importFiles[] = self::getIngestData($file);
			else
				$importFiles[] = self::getIngestData($articleCopyeditedPath . '/' . $file);
		}

		// Import files into OJS
		foreach ($importFiles as $fileInfo) {
			if ($fileInfo['mime']) {
					
				$message .= $articleFileManager->filesDir;

				if (is_file($fileInfo['path'])) {

					// Copy the file into the OJS system
					if (!is_writable($articleFileManager->filesDir))
						return $message . "\nError: " . $articleFileManager->filesDir . " is not writable\n";

					$fileInfo['newFileID'] = $articleFileManager->handleCopy($fileInfo['path'], $fileInfo['mime'], $fileInfo['type']);
					if (!$fileInfo['newFileID'])
						$message .= 'Could not copy ' . $fileInfo['path'] . " into OJS\n" . print_r($fileInfo, 1) . "\n";

					$articleFile = $articleFileDAO->getArticleFile($fileInfo['newFileID']);
					$fileInfo['name'] = $articleFile->getFileName();

					// Set primary files as galleys
					if ($fileInfo['type'] == 'PB') {

						if(strpos($fileInfo['filename'], "-press") != false) {
							continue;
						}

						// Gather article information for the file
						//$articleId = self::getArticleIdFromUrl();
						$article = $articleDao->getArticle($articleId);

						// Create an appropriate galley object
						$fileInfo['fileExt'] = strtolower(array_pop(explode('.', $fileInfo['path'])));
						if (isset($fileInfo['fileExt']) && strstr($fileInfo['fileExt'], 'htm'))
							$galley = &new ArticleHTMLGalley(); // Assume HTML galley
						else
							$galley = &new ArticleGalley();

						// Set the related IDs
						$galley->setArticleId($articleId);
						$galley->setFileId($fileInfo['newFileID']);

						// Make sure there'a a label for the file
						if ($fileInfo['label'] == null) {

							// Generate label based on file type
							$journal = &Request::getJournal();
							if (!$journal) {
								$journalDao = &DAORegistry::getDAO('JournalDAO');
								$journal = $journalDao->getJournal($article->getJournalId());
							}

							$enablePublicGalleyId = $journal->getSetting('enablePublicGalleyId');

							// Import galley differently based on type
							if ($galley->isHTMLGalley()) {
								$galley->setLabel('HTML');
								if ($enablePublicGalleyId)
									$galley->setPublicGalleyId('html' . $fileInfo['labelSuffix']);
							} else if (isset($fileInfo['mime'])) {
								if (strstr($fileInfo['mime'], 'pdf')) {
									$galley->setLabel('PDF');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('pdf' . $fileInfo['labelSuffix']);
								} else if (strstr($fileInfo['mime'], 'postscript')) {
									$galley->setLabel('PostScript');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('ps' . $fileInfo['labelSuffix']);
								} else if (strstr($fileInfo['mime'], 'xml')) {
									$galley->setLabel('XML');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('xml' . $fileInfo['labelSuffix']);
								}
							}

							// If there's no label set one
							if ($galley->getLabel() == null)
								$galley->setLabel(PKPLocale::translate('common.untitled'));
						}
						else
							$galley->setLabel($fileInfo['label']);

						// Set locale
						$galley->setLocale($fileInfo['locale']);

						// Insert galley into OJS
						if (!$galleyDao->insertGalley($galley))
							$message .= 'Could not insert galley for ' . $fileInfo['path'] . "\n";

						$article->galleyId = $galley->getGalleyId();
						$fileInfo['fileId'] = $galley->getGalleyId();

					}
					// Insert Suppfiles
					elseif ($fileInfo['type'] == 'SP') {
							
						static $suppIteration = 1;
						$nameSuffix = '';

						// Get any extra public file name info to include, e.g. "web"
						$suppTitle = ucfirst($fileInfo['label']) . ' ' . $suppIteration;
						$pubId = $fileInfo['label'] . '-' . $articleId . '-' . $suppIteration;

						// If importing an image and it doesn't have -web or -raw then skip it.
						if (stristr($fileInfo['mime'], 'image'))
						if (!stristr(pathinfo($fileInfo['path'], PATHINFO_FILENAME), 'web') && !stristr(pathinfo($fileInfo['path'], PATHINFO_FILENAME), 'raw'))
							continue;

						// Create new supplementary file object
						$suppfile = &new SuppFile();
						$suppfile->setArticleId($articleId);
						$suppfile->setFileId($fileInfo['newFileID']);
						$suppfile->setType($fileInfo['suppType']);
						$suppfile->setTitle($fileInfo['filename'], $fileInfo['locale']); // Localized
						$suppfile->setPublicSuppFileId($fileInfo['filename']);

						// Add suppfile object to the database
						$suppfileDao->insertSuppFile($suppfile);
						$fileInfo['fileId'] = $suppfile->getId();
					}

					// Add record of importing a galley into OJS
					// self::statusUpdate('fileImport', array('status' => '', 'src' => $fileInfo['path'], 'ojsFileId' => $fileInfo['newFileID'], 'galleyId' => $fileInfo['fileId']));
				}

			}
			else
				$message .= 'No mime type found for "' . $file . "\"\n";
		}

		echo $message;
		return $message;
	}

	/**
	 * getIngestData - Gathers the information about a file and generates derivative files if needed
	 *
	 * @param type $filename
	 * @return array
	 */
	function getIngestData($filename) {
		if (!file_exists($filename))
			return false;
		$ingestData = array();
		$i = 0;
		$pathParts = pathinfo($filename);
		static $pubFile = 0;

		$ingestData['path'] = $filename;
		$ingestData['locale'] = PKPLocale::getLocale();

		// Use pathinfo as a quick and easy filename extraction
		$pathParts = pathinfo($filename);
		$ingestData['filename'] = $pathParts['filename'];

		// Gank the revision number from the filename
		$versioning = explode('-', $pathParts['filename']);
		$revision = (array_key_exists(2, $versioning)) ? $versioning[2] : false;
		$ingestData['rev'] = $revision;

		// Grab the mime type and subtype politely if we can and fake it if we can't
		if (file_exists($filename)) {
			$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
			$ingestData['mime'] = finfo_file($fileInfo, $filename);
			finfo_close($fileInfo);
		}
		else
			$ingestData['mime'] = self::fallback_mime_content_type($filename);

		// Assign an OJS type based on path
		if ($name = stristr($filename, 'primary/')) {

			$pubFile++;
			$ingestData['type'] = 'PB';
			$ingestData['label'] = null;
			// If there's any existing primary files (galleys) with the same name than iterate the suffix
			$ingestData['labelSuffix'] = ($pubFile > 1) ? $ingestData['labelSuffix'] = '-' . $pubFile : '';
		} elseif ($name = stristr($filename, 'supplementary')) {

			$ingestData['type'] = 'SP';
			$ingestData['suppType'] = 'Figures';
			$ingestData['label'] = 'figure';
		}
		else{
			$ingestData['type'] = 'ED';
		}

		// Return!
		return $ingestData;
	}

	/** function filesystemToOjs
	 * Imports files in a directory into OJS under the following rules:
	 *  - Files in the 'primary' subdirectory are imported as layout files (LE)
	 *  - Files in the 'supplementary' subdirectory are imported as supplementary files (SP)
	 *  - Files not in one of these directories are not imported
	 */
	function filesystemToOjs($path) {

		$dirList = self::drillDirectory($path, $path);
		$articleFileManager = new ArticleFileManager(self::getArticleIdFromUrl());
		$articleFileDAO = new SubmissionFileDAO();
		$articleFile = new ArticleFile();

		static $i = 0;
		static $pubFile = 0;

		foreach ($dirList as $filename => $dateMod) {

			// Put primary and supplementary files into the OJS system
			if ($name = stristr($filename, 'primary/')) {
				$pubFile++;
				$versioning = explode('-', $name);
				$fileInfo[$i]['rev'] = $versioning[2];
				//$fileInfo[$i]['type'] = 'LE';
				$fileInfo[$i]['type'] = 'PB';
				$fileInfo[$i]['mime'] = self::fallback_mime_content_type($path . '/' . $filename);
				if ($pubFile > 1) {
					$fileInfo[$i]['labelSuffix'] = '-' . $pubFile;
				}
				else
					$fileInfo[$i]['labelSuffix'] = '';
			}
			elseif ($name = stristr($filename, 'supplementary')) {
				$workingSuppfile = $filename;

				// Add in supplementary file data
				$versioning = explode('-', $name);
				$fileInfo[$i]['rev'] = $versioning[2];
				$fileInfo[$i]['type'] = 'SP';
				$fileInfo[$i]['mime'] = self::fallback_mime_content_type($path . '/' . $filename);
			}
			$fileInfo[$i]['path'] = $path . '/' . $filename;
			// If a file has a mime type, import it into OJS, get the OJS file ID,
			// get the file name, and register it in plugin_data
			if ($fileInfo[$i]['mime']) {
				// Import the file into OJS, regardless if it's a suppfile or galley
				if (is_file($fileInfo[$i]['path'])) {

					// Add the file to OJS
					$fileInfo[$i]['locale'] = 'en_US';
					$fileInfo[$i]['newFileID'] = $articleFileManager->handleCopy($file, $fileInfo[$i]['mime'], $fileInfo[$i]['type']);
					$articleFile = $articleFileDAO->getArticleFile($fileInfo[$i]['newFileID']);
					$fileInfo[$i]['name'] = $articleFile->getFileName();


					// MARK: ### GALLEY IMPORT ###
					// Mark galleys as such
					if ($fileInfo[$i]['type'] == 'PB') {
						$fileInfo[$i]['fileExt'] = strtolower(array_pop(explode('.', $filename)));
						$articleDao = & DAORegistry::getDAO('ArticleDAO');
						$article = $articleDao->getArticle($articleId);
						$articleId = self::getArticleIdFromUrl();
						$galleyDao = &DAORegistry::getDAO('ArticleGalleyDAO');

						// ### Imported code ###


						if (isset($fileInfo[$i]['fileExt']) && strstr($fileInfo[$i]['fileExt'], 'htm')) {
							// Assume HTML galley
							$galley = &new ArticleHTMLGalley();
						} else {
							$galley = &new ArticleGalley();
						}

						$galley->setArticleId($articleId);
						$galley->setFileId($fileInfo[$i]['newFileID']);

						if ($fileInfo[$i]['label'] == null) {
							// Generate initial label based on file type
							$journal = & Request::getJournal();
							$enablePublicGalleyId = $journal->getSetting('enablePublicGalleyId');
							if ($galley->isHTMLGalley()) {
								$galley->setLabel('HTML');
								if ($enablePublicGalleyId)
									$galley->setPublicGalleyId('html' . $fileInfo[$i]['labelSuffix']);
							} else if (isset($fileInfo[$i]['mime'])) {
								if (strstr($fileInfo[$i]['mime'], 'pdf')) {
									$galley->setLabel('PDF');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('pdf' . $fileInfo[$i]['labelSuffix']);
								} else if (strstr($fileInfo[$i]['mime'], 'postscript')) {
									$galley->setLabel('PostScript');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('ps' . $fileInfo[$i]['labelSuffix']);
								} else if (strstr($fileInfo[$i]['mime'], 'xml')) {
									$galley->setLabel('XML');
									if ($enablePublicGalleyId)
										$galley->setPublicgalleyId('xml' . $fileInfo[$i]['labelSuffix']);
								}
							}

							if ($galley->getLabel() == null) {
								$galley->setLabel(PKPLocale::translate('common.untitled'));
							}
						} else {
							$galley->setLabel($fileInfo[$i]['label']);
						}
						$galley->setLocale($fileInfo[$i]['locale']);

						// Insert new galley
						$galleyDao->insertGalley($galley);
						$article->galleyId = $galley->getGalleyId();
					}
					// ##### END imported code #####

					$fileInfo[$i]['galleyId'] = print_r($article->galley, 1);

					self::statusUpdate('fileImport', array('status' => '', 'src' => $fileInfo[$i]['path'], 'ojsFileId' => $fileInfo[$i]['newFileID'], 'galleyId' => $fileInfo[$i]['galleyId']));
				}
			}
			$i++;
		}
		return $fileInfo;
	}

	/** function publishArticle
	 * Publishing an article to a specified issue in OJS
	 */
	function publishArticle($articleId, $journalId, $issueId) {
		//$submissionEditHandler = new submissionEditHandler();
		//$submissionEditHandler->scheduleForPublication();
		return FALSE;
	}

	// MARK: Reporting
	/** function displayProcessResults
	* Displays the results of the process attempt
	*/
	function displayProcessResults($sessionId, $data) {
		$externalProcessingDao = & DAORegistry::getDAO('ExternalProcessingDAO');
		$articleFileDao = & DAORegistry::getDAO('SubmissionFileDAO');
		$articleId = self::getArticleIdFromUrl();
		$downloadBaseUrl = Request::url(null, 'editor', 'downloadFile') . '/' . $articleId;

		$importSessionResults = (is_array($externalProcessingDao->getImportResults($sessionId)) ) ? array_merge($externalProcessingDao->getImportSessionInfo($sessionId), $externalProcessingDao->getImportResults($sessionId)) : $externalProcessingDao->getImportSessionInfo($sessionId);

		$sentArchiveURL = $downloadBaseUrl . '/' . $result["file_id"] . $data['sentArchive'];
		$receivedArchiveURL = $downloadBaseUrl . '/' . $data['receivedArchive'];

		$sentArchiveLink = '<a href="' . $sentArchiveURL . '">' . PKPLocale::translate('plugins.generic.externalProcessing.archive.download') . '</a>';
		$receivedArchiveDate = (array_key_exists('receivedArchive', $data)) ? date('m-d-Y', strtotime($data['receivedDate'])) : PKPLocale::translate('plugins.generic.externalProcessing.pending');
		$receivedArchiveLink = (array_key_exists('receivedArchive', $data)) ? '<a href="' . $receivedArchiveURL . '">' . PKPLocale::translate('plugins.generic.externalProcessing.archive.download') . '</a>' : PKPLocale::translate('plugins.generic.externalProcessing.pending');
		// Loop through the values we're reporting on and assign the correct text to them
		$reports = array('sentArchive', 'receivedArchive', 'xmlImportResult');
		foreach ($reports as $item) {
			if (!array_key_exists($item, $data)) {
				$report[$item] = PKPLocale::translate('plugins.generic.externalProcessing.pending');
			} elseif ($item) {

			}

			$item . '<a href="' . $receivedArchiveURL . '">' . PKPLocale::translate('plugins.generic.externalProcessing.archive.download') . '</a>';
		}


		$html .= '
				<div class="round">
				<h4>' . PKPLocale::translate('plugins.generic.externalProcessing.archive.round') . ' ' . $data['venderRound'] . ' </h4>
						<table class="info fancy">
						<tbody>
						<tr>
						<th>
						&nbsp;
						</th>
						<th>
						Date
						</th>
						<th>
						' .
						PKPLocale::translate('plugins.generic.externalProcessing.archive.download')
						. '
								</th>
								</tr>
								<tr>
								<th>
								' .
								PKPLocale::translate('plugins.generic.externalProcessing.archive.sent')
								. '
										</th>
										<td>
										' . date('m-d-Y', strtotime($data['sentDate'])) . '
												</td>
												<td>
												' . $sentArchiveLink . '
														</td>
														</tr>
														<tr>
														<th>
														' .
														PKPLocale::translate('plugins.generic.externalProcessing.archive.received')
														. '
																</th>

																<td>
																' . $receivedArchiveDate . '
																		</td>
																		<td>
																		' . $receivedArchiveLink . '
																				</td>';



		/** TODO: This button held in reserve until we have round selection functionality, probably in v1.1
		 $html .= '
		 <tr>
		 <th colspan="3">
		 <input type="button" value="'.PKPLocale::translate('plugins.generic.externalProcessing.round.process').'" class="button defaultButton">
		 </th>
		 </tr>
		 </tbody>
		 </table>
		 </div>
		 ';
		 * */
		$html .= '
				</tbody>
				</table>
				' . PKPLocale::translate('plugins.generic.externalProcessing.debriefing') . '
						</div>
						';
		return $html;
	}

	/** function statusUpdate
	 * Updates the plugin_data table with statuses of various operations
	 */
	function statusUpdate($statusToUpdate, $data = NULL) {
		if (!class_exists('ExternalProcessingDAO')) {
			import('plugins.generic.externalProcessing.ExternalProcessingDAO');
			$externalProcessingDao = new ExternalProcessingDAO($this->getName());
			DAORegistry::registerDAO('ExternalProcessingDAO', $externalProcessingDao);
		}
		$externalProcessingDao = & DAORegistry::getDAO('ExternalProcessingDAO');

		$venderSessionId = ( $externalProcessingDao->getLastSessionId()) ? $externalProcessingDao->getLastSessionId() : '0';
		if (is_bool($data['status'] || $data['status'] == '')) {
			$data['status'] = ($data['status']) ? 1 : 0;
		}

		// TODO: Fix datetime so it doesn't have quotes around it
		$datetime = $externalProcessingDao->datetimeToDB(time());
		switch ($statusToUpdate) {

			case 'archiveDecompress':
				$actionPrefix = 'archive_decompress';
				$externalProcessingDao->storeData($actionPrefix . '_result', $data['status'], 'bool', 'vender_import_session', $venderSessionId, 'int');
				break;

			case 'createSession':
				$venderSessionId = $venderSessionId + 1;
				$actionPrefix = 'vender_import_session';
				$externalProcessingDao->storeData($actionPrefix, $venderSessionId, 'int', 'src_archive', $data['archive_id'], 'file_id');
				$externalProcessingDao->storeData($actionPrefix . '_date', $datetime, 'datetime', 'vender_import_session', $venderSessionId, 'data_id');
				break;

			case 'fileImport':
				// Store the filename of the attempted import and associate it with the source archive
				// AND store the datetime of the attempt, associating it with the source filename
				$actionPrefix = 'file_import';
				$ojsFileId = (integer) $data['ojsFileId'];
				$pluginDataId = $externalProcessingDao->getDataInsertId();
				$externalProcessingDao->storeData($actionPrefix . '_file_id', $ojsFileId, 'int', 'vender_import_session', $venderSessionId, 'data_id');
				$externalProcessingDao->storeData($actionPrefix . '_galley_id', $data['galleyId'], 'int', 'vender_import_session', $venderSessionId, 'data_id');

				break;

			case 'xmlImport':
				$actionPrefix = 'xml_import';
				$externalProcessingDao->storeData($actionPrefix . '_result', $data['status'], 'bool', 'vender_import_session', $venderSessionId, 'int');
				break;

			case 'report':
				$actionPrefix = 'report';
				$externalProcessingDao->storeData($actionPrefix, $data['type'], 'str', 'details', $data['details']);
				break;

			default:
				$actionPrefix = $statusToUpdate;
				// TODO: Create failsafe for when there's no session ID or the wrong type
				$externalProcessingDao->storeData('default_' . $actionPrefix, print_r($data, true), 'string', 'vender_import_session', $venderSessionId, 'int');
				break;
		}
		return $data;
	}

	// MARK: Archive version switching
	// TODO: Archive version switching
	// MARK: Publishing
	// TODO: Publishing from an archive
	// MARK: Utilities

	/** function drillDirectory
	 * Returns a nested array with the files in a directory, as well as
	 * identifying information about the files
	 *
	 * Parameters:
	 *  $path - Directory path to drill
	 *  $basePath (optional) - Base path to prefix to the other path
	 */
	function drillDirectory($path, $basePath = false) {
		// TODO: switch to using RecursiveDirectoryIterator method
		$ignore = array('cgi-bin', '.', '..');
		$dh = @opendir($path);
		static $i = 0;
		static $files = array();
		while (false !== ( $file = readdir($dh) )) { // Loop through the directory
			if (!in_array($file, $ignore)) {
				// If it's a directory keep drilling down
				if (is_dir($path . '/' . $file)) {
					self::drillDirectory($path . '/' . $file, $basePath);
				}
				// If it's a file then get the data we need
				else {
					if (!function_exists('filemtime'))
						return FALSE;
					$relPath = str_replace($basePath . '/', '', $path);

					$size = filesize($path . '/' . $file);
					$date = date("m-d-y", filemtime($path . '/' . $file));
					$time = date("H:i", filemtime($path . '/' . $file));
					$file = ($relPath) ? $relPath . '/' . $file : $file;
					$files[$file] = $size . '::' . $date . ':' . $time;
					$i++;
				}
			}
		}
		closedir($dh);
		return $files;
	}

	// A function to merge arrays
	function array_xor($array_a, $array_b) {
		$union_array = array_merge($array_a, $array_b);
		$intersect_array = array_intersect($array_a, $array_b);
		return array_diff($union_array, $intersect_array);
	}

	/** function fallback_mime_content_type
	 * Crudely emulates a missing php-native fallback_mime_content_type() function
	 */
	function fallback_mime_content_type($filename) {
		//        if (function_exists('finfo_open')) {
		//            $archiveInfo = finfo_open(FILEINFO_MIME_TYPE);
		//            $mimeType = finfo_file($archiveInfo, $archive);
		//            finfo_close($archiveInfo);
		//            return $mimeType;
		//        } else {

		$mime_types = array(
				'txt' => 'text/plain',
				'htm' => 'text/html',
				'html' => 'text/html',
				'php' => 'text/html',
				'css' => 'text/css',
				'js' => 'application/javascript',
				'json' => 'application/json',
				'xml' => 'application/xml',
				'swf' => 'application/x-shockwave-flash',
				'flv' => 'video/x-flv',
				// images
				'png' => 'image/png',
				'jpe' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'jpg' => 'image/jpeg',
				'gif' => 'image/gif',
				'bmp' => 'image/bmp',
				'ico' => 'image/vnd.microsoft.icon',
				'tiff' => 'image/tiff',
				'tif' => 'image/tiff',
				'svg' => 'image/svg+xml',
				'svgz' => 'image/svg+xml',
				// archives
				'zip' => 'application/zip',
				'rar' => 'application/x-rar-compressed',
				'exe' => 'application/x-msdownload',
				'msi' => 'application/x-msdownload',
				'cab' => 'application/vnd.ms-cab-compressed',
				// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',
		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',
		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		);

		$ext = strtolower(array_pop(explode('.', $filename)));
		if (array_key_exists($ext, $mime_types)) {
			return $mime_types[$ext];
		} elseif (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimetype = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mimetype;
		} else {
			return 'application/octet-stream';
		}
	}

}

?>
