<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

include_once('createsession.php');

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    if (isset($_SESSION['neoCMSUserid'])) {

        include_once('urldissect.php');
        include_once('ftpstart.php');
        include_once('checkpath.php');

        $message = 'Your page was successfully published.';
        $encode = 'utf-8';
        $doctype = 'xhtml';
        $includes = $conArr = $filecon = array();

        $edit = array();
        $eles = array();

        $inc = 0;
        $count = 0;

        if ($_POST) {

            // begin functions

            include_once('cleaner.php');

            function cleanpost()
            {

                global $edit;

                foreach ($_POST as $key => $post) {

                    $post = thecleaner($post);
                    if (preg_match("/^edit/", $key)) $edit[$key] = $post;

                }

            }

            function loadfile($url, $incpath, $f)
            {

                global $message, $includes, $pagepath, $ftpconn, $ftpwd, $doctype, $encode, $edit;

                $ssi = false;

                $pagename = (strstr($url, $_SERVER['DOCUMENT_ROOT'])) ? explode($_SERVER['DOCUMENT_ROOT'], $url) : explode('../../', $url);
                $pagename = trim($pagename[1], '/');
                $pagename = str_ireplace('/', '-', $pagename);
                $pagefile = $pagename;

                if (@file_exists($url)) {

                    // read the file
                    $doc = fopen($url, "r");
                    $content = stream_get_contents($doc);
                    fclose($doc);

                    if ((!stristr($content, '<?php') && !strstr($content, '<%')) || ((preg_match('/>[^<]+</', $content) || preg_match('/<html/', $content)) && strstr($content, 'neocms'))) {

                        if (preg_match('/<!DOCTYPE[^>]*>/', $content)) {
                            preg_match_all('/<!DOCTYPE[^>]*>/', $content, $dtarr);
                            $doctype = $dtarr[0][0];
                        }
                        if (preg_match('/(?:<meta[^>]*charset=\"*)([^\"<]+)/', $stuff)) {
                            preg_match_all($charreg, $stuff, $enc);
                            $encode = $enc[1][0];
                        }

                        if ($f) cleanpost();

                        $elereg = '/<[^>]*class=(?:\"|\'|)[^>]*(\bneocms\b|\bneocmsRepeatArea\b)[^>]*?(?:\"|\'|)[^>]*>/';
                        $reginclude = '/(?:include\b|include_once\b|require\b|require_once\b)(?:\s*\(*[^;\?>]*)(?:\"|\')([^\"\'<>]*)(?:(\"|\')\)*(;|[\s\t\r\n]*\?>))/i'; // php include regexp
                        if (!preg_match($reginclude, $content)) $reginclude = '/(?:<!--#include\s(?:virtual|file)\=)(?:\"|\')([^\"\'<>]*)(?:\"|\')(?:\s*-->)/i'; // SSI regexp

                        // create a backup
                        $pageext = explode('.', $pagename);
                        $pagename = preg_replace('/(\.php|\.asp|\.html|\.htm|\.phtml|\.shtml|\.cfm)/', '', $pagename);
                        $backup = dirname(__FILE__) . '/../backups/' . $pagename . '_Bak.' . $pageext[1];

                        if ($ftpconn) {

                            $backdoc = tmpfile();
                            if (!$backdoc) {
                                $backdocp = tempnam(dirname(__FILE__) . '/../settings/tmp', 'Back');
                                chmod($backdocp, 0777);
                                $backdoc = fopen($backdocp, 'w+');
                            }

                            fwrite($backdoc, $content);
                            rewind($backdoc);

                            $path = urldissect($_SERVER['SCRIPT_NAME'], false, 2);

                            $remotefile = $ftpwd . $path . 'backups/' . $pagename . '_Bak.' . $pageext[1];

                            echo "Backup File: $remotefile\n\n";

                            if (ftp_fput($ftpconn, $remotefile, $backdoc, FTP_BINARY)) echo "Uploaded Backup: $remotefile!\n\n";
                            else $message = '<strong>Error:</strong> neocms failed creating backup for: <em>' . $remotefile . '</em>';

                            fclose($backdoc);
                            if (isset($backdocp)) unlink($backdocp);

                        } elseif (is_writable($url)) {

                            $backdoc = fopen($backup, "w+");
                            fwrite($backdoc, $content);
                            fclose($backdoc);

                        } else $message = '<strong>Error:</strong> You do not have permission to edit this page: <em>' . $pagefile . "</em>. Try <a href='settings/ftpinfo.php' title='Enter your FTP info'>saving your FTP info here</a>.";

                        // clean up content

                        if (preg_match($reginclude, $content)) {

                            preg_match_all($reginclude, $content, $includes[$url]);

                            $incArr = preg_split($reginclude, $content);
                            $incfiles = $includes[$url][1];
                            $neoCon = array();

                            foreach ($incArr as $k => $v) {

                                $docroot = $_SERVER['DOCUMENT_ROOT'];

                                if (@file_exists($docroot . $incfiles[$k]) && $incfiles[$k] != '') {

                                    $neoCon[$k] = $v;

                                    $incfile = $docroot . $incfiles[$k];
                                    $incpath = false;

                                } elseif (!stristr($incfiles[$k], 'http://')) {

                                    $neoCon[$k] = $v;

                                    $incfile = "$pagepath/$incfiles[$k]";

                                    if ($incpath) {

                                        $incpathreg = str_replace('/', '\/', $incpath);
                                        $incpathreg = '/(^' . $incpathreg . '\/|\/' . $incpathreg . '\/)/';

                                        $incorigpath = preg_replace($incpathreg, '', $incfiles[$k], 1);

                                        $newinc = "$pagepath/$incpath/$incorigpath";

                                        $incfile = (@file_exists($newinc)) ? $newinc : $incfile;

                                        $incpatharr = explode('/', $incorigpath);
                                        array_pop($incpatharr);
                                        $incpath = (sizeof($incpatharr) > 0) ? $incpath . implode('/', $incpatharr) : false;

                                    } else {

                                        $incpatharr = explode('/', $incfiles[$k]);
                                        array_pop($incpatharr);
                                        $incpath = (sizeof($incpatharr) > 0) ? $incpath . implode('/', $incpatharr) : false;

                                    }

                                } else $incpath = false;

                                if (!preg_match('/\/$/', $incfile)) $neoCon[$incfile] = loadfile($incfile, $incpath, false);

                            }

                            return $neoCon;

                        } else return $content;
                    }
                } else return false;

            }

            function output()
            {

                global $message;

                $script = '<script language="javascript" type="text/javascript">window.top.window.saveFeedback("' . $message . '");</script>';
                echo $script;

            }

            function conArray($content)
            {

                $conArray = false;

                foreach ($content as $con) {

                    if (is_array($con)) $conArray = true;

                }

                return conArray;

            }

            function makeEdits($conpost)
            {

                global $edit, $eles, $inc, $count;

                $commreg = '/<!--(.|\n|\r)*?-->/';

                preg_match_all($commreg, $conpost, $comments);

                $conpost = preg_replace($commreg, '<!--!COMMENT!-->', $conpost);

                $elereg = '/<[^>]*class\s*=\s*(?:\"|\'|)[^\"|\']*(\bneocms\b|\bneocmsRepeatArea\b)[^\"|\'|=]*?(?:\"|\'|)[^>]*>/';

                preg_match_all($elereg, $conpost, $neocmseles);

                if ($neocmseles[0]) {

                    $newsplice = '';

                    foreach ($neocmseles[0] as $neocmsele) {

                        $splice = explode($neocmsele, $conpost, 2);

                        $newsplice = $splice[0];

                        $post = $splice[1];

                        $rest = '';

                        preg_match_all("/^<([^>\s]*)\b/i", $neocmsele, $eleTag);

                        $eleTag = $eleTag[1][0];

                        if (stristr($eleTag, 'img')) {

                            if (isset($edit["editImg$count"])) {

                                $editStr = array();

                                $editIncom = $edit["editImg$count"];
                                $editIncom = explode(',', $editIncom);
                                foreach ($editIncom as $eikey => $eipost) {
                                    $eipost = explode('::', $eipost);
                                    $editStr[$eipost[0]] = $eipost[1];
                                }

                                $editContent = '';

                                if (isset($editStr['link']) && $editStr['link'] != '') {

                                    if (preg_match('/(<a[^>]*>)$/', $newsplice)) {

                                        preg_match_all('/(<a[^>]*href=(?:\"|\'))([^\"\s]*)((?:\"|\')[^>]*>)$/', $newsplice, $lastlink);
                                        $newlink = $lastlink[1][0] . $editStr['link'];
                                        if ($editStr['rel'] != '') $newlink .= '" rel="' . $editStr['rel'];
                                        $newlink .= $lastlink[3][0];

                                        $newsplice = preg_replace('/(<a[^>]*>)$/', "$newlink", $newsplice);

                                    } else {

                                        $newsplice .= '<a href="' . $editStr['link'] . '"';
                                        if ($editStr['rel'] != '') $newsplice .= ' rel="' . $editStr['rel'] . '"';
                                        $newsplice .= '>';
                                        $post = '</a>' . $post;

                                    }

                                } else {

                                    if (preg_match('/(<a[^>]*>)$/', $newsplice)) $newsplice = preg_replace('/(<a[^>]*>)$/', '', $newsplice);
                                    if (preg_match('/^(<\/a>)/', $post)) $post = preg_replace('/^(<\/a>)/', '', $post);

                                }

                                $editContent .= '<img src="' . $editStr['src'] . '" alt="' . $editStr['alt'] . '"';
                                if ($editStr['class'] != '') $editContent .= ' class="' . $editStr['class'] . '"';
                                if ($editStr['width'] != '') $editContent .= ' width="' . $editStr['width'] . '"';
                                if ($editStr['height'] != '') $editContent .= ' height="' . $editStr['height'] . '"';
                                $editContent .= '/>';

                            } else $editContent = '';

                            $post = $editContent . $post;

                        } else {

                            $eleTagReg = '/<\/*' . $eleTag . '[^>]*>/i';

                            preg_match_all($eleTagReg, $post, $tags);

                            $contents = preg_split($eleTagReg, $post);

                            $track = 1;

                            foreach ($tags[0] as $tag) {

                                if (strpos($tag, '</') === 0) $track--;
                                else $track++;

                                array_shift($contents);

                                if ($track == 0) break;
                                else array_shift($tags[0]);

                            }

                            if (sizeof($tags[0]) > 1) {

                                foreach ($tags[0] as $tag) {

                                    if (isset($edit["editVid$count"])) $rest .= $contents[0];
                                    else $rest .= $tag . $contents[0];

                                    array_shift($contents);

                                }

                            } else {

                                if (isset($edit["editVid$count"])) $rest = $contents[0];
                                else  $rest = $tags[0][0] . $contents[0];

                            }

                        }

                        $neocmsele = preg_replace('/neocms\b|neocmsRepeatArea\b/', '$0--D0n3--', $neocmsele);

                        if (isset($edit["edit$count"])) $newsplice .= $neocmsele . "\n" . $edit["edit$count"] . "\n" . $rest;
                        elseif (isset($edit["editImg$count"]) && stristr($eleTag, 'img')) $newsplice .= $post;
                        elseif (isset($edit["editVid$count"]) && (stristr($eleTag, 'embed') || stristr($eleTag, 'object'))) $newsplice .= "\n" . $edit["editVid$count"] . "\n" . $rest;
                        elseif (sizeof($splice) <= 1) $newsplice .= $post;
                        else $newsplice .= $neocmsele . $post;

                        $conpost = $newsplice;

                        $count++;

                        $rest = '';
                    }

                    $newsplice = preg_replace('/--D0n3--/', '', $newsplice);

                } else $newsplice = $conpost;

                foreach ($comments[0] as $com) {

                    $newsplice = preg_replace('/<!--!COMMENT!-->/', $com, $newsplice, 1);

                }

                return $newsplice;

            }

            function editFile($content, $url)
            {

                global $message, $includes, $edit, $inc;

                $fileedits = array();

                if (is_array($content)) {

                    foreach ($content as $conkey => $conpost) {

                        if (is_array($conpost)) $content[$conkey] = editFile($conpost, $conkey);
                        else $content[$conkey] = makeEdits($conpost);

                    }

                    foreach ($content as $ckey => $cpost) {

                        if (preg_match('/^(\d+)$/', $ckey)) {

                            if (isset($includes[$url][0][$inc])) $fileedits[$url] .= $cpost . $includes[$url][0][$inc];
                            else $fileedits[$url] .= $cpost;
                            $inc++;
                        } else $fileedits["$ckey"] = $cpost;

                    }
                } else $fileedits[$url] = makeEdits($content);

                $inc = 0;

                return $fileedits;

            }

            function cleanFilecon($files)
            {

                global $filecon;

                foreach ($files as $fkey => $fpost) {

                    if (is_array($fpost)) cleanFilecon($fpost);
                    else {

                        if (!$filecon[$fkey]) $filecon[$fkey] = $fpost;

                    }

                }

            }

            function flatten($ar)
            {
                $toflat = array($ar);
                $res = array();

                while (($r = array_shift($toflat)) !== NULL) {
                    foreach ($r as $v) {
                        if (is_array($v)) {
                            $toflat[] = $v;
                        } else {
                            $res[] = $v;
                        }
                    }
                }

                return $res;
            }

            $conArr = loadfile($pageurl, false, true);

            if (is_array($conArr)) {
                $missing = array_search(false, $conArr);
                if ($conArr[$missing] == '') $missing = false;
            } elseif ($conArr) $missing = false;

            if ($missing === false) {

                cleanFilecon(editFile($conArr, $pageurl));

                if (preg_match('/successfully/', $message)) {

                    foreach ($filecon as $fkey => $fpost) {

                        if ($fpost != '') {

                            if (@file_exists($fkey)) {

                                if ($ftpconn) {

                                    $doc = tmpfile();
                                    if (!$doc) {
                                        $docp = tempnam(dirname(__FILE__) . '/../settings/tmp', 'Doc');
                                        chmod($docp, 0777);
                                        $doc = fopen($docp, 'w+');
                                    }
                                    $remotefile = '';

                                    fwrite($doc, $fpost);
                                    rewind($doc);

                                    $incs = flatten($includes);

                                    if (strstr($fkey, $_SERVER['DOCUMENT_ROOT'])) {

                                        $nfkey = explode($_SERVER['DOCUMENT_ROOT'], $fkey);
                                        $nfkey = $nfkey[1];

                                        if (in_array($nfkey, $incs)) $remotefile = $ftpwd . $nfkey;
                                        else {
                                            $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                                            $fkey = explode('../../', $fkey, 2);

                                            $remotefile = $ftpwd . $path . $fkey[1];
                                        }

                                    } else {

                                        $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                                        $fkey = explode('../../', $fkey, 2);

                                        $remotefile = $ftpwd . $path . $fkey[1];

                                    }

                                    if (ftp_fput($ftpconn, $remotefile, $doc, FTP_BINARY)) echo "Uploaded: $remotefile!\n\n";
                                    else $message = '<strong>Error:</strong> neocms publish this page: <em>' . $remotefile . '</em>';

                                    fclose($doc);
                                    if (isset($docp)) unlink($docp);

                                } elseif (is_writable($fkey)) {

                                    $doc = fopen($fkey, "w");
                                    fwrite($doc, $fpost);
                                    fclose($doc);

                                } else $message = "<strong>Error:</strong> You do not have permission to edit this page.";

                            } else $message = '<strong>Error:</strong> neocms is unable find this page.';

                        }

                    }
                }

            } else $message = '<strong>Error:</strong> neocms is unable find this page: <em>' . $missing . '</em>';

            output();

        } else output();

        if ($ftpconn) ftp_close($ftpconn);
    }
} else {
    $message = '<strong>Error:</strong> neocms is unable find your session.';
    output();
}

?>
