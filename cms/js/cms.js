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

    // Dirty state protects unpublished browser changes from accidental navigation.
    let dirty = false;

    // Permissions are replaced by the authoritative dashboard response after initialisation.
    let permissions = {draft: true, publish: false, schedule: false, manage: false};

    // Remember offered drafts so an iframe rewrite does not prompt for the same draft twice.
    const loadedDrafts = new Set();

    // Server-rendered values keep selectors and mutating requests aligned with configuration.
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
    const editableClass = $('meta[name="neo-editable-class"]').attr('content') || 'editable';
    const editableSelector = '.' + editableClass;

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
        return $.ajax({url: '/cms/controller/', method: method || 'GET', data: payload, dataType: 'json'})
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
        $('#saveDraft').on('click', saveDraft);
        $('#savePage').on('click', publishPage);
        $('#scheduleButton').on('click', openSchedule);
        $('#mediaButton').on('click', openMedia);
        $('#seoButton').on('click', openSeo);
        $('#moreButton').on('click', function () { $('#toolsDialog').dialog('open'); });
        $('#revisionsButton').on('click', openRevisions);
        $('#accessibilityButton').on('click', runAccessibilityCheck);
        $('#sharedButton').on('click', openShared);
        $('#menusButton').on('click', openMenus);
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
        iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        currentElement = null;

        $(iframeDoc).off('.neocms');
        // Editable regions open TinyMCE; generated block controls retain their own click behaviour.
        $(iframeDoc).on('click.neocms', editableSelector, function (event) {
            if ($(event.target).closest('.button-container').length) return;
            event.preventDefault();
            event.stopPropagation();
            openEditor($(this));
        });
        // Internal page navigation receives the same unpublished-change protection as the window.
        $(iframeDoc).on('click.neocms', 'a', function (event) {
            if (dirty && !window.confirm('Leave this page and discard unpublished changes?')) {
                event.preventDefault();
            }
        });
        $(iframeDoc).on('click.neocms', '.duplicate-before, .duplicate-after', duplicateBlock);
        $(iframeDoc).on('click.neocms', '.delete-block', deleteBlock);
        addBlockControls();
        updateUrl();
        offerDraft();
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
                url: '/cms/image_upload.php', method: 'POST', data: form, processData: false, contentType: false,
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

    // Apply modal edits to the iframe only; the page remains unpublished until explicitly saved.
    $('#saveBtn').on('click', function () {
        const editor = tinymce.get('editor');
        if (editor && currentElement) {
            currentElement.html(editor.getContent());
            markDirty();
        }
        $('#editModal').dialog('close');
    });

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
            markDirty();
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
        return '<!DOCTYPE html>\n' + clone.documentElement.outerHTML;
    }

    /** Return the public path of the page currently displayed in the iframe. */
    function currentUri() {
        return iframeDoc ? iframeDoc.location.pathname : '/';
    }

    /** Save the in-memory page as a private server-side draft. */
    async function saveDraft() {
        try {
            const result = await api('saveDraft', {uri: currentUri(), content: serialisePage()}, 'POST');
            dirty = false;
            showMessage(result.message, 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Run the pre-publish accessibility prompt and publish the complete page. */
    async function publishPage() {
        if (!permissions.publish) return;
        try {
            const issues = accessibilityIssues();
            if (issues.length && !window.confirm('The accessibility check found ' + issues.length + ' issue(s). Publish anyway?')) return;
            const result = await api('save', {uri: currentUri(), content: serialisePage()}, 'POST');
            dirty = false;
            showMessage(result.message, 'success');
            addBlockControls();
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Offer to replace the public preview with its most recent saved draft. */
    async function offerDraft() {
        const uri = currentUri();
        if (loadedDrafts.has(uri) || uri.startsWith('/cms/')) return;
        loadedDrafts.add(uri);
        try {
            const draft = await api('getDraft', {uri: uri});
            if (draft.exists && window.confirm('A saved draft exists for this page. Load it?')) {
                iframeDoc.open(); iframeDoc.write(draft.content); iframeDoc.close();
                dirty = true;
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
            showMessage(result.message, 'success');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load searchable page metadata and role-appropriate management controls. */
    async function openPages() {
        try {
            const pages = await api('getPages');
            const list = $('#fileList').empty();
            pages.forEach(function (page) {
                const row = $('<li>').attr('data-search', (page.name + ' ' + page.title).toLowerCase());
                $('<button class="page-open">').text(page.title + ' - ' + page.name + (page.draft ? ' [draft]' : '')).on('click', function () { navigateTo(page.url); }).appendTo(row);
                if (permissions.manage) {
                    $('<button>').text('Duplicate').on('click', function () { managePage('duplicate', page.url); }).appendTo(row);
                    $('<button>').text('Rename').on('click', function () { managePage('rename', page.url); }).appendTo(row);
                    $('<button class="danger-text">').text('Delete').on('click', function () { managePage('delete', page.url); }).appendTo(row);
                }
                list.append(row);
            });
            $('#fileListDialog').dialog('open');
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Hide page-picker rows that do not contain the case-insensitive search text. */
    function filterPages() {
        const query = $(this).val().toLowerCase();
        $('#fileList li').each(function () { $(this).toggle($(this).data('search').includes(query)); });
    }

    /** Navigate the preview after protecting any unpublished changes. */
    function navigateTo(url) {
        if (dirty && !window.confirm('Discard unpublished changes?')) return;
        dirty = false;
        $('#frameContainer').attr('src', url);
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
                $('<label>').append($('<input type="radio" name="item">').val(item.id).prop('checked', index === 0), document.createTextNode(' ' + item.name)).appendTo(list);
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
            if (!revisions.length) list.text('No revisions have been recorded for this page.');
            revisions.forEach(function (revision) {
                const row = $('<div class="list-row">').text(new Date(revision.created).toLocaleString() + ' - ' + revision.user + ' - ' + revision.reason + ' ');
                if (permissions.publish) $('<button>').text('Restore').on('click', function () { restoreRevision(revision.id); }).appendTo(row);
                list.append(row);
            });
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
        markDirty();
        $('#mediaDialog').dialog('close');
    }

    /** Delete a media file, warning more firmly when public pages still reference it. */
    async function deleteMedia(item) {
        if (item.uses && !window.confirm('This image is used on ' + item.uses + ' page location(s). Delete it anyway?')) return;
        try { showMessage((await api('deleteMedia', {name: item.name}, 'POST')).message, 'success'); openMedia(); } catch (error) { showMessage(error.message, 'error'); }
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
        markDirty();
        $('#seoDialog').dialog('close');
        showMessage('SEO settings applied. Publish the page to make them live.', 'success');
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
            const list = $('#sharedList').empty();
            Object.keys(blocks).forEach(function (key) {
                $('<button class="list-row">').text(key + ' - updated ' + new Date(blocks[key].updated).toLocaleString()).on('click', function () {
                    $('#sharedKey').val(key); $('#sharedContent').val(blocks[key].content);
                }).appendTo(list);
            });
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
                markDirty();
            }
            showMessage(result.message, 'success');
            openShared();
        } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Load named navigation menus and their editable line-based representation. */
    async function openMenus() {
        try {
            const menus = await api('menus');
            const list = $('#menuList').empty();
            Object.keys(menus).forEach(function (name) {
                $('<button class="list-row">').text(name).on('click', function () {
                    $('#menuName').val(name);
                    $('#menuItems').val(menus[name].items.map(function (item) { return item.label + ' | ' + item.url + (item.parent ? ' | ' + item.parent : ''); }).join('\n'));
                }).appendTo(list);
            });
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
                currentElement.html(result.html); markDirty();
            }
            showMessage(result.message, 'success');
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
            $('#saveDraft').toggle(permissions.draft);
            const content = $('#dashboardContent').empty();
            $('<h3>').text('Pending work').appendTo(content);
            $('<p>').text(Object.keys(dashboard.drafts).length + ' draft(s), ' + Object.keys(dashboard.schedules).length + ' scheduled publication(s).').appendTo(content);
            Object.keys(dashboard.schedules).forEach(function (id) {
                const job = dashboard.schedules[id];
                const row = $('<div class="list-row">').text(job.uri + ' - ' + new Date(job.publish_at).toLocaleString() + ' ');
                if (permissions.schedule) $('<button>').text('Cancel').on('click', function () { cancelSchedule(id); }).appendTo(row);
                content.append(row);
            });
            $('<h3>').text('Recent activity').appendTo(content);
            dashboard.activity.forEach(function (entry) { $('<div class="list-row">').text(new Date(entry.created).toLocaleString() + ' - ' + entry.user + ' - ' + entry.action + ': ' + entry.target).appendTo(content); });
            if (dashboard.problems.length) {
                $('<h3>').text('System problems').appendTo(content);
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
        try { await api('logout', {}, 'POST'); window.location.href = '/cms/login/'; } catch (error) { showMessage(error.message, 'error'); }
    }

    /** Mark the preview as unpublished and refresh its toolbar status. */
    function markDirty() {
        dirty = true;
        updateUrl();
    }

    /** Display the active path and whether browser changes remain unpublished. */
    function updateUrl() {
        $('#urlbox').empty().append($('<strong>').text(dirty ? 'Unpublished: ' : 'Editing: '), $('<span>').text(iframeDoc ? iframeDoc.location.pathname : ''));
    }
    /** Format a byte count compactly for media cards. */
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
})(jQuery);

/** Display a temporary, colour-coded status message above the administration interface. */
function showMessage(message, type) {
    const bar = jQuery('#message-bar');
    bar.stop(true, true).text(message || 'Done').css({backgroundColor: type === 'error' ? '#a51d2d' : '#18733c'}).slideDown();
    window.setTimeout(function () { bar.slideUp(); }, 5000);
}
