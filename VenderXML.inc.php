<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ExportXML
 *
 * @author cdrsdesign
 */
class VenderXML {

    function build($articleId, $journalId, $sendArticleFields, $reviewRound, $sendArticleFields = NULL, $suppFileData) {
        $articleDao = & DAORegistry::getDAO('ArticleDAO');
        $article = $articleDao->getArticle($articleId, $journalId);
        $editorDAO = & DAORegistry::getDAO('EditAssignmentDAO');
        $editor = $editorDAO->getEditAssignmentsByArticleId($articleId);
        $sectionDAO = & DAORegistry::getDAO('SectionDAO');
        $sectionData = $editorDAO->getEditAssignmentsByArticleId($articleId);
        $dateSubmitted = strtotime($article->getDateSubmitted());
        // TODO: Get authorfile for appropriate file
        $externalProcessingDao = & DAORegistry::getDAO('ExternalProcessingDAO');
        $articleDateAccepted = strtotime($externalProcessingDao->getDateAccepted($articleId));

        // Prepare the requested fields
        if ($sendArticleFields) {
            foreach ($sendArticleFields as $sendArticleField) {
                $sendArticleField = trim($sendArticleField);
                switch ($sendArticleField) {
                    // Ignore fields if they're being included anyway
                    case 'title': break;
                    case 'id': break;
                    case 'authors': break;
                    case 'abstract': break;
                    default:
                    $reqFields[$sendArticleField] = ExternalProcessingPlugin::getXmlArticleData($article, $sendArticleField);
                    break;
                }
            }
        }

        // Create a DOMDocument for the XML
        $implementation = new DOMImplementation();
        $dtd = $implementation->createDocumentType('root [<!ENTITY nbsp "&#160;">]');
        $doc = $implementation->createDocument('', '', $dtd);
        //$doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://dtd.nlm.nih.gov/publishing/3.0/index.cgi?show=./journalpublishing3.dtd', 'article');
        $root->setAttribute('xml:lang', Locale::getLocale());
        $doc->appendChild($root);

        // Prepare the front matter
        $front = $doc->createElement('front');
        $articleMeta = $doc->createElement('article-meta');

        // Article ID
        $articleId = $doc->createElement('article-id', $articleId);
        $articleIdType = $doc->createAttribute('pub-id-type');
        $articleIdType->value = 'tohmid';
        $articleId->appendChild($articleIdType);
        $articleMeta->appendChild($articleId);
        
        //DOI
        $docDoi= $doc->createElement('doi', $article->getStoredDOI());
        $articleMeta->appendChild($docDoi);

        // Journal Section
        $categories = $doc->createElement('article-categories');
        $fyi = $doc->createComment('Section information provided for external processing internal reference');
        $categories->appendChild($fyi);
        $catSubjectGroup = $doc->createElement('subj-group');
        $catSubjectName = $doc->createElement('subject', $article->_data['sectionTitle']);
        $catSubjectGroup->appendChild($catSubjectName);
        $categories->appendChild($catSubjectGroup);
        $articleMeta->appendChild($categories);

        // Article title
        $titleGroup = $doc->createElement('title-group');
        $fyi = $doc->createComment('Title to be updated by external processing');
        $titleGroup->appendChild($fyi);
        $articleTitle = $doc->createElement('article-title', $article->getTitle(Locale::getLocale()));
        $titleGroup->appendChild($articleTitle);
        $articleMeta->appendChild($titleGroup);

        // Article contributors (authors, editors, etc.)
        $contribGroup = $doc->createElement('contrib-group');

        // Authors
        $fyi = $doc->createComment('Author names to be updated by external processing');
        $titleGroup->appendChild($fyi);
        
        VenderXML::createAuthors($article, $doc, $contribGroup);        

        $fyi = $doc->createComment('Editor information provided for external processing internal reference');
        $articleMeta->appendChild($fyi);
        // Editor
        $contrib = $doc->createElement('contrib');
        $contribType = $doc->createAttribute('contrib-type');

        $contribType->value = 'editor';
        $contrib->appendChild($fyi);
        $contrib->appendChild($contribType);
        $contribName = $doc->createElement('name');
        $contribSurname = $doc->createElement('surname', $editor->records->fields['last_name']);
        $contribGivenNames = $doc->createElement('given-names', $editor->records->fields['first_name']);
        $contribBio = $doc->createElement('bio');
        $contribBioEmail = $doc->createElement('email', $editor->records->fields['email']);
        $contribName->appendChild($contribSurname);
        $contribName->appendChild($contribGivenNames);
        $contrib->appendChild($contribName);
        $contribBio->appendChild($contribBioEmail);
        $contrib->appendChild($contribBio);
        $contribGroup->appendChild($contrib);
        $articleMeta->appendChild($contribGroup);

        // Add the review round to a custom meta element
        $customMeta = $doc->createElement('custom-meta');
        $customMetaRound = $doc->createElement('meta-name', 'round');
        $customMetaRoundVal = $doc->createElement('meta-value', $reviewRound);
        $customMeta->appendChild($customMetaRound);
        $customMeta->appendChild($customMetaRoundVal);
        $articleMeta->appendChild($customMeta);

        // Build the article history

        $history = $doc->createElement('history');
        $fyi = $doc->createComment('Received and accepted information provided for external processing internal reference');
        $history->appendChild($fyi);

        $dateReceived = $doc->createElement('date');
        $dateReceivedAttr = $doc->createAttribute('date-type');
        $dateReceivedAttr->value = 'received';
        $dateReceived->appendChild($dateReceivedAttr);
        $dateRecDay = $doc->createElement('day', date("d", $dateSubmitted));
        $dateRecMo = $doc->createElement('month', date("m", $dateSubmitted));
        $dateRecYear = $doc->createElement('year', date("Y", $dateSubmitted));

        $dateReceived->appendChild($dateRecDay);
        $dateReceived->appendChild($dateRecMo);
        $dateReceived->appendChild($dateRecYear);

        $dateAccepted = $doc->createElement('date');
        $dateAcceptedAttr = $doc->createAttribute('date-type');
        $dateAcceptedAttr->value = 'accepted';
        $dateAccepted->appendChild($dateAcceptedAttr);
        $dateAccDay = $doc->createElement('day', date("d", $articleDateAccepted));
        $dateAccMo = $doc->createElement('month', date("m", $articleDateAccepted));
        $dateAccYear = $doc->createElement('year', date("Y", $articleDateAccepted));

        $dateAccepted->appendChild($dateAccDay);
        $dateAccepted->appendChild($dateAccMo);
        $dateAccepted->appendChild($dateAccYear);

        $history->appendChild($dateReceived);
        $history->appendChild($dateAccepted);
        $articleMeta->appendChild($history);

        // Article abstract
        $articleAbstract = $doc->createElement('abstract', $article->getArticleAbstract());
        $fyi = $doc->createComment('Abstract to be updated by external processing');
        $articleAbstract->appendChild($fyi);
        $articleMeta->appendChild($articleAbstract);

        // Add requested fields into the DOMDocument
        if (is_array($reqFields)) {
            foreach ($reqFields as $fieldName => $fieldVal) {
                $reqElement = $doc->createElement($fieldName, $fieldVal);
                $articleMeta->appendChild($reqElement);
            }
        }

        // Add in supplementary material
        if (is_array($suppFileData)) {
            $body = $doc->createElement('body');
            $fyi = $doc->createComment('Supplementary file information provided for external processing internal reference');
            $body->appendChild($fyi);
            foreach ($suppFileData as $suppFile) {
                $suppMaterial = $doc->createElement('supplementary-material');
                $suppMaterialHref = $doc->createAttribute('xlink:href');
                $suppMaterialHref->value = $suppFile['href'];
                $suppMaterial->appendChild($suppMaterialHref);

                $suppLabel = $doc->createElement('supplementary-material', $suppFile['label']);
                $suppMaterial->appendChild($suppLabel);

                $suppCaption = $doc->createElement('supplementary-material', $suppFile['caption']);
                $suppMaterial->appendChild($suppCaption);
                $body->appendChild($suppMaterial);
            }
        }


        // Pull it all together
        $front->appendChild($articleMeta);
        $root->appendChild($front);
        if ($body)
            $root->appendChild($body);
        
        return $doc->saveXML();
    }
    
