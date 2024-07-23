<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    if (isset($_SESSION['neoCMSUserid'])) {

        $pageurl = $_SERVER['HTTP_REFERER']; //incoming page url

        if (stristr($pageurl, $_SERVER['HTTP_HOST'])) {

            $type = $_GET['type'];

            if ($type == 'img') $files = 'bmp,gif,jpeg,jpg,png';
            elseif ($type == 'pages') $files = 'html,htm,php,cfm,phtml,shtml,asp';
            else $files = 'psd,pdf,swf,sit,tar,tgz,zip,gzip,bmp,gif,jpeg,jpg,jpe,png,txt,doc,docx,xl,xls,flv,mov,qt,mpg,mpeg,mp3,aiff,aif,aac,wav,ppt,rtf,html,shtml,htm,php,cfm,phtml,asp';

            $files = explode(',', $files);

            $pattern = '/(';

            foreach ($files as $file) {

                $pattern .= '\.' . $file . '|';

            }

            $matches = array();
            $includes = array();

            $pattern = trim($pattern, '|') . ')/i';

            function findfiles($path, $pattern)
            {

                global $matches, $pathdefault, $type;

                $path = rtrim(str_replace("\\", "/", $path), '/') . '/';

                $entries = array();
                $dir = dir($path);
                $neocmspath = "/\/" . $_SESSION['neoCMSPath'] . "\//";

                while (false !== ($entry = $dir->read())) {

                    if (!preg_match($neocmspath, $path)) $entries[] = $entry;

                }

                $dir->close();

                foreach ($entries as $entry) {

                    $fullname = $path . $entry;
                    $pathname = str_replace('../..', '', $path);

                    $exFolders = preg_replace('/,\s*/', '/|', $_SESSION['neoCMSExFolders']);
                    $exFolders = str_replace('-', '\-', $exFolders);
                    $exFolders = str_replace('.', '\.', $exFolders);
                    $exFolders = '/' . str_replace('/', '\/', $exFolders) . '\//';

                    if ($entry != '.' && $entry != '..' && is_dir($fullname) && is_readable($fullname) && (!preg_match($exFolders, $fullname) || $_SESSION['neoCMSExFolders'] == '')) findfiles($fullname, $pattern);
                    elseif ($entry != '.' && $entry != '..' && is_file($fullname) && preg_match($pattern, $entry)) {

                        if (!in_array($pathname, $matches)) {

                            if (preg_match('/\.html|\.htm|\.php|\.cfm|\.phtml|\.shtml|\.asp/', $fullname)) {

                                $content = file_get_contents($fullname);

                                if (checkContent($content)) $matches[] = $fullname;

                            } else $matches[] = $fullname;

                        }

                    }

                }

            }

            function checkContent($content)
            {

                global $type;

                if (stristr($content, '<?php') || stristr($content, '<%')) {

                    if (((!stristr($content, '<?php') && !strstr($content, '<%')) || (preg_match('/>[^<]+</', $content) || (preg_match('/<html/', $content))) || $type == 'pages')) {
                        if (strstr($content, 'neocms')) return true;
                        else return false;
                    } else return false;

                } elseif (preg_match('/<html/', $content) || $type == 'pages') {
                    if (strstr($content, 'neocms')) return true;
                }

            }

            function siteURL()
            {
                $purl = 'http';
                $req = explode('/' . $_SESSION['neoCMSPath'] . '/', $_SERVER["REQUEST_URI"]);
                $req = $req[0];

                if ($_SERVER["HTTPS"] == "on") $purl .= "s";

                $purl .= "://";

                if ($_SERVER["SERVER_PORT"] != "80") $purl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . "$req/";
                else $purl .= $_SERVER["SERVER_NAME"] . "$req/";

                return strtolower($purl);
            }

            findfiles('../../', $pattern);
            sort($matches);

            $includes = array_unique($includes);

            $pathreg = "/\/$pathdefault\//";
            $imghtml = '';
            $filehtml = '';
            $pagehtml = '';

            if (sizeof($matches) > 0) {

                foreach ($matches as $match) {

                    $fname = str_replace('../../', '', $match);
                    $flink = siteURL() . $fname;

                    if (preg_match('/\.bmp|\.gif|\.jpeg|\.jpg|\.png/i', $fname)) {

                        if (!strstr($imghtml, 'neoCMSImgUL')) $imghtml = '<ul id="neoCMSImgUL" class="clearfix"></ul>';

                        if (($_SESSION['neoCMSImagePath'] == null || $_SESSION['neoCMSImagePath'] == '') || strstr($fname, $_SESSION['neoCMSImagePath'])) $imghtml = str_replace('</ul>', '<li><div class="mask"><img src="' . $flink . '" alt="' . $fname . ' Thumbnail"/></div><p>' . $fname . '</p></li></ul>', $imghtml);

                    } elseif (preg_match('/\.html|\.htm|\.php|\.phtml|\.cfm|\.shtml|\.asp/i', $fname)) {

                        if (!strstr($pagehtml, 'neoCMSPagesUL')) $pagehtml = '<ul id="neoCMSPagesUL" class="clearfix"></ul>';

                        $pagehtml = str_replace('</ul>', '<li href="' . $flink . '">' . $fname . '</li></ul>', $pagehtml);

                    } else {

                        if (!strstr($filehtml, 'neoCMSFileUL')) $filehtml = '<ul id="neoCMSFileUL" class="clearfix"></ul>';

                        if (($_SESSION['neoCMSFilePath'] == null || $_SESSION['neoCMSFilePath'] == '') || strstr($fname, $_SESSION['neoCMSFilePath'])) $filehtml = str_replace('</ul>', '<li href="' . $flink . '">' . $fname . '</li></ul>', $filehtml);

                    }

                }

                $matches = $imghtml . $pagehtml . $filehtml;

                $script = "<script language='javascript' type='text/javascript'>window.top.window.fileOption('" . addslashes($matches) . "');</script>";
                echo $script;

            } else {

                $script = '<script language="javascript" type="text/javascript">window.top.window.fileOption(\'<ul class="clearfix"><li>No Results Found</li></ul>\');</script>';
                echo $script;

            }

            $endscript = "<script language='javascript' type='text/javascript'>window.top.window.destroyFileLoader('$type');</script>";
            echo $endscript;

        }

    }

}

?>