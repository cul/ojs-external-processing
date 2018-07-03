<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class VenderExtract {

    function openArchive($archive) {
        $archiveInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($archiveInfo, $archive) . "\n";
        finfo_close($archiveInfo);
        
        if (!in_array($archiveExt, $supportedTypes)) return false;
        return $mimeType;
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
        // If the directory doesn't exist then decompress the archive, otherwise compare the directory to the archive and extract as needed
        if (!is_dir($path)) {

            // Extract the archive and import the files into OJS writing the result to the db
            $archiveDecompressResult = self::archiveDecompress($file, $path);

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
            system('unzip -l' . $archive . ' ' . $toExtract . ' -d ' . $destination, $result);
            ob_end_clean();
        } else
            $result = 9;
        // Report the correct status
        return $result;
    }

}

?>