    function createAuthors(&$article, &$doc, &$contribGroup){

        foreach ($article->getAuthors() as $author) {
            $contrib = $doc->createElement('contrib');
            
            $contribType = $doc->createAttribute('contrib-type');
            $contribType->value = 'author';
            $contrib->appendChild($contribType);
            
            $authorId = $doc->createAttribute('id');
            $authorId->value = $author->_data['id'];
            $contrib->appendChild($authorId);
            
            $contribName = $doc->createElement('name');
            $contribSurname = $doc->createElement('surname', trim($author->_data['lastName']));
            $middleName = $doc->createElement('initials', trim($author->_data['middleName']));
            $contribGivenNames = $doc->createElement('given-names', trim($author->_data['firstName']));
            $contribName->appendChild($contribSurname);
            $contribName->appendChild($middleName);
            $contribName->appendChild($contribGivenNames);
            
            $contribBio = $doc->createElement('bio');
            $contribBioEmail = $doc->createElement('email', $author->_data['email']);
            $contrib->appendChild($contribName);
            $contribBio->appendChild($contribBioEmail);
            $contrib->appendChild($contribBio);
            
            $affiliatons = $doc->createElement('aff', $author->getLocalizedAffiliation());

            $contrib->appendChild($affiliatons);

            $contribGroup->appendChild($contrib);
        }
    }
    

} // ================================================== //

?>