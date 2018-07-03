<?php

	/**
	 * @file ExternalProcessingDAO.inc.php
	 *
	 * Copyright (c) 2003-2010 John Willinsky
	 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
	 *
	 * @class ExternalProcessingDAO
	 * @ingroup plugins_generic_externalProcessing	 *
	 * @brief Extended DAO methods for interactions with third-party editing
	 * NB: These require access to a number of hooks in ArticleGalleyDAO
	 * to override the default methods; this is prime for refactoring!
	 */
	
	// $Id$
	
	
	import('classes.article.ArticleDAO');
	
	
	class ExternalProcessingDAO extends DAO {
		/** @var $parentPluginName string Name of parent plugin */
		var $parentPluginName;
		
		/**
		 * Constructor
		 */
		function ExternalProcessingDAO($parentPluginName) {
			$this->parentPluginName = $parentPluginName;
			parent::__construct();
		}
		
		function updateArticleSettings($articleId, $articleSettings){
			$updateArticleSettingsSQL = "UPDATE article_settings SET setting_value = CASE setting_name WHEN 'abstract' THEN ? WHEN 'title' THEN ? WHEN 'cleanTitle' THEN ? END WHERE article_id = ?";
			return $this->update( $updateArticleSettingsSQL, array( $articleSettings['abstract'], $articleSettings['title'], $articleSettings['cleanTitle'], $articleId ) );
		}
		
		function getDateAccepted($articleId){
			$sql ="select min(date_decided) as accepted_date from edit_decisions where article_id = " . $articleId . " and decision = 1";
			$dbResult = $this->retrieve( $sql );
			$acceptedDate = $dbResult->fields['accepted_date'];
			return $acceptedDate;
		}

		function getArticleAuthorData($articleId, $authorInfo) {
			$getArticleAuthorSQL = "SELECT author_id, primary_contact, seq FROM authors WHERE `submission_id` = ? AND `first_name` = ? AND `middle_name` = ? AND `last_name` = ? AND `email` = ?";
			return $this->retrieve( $getArticleAuthorSQL, array( $articleId, $authorInfo['firstName'], $authorInfo['middleName'], $authorInfo['lastName'], $authorInfo['email'] ) );
		}
		
		function updateArticleAuthor($authorInfo) {
			$updateAuthorSQL = 'UPDATE authors SET `seq` = ? WHERE `author_id` = ?';
			return $this->update( $updateAuthorSQL, array( $authorInfo['sequence'], $authorInfo['author_id'] ));
		}
		
		function insertArticleAuthor($authorInfo){
			$insertAuthorSQL = 'INSERT INTO authors(`submission_id`,`seq`,`first_name`,`middle_name`,`last_name`,`email`) 
			VALUES(?, ?, ?, ?, ?, ?)';
			return $this->update( $insertAuthorSQL, array($authorInfo['articleID'], $authorInfo['sequence'],$authorInfo['firstName'],$authorInfo['middleName'],$authorInfo['lastName'],$authorInfo['email']) );
		}
		
		/** function storeData 
		  * Writes data to the plugin_data table
		*/
		function storeData($data_name, $data_val, $data_type, $assoc_name = NULL, $assoc_val = NULL, $assoc_type = NULL, $journal_id = NULL, $locale = 'en_us') {
			$storeDataSQL = 'INSERT INTO plugin_data(`plugin_name`,`locale`,`journal_id`,`data_name`,`data_val`, `data_type`, `assoc_name`, `assoc_val`, `assoc_type`) 
			VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)';
			return $this->update( $storeDataSQL, array( 
				'externalProcessingPlugin',
				$locale, 
				$journal_id, 
				$data_name, 
				$data_val, 
				$data_type, 
				$assoc_name, 
				$assoc_val, 
				$assoc_type ) );
		}
		
		/** function getData 
		 * Reads data from the plugin_data table
		 */
		function readData($dataName){
			// TODO: Change all of this to read data generically
			$getArticleAuthorSQL = "SELECT locale, data_name, data_val, data_type, assoc_name, assoc_val, assoc_type FROM plugin_data WHERE `data_name` = ?";
			return $this->retrieve( $getArticleAuthorSQL, array( $dataName ) );
		}
		
		/** function getArchiveStatus 
		 * Gets session id of the most recent import session
		 */
		function archiveProcessed($archiveFileId){
			$sql = "SELECT `assoc_val`
			FROM  `plugin_data` 
			WHERE  `data_name` =  'vender_import_session'
			AND `assoc_name` = 'src_archive'
			AND `assoc_val` = ".$archiveFileId."
			LIMIT 1";
			$archiveStatusResult = $this->retrieve( $sql );
			$archiveStatus = $archiveStatusResult->fields[0];

			return $archiveStatus;
		}
		
		/** function getLastSessionId 
		 * Gets session id of the most recent import session
		 */
		function getLastSessionId(){
			$sql = "SELECT MAX( CONVERT( `data_val`, SIGNED ) ) 
			FROM  `plugin_data` 
			WHERE  `data_name` =  'vender_import_session'
			LIMIT 1";
			$return = $this->retrieve( $sql );
			$lastSessionId = (int) $return->fields[0];
			//$lastSessionId = ($f - 1);
			return $lastSessionId;
		}
		
		/** function getImportSessionInfo 
		 * Gets the info about an import session
		 */
		function getImportSessionInfo($importSessionId){
			$importSessionSql = "SELECT `data_name`, `data_val`, `assoc_name`, `assoc_val` 
			FROM  `plugin_data` 
			WHERE  `data_name` =  'vender_import_session'
			AND `data_val` = '". $importSessionId ."'
			LIMIT 1";
			$importSessionResult = $this->retrieve( $importSessionSql );
			$importSessionInfo[$importSessionResult->fields['data_name']] = $importSessionResult->fields['data_val'];
			$importSessionInfo[$importSessionResult->fields['assoc_name']] = $importSessionResult->fields['assoc_val'];
			return $importSessionInfo;
		}
		
		/** function getImportResults
		 * Gets the results from a specified import session
		 */
		function getImportResults($importSessionId){
			
			$dataSql = "SELECT `data_name`, `data_val` 
			FROM `plugin_data` 
			WHERE `assoc_name` = 'vender_import_session' 
			AND `assoc_val` = '". $importSessionId ."' 
			AND `plugin_name` = 'externalProcessingPlugin'
			ORDER BY `data_id` ASC";
			
			$dataSqlResults = $this->retrieve( $dataSql );
			while (!$dataSqlResults->EOF) {

				// If we already have a record for a key but have more results with the same name convert the key into an array
				if(is_array($importResults) && array_key_exists($dataSqlResults->fields['data_name'],$importResults) ){
					// Convert the string into an array
					if(!is_array($importResults[$dataSqlResults->fields['data_name']]) ){
						$importResults[$dataSqlResults->fields['data_name']] = array($importResults[$dataSqlResults->fields['data_name']]);
					}

					$importResults[$dataSqlResults->fields['data_name']][] = $dataSqlResults->fields['data_val'];
					while ( array_key_exists($key, $importResults[$dataSqlResults->fields['data_name']]) ) {
						static $i = 1;
						$key = $i++;
					}
					// TODO: Right now this throws a warning of trying to modify
					// property of a non-object. This will need to be fixed.
					$importResults->fields['data_name'][$key] = $dataSqlResults->fields['data_name'];
					unset($i);

				}
				else {
					//$importResults = array();
					$importResults[$dataSqlResults->fields['data_name']] = $dataSqlResults->fields['data_val'];
				}
				$dataSqlResults->MoveNext();
			}	
			return $importResults;
		}		
		

		/** function getDataInsertId 
		 * Gets the ID of the last written record in the plugin_data table
		 */
		function getDataInsertId() {
			return $this->getInsertId('plugin_data', 'data_id');
		}
		
	}
	
	?>
