/**
 * NeoCMS administration client.
 *
 * The public page is loaded into a same-origin iframe, edited in memory, and only written to disk
 * through authenticated API calls. Keeping that distinction explicit prevents a modal's Save
 * button from accidentally becoming a rather enthusiastic Publish button.
 */
(function ($) {
    'use strict';

    // References to the active iframe document and currently selected editable element.
    let iframeDoc = null;
    let currentElement = null;
    let currentImage = null;

    // Dirty state protects unpublished browser changes from accidental navigation.
    let dirty = false;

    // Permissions are replaced by the authoritative dashboard response after initialisation.
    let permissions = {draft: true, publish: false, schedule: false, manage: false};

    // Remember offered drafts so an iframe rewrite does not prompt for the same draft twice.
    const loadedDrafts = new Set();

    // True once the page shown has been saved as a server-side draft (cleared by any navigation).
    let draftSaved = false;

    // Path of the page shown in the preview. Writing a draft into the frame (document.open) makes the frame's own URL
    // become the admin page's, so the real page path is captured when the page loads instead.
    let framePath = '/';

    // Set while a saved draft is written into the preview, so its load event is not treated as navigation.
    let writingDraft = false;

    // URL of a preview page that was reloaded to bypass the browser cache; its next load is the fresh one.
    let revalidatedHref = null;

    // Server-rendered values keep selectors and mutating requests aligned with configuration.
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
    const editableClass = $('meta[name="neo-editable-class"]').attr('content') || 'editable';
    const editableSelector = '.' + editableClass;
    // A styled box standing in for a photo: role="img" on something other than an <img> or <svg>.
    const placeholderSelector = '[role="img"]:not(img):not(svg):not([aria-hidden="true"])';
    // URL prefix of the site root (empty at a domain root, "/neocms" for http://localhost/neocms/).
    const basePath = $('meta[name="neo-base-path"]').attr('content') || '';

    // Initialise controls after the administration document is ready.
    $(function () {
        initialiseDialogs();
        bindToolbar();
        loadDashboard(false);
        $('#frameContainer').on('load', initialiseFrame);

        // Browsers display their own standard warning text for beforeunload events.
        window.addEventListener('beforeunload', function (event) {
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    });

    /**
     * Call one controller action and convert failed JSON responses into ordinary Error objects.
     * POST requests automatically include the session CSRF token.
     */
    function api(action, data, method) {
        const payload = Object.assign({}, data || {}, {action: action});
        if ((method || 'GET') === 'POST') {
            payload.csrf_token = csrfToken;
        }
        return $.ajax({url: basePath + '/cms/controller/', method: method || 'GET', data: payload, dataType: 'json'})
            .catch(function (xhr) {
                const response = xhr.responseJSON || {};
                throw new Error(response.error || 'The CMS request failed');
            });
    }

    /** Configure all jQuery UI dialogues once, leaving individual workflows to populate them. */
    function initialiseDialogs() {
        $('.cms-dialog, #newPageDialog, #fileListDialog').hide();
        $('.cms-dialog, #newPageDialog, #fileListDialog').not('#editModal').each(function () {
            $(this).dialog({autoOpen: false, modal: true, width: Math.min(760, window.innerWidth - 30)});
        });
        // The page list is a wide table, so it gets a wider dialogue than the other tools.
        $('#fileListDialog').dialog('option', 'width', Math.min(1000, window.innerWidth - 30));
        $('#editModal').dialog({
            autoOpen: false,
            modal: true,
            width: Math.min(900, window.innerWidth - 30),
            open: initialiseEditor,
            close: destroyEditor
        });
    }

    /** Connect toolbar controls and forms to their workflow handlers. */
    function bindToolbar() {
        $('#dashboardButton').on('click', function () { loadDashboard(true); });
        $('#newPage').on('click', openNewPage);
        $('#selectPage').on('click', openPages);
        $('#mediaButton').on('click', openMedia);
        $('#seoButton').on('click', openSeo);
        $('#moreButton').on('click', function () { $('#toolsDialog').dialog('open'); });
        $('#revisionsButton').on('click', openRevisions);
        $('#accessibilityButton').on('click', runAccessibilityCheck);
        $('#sharedButton').on('click', openShared);
        $('#menusButton').on('click', openMenus);
        $('#siteScanButton').on('click', openSiteScan);
        $('#siteScanApply').on('click', applySiteScan);
        $('#imageForm').on('submit', applyImage);
        $('#seoForm').on('submit', applySeo);
        $('#scheduleForm').on('submit', schedulePage);
        $('#sharedForm').on('submit', saveShared);
        $('#menuForm').on('submit', saveMenu);
        $('#newPageForm').on('submit', createPage);
        $('#pageSearch').on('input', filterPages);
        $('#logoutButton').on('click', logout);
        $('#closeEditorBtn').on('click', function () { $('#editModal').dialog('close'); });
        $('.viewport-button').on('click', function () {
            $('#frameContainer').css({width: $(this).data('width'), margin: '0 auto'});
        });
    }

    /**
     * Bind editing behaviour whenever the preview iframe loads a page.
     * Namespaced events are removed first so repeated navigation cannot multiply handlers.
     */
    function initialiseFrame() {
        const iframe = document.getElementById('frameContainer');
        // Static pages carry no cache headers, so a browser may reuse a stale copy (for example after the whole site was
        // replaced). Reload each newly opened page once, which revalidates it; skip when edits would be discarded.
        const href = iframe.contentWindow.location.href;
        // The load caused by writing a saved draft into the frame is not a navigation: keep the draft's content and state.
        const draftWrite = writingDraft;
        writingDraft = false;
        if (!dirty && !draftWrite && href !== 'about:blank' && revalidatedHref !== href) {
            revalidatedHref = href;
            iframe.contentWindow.location.reload();
            return;
        }
        revalidatedHref = null;
        if (!draftWrite) {
            draftSaved = false;
            framePath = iframe.contentWindow.location.pathname;
        }
        iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        currentElement = null;

        $(iframeDoc).off('.neocms');
        // Editable regions open TinyMCE; generated block controls retain their own click behaviour.
        $(iframeDoc).on('click.neocms', editableSelector, function (event) {
            if ($(event.target).closest('.button-container').length) return;
            event.preventDefault();
            event.stopPropagation();
            // Clicking an image inside a region edits just that image; the rest of the region opens the content editor.
            const image = $(event.target).closest('img');
            const placeholder = image.length ? $() : imagePlaceholder(event.target);
            if (image.length) openImageEditor(image);
            else if (placeholder.length) openImageEditor(placeholder);
            else openEditor($(this));
        });
        // Internal page navigation receives the same unpublished-change protection as the window.
        $(iframeDoc).on('click.neocms', 'a', function (event) {
            if (dirty && !window.confirm('Leave this page and discard unpublished changes?')) {
                event.preventDefault();
            }
        });
        $(iframeDoc).on('click.neocms', '.duplicate-before, .duplicate-after', duplicateBlock);
        $(iframeDoc).on('click.neocms', '.delete-block', deleteBlock);
        // Images marked by the site scan open the image dialogue, unless an editable region owns them.
        // Editor-only cue that images are clickable; a removable <style> keeps it out of saved pages.
        $('<style id="neo-editor-style">').text(
            'img[data-neo-image],' + placeholderSelector + '[data-neo-image]{cursor:pointer;outline:2px dashed #4a90d9;outline-offset:2px}'
            + editableSelector + ' img,' + editableSelector + ' ' + placeholderSelector + '{cursor:pointer}'
            + editableSelector + ' img:hover,' + editableSelector + ' ' + placeholderSelector + ':hover{outline:2px dashed #4a90d9;outline-offset:2px}'
        ).appendTo(iframeDoc.head || iframeDoc.documentElement);
        $(iframeDoc).on('click.neocms', 'img[data-neo-image], ' + placeholderSelector + '[data-neo-image]', function (event) {
            if ($(this).closest(editableSelector).length) return;
            event.preventDefault();
            event.stopPropagation();
            openImageEditor($(this));
        });
        addBlockControls();
        updateUrl();
        offerDraft();
    }

    /** The photo-placeholder box containing a click target, when it has no real image yet. */
    function imagePlaceholder(target) {
        return $(target).closest(placeholderSelector).filter(function () { return !$(this).find('img').length; });
    }

    /** Open TinyMCE with the selected region's inner HTML. */
    function openEditor(element) {
        currentElement = element;
        $('#editor').val(element.html());
        $('#editModal').dialog('open');
    }

    /** Initialise TinyMCE after the dialogue is visible and measurable. */
    function initialiseEditor() {
        tinymce.init({
            selector: '#editor', height: 360, branding: false, promotion: false, license_key: 'gpl',
            plugins: 'preview searchreplace autolink autosave directionality code visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount quickbars emoticons help',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media table | code preview fullscreen',
            images_upload_handler: uploadImage,
            automatic_uploads: true,
            convert_urls: false
        });
    }

    /** Remove the editor instance when the dialogue closes. */
    function destroyEditor() {
        const editor = tinymce.get('editor');
        if (editor) editor.remove();
    }

    /**
     * Upload a TinyMCE image with progress reporting and CSRF protection.
     *
     * @return {Promise<string>} Resolves to the public image URL expected by TinyMCE.
     */
    function uploadImage(blobInfo, progress) {
        return new Promise(function (resolve, reject) {
            const form = new FormData();
            form.append('file', blobInfo.blob(), blobInfo.filename());
            form.append('csrf_token', csrfToken);
            $.ajax({
                url: basePath + '/cms/image_upload.php', method: 'POST', data: form, processData: false, contentType: false,
                xhr: function () {
                    const xhr = $.ajaxSettings.xhr();
                    xhr.upload.addEventListener('progress', function (event) {
                        if (event.lengthComputable) progress(event.loaded / event.total * 100);
                    });
                    return xhr;
                }
            }).done(function (response) { resolve(response.location); })
                .fail(function (xhr) { reject((xhr.responseJSON || {}).error || 'Upload failed'); });
        });
    }

    // Save applies the edit to the preview and keeps it as a private draft; the public page changes only on Publish.
    $('#saveBtn').on('click', function () {
        const changed = applyEditorContent();
        $('#editModal').dialog('close');
        if (changed) commitEdit();
    });

    // Publish Now and Schedule Publish act on the whole page, so the editor content is applied to the preview first.
    // The editor stays open until the action is confirmed, so a cancelled confirm or dialogue loses nothing.
    $('#publishBtn').on('click', async function () {
        if (!applyEditorContent()) return;
        markDirty();
        if (await publishPage()) $('#editModal').dialog('close');
    });
    $('#scheduleBtn').on('click', function () {
        if (!applyEditorContent()) return;
        markDirty();
        openSchedule();
    });

    /** Copy the content editor's HTML into the selected region of the preview; false when there is nothing to apply. */
    function applyEditorContent() {
        const editor = tinymce.get('editor');
        if (!(editor && currentElement)) return false;
        currentElement.html(editor.getContent());
        return true;
    }

    /** Clone a repeatable block before or after its source, then open the clone for editing. */
    function duplicateBlock(event) {
        event.preventDefault();
        event.stopPropagation();
        const source = $(this).closest('.neo-dupe');
        const clone = source.clone();
        clone.find('.button-container').remove();
        if ($(this).hasClass('duplicate-before')) clone.insertBefore(source); else clone.insertAfter(source);
        addBlockControls();
        markDirty();
        openEditor(clone);
    }

    /** Remove a repeatable block after explicit confirmation. */
    function deleteBlock(event) {
        event.preventDefault();
        event.stopPropagation();
        if (window.confirm('Delete this content block?')) {
            $(this).closest('.neo-dupe').remove();
            commitEdit();
        }
    }

    /** Add transient clone and delete controls to every repeatable block in the preview. */
    function addBlockControls() {
        $(iframeDoc).find('.neo-dupe').each(function () {
            const block = $(this);
            block.find('.button-container').remove();
            // Relative positioning anchors the absolutely positioned controls to this block.
            if (block.css('position') === 'static') {
                block.attr('data-neo-original-position', 'static').css('position', 'relative');
            }
            $('<div class="button-container"><button class="duplicate-before" title="Clone before">Before</button><button class="duplicate-after" title="Clone after">After</button><button class="delete-block" title="Delete block">Delete</button></div>').prependTo(block);
        });
    }

    /**
     * Clone and serialise the complete page after removing administration-only decorations.
     *
     * @return {string} A standalone HTML document suitable for drafts or publication.
     */
    function serialisePage() {
        if (!iframeDoc) throw new Error('No page is loaded');
        const clone = iframeDoc.cloneNode(true);
        $(clone).find('.button-container').remove();
        $(clone).find('[data-neo-original-position="static"]').css('position', '').removeAttr('data-neo-original-position');
        $(clone).find(editableSelector + ', .neo-dupe').css('cursor', '');
        $(clone).find('#neo-editor-style, base[data-neo-base]').remove();
        return '<!DOCTYPE html>\n' + clone.documentElement.outerHTML;
    }

    /** Return the public path of the page currently displayed in the iframe. */
    function currentUri() {
        const path = framePath;
        // Server-side URIs are relative to the site root, so drop the subfolder prefix.
        return basePath && (path === basePath || path.startsWith(basePath + '/')) ? path.slice(basePath.length) || '/' : path;
    }

    /** Save the in-memory page as a private server-side draft; the public page is untouched. */
    async function saveDraft(message) {
        try {
            await api('saveDraft', {uri: currentUri(), content: serialisePage()}, 'POST');
            dirty = false;
            draftSaved = true;
            // A draft now exists for this page, so leaving and returning must offer it again.
            loadedDrafts.delete(currentUri());
            updateUrl();
            showMessage(message || 'Saved as a draft.' + (permissions.publish ? ' Publish to make it live.' : ''), 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** An edit the author has confirmed (dialogue Save or Apply): mark the page changed and keep it as a draft. */
    async function commitEdit(message) {
        markDirty();
        await saveDraft(message);
    }

    /** Run the pre-publish accessibility prompt and publish the complete page. */
    async function publishPage() {
        if (!permissions.publish) return false;
        try {
            const issues = accessibilityIssues();
            if (issues.length && !window.confirm('The accessibility check found ' + issues.length + ' issue(s). Publish anyway?')) return false;
            const result = await api('save', {uri: currentUri(), content: serialisePage()}, 'POST');
            dirty = false;
            draftSaved = false;
            updateUrl();
            showMessage(result.message, 'success');
            addBlockControls();
            return true;
        } catch (error) {
            showMessage(error.message, 'error');
            return false;
        }
    }

    /** Offer to replace the public preview with its most recent saved draft. */
    /**
     * Give a draft the address of the page it belongs to. Writing HTML into the frame makes the frame's URL the admin
     * page's, so without a base the draft's relative stylesheets, images, and links would resolve inside /cms/ and the
     * page would render unstyled. The helper is marked so serialisePage() removes it again.
     */
    function withBase(html, pageUrl) {
        if (/<base[\s>]/i.test(html)) return html;
        const tag = '<base data-neo-base href="' + pageUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '">';
        return /<head[^>]*>/i.test(html) ? html.replace(/<head[^>]*>/i, function (head) { return head + tag; }) : tag + html;
    }

    async function offerDraft() {
        const uri = currentUri();
        if (loadedDrafts.has(uri) || uri.startsWith('/cms/')) return;
        loadedDrafts.add(uri);
        try {
            const draft = await api('getDraft', {uri: uri});
            if (draft.exists && window.confirm('A saved draft exists for this page. Load it?')) {
                writingDraft = true;
                const pageUrl = iframeDoc.location.href;
                iframeDoc.open(); iframeDoc.write(withBase(draft.content, pageUrl)); iframeDoc.close();
                // The frame now matches the saved draft, so there is nothing unsaved.
                dirty = false;
                draftSaved = true;
                updateUrl();
            }
        } catch (error) {
            // A page without an available draft needs no noisy notification.
        }
    }

    /** Open the scheduling dialogue with a sensible default of one hour from now. */
    function openSchedule() {
        const date = new Date(Date.now() + 3600000);
        $('#publishAt').val(new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16));
        $('#scheduleDialog').dialog('open');
    }

    /** Convert the editor's local date to an absolute timestamp and queue the page. */
    async function schedulePage(event) {
        event.preventDefault();
        try {
            const result = await api('schedule', {uri: currentUri(), content: serialisePage(), publish_at: new Date($('#publishAt').val()).toISOString()}, 'POST');
            dirty = false;
            $('#scheduleDialog').dialog('close');
            if ($('#editModal').dialog('isOpen')) $('#editModal').dialog('close');
            showMessage(result.message, 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load searchable page metadata and role-appropriate management controls. */
    async function openPages() {
        try {
            const pages = await api('getPages');
            const body = $('#fileList tbody').empty();
            $('#fileList .manage-col').toggle(permissions.manage);
            $('#pageSearch').val('');
            $('#pageListEmpty').prop('hidden', true);
            pages.forEach(function (page) {
                const row = $('<tr class="page-row">').attr('data-search', (page.name + ' ' + page.title).toLowerCase()).on('click', function () { navigateTo(page.url); });
                const name = $('<td class="page-cell">').append(docIcon());
                $('<button type="button" class="page-open">').text(page.name).appendTo(name);
                if (page.draft) $('<span class="badge-draft">').text('Draft').appendTo(name);
                row.append(name, $('<td class="page-title">').text(page.title), $('<td class="page-date">').text(formatDate(page.modified)));
                const actions = $('<td class="row-actions manage-col">').toggle(permissions.manage).appendTo(row);
                if (permissions.manage) {
                    [['Duplicate', 'duplicate'], ['Rename', 'rename'], ['Delete', 'delete']].forEach(function (action) {
                        $('<button type="button">').text(action[0]).toggleClass('danger-text', action[1] === 'delete')
                            .on('click', function (event) { event.stopPropagation(); managePage(action[1], page.url); }).appendTo(actions);
                    });
                }
                body.append(row);
            });
            $('#fileListDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Small line icon; paths are SVG path markup drawn on a 24px grid. */
    function svgIcon(paths) {
        return $('<svg class="doc-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>');
    }

    /** Document icon shown beside each page. */
    function docIcon() {
        return svgIcon('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>');
    }

    /** Clock icon shown beside revision times. */
    function clockIcon() {
        return svgIcon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');
    }

    /**
     * Append an empty table to a container and return its body.
     * Headings may be text or ready-made elements (for example a select-all checkbox).
     */
    function dataTable(container, headings) {
        const head = $('<tr>');
        headings.forEach(function (heading) { $('<th>').append(typeof heading === 'string' ? document.createTextNode(heading) : heading).appendTo(head); });
        const body = $('<tbody>');
        $('<div class="table-wrap">').append($('<table class="data-table">').append($('<thead>').append(head), body)).appendTo(container);
        return body;
    }

    /** A small button for a table row's action column; clicks do not also trigger the row. */
    function rowButton(label, handler, danger) {
        return $('<button type="button" class="row-button">').text(label).toggleClass('danger-text', !!danger)
            .on('click', function (event) { event.stopPropagation(); handler(); });
    }

    /** Short local date and time for the page table. */
    function formatDate(iso) {
        const date = new Date(iso);
        return isNaN(date) ? '' : date.toLocaleDateString(undefined, {day: 'numeric', month: 'short', year: 'numeric'}) + ', ' + date.toLocaleTimeString(undefined, {hour: '2-digit', minute: '2-digit'});
    }

    /** Hide page-picker rows that do not contain the case-insensitive search text. */
    function filterPages() {
        const query = $(this).val().toLowerCase();
        $('#fileList tbody tr').each(function () { $(this).toggle($(this).data('search').includes(query)); });
        $('#pageListEmpty').prop('hidden', $('#fileList tbody tr:visible').length > 0);
    }

    /** Navigate the preview after protecting any unpublished changes. */
    function navigateTo(url) {
        if (dirty && !window.confirm('Discard unpublished changes?')) return;
        dirty = false;
        $('#frameContainer').attr('src', basePath + url);
        $('#fileListDialog').dialog('close');
    }

    /** Run an administrator page operation, collecting a target path when required. */
    async function managePage(operation, uri) {
        let target = '';
        if (operation === 'delete') {
            if (!window.confirm('Delete ' + uri + '? A revision will be retained.')) return;
        } else {
            target = window.prompt('Target path', uri.replace(/\.html?$/, '') + (operation === 'duplicate' ? '-copy.html' : '.html'));
            if (!target) return;
        }
        try {
            const result = await api('page', {operation: operation, uri: uri, target: target}, 'POST');
            showMessage(result.message, 'success');
            openPages();
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load available templates and open the new-page dialogue. */
    async function openNewPage() {
        try {
            const templates = await api('getTemplates');
            const list = $('#radioList').empty();
            templates.forEach(function (item, index) {
                $('<label class="template-option">').append($('<input type="radio" name="item">').val(item.id).prop('checked', index === 0), docIcon(), $('<span>').text(item.name)).appendTo(list);
            });
            $('#newPageDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Create a template-based page and immediately navigate the preview to it. */
    async function createPage(event) {
        event.preventDefault();
        try {
            const result = await api('newPage', {template: $('input[name="item"]:checked').val(), filename: $('#filename').val()}, 'POST');
            $('#newPageDialog').dialog('close');
            navigateTo(result.url);
            showMessage(result.message, 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load the current page's revision history and render restore controls for publishers. */
    async function openRevisions() {
        try {
            const revisions = await api('revisions', {uri: currentUri()});
            const list = $('#revisionsList').empty();
            if (!revisions.length) {
                $('<p class="empty-state">').text('No revisions have been recorded for this page.').appendTo(list);
            } else {
                const body = dataTable(list, permissions.publish ? ['When', 'User', 'Reason', 'Actions'] : ['When', 'User', 'Reason']);
                revisions.forEach(function (revision) {
                    const row = $('<tr>');
                    $('<td class="page-cell">').append(clockIcon(), document.createTextNode(formatDate(revision.created))).appendTo(row);
                    row.append($('<td>').text(revision.user), $('<td class="page-title">').text(revision.reason));
                    if (permissions.publish) $('<td class="row-actions">').append(rowButton('Restore', function () { restoreRevision(revision.id); })).appendTo(row);
                    body.append(row);
                });
            }
            $('#toolsDialog').dialog('close');
            $('#revisionsDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Restore and publish a selected revision after confirmation. */
    async function restoreRevision(id) {
        if (!window.confirm('Restore and publish this revision?')) return;
        try {
            const result = await api('restoreRevision', {id: id}, 'POST');
            dirty = false;
            $('#frameContainer').attr('src', result.url + '?neo=' + Date.now());
            $('#revisionsDialog').dialog('close');
            showMessage(result.message, 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Build the media library with previews, metadata controls, use counts, and deletion. */
    async function openMedia() {
        try {
            const media = await api('media');
            const list = $('#mediaList').empty();
            media.forEach(function (item) {
                const card = $('<div class="media-card">');
                $('<img>').attr({src: item.url, alt: item.alt || ''}).on('click', function () { insertMedia(item); }).appendTo(card);
                $('<strong>').text(item.name).appendTo(card);
                $('<small>').text(formatBytes(item.size) + ' - used ' + item.uses + ' time(s)').appendTo(card);
                const alt = $('<input type="text" placeholder="Alternative text">').val(item.alt).appendTo(card);
                $('<button>').text('Save alt text').on('click', async function () {
                    try { showMessage((await api('updateMedia', {name: item.name, alt: alt.val()}, 'POST')).message, 'success'); } catch (error) { showMessage(error.message, 'error'); }
                }).appendTo(card);
                if (permissions.manage) $('<button class="danger-text">').text('Delete').on('click', function () { deleteMedia(item); }).appendTo(card);
                list.append(card);
            });
            $('#mediaDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Append a library image to the currently selected editable region. */
    function insertMedia(item) {
        if (!currentElement) {
            showMessage('Open an editable region first, then select an image.', 'error');
            return;
        }
        currentElement.append($('<img>').attr({src: item.url, alt: item.alt || ''}));
        $('#mediaDialog').dialog('close');
        commitEdit();
    }

    /** Delete a media file, warning more firmly when public pages still reference it. */
    async function deleteMedia(item) {
        if (item.uses && !window.confirm('This image is used on ' + item.uses + ' page location(s). Delete it anyway?')) return;
        try { showMessage((await api('deleteMedia', {name: item.name}, 'POST')).message, 'success'); openMedia(); } catch (error) { showMessage(error.message, 'error'); }
    }

    // Pages per request: small enough for smooth progress updates, large enough to keep request overhead low.
    const SCAN_BATCH = 10;
    const APPLY_BATCH = 5;
    let siteScanBusy = false;

    /** Show the scan progress bar with a file count; without a total the bar is indeterminate. */
    function showScanProgress(label, done, total, current) {
        const bar = $('#scanProgressBar');
        if (total > 0) bar.attr('max', total).val(done); else bar.removeAttr('value');
        $('#scanProgressText').text(total > 0
            ? label + ': ' + done + ' of ' + total + ' pages (' + Math.round(done / total * 100) + '%)' + (current ? ' - ' + current : '')
            : label + '...');
        $('#siteScanProgress').prop('hidden', false);
    }

    /** Lock the scan controls while a batch run is in progress. */
    function setScanBusy(busy) {
        siteScanBusy = busy;
        $('#siteScanApply, #siteScanOptions input, #siteScanPages input').prop('disabled', busy);
    }

    /** Open the scan dialogue and analyse the site. */
    async function openSiteScan() {
        $('#toolsDialog').dialog('close');
        $('#siteScanDialog').dialog('open');
        await runSiteScan();
    }

    /** Analyse every page in small batches so progress and a page count can be shown. */
    async function runSiteScan() {
        if (siteScanBusy) return;
        setScanBusy(true);
        $('#siteScanSummary, #siteScanPages').empty();
        try {
            showScanProgress('Finding pages');
            const listing = await api('listSitePages');
            const results = [];
            const used = new Set();
            for (let i = 0; i < listing.pages.length; i += SCAN_BATCH) {
                const batch = listing.pages.slice(i, i + SCAN_BATCH);
                showScanProgress('Analysing', i, listing.pages.length, batch[0]);
                const result = await api('analyseSitePages', {uris: JSON.stringify(batch)});
                results.push.apply(results, result.pages);
                result.usedUploads.forEach(function (name) { used.add(name); });
            }
            if (listing.pages.length) showScanProgress('Analysed', listing.pages.length, listing.pages.length);
            else $('#scanProgressText').text('No pages found.');
            setScanBusy(false);
            renderSiteScan(buildSiteScan(results, listing.uploads, used));
        } catch (error) {
            showMessage(error.message, 'error');
            $('#siteScanProgress').prop('hidden', true);
        } finally {
            setScanBusy(false);
        }
    }

    /** Combine per-page results into the site totals, folder counts, and upload usage shown in the report. */
    function buildSiteScan(pages, uploads, usedUploads) {
        const summary = {pages: pages.length, editable: 0, needTagging: 0, manual: 0, images: 0, missingAlt: 0, brokenRefs: 0, seoGaps: 0};
        const structure = {};
        pages.forEach(function (page) {
            summary.editable += page.editableRegions > 0 ? 1 : 0;
            summary.needTagging += !page.editableRegions && page.proposed.length ? 1 : 0;
            summary.manual += !page.editableRegions && !page.proposed.length ? 1 : 0;
            summary.images += page.images;
            summary.missingAlt += page.missingAlt;
            summary.brokenRefs += page.broken.length;
            summary.seoGaps += page.seoMissing.some(function (key) { return key === 'title' || key === 'description'; }) ? 1 : 0;
            const folder = page.uri.replace(/[^/]*$/, '').replace(/(.)\/$/, '$1');
            structure[folder] = (structure[folder] || 0) + 1;
        });
        return {
            summary: summary, structure: structure, pages: pages,
            uploads: {files: uploads.length, unused: uploads.filter(function (name) { return !usedUploads.has(name); }).length}
        };
    }

    /** Render scan totals, folder structure, and one selectable row per page. */
    function renderSiteScan(scan) {
        const s = scan.summary;
        const summary = $('#siteScanSummary').empty();
        const stats = $('<div class="stat-row">').appendTo(summary);
        [
            [s.pages, 'pages found', ''], [s.editable, 'already editable', ''], [s.needTagging, 'can be made editable', ''],
            [s.manual, 'need manual tagging', 'No safe content area was found on these pages.'],
            [s.images + ' (' + s.missingAlt + ' without alt)', 'images', ''],
            [s.brokenRefs, 'broken references', 'Local image, CSS, or script files that do not exist.'],
            [s.seoGaps, 'missing title or description', ''], [scan.uploads.files + ' (' + scan.uploads.unused + ' unused)', 'uploaded files', '']
        ].forEach(function (stat) { $('<div class="stat">').attr('title', stat[2]).append($('<strong>').text(stat[0]), document.createTextNode(' ' + stat[1])).appendTo(stats); });
        $('<p class="scan-note">').text('Folders: ' + Object.keys(scan.structure).map(function (dir) { return dir + ' (' + scan.structure[dir] + ')'; }).join(', ')).appendTo(summary);

        // After the first run only pages with something new to do are listed; finished pages stay out of the way.
        const pending = scan.pages.filter(function (page) { return page.changes.length > 0; });
        const list = $('#siteScanPages').empty();
        if (!pending.length) {
            $('<p class="success-text">').text('Nothing new to change: all ' + scan.pages.length + ' page(s) are up to date.').appendTo(list);
        } else {
            $('<p>').text(pending.length + ' page(s) have changes to make (' + (scan.pages.length - pending.length) + ' already up to date):').appendTo(list);
            const all = $('<input type="checkbox" checked aria-label="Select all pages">');
            const body = dataTable(list, [all, 'Page', 'Changes to make']);
            all.on('change', function () { body.find('input:not(:disabled)').prop('checked', this.checked); });
            pending.forEach(function (page) {
                const row = $('<tr class="page-row">');
                const box = $('<input type="checkbox" checked>').val(page.uri).on('click', function (event) { event.stopPropagation(); });
                row.on('click', function () { if (!box.prop('disabled')) box.prop('checked', !box.prop('checked')); });
                $('<td class="check-cell">').append(box).appendTo(row);
                const name = $('<td class="page-cell">').append(docIcon(), $('<strong>').text(page.uri)).appendTo(row);
                if (page.title) $('<span class="page-sub">').text(page.title).appendTo(name);
                $('<td class="page-title">').text(page.changes.join('; ')).appendTo(row);
                body.append(row);
            });
        }
        $('#siteScanApply').prop('disabled', !pending.length);
    }

    /** Add the chosen markers to the ticked pages in batches, showing progress, then refresh the report. */
    async function applySiteScan() {
        if (siteScanBusy) return;
        const uris = $('#siteScanPages tbody input:checked').map(function () { return $(this).val(); }).get();
        if (!uris.length) { showMessage('Select at least one page.', 'error'); return; }
        if (!window.confirm('Add editing markers to ' + uris.length + ' page(s)? A revision of each is kept.')) return;
        const options = {content: $('#scanContent').is(':checked'), images: $('#scanImages').is(':checked'), seo: $('#scanSeo').is(':checked')};
        setScanBusy(true);
        let updated = 0;
        try {
            for (let i = 0; i < uris.length; i += APPLY_BATCH) {
                const batch = uris.slice(i, i + APPLY_BATCH);
                showScanProgress('Updating', i, uris.length, batch[0]);
                updated += (await api('applySiteTagging', {uris: JSON.stringify(batch), options: JSON.stringify(options)}, 'POST')).updated_pages;
            }
            showScanProgress('Updated', uris.length, uris.length);
            showMessage('Updated ' + updated + ' page(s). Each has a revision to restore.', 'success');
        } catch (error) {
            showMessage(error.message + ' (' + updated + ' page(s) were updated before this error.)', 'error');
        }
        setScanBusy(false);
        // Reload the preview so it picks up the new markers, unless that would discard unpublished edits.
        if (!dirty) document.getElementById('frameContainer').contentWindow.location.reload();
        await runSiteScan();
    }
    /** Open the image dialogue for an image outside any editable region, offering the media library. */
    async function openImageEditor(image) {
        currentImage = image;
        // A placeholder has no image yet, so it starts empty.
        $('#imageSrc').val(image.is('img') ? image.attr('src') || '' : '');
        $('#imageAlt').val(image.is('img') ? image.attr('alt') || '' : '');
        const picker = $('#imagePicker').empty();
        try {
            (await api('media')).forEach(function (item) {
                $('<img>').attr({src: item.url, alt: item.alt || '', title: item.name}).on('click', function () {
                    $('#imageSrc').val(item.url);
                    if (!$('#imageAlt').val()) $('#imageAlt').val(item.alt || '');
                }).appendTo(picker);
            });
        } catch (error) { showMessage(error.message, 'error'); }
        $('#imageDialog').dialog('open');
    }

    /** Apply the dialogue's image address and alternative text to the preview. */
    function applyImage(event) {
        event.preventDefault();
        if (!currentImage) return;
        if (currentImage.is('img')) {
            currentImage.attr({src: $('#imageSrc').val(), alt: $('#imageAlt').val()});
        } else {
            // Fill the placeholder's own shape (size, ratio, rounded corners) with the chosen image.
            const photo = $('<img data-neo-image>').attr({src: $('#imageSrc').val(), alt: $('#imageAlt').val()})
                .css({display: 'block', width: '100%', height: '100%', 'object-fit': 'cover', 'border-radius': 'inherit'});
            currentImage.removeAttr('role aria-label data-neo-image').empty().append(photo);
        }
        currentImage = null;
        $('#imageDialog').dialog('close');
        commitEdit();
    }

    /** Read current document metadata into the SEO form. */
    function openSeo() {
        if (!iframeDoc) return;
        $('#seoTitle').val(iframeDoc.title);
        $('#seoDescription').val(metaContent('description'));
        $('#seoImage').val(metaProperty('og:image'));
        $('#seoCanonical').val($(iframeDoc).find('link[rel="canonical"]').attr('href') || '');
        $('#seoNoIndex').prop('checked', /noindex/i.test(metaContent('robots')));
        $('#seoDialog').dialog('open');
    }

    /** Apply SEO form values to the in-memory iframe document. */
    function applySeo(event) {
        event.preventDefault();
        iframeDoc.title = $('#seoTitle').val();
        setMeta('name', 'description', $('#seoDescription').val());
        setMeta('property', 'og:title', $('#seoTitle').val());
        setMeta('property', 'og:description', $('#seoDescription').val());
        setMeta('property', 'og:image', $('#seoImage').val());
        setMeta('name', 'robots', $('#seoNoIndex').is(':checked') ? 'noindex,nofollow' : 'index,follow');
        let canonical = $(iframeDoc).find('link[rel="canonical"]');
        if (!canonical.length) canonical = $('<link rel="canonical">').appendTo(iframeDoc.head);
        canonical.attr('href', $('#seoCanonical').val());
        $('#seoDialog').dialog('close');
        commitEdit('SEO settings saved to the draft.' + (permissions.publish ? ' Publish to make them live.' : ''));
    }

    /** Return a named metadata value, or an empty string when the tag is absent. */
    function metaContent(name) {
        return $(iframeDoc).find('meta[name="' + name + '"]').attr('content') || '';
    }

    /** Return an Open Graph property value, or an empty string when absent. */
    function metaProperty(name) {
        return $(iframeDoc).find('meta[property="' + name + '"]').attr('content') || '';
    }

    /** Create or update one metadata tag in the preview document's head. */
    function setMeta(attribute, name, content) {
        let tag = $(iframeDoc).find('meta[' + attribute + '="' + name + '"]');
        if (!tag.length) tag = $('<meta>').attr(attribute, name).appendTo(iframeDoc.head);
        tag.attr('content', content);
    }

    /** Run the local accessibility checks and present every finding in a dialogue. */
    function runAccessibilityCheck() {
        const issues = accessibilityIssues();
        const results = $('#accessibilityResults').empty();
        if (!issues.length) results.append($('<p class="success-text">').text('No common content accessibility problems were found.'));
        else issues.forEach(function (issue) { $('<div class="issue">').text(issue).appendTo(results); });
        $('#toolsDialog').dialog('close');
        $('#accessibilityDialog').dialog('open');
    }

    /**
     * Inspect common authoring concerns that can be checked safely in the browser.
     * This is a practical pre-flight check, not a claim of full WCAG conformance.
     */
    function accessibilityIssues() {
        if (!iframeDoc) return ['No page is loaded.'];
        const issues = [];
        $(iframeDoc).find('img').each(function (index) { if (!this.hasAttribute('alt')) issues.push('Image ' + (index + 1) + ' has no alt attribute.'); });
        $(iframeDoc).find('a').each(function (index) { if (!$(this).text().trim() && !$(this).attr('aria-label')) issues.push('Link ' + (index + 1) + ' has no accessible text.'); });
        let previous = 0;
        $(iframeDoc).find('h1,h2,h3,h4,h5,h6').each(function () {
            const level = Number(this.tagName.slice(1));
            if (previous && level > previous + 1) issues.push('Heading level jumps from H' + previous + ' to H' + level + '.');
            previous = level;
        });
        if ($(iframeDoc).find('h1').length !== 1) issues.push('The page should normally contain exactly one H1 heading.');
        if (!iframeDoc.title.trim()) issues.push('The page title is empty.');
        if (!metaContent('description')) issues.push('The meta description is empty.');
        $(iframeDoc).find('input,select,textarea').each(function () { if (!this.id || !$(iframeDoc).find('label[for="' + this.id + '"]').length) issues.push('A form field has no associated label.'); });
        return issues;
    }

    /** Load the shared-content registry and allow an existing block to be selected for editing. */
    async function openShared() {
        try {
            const blocks = await api('shared');
            const keys = Object.keys(blocks);
            const list = $('#sharedList').empty();
            if (!keys.length) {
                $('<p class="empty-state">').text('No shared blocks yet. Use the form below to create one.').appendTo(list);
            } else {
                const body = dataTable(list, ['Block', 'Last updated', 'Updated by', 'Actions']);
                keys.forEach(function (key) {
                    const load = function () { $('#sharedKey').val(key); $('#sharedContent').val(blocks[key].content); };
                    const row = $('<tr class="page-row">').on('click', load);
                    $('<td class="page-cell">').append(docIcon(), $('<strong>').text(key)).appendTo(row);
                    row.append($('<td class="page-date">').text(formatDate(blocks[key].updated)), $('<td>').text(blocks[key].user || ''),
                        $('<td class="row-actions">').append(rowButton('Edit', load)));
                    body.append(row);
                });
            }
            $('#toolsDialog').dialog('close');
            $('#sharedDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Save and propagate a shared block, optionally marking the selected region with its key. */
    async function saveShared(event) {
        event.preventDefault();
        try {
            const key = $('#sharedKey').val();
            const result = await api('saveShared', {key: key, content: $('#sharedContent').val()}, 'POST');
            if (currentElement && window.confirm('Mark the currently selected editable region as this shared block?')) {
                currentElement.attr('data-neo-shared', key).html($('#sharedContent').val());
                await commitEdit(result.message + ' This page was also saved as a draft.');
            } else {
                showMessage(result.message, 'success');
            }
            openShared();
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load named navigation menus and their editable line-based representation. */
    async function openMenus() {
        try {
            const menus = await api('menus');
            const names = Object.keys(menus);
            const list = $('#menuList').empty();
            if (!names.length) {
                $('<p class="empty-state">').text('No menus yet. Use the form below to create one.').appendTo(list);
            } else {
                const body = dataTable(list, ['Menu', 'Items', 'Last updated', 'Actions']);
                names.forEach(function (name) {
                    const load = function () {
                        $('#menuName').val(name);
                        $('#menuItems').val(menus[name].items.map(function (item) { return item.label + ' | ' + item.url + (item.parent ? ' | ' + item.parent : ''); }).join('\n'));
                    };
                    const row = $('<tr class="page-row">').on('click', load);
                    $('<td class="page-cell">').append(docIcon(), $('<strong>').text(name)).appendTo(row);
                    row.append($('<td>').text(menus[name].items.length), $('<td class="page-date">').text(formatDate(menus[name].updated)),
                        $('<td class="row-actions">').append(rowButton('Edit', load)));
                    body.append(row);
                });
            }
            $('#toolsDialog').dialog('close');
            $('#menusDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Parse menu lines, save the structure, and optionally insert its rendered navigation. */
    async function saveMenu(event) {
        event.preventDefault();
        const items = $('#menuItems').val().split('\n').map(function (line) {
            const parts = line.split('|');
            return {label: (parts[0] || '').trim(), url: (parts[1] || '').trim(), parent: (parts[2] || '').trim()};
        }).filter(function (item) { return item.url; });
        try {
            const result = await api('saveMenu', {name: $('#menuName').val(), items: JSON.stringify(items)}, 'POST');
            if (currentElement && window.confirm('Insert this menu into the currently selected editable region?')) {
                currentElement.html(result.html);
                await commitEdit(result.message + ' This page was also saved as a draft.');
            } else {
                showMessage(result.message, 'success');
            }
            openMenus();
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /**
     * Refresh authoritative permissions and dashboard data, optionally opening the dialogue.
     * Server permissions control visibility; the server still enforces every operation regardless.
     */
    async function loadDashboard(open) {
        try {
            const dashboard = await api('dashboard');
            permissions = dashboard.permissions;
            $('.publish-only').toggle(permissions.publish);
            $('.manage-only').toggle(permissions.manage);
            const content = $('#dashboardContent').empty();
            const draftUris = Object.keys(dashboard.drafts);
            const jobIds = Object.keys(dashboard.schedules);

            $('<div class="stat-row">').append(
                $('<div class="stat">').append($('<strong>').text(draftUris.length), document.createTextNode(' draft(s)')),
                $('<div class="stat">').append($('<strong>').text(jobIds.length), document.createTextNode(' scheduled publication(s)'))
            ).appendTo(content);

            if (jobIds.length) {
                $('<h3 class="dialog-section">').text('Scheduled publications').appendTo(content);
                const body = dataTable(content, permissions.schedule ? ['Page', 'Publishes', 'Status', 'Actions'] : ['Page', 'Publishes', 'Status']);
                jobIds.forEach(function (id) {
                    const job = dashboard.schedules[id];
                    const row = $('<tr>');
                    $('<td class="page-cell">').append(docIcon(), document.createTextNode(job.uri)).appendTo(row);
                    row.append($('<td class="page-date">').text(formatDate(job.publish_at)),
                        $('<td>').append($('<span>').addClass(job.status === 'failed' ? 'badge-failed' : 'badge-pending').text(job.status === 'failed' ? 'Failed' : 'Pending')));
                    if (permissions.schedule) $('<td class="row-actions">').append(rowButton('Cancel', function () { cancelSchedule(id); })).appendTo(row);
                    body.append(row);
                });
            }
            if (draftUris.length) {
                $('<h3 class="dialog-section">').text('Drafts').appendTo(content);
                const body = dataTable(content, ['Page', 'Saved', 'By']);
                draftUris.forEach(function (uri) {
                    const row = $('<tr>');
                    $('<td class="page-cell">').append(docIcon(), document.createTextNode(uri)).appendTo(row);
                    row.append($('<td class="page-date">').text(formatDate(dashboard.drafts[uri].updated)), $('<td>').text(dashboard.drafts[uri].user || ''));
                    body.append(row);
                });
            }

            $('<h3 class="dialog-section">').text('Recent activity').appendTo(content);
            if (!dashboard.activity.length) {
                $('<p class="empty-state">').text('No activity has been recorded yet.').appendTo(content);
            } else {
                const body = dataTable(content, ['When', 'User', 'Action', 'Target']);
                dashboard.activity.forEach(function (entry) {
                    body.append($('<tr>').append($('<td class="page-date">').text(formatDate(entry.created)), $('<td>').text(entry.user),
                        $('<td>').text(entry.action), $('<td class="page-title">').text(entry.target)));
                });
            }
            if (dashboard.problems.length) {
                $('<h3 class="dialog-section">').text('System problems').appendTo(content);
                dashboard.problems.forEach(function (problem) { $('<div class="issue">').text(problem).appendTo(content); });
            }
            if (open) $('#dashboardDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Cancel one queued publication and refresh the dashboard. */
    async function cancelSchedule(id) {
        try { showMessage((await api('cancelSchedule', {id: id}, 'POST')).message, 'success'); loadDashboard(true); } catch (error) { showMessage(error.message, 'error'); }
    }

    /** End the session after protecting unpublished browser changes. */
    async function logout() {
        if (dirty && !window.confirm('Log out and discard unpublished changes?')) return;
        try { await api('logout', {}, 'POST'); window.location.href = basePath + '/cms/login/'; } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Mark the preview as unpublished and refresh its toolbar status. */
    function markDirty() {
        dirty = true;
        updateUrl();
    }

    /** Display the active path and whether browser changes remain unpublished. */
    function updateUrl() {
        $('#urlbox').empty().append($('<strong>').text(dirty ? 'Unsaved: ' : draftSaved ? 'Draft: ' : 'Editing: '), $('<span>').text(iframeDoc ? framePath : ''));
    }
    /** Format a byte count compactly for media cards. */
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
})(jQuery);

/** Display a temporary, colour-coded status message above the administration interface. */
/** Show a success or error snackbar; errors stay longer and are announced to assistive technology immediately. */
function showMessage(message, type) {
    const bar = jQuery('#message-bar');
    const isError = type === 'error';
    bar.attr('role', isError ? 'alert' : 'status').removeClass('show is-success is-error').addClass(isError ? 'is-error' : 'is-success').prop('hidden', false);
    bar.find('.snack-text').text(message || 'Done');
    void bar[0].offsetWidth; // Restart the transition when a message replaces another.
    bar.addClass('show');
    window.clearTimeout(showMessage.timer);
    showMessage.timer = window.setTimeout(hideMessage, isError ? 9000 : 5000);
}

/** Slide the snackbar away, then remove it from the layout. */
function hideMessage() {
    const bar = jQuery('#message-bar').removeClass('show');
    window.setTimeout(function () { if (!bar.hasClass('show')) bar.prop('hidden', true); }, 250);
}

jQuery(function () { jQuery('#message-bar .snack-close').on('click', hideMessage); });