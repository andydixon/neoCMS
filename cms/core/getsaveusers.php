<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    if (isset($_SESSION['neoCMSUserid'])) {

        $userproxy = 'https://www.neogate.com/licencing/users-serv.php?';

        if ($_POST && $_POST['neoCMSAcctUser'] && $_POST['neoCMSAcctEmail'] && $_POST['neoCMSAcctUser-orig'] && $_POST['neoCMSAcctEmail-orig']) {

            include_once('getpath.php');

            $usergets = 'neoCMSUsername=' . urlencode($_SESSION['neoCMSUserid']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path) . '&neoCMSAcctUser=' . urlencode($_POST['neoCMSAcctUser']) . '&neoCMSAcctEmail=' . urlencode($_POST['neoCMSAcctEmail']) . '&neoCMSOrigEmail=' . urlencode($_POST['neoCMSAcctEmail-orig']) . '&neoCMSOrigUser=' . urlencode($_POST['neoCMSAcctUser-orig']);

            if ($_POST['neoCMSAcctName']) $usergets .= '&neoCMSAcctName=' . urlencode($_POST['neoCMSAcctName']);
            if ($_POST['neoCMSAcctPass'] != '' && $_POST['neoCMSAcctPassConfirm'] != '') $usergets .= '&neoCMSAcctPass=' . urlencode($_POST['neoCMSAcctPass']) . '&neoCMSAcctPassConfirm=' . urlencode($_POST['neoCMSAcctPassConfirm']);

            header("Location:$userproxy$usergets");

        } elseif ($_POST && $_POST['neoCMSNewAcctUser'] && $_POST['neoCMSNewAcctEmail'] && $_POST['neoCMSNewAcctName'] && $_POST['neoCMSNewAcctPass'] && $_POST['neoCMSNewAcctPassConfirm']) {

            include_once('getpath.php');

            $usergets = 'neoCMSUsername=' . urlencode($_SESSION['neoCMSUserid']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path) . '&neoCMSNewAcctUser=' . urlencode($_POST['neoCMSNewAcctUser']) . '&neoCMSNewAcctEmail=' . urlencode($_POST['neoCMSNewAcctEmail']) . '&neoCMSNewAcctName=' . urlencode($_POST['neoCMSNewAcctName']) . '&neoCMSNewAcctPass=' . urlencode($_POST['neoCMSNewAcctPass']) . '&neoCMSNewAcctPassConfirm=' . urlencode($_POST['neoCMSNewAcctPassConfirm']);

            header("Location:$userproxy$usergets");

        } elseif (empty($_GET)) {

            include_once('getpath.php');

            $usergets = 'neoCMSUsername=' . urlencode($_SESSION['neoCMSUserid']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path);

            header("Location:$userproxy$usergets");

        } elseif ($_GET && $_GET['neoCMSDelUser']) {

            include_once('getpath.php');

            $usergets = 'neoCMSUsername=' . urlencode($_SESSION['neoCMSUserid']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path) . '&neoCMSDelUser=' . urlencode($_GET['neoCMSDelUser']);

            header("Location:$userproxy$usergets");

        } elseif ($_GET && $_GET['neoCMSSess'] == $_SESSION['neoCMSSess']) {

            $admin = array();
            $users = array();

            foreach ($_GET as $key => $val) {

                if (strstr($key, '-A')) $admin[str_replace('-A', '', $key)] = $val;
                elseif (!strstr($key, 'neoCMSSess') && !strstr($key, 'neoCMSUserMessage')) {

                    $num = explode('-', $key);
                    $users[$num[1]][$num[0]] = $val;

                }

            }

            $adminscript = '<script type="text/javascript">window.top.window.adminUser("' . $admin['neoCMSName'] . '", "' . $admin['neoCMSUsername'] . '", "' . $admin['neoCMSEmail'] . '");</script>';
            echo $adminscript;

            foreach ($users as $user) {

                $userscript = '<script type="text/javascript">window.top.window.buildUser("' . $user['neoCMSName'] . '", "' . $user['neoCMSUsername'] . '", "' . $user['neoCMSEmail'] . '", "' . $user['neoCMSId'] . '");</script>';
                echo $userscript;

            }

            if ($_GET['neoCMSUserMessage'] != '0') {

                $m = $_GET['neoCMSUserMessage'];

                if ($m == '1') $getmessage = "Your account settings were successfully saved.";
                elseif ($m == '2') $getmessage = "Your new account has been successfully created.";
                elseif ($m == '3') $getmessage = "<strong>Error:</strong> This account has already been created.";
                elseif ($m == '4') $getmessage = "Account successfully deleted.";
                elseif ($m == '5') $getmessage = "<strong>Error:</strong> There was an error deleting this account.";
                else $getmessage = "<strong>Error:</strong> There was an error saving.";

                $savescript = '<script type="text/javascript">window.top.window.userBar("' . $getmessage . '");</script>';
                echo $savescript;

                $_SESSION['neoCMSUserid'] = $admin['neoCMSUsername'];
            }

            echo '<script type="text/javascript">window.top.window.removeFrames();</script>';
        }
    }
}

?>