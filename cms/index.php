<?php
/** Server-rendered shell for the authenticated NeoCMS administration application. */

// Load configuration before initialising sessions and role-aware controls.
require_once "config.php";

// Resolve project classes without requiring Composer.
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "./src/{$classPath}.php";
});

use NeoCMS\Authentication;
use NeoCMS\SecurityHeaders;

// Prepare the session values required by both the rendered shell and JavaScript client.
$authentication = new Authentication($config['authentication'] ?? [], $config['roles'] ?? [], $config['security'] ?? []);
$csrfToken = $authentication->getCsrfToken();
// Only a single valid CSS class is allowed; malformed values fall back safely.
$editableClass = $config['editableClass'] ?? 'editable';
if (!is_string($editableClass) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $editableClass)) {
    $editableClass = 'editable';
}

// Keep the administration shell private by redirecting anonymous requests to login.
if (!$authentication->isLoggedIn()) {
    header("Location: /cms/login/");
    exit;
}
SecurityHeaders::html(true, isset($config['security']['cookieSecure']) ? (bool) $config['security']['cookieSecure'] : null);
?>

<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>NeoCMS</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="neo-editable-class" content="<?php echo htmlspecialchars($editableClass, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha384-fgGyf7Mo7DURSOMnOy7ed+dkq5Job205Gnzu6QIg0BOHKaqt4D76Dt8VlDCzcMHV" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.14.2/jquery-ui.min.js" integrity="sha384-tBcEcHGtNy7/Mx08+YxuvQ6v6s0N2jgehtFiT+bLtGwTj/txXtB/L5GqXfggm5sS" crossorigin="anonymous"></script>
    <script src="/cms/tinymce/tinymce.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/base/jquery-ui.css" integrity="sha384-pUvA/6DQjteMxpaV6uGxZ1QuYrFLJgrLMvBWf06VcJIg6ky/Y5m3UZJlrv11V1I+" crossorigin="anonymous">
    <link rel="stylesheet" href="/cms/css/editor.css">
</head>
<body data-role="<?php echo htmlspecialchars($authentication->getRole(), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Status messages sit outside the flex layout so they may overlay the full viewport. -->
<div id="message-bar" style="display:none;"></div>
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
                <button class="headerButton manage-only" id="newPage">New Page</button>
                <button class="headerButton" id="selectPage">Pages</button>
                <button class="headerButton" id="mediaButton">Media</button>
                <button class="headerButton" id="seoButton">SEO</button>
                <button class="headerButton" id="moreButton">Tools</button>
            </div>
            <div class="toolbar-row publish-controls">
                <button class="headerButton" id="saveDraft">Save Draft</button>
                <button class="headerButton publish-only" id="scheduleButton">Schedule</button>
                <button class="headerButton dangerButton publish-only" id="savePage">Publish</button>
                <button class="headerButton viewport-button" data-width="100%">Desktop</button>
                <button class="headerButton viewport-button" data-width="768px">Tablet</button>
                <button class="headerButton viewport-button" data-width="390px">Mobile</button>
            </div>
        </div>
        <div class="loggedInDetails">
            <?php echo htmlspecialchars($authentication->getLoggedInUser(), ENT_QUOTES, 'UTF-8'); ?>
            (<?php echo htmlspecialchars($authentication->getRole(), ENT_QUOTES, 'UTF-8'); ?>)<br/>
            <button id="logoutButton" class="link-button">Log out</button><br/>
            <?php
            if (!is_writable("logs/")) {
                echo "<span class='redText'>Make /cms/logs/ writable.</span>";
            }
            ?>
        </div>
    </div>
    <iframe id="frameContainer" src="<?php echo $config['skipWelcomePage'] ? "/" : "welcome.html"; ?>"
            class="frame" sandbox="allow-same-origin"></iframe>
</div>
<!-- TinyMCE content editor. Saving here changes the preview, not the public page. -->
<div id="editModal" class="cms-dialog" title="Edit Content">
    <textarea id="editor"></textarea>
    <div class="editor-actions">
        <button id="saveBtn" type="button">Save</button>
        <button id="closeEditorBtn" type="button">Close</button>
    </div>
</div>

<!-- Template and target path controls for administrator page creation. -->
<div id="newPageDialog" title="Create a New Page">
    <div class="newpage-content">
        <form id="newPageForm">
            <div id="radioList">
                <!-- JavaScript populates available filesystem templates here. -->
            </div>
            <label for="filename">New Filename:</label>
            <input type="text" id="filename" name="filename" placeholder="Enter new filename" required>

            <button type="submit">Submit</button>
        </form>
    </div>
</div>

<!-- Searchable page picker with role-dependent management actions. -->
<div id="fileListDialog" title="Select an Existing Page">
    <div class="filelist-content">
        <input id="pageSearch" type="search" placeholder="Search pages">
        <ul id="fileList">
            <!-- JavaScript populates discovered editable pages here. -->
        </ul>
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
        <button id="menusButton" class="manage-only">Navigation menus</button>
    </div>
</div>

<!-- Reusable uploaded-image browser and metadata editor. -->
<div id="mediaDialog" class="cms-dialog" title="Media Library">
    <p>Select an image to insert it into the open editable region.</p>
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
    <form id="sharedForm">
        <label>Block name<input id="sharedKey" type="text" pattern="[A-Za-z0-9_-]+" required></label>
        <label>HTML content<textarea id="sharedContent" rows="8" required></textarea></label>
        <button type="submit">Save and update every page</button>
    </form>
    <div id="sharedList"></div>
</div>

<!-- Line-oriented menu editor supporting an optional parent label for nesting. -->
<div id="menusDialog" class="cms-dialog" title="Navigation Menus">
    <form id="menuForm">
        <label>Menu name<input id="menuName" type="text" pattern="[A-Za-z0-9_-]+" required></label>
        <label>One item per line: Label | URL | Optional parent label<textarea id="menuItems" rows="9" placeholder="Home | /&#10;About | /about.html&#10;Team | /team.html | About" required></textarea></label>
        <button type="submit">Save menu</button>
    </form>
    <div id="menuList"></div>
</div>

<script src="/cms/js/cms.js"></script>

</body>
</html>
