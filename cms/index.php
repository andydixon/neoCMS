<?php
/** Server-rendered shell for the authenticated NeoCMS administration application. */

// Load configuration before initialising sessions and role-aware controls.
require_once __DIR__ . '/bootstrap.php';

use NeoCMS\Authentication;
use NeoCMS\SecurityHeaders;

// Prepare the session values required by both the rendered shell and JavaScript client.
$authentication = new Authentication(...\NeoCMS\UserStore::authArgs($config));
$csrfToken = $authentication->getCsrfToken();
// Only a single valid CSS class is allowed; malformed values fall back safely.
$editableClass = $config['editableClass'] ?? 'editable';
if (!is_string($editableClass) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $editableClass)) {
    $editableClass = 'editable';
}

// Keep the administration shell private by redirecting anonymous requests to login.
if (!$authentication->isLoggedIn()) {
    header("Location: " . $config['basePath'] . "/cms/login/");
    exit;
}
SecurityHeaders::html(true, isset($config['security']['cookieSecure']) ? (bool) $config['security']['cookieSecure'] : null);
?>

<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>NeoCMS</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="neo-base-path" content="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="neo-editable-class" content="<?php echo htmlspecialchars($editableClass, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha384-fgGyf7Mo7DURSOMnOy7ed+dkq5Job205Gnzu6QIg0BOHKaqt4D76Dt8VlDCzcMHV" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.14.2/jquery-ui.min.js" integrity="sha384-tBcEcHGtNy7/Mx08+YxuvQ6v6s0N2jgehtFiT+bLtGwTj/txXtB/L5GqXfggm5sS" crossorigin="anonymous"></script>
    <script src="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>/cms/tinymce/tinymce.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/base/jquery-ui.css" integrity="sha384-pUvA/6DQjteMxpaV6uGxZ1QuYrFLJgrLMvBWf06VcJIg6ky/Y5m3UZJlrv11V1I+" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>/cms/css/editor.css">
</head>
<body data-role="<?php echo htmlspecialchars($authentication->getRole(), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Status messages sit outside the flex layout so they may overlay the full viewport. -->
<div id="message-bar" role="status" aria-live="polite" hidden>
    <span class="snack-icon" aria-hidden="true"></span>
    <span class="snack-text"></span>
    <button type="button" class="snack-close" aria-label="Dismiss message">&times;</button>
</div>
<div class="pageContainer">
    <div class="controls">
        <div class="logo"></div>
        <?php if ($config['showFullUrl']) {?>
        <div id="urlbox"></div>
        <?php } ?>
        <!-- Primary content tools and publication controls are grouped into two wrapping rows. -->
        <div class="buttonContainer">
            <div class="toolbar-row">
                <button class="headerButton" id="dashboardButton">Dashboard</button>
                <button class="headerButton" id="selectPage">Pages</button>
                <button class="headerButton" id="mediaButton">Media</button>
                <button class="headerButton" id="seoButton">SEO</button>
                <button class="headerButton manage-only" id="menusButton">Navigation Menus</button>
                <button class="headerButton" id="moreButton">Tools</button>
            </div>
            <div class="toolbar-row publish-controls">
                <button class="headerButton viewport-button active" data-width="100%" title="Desktop view" aria-label="Desktop view" aria-pressed="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="19" height="12.5" rx="1.5"/><path d="M8 20.5h8M12 16.5v4"/></svg></button>
                <button class="headerButton viewport-button" data-width="768px" title="Tablet view" aria-label="Tablet view" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2.5" width="14" height="19" rx="2"/><path d="M11 18.5h2"/></svg></button>
                <button class="headerButton viewport-button" data-width="390px" title="Mobile view" aria-label="Mobile view" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/></svg></button>
            </div>
        </div>
        <div class="loggedInDetails">
            <span id="whoami"><?php $profile = \NeoCMS\UserStore::fromConfig($config)->profile($authentication->getLoggedInUser()); echo htmlspecialchars($profile['name'] !== '' ? $profile['name'] : $authentication->getLoggedInUser(), ENT_QUOTES, 'UTF-8'); ?></span>
            (<?php echo htmlspecialchars($authentication->getRole(), ENT_QUOTES, 'UTF-8'); ?>)<br/>
            <button id="logoutButton" class="link-button">Log out</button><br/>
            <?php
            if (!is_writable("logs/")) {
                echo "<span class='redText'>Make /cms/logs/ writable.</span>";
            }
            ?>
        </div>
    </div>
    <iframe id="frameContainer" src="<?php echo $config['skipWelcomePage'] ? htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8') . "/" : "welcome.html"; ?>"
            class="frame" sandbox="allow-same-origin"></iframe>
</div>
<!-- TinyMCE content editor. Save Draft keeps changes private; Schedule Publish and Publish Now act on the whole page. -->
<div id="editModal" class="cms-dialog" title="Edit Content">
    <textarea id="editor"></textarea>
    <div class="editor-actions">
        <button id="saveBtn" type="button" title="Applies your changes and saves them as a private draft">Save Draft</button>
        <button id="scheduleBtn" type="button" class="publish-only" title="Publishes this page automatically at a time you choose">Schedule Publish</button>
        <button id="publishBtn" type="button" class="publish-only publish-now" title="Replaces the live page now; a revision of the old page is kept">Publish Now</button>
        <button id="closeEditorBtn" type="button">Cancel</button>
    </div>
</div>

<!-- Searchable page picker with role-dependent management actions. -->
<div id="fileListDialog" title="Pages">
    <div class="filelist-content">
        <input id="pageSearch" type="search" placeholder="Search pages" aria-label="Search pages">
        <div class="table-wrap">
            <table id="fileList" class="data-table">
                <thead>
                    <tr><th>Page</th><th>Title</th><th>Last modified</th><th class="manage-col">Actions</th></tr>
                </thead>
                <tbody>
                    <!-- JavaScript populates discovered editable pages here. -->
                </tbody>
            </table>
            <p id="pageListEmpty" class="scan-note" hidden>No pages match your search.</p>
        </div>
    </div>
    <!-- New page wizard: template, name, navigation group. Administrators only. -->
    <div id="newPageSection" class="manage-only">
        <h3 class="dialog-section">New page</h3>
        <div class="newpage-content">
            <form id="newPageForm">
                <p id="newPageStep" class="step-indicator"></p>
                <section data-step="1">
                    <h3 class="dialog-section">Choose a template</h3>
                    <div id="radioList">
                        <!-- JavaScript populates available filesystem templates here. -->
                    </div>
                    <div class="step-actions"><button type="button" class="step-next">Next</button></div>
                </section>
                <section data-step="2" hidden>
                    <h3 class="dialog-section">Name your page</h3>
                    <label for="pageName">Page name</label>
                    <input type="text" id="pageName" maxlength="80" placeholder="For example: About us" autocomplete="off">
                    <p class="scan-note">Used as the page title and the navigation link text. The filename is created for you.</p>
                    <div class="step-actions"><button type="button" class="step-back">Back</button><button type="button" class="step-next">Next</button></div>
                </section>
                <section data-step="3" hidden>
                    <h3 class="dialog-section">Choose a navigation group</h3>
                    <div id="menuChoice">
                        <!-- JavaScript lists the saved navigation menus here. -->
                    </div>
                    <p class="scan-note">The page is saved as a private draft. It appears on the site, and in this menu, only once you publish it.</p>
                    <div class="step-actions"><button type="button" class="step-back">Back</button><button type="submit">Save</button></div>
                </section>
            </form>
        </div>
    </div>
</div>

<!-- Operational overview: pending work, schedules, activity, and filesystem warnings. -->
<div id="dashboardDialog" class="cms-dialog" title="Dashboard"><div id="dashboardContent"></div></div>

<!-- Secondary authoring and site-wide management tools. -->
<div id="toolsDialog" class="cms-dialog" title="Tools">
    <div class="tool-grid">
        <button id="revisionsButton">Revision history</button>
        <button id="accessibilityButton">Accessibility check</button>
        <button id="sharedButton" class="manage-only">Shared content</button>
        <button id="siteScanButton" class="manage-only">Site scan</button>
        <button id="usersButton">Users</button>
    </div>
</div>

<!-- Reusable uploaded-image browser and metadata editor. -->
<div id="mediaDialog" class="cms-dialog" title="Media Library">
    <div class="media-toolbar">
        <button type="button" id="mediaUploadButton">Upload files</button>
        <input type="file" id="mediaFile" multiple hidden accept="<?php echo htmlspecialchars('.' . implode(',.', \NeoCMS\MediaTypes::extensions()), ENT_QUOTES, 'UTF-8'); ?>">
        <div id="mediaTabs" class="media-tabs" role="tablist" aria-label="Media categories"></div>
    </div>
    <p id="mediaHint" class="scan-note"></p>
    <div id="mediaUploadReport" class="scan-note" role="status" aria-live="polite"></div>
    <div id="mediaList" class="media-grid"></div>
</div>

<!-- Page history and publisher-only restoration controls. -->
<div id="revisionsDialog" class="cms-dialog" title="Revision History"><div id="revisionsList"></div></div>

<!-- In-document title, search metadata, canonical URL, and robots settings. -->
<div id="seoDialog" class="cms-dialog" title="SEO and Page Settings">
    <form id="seoForm">
        <label>Page title<input id="seoTitle" type="text" required></label>
        <label>Meta description<textarea id="seoDescription"></textarea></label>
        <label>Canonical URL<input id="seoCanonical" type="url"></label>
        <label>Open Graph image<input id="seoImage" type="text" placeholder="/uploads/image.jpg"></label>
        <label><input id="seoNoIndex" type="checkbox"> Ask search engines not to index this page</label>
        <button type="submit">Apply settings</button>
    </form>
</div>

<!-- Future publication time is entered locally and submitted as an absolute timestamp. -->
<div id="scheduleDialog" class="cms-dialog" title="Schedule Publication">
    <form id="scheduleForm">
        <label>Publish date and time<input id="publishAt" type="datetime-local" required></label>
        <button type="submit">Schedule</button>
    </form>
</div>

<!-- Results from the browser-side authoring accessibility checks. -->
<div id="accessibilityDialog" class="cms-dialog" title="Accessibility Check"><div id="accessibilityResults"></div></div>

<!-- Site-wide shared-region registry and propagation form. -->
<div id="sharedDialog" class="cms-dialog" title="Shared Content">
    <div id="sharedList"></div>
    <form id="sharedForm">
        <label>Block name<input id="sharedKey" type="text" pattern="[A-Za-z0-9_-]+" required></label>
        <label>HTML content<textarea id="sharedContent" rows="8" required></textarea></label>
        <button type="submit">Save and update every page</button>
    </form>
</div>

<!-- Line-oriented menu editor supporting an optional parent label for nesting. -->
<div id="menusDialog" class="cms-dialog" title="Navigation Menus">
    <div id="menuList"></div>
    <p class="scan-note"><button id="menuScanButton" type="button">Scan site for new navigation</button> Finds navigation added to any page since the last scan.</p>
    <form id="menuForm" hidden>
        <h3 class="dialog-section">Editing menu: <code id="menuKey"></code></h3>
        <input id="menuName" type="hidden">
        <label>Menu name<input id="menuTitle" type="text" maxlength="60" placeholder="Display name"></label>
        <label>One item per line: Label | URL | Optional parent label<textarea id="menuItems" rows="9" placeholder="Home | /&#10;About | /about.html&#10;Team | /team.html | About" required></textarea></label>
        <button type="submit">Save menu</button>
    </form>
</div>

<!-- Own account (everyone), role descriptions, and account management (administrators only). -->
<div id="usersDialog" class="cms-dialog" title="Users">
    <h3 class="dialog-section">My account</h3>
    <form id="profileForm" autocomplete="off">
        <label>Login name <span class="scan-note">(used to sign in; set by an administrator)</span><input id="profileLogin" type="text" readonly></label>
        <label>Display name<input id="profileName" type="text" maxlength="80" required autocomplete="name"></label>
        <label>Email address <span class="scan-note">(contact details only; not used to sign in)</span><input id="profileEmail" type="email" maxlength="254" autocomplete="email"></label>
        <label>Current password <span class="scan-note">(only needed to change the email address)</span><input id="profileCurrent" type="password" maxlength="4096" autocomplete="current-password"></label>
        <button type="submit">Save details</button>
    </form>
    <form id="passwordForm" autocomplete="off">
        <h3 class="dialog-section">Change password</h3>
        <label>Current password<input id="pwCurrent" type="password" maxlength="4096" required autocomplete="current-password"></label>
        <label>New password <span class="scan-note">(12 to 72 characters)</span><input id="pwNew" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
        <label>Confirm new password<input id="pwConfirm" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
        <button type="submit">Change password</button>
    </form>
    <p id="passwordManaged" class="scan-note" hidden>Your password is managed in the site configuration (config.local.php) and cannot be changed here.</p>

    <h3 class="dialog-section">Roles</h3>
    <div id="rolesList"></div>

    <div id="userAdmin" class="manage-only">
        <h3 class="dialog-section">User accounts</h3>
        <div id="userList"></div>
        <p class="scan-note"><button id="userAddButton" type="button">Add user</button> <button id="userInviteButton" type="button">Invite user</button> Accounts marked Config are managed in config.local.php and cannot be changed here.</p>
        <form id="userForm" autocomplete="off" hidden>
            <h3 class="dialog-section" id="userFormTitle"></h3>
            <input id="userExisting" type="hidden">
            <label id="userUsernameRow">Login name <span class="scan-note">(3 to 32 letters, numbers, . - _)</span><input id="userUsername" type="text" maxlength="32" autocomplete="off"></label>
            <label>Display name<input id="userName" type="text" maxlength="80" required autocomplete="off"></label>
            <label>Email address <span class="scan-note">(contact details only; not used to sign in)</span><input id="userEmail" type="email" maxlength="254" autocomplete="off"></label>
            <label>Role<select id="userRole"><option value="editor">Editor</option><option value="administrator">Administrator</option></select></label>
            <label id="userPasswordRow">Password <span class="scan-note" id="userPasswordNote"></span><input id="userPassword" type="password" maxlength="72" autocomplete="new-password"></label>
            <label>Your password <span class="scan-note">(to confirm this change)</span><input id="userConfirm" type="password" maxlength="4096" required autocomplete="current-password"></label>
            <button type="submit" id="userSubmit">Save user</button> <button type="button" id="userCancel">Cancel</button>
        </form>
        <form id="confirmBox" autocomplete="off" hidden>
            <h3 class="dialog-section" id="confirmTitle"></h3>
            <label>Your password<input id="confirmPassword" type="password" maxlength="4096" required autocomplete="current-password"></label>
            <button type="submit">Confirm</button> <button type="button" id="confirmCancel">Cancel</button>
        </form>
        <div id="inviteResult" hidden>
            <h3 class="dialog-section">Invitation link</h3>
            <p class="scan-note">Send this link to the person. It is shown only once, works once, and expires in 7 days. Their login name is <strong id="inviteLogin"></strong>.</p>
            <input id="inviteLink" type="text" readonly> <button type="button" id="inviteCopy">Copy link</button>
        </div>
    </div>
</div>
<!-- Whole-site analysis with one-click tagging of content regions, images, and SEO tags. Administrators only. -->
<div id="siteScanDialog" class="cms-dialog" title="Site Scan">
    <div id="siteScanProgress" hidden>
        <progress id="scanProgressBar" max="100" value="0"></progress>
        <div id="scanProgressText" role="status" aria-live="polite"></div>
    </div>
    <div id="siteScanSummary"></div>
    <fieldset id="siteScanOptions">
        <legend>Changes to make</legend>
        <label><input type="checkbox" id="scanContent" checked> Make content areas editable</label>
        <label><input type="checkbox" id="scanImages" checked> Make images editable</label>
        <label><input type="checkbox" id="scanSeo" checked> Add missing SEO tags (never overwrites existing ones)</label>
        <label><input type="checkbox" id="scanMenus" checked> Detect navigation menus (fills Navigation Menus; never overwrites a saved menu)</label>
    </fieldset>
    <div id="siteScanPages"></div>
    <p class="scan-note">Each changed page keeps a revision, so this can be undone from Revision history.</p>
    <button id="siteScanApply" type="button">Apply to selected pages</button>
</div>

<!-- Replace or describe an image that sits outside an editable region. -->
<div id="imageDialog" class="cms-dialog" title="Edit Image">
    <div id="imagePicker" class="media-grid"></div>
    <form id="imageForm">
        <label>Image address<input id="imageSrc" type="text" required></label>
        <label>Alternative text<input id="imageAlt" type="text"></label>
        <button type="submit">Apply image</button>
    </form>
</div>

<script src="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>/cms/js/cms.js"></script>

</body>
</html>
