<?php

class VenderFTP {

    public static function ftpSend($host, $user, $password, $remoteDirectory, $localFilePath, $saveAsFileName) {
        $conn = ftp_connect($host);
        if (ftp_login($conn, $user, $password)) {
            if ($remoteDirectory != null && $remoteDirectory != "")
                if (ftp_chdir($conn, $remoteDirectory) === FALSE)
                    throw new Exception("Could not chdir to ftp://" . $host . ':' . $remoteDirectory);
            ftp_pasv($conn, TRUE);
            if (ftp_put($conn, $saveAsFileName, $localFilePath, FTP_BINARY))
                return TRUE;
            else
                throw new Exception("Could not upload files to ftp://" . $host);
        }

        throw new Exception("Could not login to ftp://" . $host);
        return FALSE;
    }

    public static function ftpFindRemote($host, $user, $password, $remoteDirectory, $fileNamePattern, $saveToDirectory) {
        if (!is_dir($saveToDirectory))
            return FALSE;
        $conn = ftp_connect($host);


        if (ftp_login($conn, $user, $password)) {
            ftp_pasv($conn, 1);
            if ($remoteDirectory != null && $remoteDirectory != "") {
                ftp_chdir($conn, $remoteDirectory);
            }

//            $items = ftp_nlist($conn, ".");
            $items = ftp_nlist($conn, '.'); // Use current directory "." instead of full path so ftp_get can function
            if (!$items)
                return FALSE;
            else {
                foreach ($items as $item) {
                    if ($fileNamePattern == $item) {
                        if (!ftp_get($conn, $saveToDirectory . $item, $item, FTP_BINARY))
                            return FALSE;
                        else {
                            //ftp_delete($conn, $item);
                            return $item;
                        }
                    }
                }
            }
        }
    }

}
