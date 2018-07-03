<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class VenderExtract {

    function dump($archive, $destination) {

        import('plugins.generic.externalProcessing.ImageTransmogrify');
        $transmogrify = new ImageTransmogrify();

        $archiveInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($archiveInfo, $archive);
        finfo_close($archiveInfo);

        // Extract using the proper method for the mime type
        switch ($mimeType) {
            case 'application/zip':
                $zip = new ZipArchive;
                if ($zip->open($archive) === TRUE) {
                    // Get listing
                    for ($i = 0; $i < $zip->numFiles; $i++)
                        $archiveFileList[] = $zip->getNameIndex($i);
                    // Extract all files
                    $zip->extractTo($destination);
                    $zip->close();
                } else
                    return 9;
                break;
            default: return $mimeType;
        }

        $fileList = $archiveFileList;

        // Go through archive files and if any are images process them
        foreach ($archiveFileList as $file) {
            $destination = (strpos($destination, -1) != '/') ? $destination.'/' : $destination;
            // Get mime type of file 
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileMimeType = finfo_file($fileInfo, $destination.$file);
            finfo_close($fileInfo);

            if (stristr($fileMimeType, 'image')) {
                // If the suppfile is an image generate the desired variants and import those
            	if ($transmogrify->actionable($destination.$file)) {
                    if ($newFile = $transmogrify->transformForWeb($destination.$file))
                        $fileList[] = $newFile;
                  //  if ($newFile = $transmogrify->transformForHighRes($destination.$file))
                  //      $fileList[] = $newFile;
                }
            }
        }
        return $fileList;
    }

    /** function archiveHandler
     * Extracts an archive and recurses through the files to import them into
     * OJS and prepare them for publishing
     *
     * Parameters:
     *  $file - (string) Archive file
     *  $path - (string) Path to archive file
     */
    function archiveHandler($file, $path) {
        // Extract the files and import into OJS
        $fileList = self::dump($file, $path);
        $fileImportResult = self::filesystemToOjs($path);

//        
//        $i = 0;
//        foreach (explode("\n", $fileList) as $record) {
//            if (!preg_match('/^\s+\d+\s+\d+-/', $record))
//                continue;
//            sscanf($record, "%d  %s %s   %s", $size, $date, $time, $filename);
//            $archiveListing[$filename] = $size . '::' . $date . ':' . $time;
//            $i++;
//        }
//
//        // Get the directory listing and compare the archive listing to the directory listing
//        $dirListing = self::drillDirectory($path, $path);
//        $differences = self::array_xor($archiveListing, $dirListing);
//
//        // If there are differences then extract the needed files
//        if (count($differences) > 0) {
//            static $archDiffs = array();
//
//            // Rewrite the key names of the files in the differences as values in the archDiffs array
//            // TODO: See if this step can be made unnecessary
//            foreach ($differences as $file => $value) {
//                // If the file exists, add it to the archDiffs array
//                if (array_key_exists($file, $archiveListing))
//                    $archDiffs[] = $file;
//            }
//            // If there are files listed in the archDiffs array then send the array out to have the specified files decompressed
//            if (count($archDiffs) > 0) {
//                $archiveDecompressResult = self::archiveDecompress($file, $path, $archDiffs);
//                // TODO: This line is probably unnecessary because we do it at the end of the function // $import = self::filesystemToOJS($path);
//            }
//        }
//        self::statusUpdate('archiveDecompress', array('status' => $archiveDecompressResult, 'archiveHandler' => 1));
//
//        // Import files in the directory into OJS
//        $fileImportResult = self::filesystemToOjs($path);
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
            system('unzip -l' . $archive . ' ' . $toExtract . ' -d ' . $destination, $result);
            ob_end_clean();
        } else
            $result = 9;
        // Report the correct status
        return $result;
    }

}

?>
