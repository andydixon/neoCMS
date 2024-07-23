<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    if (isset($_SESSION['neoCMSUserid'])) {

        include_once('urldissect.php');
        include_once('ftpstart.php');
        include_once('checkpath.php');

        $message = '<strong>Error:</strong> There was an error restoring your page. Please try again.';

        if (@file_exists($pageurl)) {

            function loadfile($url, $incpath, $inc)
            {

                global $message, $pagepath;

                // read the file

                $doc = fopen($url, "r");
                $content = stream_get_contents($doc);
                fclose($doc);

                $reginclude = '/(?:include\b|include_once\b|require\b|require_once\b)(?:\s*\(*[^;\?>]*)(?:\"|\')([^\"\'<>]*)(?:(\"|\')\)*(;|[\s\t\r\n]*\?>))/i'; // php include regexp
                if (!preg_match($reginclude, $content)) $reginclude = '/(?:<!--#include\s(?:virtual|file)\=)(?:\"|\')([^\"\'<>]*)(?:\"|\')(?:\s*-->)/i'; // SSI regexp

                // read the backup
                $pagename = (strstr($url, $_SERVER['DOCUMENT_ROOT'])) ? explode($_SERVER['DOCUMENT_ROOT'], $url) : explode('../../', $url);
                $pagename = trim($pagename[1], '/');
                $pagename = str_ireplace('/', '-', $pagename);

                $pageext = explode('.', $pagename);
                $pagename = preg_replace('/(\.php|\.asp|\.html|\.htm|\.phtml|\.shtml|\.cfm)/', '', $pagename);
                $backup = dirname(__FILE__) . '/../backups/' . $pagename . '_Bak.' . $pageext[1];

                if (@file_exists($backup)) {

                    $backdoc = fopen($backup, "r");
                    $backcontent = stream_get_contents($backdoc);

                    fclose($backdoc);

                    echo "File Found: $backup\n\n";

                    editfile($content, $backcontent, $url, $incpath, $inc);

                }

                if (preg_match($reginclude, $content)) {

                    preg_match_all($reginclude, $content, $includes);

                    foreach ($includes[1] as &$value) {

                        if (@file_exists($_SERVER['DOCUMENT_ROOT'] . $value) && $value != '') {

                            $incfile = $_SERVER['DOCUMENT_ROOT'] . $value;
                            $incpath = false;

                        } elseif (!stristr($value, 'http://')) {

                            $incfile = "$pagepath/$value";

                            if ($incpath) {

                                $incpathreg = preg_replace('/\//', '\/', $incpath);
                                $incpathreg = '/(^' . $incpathreg . '\/|\/' . $incpathreg . '\/)/';

                                $incorigpath = preg_replace($incpathreg, '', $value, 1);

                                $newinc = "$pagepath/$incpath/$incorigpath";

                                $incfile = (@file_exists($newinc)) ? $newinc : $incfile;

                                $incpatharr = explode('/', $incorigpath);
                                array_pop($incpatharr);
                                $incpath = (sizeof($incpatharr) > 0) ? $incpath . implode('/', $incpatharr) : false;

                            } else {

                                $incpatharr = explode('/', $value);
                                array_pop($incpatharr);
                                $incpath = (sizeof($incpatharr) > 0) ? $incpath . implode('/', $incpatharr) : false;

                            }

                        } else $incpath = false;

                        if (!preg_match('/\/$/', $incfile)) loadfile($incfile, $incpath, true);

                    }

                }
            }

            function editfile($content, $backcontent, $url, $incpath, $inc)
            {

                global $ftpconn, $ftpwd;

                if ($content != $backcontent && ((!stristr($content, '<?php') && !strstr($content, '<%')) || (preg_match('/>[^<]+</', $backcontent) || preg_match('/<html/', $backcontent)) && strstr($backcontent, 'neocms'))) {

                    if ($ftpconn) {

                        $restdoc = tmpfile();
                        if (!$restdoc) {
                            $restdocp = tempnam(dirname(__FILE__) . '/../settings/tmp', 'Rest');
                            chmod($restdocp, 0777);
                            $restdoc = fopen($restdocp, 'w+');
                        }

                        fwrite($restdoc, $backcontent);
                        rewind($restdoc);

                        if (strstr($url, $_SERVER['DOCUMENT_ROOT'])) {

                            $nfkey = explode($_SERVER['DOCUMENT_ROOT'], $url);
                            $nfkey = $nfkey[1];

                            if ($inc) $remotefile = $ftpwd . $nfkey;
                            else {
                                $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                                $filename = explode('../../', $url);
                                $filename = "$filename[1]";

                                $remotefile = $ftpwd . $path . $filename;
                            }

                        } else {

                            $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                            $filename = explode('../../', $url);
                            $filename = "$filename[1]";

                            $remotefile = $ftpwd . $path . $filename;

                        }

                        if (ftp_fput($ftpconn, $remotefile, $restdoc, FTP_BINARY)) echo "Uploaded: $remotefile\n\n";
                        else $message = '<strong>Error:</strong> neocms could not find this page: <em>' . $remotefile . '</em>';

                        fclose($restdoc);
                        if (isset($restdocp)) unlink($restdocp);

                    } elseif (is_writable($url)) {
                        //restores file
                        $restdoc = fopen($url, "w");
                        fputs($restdoc, $backcontent);
                        fclose($restdoc);
                    } else $message = "<strong>Error:</strong> You do not have permission to edit this page. Try <a href='settings/ftpinfo.php' title='Enter your FTP info'>saving your FTP info here</a>.";

                }

            }

            loadfile($pageurl, false, false);

            $message = 'Your page was successfully restored.';
            $script = '<script language="javascript" type="text/javascript">window.top.window.ifrReload();window.top.window.saveFeedback("' . $message . '");</script>';
            echo $script;

        } else {
            $message = '<strong>Error:</strong> neocms could not find this page: <em>' . $url . '</em>';
            $script = '<script language="javascript" type="text/javascript">window.top.window.saveFeedback("' . $message . '");</script>';
            echo $script;
        }

        if ($ftpconn) ftp_close($ftpconn);

    }
}

?>