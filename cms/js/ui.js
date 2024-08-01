var undoC, neoCMSGlobalPath, neoCMSPath, neoCMSFiles, neoCMSHTMLEdit, neoCMSFilePath, neoCMSImagePath, fileTarget,
    origImg,
    bm;
$(function () {
    $(window).bind('load', neocmsStart);
    neocmsGlobals();
    $('#neoCMSPageSrc').height($(window).height() - 60)
});
neocmsStart = function () {
    ifrDoc = getIfrDoc();
    $(window).unbind('load');
    $('#neoCMSTopBanner').css('background', 'url(images/loading.gif) repeat-x bottom');
    undoC = 0;
    neoCMSGlobalPath = null;
    neoCMSPath = null;
    neoCMSFiles = null;
    neoCMSHTMLEdit = null;
    neoCMSFilePath = null;
    neoCMSImagePath = null;
    $('#neoCMSDashboard a').bind('click', function () {
        if ($('#neoCMSPageEdits .edit', ifrDoc).length) {
            navAwayPrompt('settings/', 'loc')
        } else window.location = 'settings/'
    });
    $('#neoCMSLogout a').bind('click', logoutPrompt);
    neocmsSetup()
};
neocmsSetup = function () {
    if (neoCMSGlobalPath == null || neoCMSHTMLEdit == null) {
        var prefsIframe;
        try {
            prefsIframe = document.createElement('<iframe name="neoCMSPrefsFrame" >')
        } catch (ex) {
            prefsIframe = document.createElement('iframe')
        }
        $(prefsIframe).attr({
            id: 'neoCMSPrefsFrame',
            name: 'neoCMSPrefsFrame',
            src: 'core/sessionConfiguration.php',
            application: 'yes'
        }).css('display', 'none');
        $('body').append(prefsIframe)
    } else getGlobals(neoCMSGlobalPath, neoCMSHTMLEdit, neoCMSFiles, neoCMSFilePath, neoCMSImagePath)
};
getGlobals = function (path, html, files, filePath, exFolders, imagePath) {
    var absPath = window.location.href.split('/' + path + '/');
    neoCMSGlobalPath = absPath[0] + '/' + path;
    neoCMSPath = path;
    neoCMSHTMLEdit = html;
    neoCMSFiles = files;
    neoCMSFilePath = filePath;
    neoCMSImagePath = imagePath;
    neoCMSExFolders = exFolders;
    $('#neoCMSPrefsFrame').remove(); // Is this needed?
    initPage();
    setTimeout(function () {
        $('#neoCMSPageSrc').bind('load', initPage)
    }, 100)
};
initPage = function () {
    getIfrDoc();
    cancel();
    $('#neoCMSGlobalUndo, #neoCMSGlobalRedo').remove();
    $('.neocms cufon, .neocmsRepeat cufon', ifrDoc).each(function () {
        $(this, ifrDoc).replaceWith($(this, ifrDoc).find('cufontext').html())
    });
    $('.neocms embed.sIFR-flash, .neocmsRepeat embed.sIFR-flash, .neocms object.sIFR-flash, .neocmsRepeat object.sIFR-flash', ifrDoc).remove();
    $('.neocms .sIFR-replaced', ifrDoc).removeClass('sIFR-replaced');
    $('html', ifrDoc).removeClass('sIFR-hasFlash');
    $('.neocms span.sIFR-alternate, .neocmsRepeat span.sIFR-alternate', ifrDoc).each(function () {
        $(this, ifrDoc).replaceWith($(this, ifrDoc).html())
    });
    linkBind();
    neocmsCSS()
};
neocmsCSS = function () {
    var ifrDocStyles = '<style type="text/css" app="neocms">\n' + 'a.neoCMSIcon, a.neoCMSRepCopyDrag, a.neoCMSRepDelete, a.neoCMSRepIcon { display: block; position: absolute; text-indent: -5000px; background-image: url("' + neoCMSGlobalPath + '/images/neocms_sprite1.png") !important; background-repeat: no-repeat ; width: 36px !important; height: 32px !important; overflow: hidden; background-position: -638px -56px !important; border: none; background-color: none; text-decoration: none; z-index: 9000; cursor: pointer; padding: 0 !important; min-height: 32px !important; max-height: 32px !important; min-width: 36px !important; max-width: 36px !important; }\n' + 'a.neoCMSIcon:hover { background-position: -638px -88px !important; }' + 'a.neoCMSRepIcon { background-position: -674px -56px !important; }\n' + 'a.neoCMSRepIcon:hover { background-position: -674px -88px !important; }' + '.neoCMSPlaceholder { display: block; background: #000 !important; opacity: 0.15 !important; *filter: alpha(opacity=15) !important; }\n' + 'a.neoCMSRepCopyDrag { width: 32px !important; height: 32px !important; left: 0 !important; background-position: -710px -56px !important; }\n' + 'a.neoCMSRepCopyDrag:hover { background-position: -710px -88px !important; }\n' + 'a.neoCMSRepDelete { width: 32px !important; height: 32px !important; left: 36px !important; background-position: -742px -56px !important; }\n' + 'a.neoCMSRepDelete:hover { background-position: -742px -88px !important; }\n' + '.neocmsRepeatArea { overflow: hidden !important; display: block; }\n' + '.neoCMSRep { cursor: move !important; *zoom: 1; position: relative; }\n' + '#neoCMSUploadImgClone { visibility: hidden; position: absolute; left: -5000px; top: 0; padding: 0; margin: 0; display: block; }\n' + '#neoCMSVidClone { visibility: hidden; position: absolute; left: -5000px; top: 0; padding: 0; margin: 0; display: block; }\n' + 'embed.neoCMSFlash, object.neoCMSFlash { visibility: hidden !important; z-index: 0 !important; }\n' + '</style>';
    $('head', ifrDoc).append(ifrDocStyles);
    changeTitle()
};
changeTitle = function () {
    var urlName = retUrl();
    urlName = urlName.split('//');
    urlName = urlName[1];
    document.title = "Editing :: " + urlName;
    neocmsGlobals();
    initElements()
};
initElements = function () {
    var inlineRegExp = new RegExp("\ba\b|abbr|acronym|\bb\b|basefont|bdo|big|br|cite|code|dfn|em\b|font|\bi\b|input|kbd|label|\bq\b|\bs\b|samp|select|small|span|strike|strong|sub|textarea|tt|\bu\b|var", "i"),
        unc = 0, rnc = 0, elems = $('.neocms, .neocmsRepeatArea .neocmsRepeat, .neocmsRepeatArea', ifrDoc);
    if (elems.length) {
        $.each(elems, function () {
            if ($(this, ifrDoc).attr('class').match(/neocmsRepeat\b/g)) var type = 'neoCMSEditArea neoCMSRep',
                typeId = 'neoCMSRepEdit' + rnc; else if ($(this, ifrDoc).attr('class').match(/neocmsRepeatArea/g)) var type = 'neoCMSEditArea neoCMSRepArea',
                typeId = 'neoCMSEditArea' + unc; else var type = 'neoCMSEditArea', typeId = 'neoCMSEditArea' + unc;
            if (!$(this, ifrDoc)[0].tagName.match(inlineRegExp) && !$(this, ifrDoc).parents('.neocms, .neocmsRepeat').length) {
                $(this, ifrDoc).addClass(type).attr({neocmsid: typeId});
                if ($(this, ifrDoc).attr('class').match(/neocmsRepeat\b/)) {
                    if (getCurStyle($(this, ifrDoc)[0], 'position') == 'static' && $(this, ifrDoc)[0].tagName != 'TR') $(this, ifrDoc).css('position', 'relative')
                }
                if ($(this, ifrDoc)[0].tagName == 'TABLE' && $(this, ifrDoc).attr('class').match(/neocmsRepeatArea/)) $(this, ifrDoc).css('display', 'table')
            }
            if (!$(this, ifrDoc).attr('class').match(/neocmsRepeat\b/g)) unc++; else rnc++
        })
    }
    $('body', ifrDoc).append($('<form/>', ifrDoc).attr({
        id: 'neoCMSPageEdits',
        action: neoCMSGlobalPath + '/core/save.php',
        method: 'POST',
        target: 'neoCMSPubIframe',
        'accept-charset': 'UTF-8,ISO-8859-15'
    }).css('display', 'none'));
    createIcons();
    neocmsRepStart()
};
createIcons = function (noanim) {
    destroyIcons();
    $('#neoCMSTopBanner').css('background', 'none');
    $('.neoCMSEditArea', ifrDoc).each(function () {
        var tog = ($(this, ifrDoc).attr('class').match(/neoCMSRep/g)) ? true : false,
            iid = $(this, ifrDoc).attr('neocmsid').replace(/neoCMSRepEdit|neoCMSEditArea/, ''),
            itype = (tog) ? 'neoCMSRepIcon' : 'neoCMSIcon', bgl = (tog) ? '-674px' : '-638px',
            type = (tog) ? 'neoCMSRepEdit' : 'neoCMSEditArea', reptype = (tog) ? '.neocmsRepeat' : '.neocms',
            udisp = (getCurStyle($(this, ifrDoc)[0], 'display') != 'none') ? 'block' : 'none',
            uvis = getCurStyle($(this, ifrDoc)[0], 'visibility'),
            padtop = getCurStyle($(this, ifrDoc)[0], 'padding-top');
        var offset = $(this, ifrDoc).offset(), oleft = (offset.left - 40 < 0) ? 0 : offset.left - 40;
        oleft = (oleft > $('body', ifrDoc).width() - 40) ? $('body', ifrDoc).width() - 50 : oleft;
        if (!$(this, ifrDoc).attr('class').match(/neoCMSRepArea/g) && udisp != 'none' && uvis != 'hidden') {
            $('body', ifrDoc).append($('<a/>', ifrDoc).addClass(itype).attr({
                id: itype + iid,
                title: 'Edit this area',
                href: 'javascript:'
            }).html('Update now').css({top: offset.top + parseInt(padtop), left: oleft}).bind('mousedown', function () {
                change(iid, itype)
            }))
        }
        if (!$(this, ifrDoc).attr('class').match(/neocmsRepeatArea/g)) {
            $(this, ifrDoc).unbind('mouseover').bind('mouseover', function () {
                if (tog) $('.neoCMSRepDelete, .neoCMSRepCopyDrag', $(this, ifrDoc)).css('display', 'block');
                $('#' + itype + iid, ifrDoc).css('background-position', bgl + ' -88px !important')
            }).unbind('mouseout').bind('mouseout', function () {
                if (tog) $('.neoCMSRepDelete, .neoCMSRepCopyDrag', $(this, ifrDoc)).css('display', 'none');
                $('#' + itype + iid, ifrDoc).css('background-position', bgl + ' -56px !important')
            })
        }
    });
    $('body', ifrDoc).unbind('mouseup').bind('mouseup', function () {
        setTimeout(function () {
            createIcons()
        }, 400)
    })
};
neocmsRepStart = function () {
    if ($('.neocmsRepeatArea', ifrDoc).length) {
        var unrac = 0;
        $('.neocmsRepeatArea', ifrDoc).each(function () {
            $(this, ifrDoc).addClass('neoCMSRepeatArea' + unrac);
            if ($(this, ifrDoc)[0].tagName == 'TABLE') $(this, ifrDoc).removeClass('clearfix');
            unrac++
        });
        neocmsRepBind()
    }
};
neocmsRepBind = function () {
    var areas = repAreas();
    var undoAreas = repAreas(true);
    repCopyDragDelete();
    $('.neocmsRepeatArea', ifrDoc).sortable('destroy').sortable({
        items: $('.neoCMSRep', ifrDoc),
        cancel: 'a.neoCMSRepCopyDrag, a.neoCMSRepDelete, a.neoCMSIcon',
        tolerance: 'pointer',
        cursorAt: 'top',
        containment: 'parent',
        cursor: 'move',
        placeholder: 'neoCMSPlaceholder',
        forcePlaceholderSize: true,
        forceHelperSize: true,
        opacity: 0.7
    }).unbind('mousedown').bind('mousedown', function () {
        repOverlay($(this, ifrDoc), areas, undoAreas)
    }).find('.neoCMSIcon').unbind('mousedown').bind('mousedown', change).end().find('*').disableSelection();
    $('.neoCMSRep', ifrDoc).draggable('destroy').draggable({
        connectToSortable: $('.ui-sortable', ifrDoc),
        handle: $('a.neoCMSRepCopyDrag', ifrDoc),
        helper: 'clone',
        containment: 'parent',
        revert: 'invalid',
        cursor: 'move',
        opacity: 0.7
    }).each(function () {
        if ($(this, ifrDoc)[0].tagName == 'TR') $(this, ifrDoc).removeClass('clearfix');
        $(this, ifrDoc).width(getCurStyle(this, 'width'))
    });
    $('.neoCMSRepDelete', ifrDoc).unbind('mousedown').bind('mousedown', function () {
        repDeletePrompt($(this, ifrDoc))
    });
    if ($.browser.msie) $('.neoCMSRep', ifrDoc).each(function () {
        $(this, ifrDoc)[0].style.removeAttribute('filter')
    });
    $('body', ifrDoc).unbind('sortstart').bind('sortstart', function (e, ui) {
        setTimeout(function () {
            $('.neoCMSPlaceholder', ifrDoc).height($('.ui-sortable-helper', ifrDoc).height() + 'px').width($('.ui-sortable-helper', ifrDoc).width() + 'px').attr('class', 'neoCMSPlaceholder ' + $('.ui-sortable-helper', ifrDoc).attr('class'));
            $('tr.neoCMSPlaceholder', ifrDoc).append($('<td/>', ifrDoc).attr('colspan', $('tr.ui-sortable-helper', ifrDoc).find('td').length))
        }, 0);
        destroyIcons()
    })
};
repCopyDragDelete = function () {
    $('a.neoCMSRepCopyDrag, a.neoCMSRepDelete', ifrDoc).remove();
    $('.neoCMSRep', ifrDoc).each(function () {
        var d = '<a class="neoCMSRepDelete" style="display:none;" title="Delete element" href="javascript:">Delete element</a>',
            c = '<a class="neoCMSRepCopyDrag" style="display:none;" title="Drag from here to create a copy" href="javascript:">Drag from here to create a copy</a>',
            t = this;
        if ($(t, ifrDoc)[0].tagName == 'TR') {
            var padTop = getCurStyle($(t, ifrDoc).find('td:first')[0], 'padding-top'), rowPos = $(t, ifrDoc).position();
            $('td:first, th:first', t).prepend(d.replace('display:none;', 'display:none; top:' + Math.round(rowPos.top) + 'px !important; left:' + (Math.round(rowPos.left) + 36) + 'px !important')).prepend(c.replace('display:none;', 'display:none; top:' + Math.round(rowPos.top) + 'px !important; left:' + Math.round(rowPos.left) + 'px !important'))
        } else $(t, ifrDoc).prepend(d).prepend(c)
    })
};
repAreas = function (undo) {
    var unrac = 0;
    var areas = new Array();
    $('.neocmsRepeatArea', ifrDoc).each(function () {
        if (undo) {
            $(this, ifrDoc).find('.neoCMSEle').css('visibility', 'visible').removeClass('neoCMSEle');
            var repAreaContent = $('<div/>', ifrDoc).append($(this, ifrDoc).clone()).html()
        } else {
            var repAreaContent = $(this, ifrDoc).clone().find('.neoCMSPlaceholder').remove().end().find('.neoCMSRep').each(function () {
                $(this, ifrDoc).replaceWith($(this, ifrDoc).find('.neocmsRepeat').clone())
            }).end().html();
            repAreaContent = repAreaContent.replace(/style=".*?"/gi, '')
        }
        $(this, ifrDoc).find('.neocmsRepeat').each(function () {
            if (!$(this, ifrDoc).find('.neoCMSRepDelete').length) {
                var repDelete = $('<a/>', ifrDoc).addClass('neoCMSRepDelete').attr({
                    title: 'Delete element',
                    href: 'javascript:'
                }).html('Delete element'), repCopy = $('<a/>', ifrDoc).addClass('neoCMSRepCopyDrag').attr({
                    title: 'Drag from here to create a copy',
                    href: 'javascript:'
                }).html('Drag from here to create a copy');
                repCopyDragDelete(this, repDelete, repCopy)
            }
        });
        areas[unrac] = repAreaContent;
        unrac++
    });
    return areas
};
repOverlay = function (rep, areas, undoAreas) {
    var pos = (getCurStyle($(rep, ifrDoc)[0], 'position') == 'static' && $(rep, ifrDoc)[0].tagName != 'TABLE') ? 'relative' : getCurStyle($(rep, ifrDoc)[0], 'position');
    $(rep, ifrDoc).css({position: pos, width: $(rep, ifrDoc).width()});
    $('body', ifrDoc).unbind('sortstop').bind('sortstop', function (event, ui) {
        var rand = Math.round(Math.random() * 10000);
        if (!$('[neocmsid=neoCMSRepEdit' + rand + ']', ifrDoc).length) ui.item.attr('neocmsid', 'neoCMSRepEdit' + rand); else ui.item.attr('neocmsid', 'neoCMSRepEdit' + rand + 1);
        repDone(rep, areas, undoAreas)
    })
};
repEditDone = function (ele) {
    var tinyDoc = getTinyDoc(), areas = repAreas(), undoAreas = repAreas(true),
        parent = $(ele, ifrDoc).parents('.neocmsRepeatArea'), t = (ele.tagName.match(/(h[0-9]|p)/i)) ? false : true,
        content = (!t && $('[neocmsid]', tinyDoc).find(ele.tagName).length) ? $('[neocmsid]', tinyDoc).parent().html() : $('[neocmsid]', tinyDoc).html();
    if (content) {
        content = cleanContent(content, t);
        var clone = $(ele, ifrDoc).clone();
        if ($(ele, ifrDoc)[0].tagName.match(/TR|THEAD|TBODY/)) clone.html(content); else clone[0].innerHTML = content;
        $(ele, ifrDoc).replaceWith(clone)
    } else $(ele, ifrDoc).html('&nbsp;');
    cancel();
    repDone(parent, areas, undoAreas)
};
repImageDone = function (ele) {
    var areas = repAreas();
    var undoAreas = repAreas(true);
    var parent = $(ele, ifrDoc).parents('.neocmsRepeatArea', ifrDoc);
    var imgSrc = ($('#neoCMSImgStatArea img').attr('src').match(/^\.\.\//)) ? $('#neoCMSImgStatArea img').attr('src').replace(/^\.\.\//, '') : $('#neoCMSImgStatArea img').attr('src');
    var neoCMSImgClass = null;
    if ($('#neoCMSImgClass').val() != '') neoCMSImgClass = $('#neoCMSImgClass').val() + ' neocmsRepeat'; else neoCMSImgClass = 'neocmsRepeat';
    $(ele, ifrDoc).addClass('neoCMSImgClass').attr({
        src: imgSrc,
        alt: $('#neoCMSImgAltTxt').val(),
        width: $('#neoCMSImgStatsWidth').val(),
        height: $('#neoCMSImgStatsHeight').val()
    });
    if ($(ele.parentNode, ifrDoc).attr('href') && $('#neoCMSImgLink').val() != $(ele.parentNode, ifrDoc).attr('href')) $(ele.parentNode, ifrDoc).attr({href: $('#neoCMSImgLink').val()});
    if ($(ele.parentNode, ifrDoc).attr('rel') && $('#neoCMSImgRel').val() != $(ele.parentNode, ifrDoc).attr('rel')) $(ele.parentNode, ifrDoc).attr({rel: $('#neoCMSImgRel').val()});
    cancel();
    repDone(parent, areas, undoAreas)
};
repDone = function (rep, areas, undoAreas) {
    $('body', ifrDoc).css('display', 'none').css('display', 'block');
    $(rep, ifrDoc).addClass('neoCMSEle');
    var editNum = $(rep, ifrDoc).attr('neocmsid').replace('neoCMSEditArea', '');
    var areaNum = $(rep, ifrDoc).attr('class').match(/neoCMSRepeatArea[0-9]+/g)[0].replace('neoCMSRepeatArea', '');
    var content = $(rep, ifrDoc).clone().find('.neoCMSPlaceholder').remove().end().find('.neoCMSRep').each(function () {
        $(this, ifrDoc).replaceWith($(this, ifrDoc).removeAttr('neocmsid').removeAttr('style').removeClass('neoCMSEditArea').removeClass('neoCMSRep').removeClass('ui-draggable').find('a.neoCMSRepCopyDrag, a.neoCMSRepDelete').remove().end().clone())
    }).end().html();
    content = cleanContent(content, true);
    createUndo(undoAreas[areaNum]);
    if (!$('#neoCMSEditTA' + editNum, ifrDoc).length) $('<textarea/>', ifrDoc).addClass('edit').attr({
        name: 'edit' + editNum,
        id: 'neoCMSEditTA' + editNum,
        rows: '1',
        cols: '1'
    }).appendTo($('#neoCMSPageEdits', ifrDoc));
    if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + editNum, ifrDoc)[0].textContent = content; else $('#neoCMSEditTA' + editNum, ifrDoc)[0].innerText = content;
    undoC++;
    redoOff();
    linkBind();
    neocmsRepBind();
    $('.neoCMSEle', ifrDoc).removeClass('neoCMSEle');
    createIcons()
};
repDeletePrompt = function (ele) {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Delete Repeatable');
            $('#neoCMSPrompt p').html('Are you sure you want to delete this element?');
            $('#neoCMSPromptBtn').attr('title', 'delete').html('delete').bind('click', function () {
                repDelete(ele)
            });
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt('nodrag')
        }))
    }
};
repDelete = function (ele) {
    var areas = repAreas();
    var undoAreas = repAreas(true);
    var parent = $(ele, ifrDoc).parents('.neocmsRepeatArea', ifrDoc);
    $(ele, ifrDoc).parents('.neoCMSRep').remove();
    repDone(parent, areas, undoAreas);
    cancel()
};
frameShim = function () {
    $('#neoCMSFrameShim').css('display', 'block').height($(document).height()).width($(document).width()).unbind('mouseup').bind('mouseup', frameShimDestroy)
};
frameShimDestroy = function () {
    $('#neoCMSFrameShim').css('display', 'none')
};
getBookmark = function (type) {
    bm = tinyMCE.activeEditor.selection.getBookmark(type)
};
setBookmark = function () {
    tinyMCE.activeEditor.selection.moveToBookmark(bm)
};
keyHandler = function () {
    $(document).bind('keypress', function (e) {
        switch (e.which || e.keyCode) {
            case 27:
                $('#neoCMSUploadIframe, #neoCMSFileFrame, , #neoCMSFolderFrame').remove();
                cancel()
        }
    });
    var tinyDoc = getTinyDoc();
    if (tinyDoc) {
        tinyKeyEvents(tinyDoc);
        $('img', tinyDoc).bind('mousedown', function () {
            tinyMCE.execCommand("mceSelectNode", false, this)
        })
    }
    $(document).bind('keydown', function (e) {
        if (e.metaKey) {
            $(document).bind('keydown', function (e) {
                switch (e.which || e.keyCode) {
                    case 37:
                        e.preventDefault();
                        break;
                    case 39:
                        e.preventDefault();
                        break
                }
            })
        }
        switch (e.which || e.keyCode) {
            case 8:
                if (!$('#neoCMSPromptWrap').length) {
                    e.preventDefault();
                    return true
                }
        }
    })
};
tinyKeyEvents = function (tinyDoc) {
    $(tinyDoc).unbind('keyup').unbind('keydown').bind('keydown', function (e) {
        var ed = tinyMCE.activeEditor.selection.getNode();
        switch (e.which || e.keyCode) {
            case 224:
            case 17:
                $(tinyDoc).unbind('keydown').bind('keydown', function (e) {
                    switch (e.which || e.keyCode) {
                        case 65:
                            if (tinyDoc.createRange) {
                                var rng = tinyDoc.createRange(),
                                    s = document.getElementById('neoCMSEditContent_ifr').contentWindow.getSelection() || window.getSelection();
                                if (s.rangeCount > 0) s.removeAllRanges();
                                rng.selectNodeContents($('[neocmsid]', tinyDoc)[0]);
                                s.addRange(rng)
                            } else {
                                var rng = tinyDoc.body.createTextRange();
                                rng.moveToElementText($('[neocmsid]', tinyDoc)[0]);
                                rng.select()
                            }
                            e.preventDefault();
                            break
                    }
                }).unbind('keyup').bind('keyup', function (e) {
                    switch (e.which || e.keyCode) {
                        case 224:
                        case 17:
                            tinyKeyEvents(tinyDoc);
                            break
                    }
                });
                break;
            case 27:
                cancel();
                break;
            case 9:
                var tag = $(ed, tinyDoc).parent()[0].tagName,
                    tag = (tag == 'LI') ? $(ed, tinyDoc).parent().parent()[0].tagName : tag,
                    newed = '<' + tag + '><li id="caret">' + $(ed, tinyDoc).html() + '</li></' + tag + '>';
                if (ed.tagName == 'LI') {
                    $(ed, tinyDoc).replaceWith(newed);
                    setCaret($('#caret', tinyDoc)[0], tinyDoc);
                    e.preventDefault()
                } else if ($(ed, tinyDoc).parent()[0].tagName == 'LI') {
                    $(ed, tinyDoc).parent().replaceWith(newed);
                    setCaret($('#caret', tinyDoc)[0], tinyDoc);
                    e.preventDefault()
                }
                break;
            case 16:
                $(tinyDoc).unbind('keydown').bind('keydown', function (e) {
                    var ed = tinyMCE.activeEditor.selection.getNode();
                    switch (e.which || e.keyCode) {
                        case 9:
                            if (ed.tagName.match(/LI/) && $(ed).parents('ul,ol').length > 1) {
                                var newed = $(ed, tinyDoc).add($(ed, tinyDoc).nextAll('li')),
                                    edpar = $(ed, tinyDoc).parent();
                                if (newed.length != $(edpar).children().length) $(newed, tinyDoc).insertAfter(edpar); else $(newed, tinyDoc).insertBefore(edpar);
                                if (!$(edpar).children().length) edpar.remove();
                                setCaret($(ed)[0], tinyDoc);
                                e.preventDefault()
                            }
                            break
                    }
                }).unbind('keyup').bind('keyup', function (e) {
                    switch (e.which || e.keyCode) {
                        case 16:
                            tinyKeyEvents(tinyDoc);
                            break
                    }
                });
                break
        }
    }).focus(function () {
        tinyKeyEvents(tinyDoc)
    })
};
setCaret = function (node, doc) {
    var ntxt = $(node, doc).html();
    if (doc.createRange) {
        var rng = doc.createRange(),
            s = document.getElementById('neoCMSEditContent_ifr').contentWindow.getSelection() || window.getSelection();
        if (s.rangeCount > 0) s.removeAllRanges();
        if (ntxt == '<br>') rng.selectNodeContents(node); else {
            rng.setStart(node, 1);
            rng.setEnd(node, 1)
        }
        s.addRange(rng)
    } else {
        var rng = doc.body.createTextRange();
        if (ntxt == '<br>') $(node, doc).empty();
        rng.moveToElementText(node);
        rng.collapse(false);
        rng.select()
    }
    $('#caret', doc).removeAttr('id')
};
retUrl = function () {
    if (frames['neoCMSPageSrc'].contentWindow) ret = frames['neoCMSPageSrc'].contentWindow.location.href; else ret = frames['neoCMSPageSrc'].location.href;
    return ret
};
getTinyDoc = function () {
    if ($('#neoCMSEditContent_ifr').length) {
        var tinyDoc = document.getElementById('neoCMSEditContent_ifr');
        tinyDoc = (tinyDoc.contentWindow) ? tinyDoc.contentWindow.document : tinyDoc.contentDocument
    } else tinyDoc = false;
    return tinyDoc
};
saveFeedback = function (message) {
    userBar(message);
    $('#neoCMSPageEdits textarea, #neoCMSPubIframe', ifrDoc).remove()
};
userBar = function (message) {
    if (!$('#neoCMSUserBar').length) {
        $('<div/>').addClass('neoCMSUserBar').attr('id', 'neoCMSUserBar').insertAfter('#neoCMSTopBanner').append($('<div/>').addClass('neoCMSUserBarIn').attr('id', 'neoCMSUserBarIn').append($('<p/>')));
        if (message.match(/successfully/gi)) {
            $('#neoCMSUserBar').find('p').html('<strong>' + message + '</strong>').end().css('opacity', '0.4').animate({opacity: '1'}, 800);
            if (message.match(/published/gi)) $('#neoCMSRestore a').attr('title', 'Restore the last published version').bind('click', restorePrompt).removeAttr('style');
            $('#neoCMSUserBarIn').prepend($('<a/>').attr('href', 'javascript:').html('Refresh This Page').bind('click', ifrReload));
            var userBarFade = setTimeout(function () {
                $('#neoCMSUserBar').animate({opacity: '0'}, 300, function () {
                    $('#neoCMSUserBar').remove()
                })
            }, 5500)
        } else {
            $('#neoCMSUserBar').find('p').html(message).end().hide().css({
                opacity: '1.0',
                backgroundColor: '#c6242b'
            }).slideDown(300);
            $('#neoCMSUserBarIn').prepend($('<a/>').attr('href', 'javascript:').html('Hide This Bar').bind('click', hideUserBar))
        }
    } else {
        $('#neoCMSUserBar').remove();
        window.clearTimeout(userBarFade);
        userBar(message)
    }
};
hideUserBar = function () {
    if ($('#neoCMSUserBar').css('position') == 'relative') $('#neoCMSUserBar').animate({marginTop: '-26px'}, 300, function () {
        $('#neoCMSUserBar').remove()
    }); else $('#neoCMSUserBar').animate({opacity: '0'}, 200, function () {
        $('#neoCMSUserBar').remove()
    })
};
change = function (iid, type) {
    if (type == 'neoCMSRepIcon') {
        var ele = $('[neocmsid=neoCMSRepEdit' + iid + ']', ifrDoc)[0];
        $(ele, ifrDoc).parents('.neocmsRepeatArea').unbind('mousedown')
    } else var ele = $('[neocmsid=neoCMSEditArea' + iid + ']', ifrDoc)[0];
    overlay(ele)
};
ifrReload = function () {
    $('#neoCMSPageSrc').unbind('load');
    if (frames['neoCMSPageSrc'].contentWindow) {
        window.frames['neoCMSPageSrc'].contentWindow.location.reload(true);
        window.frames['neoCMSPageSrc'].contentWindow.location.reload(true)
    } else {
        window.frames['neoCMSPageSrc'].location.reload(true);
        window.frames['neoCMSPageSrc'].location.reload(true)
    }
    $('#neoCMSPageSrc').bind('load', initPage)
};
editorStyles = function (tinyDoc) {
    $('link, style', ifrDoc).not('[media="print"], [media="handheld"], [app="neocms"]').each(function () {
        var cssHref = $(this, ifrDoc).attr('href');
        if (cssHref) {
            cssHref = (cssHref.match(/^http(s*):\/\//)) ? cssHref : fpath() + cssHref;
            var cssDiv = $('<div/>', ifrDoc).append($(this, ifrDoc).clone().attr('href', cssHref));
            $('head', tinyDoc).html($('head', tinyDoc).html() + $(cssDiv, ifrDoc).html());
            cssDiv = null
        } else {
            var cssDiv = $('<div/>', ifrDoc).append($(this, ifrDoc).clone());
            $('head', tinyDoc).html($('head', tinyDoc).html() + $(cssDiv, ifrDoc).html());
            cssDiv = null
        }
    })
};
windowOrient = function () {
    if ($('li#neoCMSFullscreen a.neoCMSMinimize').length) $('#neoCMSEditWrap .mceIframeContainer').width('auto');
    var ele = $('.neoCMSEle', ifrDoc)[0];
    if (ele) {
        var tinyDoc = getTinyDoc();
        setTimeout(function () {
            editorSize(tinyDoc, ele)
        }, 100)
    }
};
hideFlash = function () {
    $('object, embed', ifrDoc).addClass('neoCMSFlash');
    if ($('#neoCMSEditContent_ifr').length) {
        var tinyDoc = getTinyDoc();
        $('object, embed', tinyDoc).addClass('neoCMSFlash')
    }
};
editorSize = function (tinyDoc, ele) {
    var eleMarLeft = (getCurStyle($(ele, ifrDoc)[0], 'margin-left') != 'auto') ? getCurStyle($(ele, ifrDoc)[0], 'margin-left') : 0,
        eleMarRight = (getCurStyle($(ele, ifrDoc)[0], 'margin-right') != 'auto') ? getCurStyle($(ele, ifrDoc)[0], 'margin-right') : 0,
        editIfrWidth = $(ele, ifrDoc).width() + parseInt(eleMarLeft) + parseInt(eleMarRight) + 44,
        editIfrWidth = (editIfrWidth - (parseInt(eleMarLeft) + parseInt(eleMarRight)) == '44' && $(ele, ifrDoc).css('width') != 'auto') ? editIfrWidth + parseInt($(ele, ifrDoc).css('width')) : editIfrWidth;
    if ($.browser.msie) $('#neoCMSEditContent_ifr').width(editIfrWidth + 20); else $('#neoCMSEditContent_ifr').width(editIfrWidth);
    $('.mceIframeContainer').height('auto');
    var ifrBodyHeight = tinyDoc.body.scrollHeight,
        windowHeight = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight;
    if ((ifrBodyHeight + 63) > (windowHeight - 130)) $('td.mceIframeContainer').height(windowHeight - 130); else setTimeout(function () {
        $('td.mceIframeContainer, #neoCMSEditContent_ifr', $('#neoCMSEditWrap')).height(ifrBodyHeight)
    }, 0);
    var wrapWidth = '';
    if ($('#neoCMSEditContent_bullist').length && $('#neoCMSEditContent_ifr')[0].offsetWidth + 10 <= 640) wrapWidth = 640; else {
        if ($.browser.msie) wrapWidth = $('#neoCMSEditContent_ifr').width() + 20; else wrapWidth = $('#neoCMSEditContent_ifr').width() + 8
    }
    if (wrapWidth < 300) wrapWidth = 300;
    $('#neoCMSEditWrap, #neoCMSEditContent_tbl td.mceIframeContainer, #neoCMSEditContent_tbl td.mceToolbar').width(wrapWidth).css('min-width', wrapWidth);
    $('#neoCMSEditTopUl').css('min-width', wrapWidth - 14);
    $('#neoCMSEditWrap').animate({opacity: 1}, 'slow').find('#neoCMSEditDone a').bind('click', function () {
        if ($(ele).attr('class').match(/neocmsRepeat\b/g)) repEditDone(ele); else done(ele)
    }).end().find('#neoCMSEditCancel a').bind('click', cancel).end().find('a.fullscreen').unbind('click').bind('click', function () {
        editorFullscreen($(this).attr('rel'))
    }).removeClass('neoCMSMinimize').attr({title: 'Maximize this window'}).html('Maximize this window').end().find('#neoCMSEditHTML').unbind('click').bind('click', function () {
        htmlPrompt(ele)
    }).end();
    dragOn($('#neoCMSEditWrap')[0], $('.neoCMSEditTopBar')[0]);
    editorPos(tinyDoc, ele, ifrBodyHeight + 63)
};
editorPos = function (tinyDoc, ele, wrapHeight) {
    var offset = $(ele, ifrDoc).offset();
    $('#neoCMSEditContent_ifr').css({float: 'none', margin: '0'});
    var sTop = (ifrDoc.documentElement.scrollTop) ? ifrDoc.documentElement.scrollTop : ifrDoc.body.scrollTop,
        sLeft = (ifrDoc.documentElement.scrollLeft) ? ifrDoc.documentElement.scrollLeft : ifrDoc.body.scrollLeft;
    if ((offset.top - sTop - 36) < 0) $('#neoCMSEditWrap').css('top', '0px'); else {
        if (!$('#neoCMSUserBar').length) $('#neoCMSEditWrap').css('top', offset.top - sTop - 34); else $('#neoCMSEditWrap').css('top', offset.top - sTop - 14)
    }
    if ((offset.left - sLeft - 14) < 0) $('#neoCMSEditWrap').css('left', '0px'); else if ((offset.left + $('#neoCMSEditWrap').width()) > $('body', ifrDoc).width()) {
        $('#neoCMSEditWrap').css('left', (offset.left - sLeft - $('#neoCMSEditWrap').width() + $(ele, ifrDoc).width() + 32));
        $('#neoCMSEditContent_ifr').css({float: 'right', clear: 'both'})
    } else {
        $('#neoCMSEditWrap').css('left', (offset.left - sLeft - 24));
        $('#neoCMSEditContent_ifr').css('float', 'left')
    }
    var tHeight = offset.top - sTop + wrapHeight, tWidth = offset.left - sLeft + $('#neoCMSEditWrap').width();
    if (tHeight > $(window).height()) {
        frames['neoCMSPageSrc'].scrollTo(sLeft, offset.top - 48);
        sTop = (ifrDoc.documentElement.scrollTop) ? ifrDoc.documentElement.scrollTop : ifrDoc.body.scrollTop;
        if ((offset.top + sTop - 48) < 0) $('#neoCMSEditWrap').css('top', '0px'); else {
            if (!$('#neoCMSUserBar').length) $('#neoCMSEditWrap').css('top', offset.top - sTop - 34); else $('#neoCMSEditWrap').css('top', offset.top - sTop - 16)
        }
    }
    if (tWidth > $(window).width()) {
        frames['neoCMSPageSrc'].scrollTo(offset.left - 48, sTop);
        sLeft = (ifrDoc.documentElement.scrollLeft) ? ifrDoc.documentElement.scrollLeft : ifrDoc.body.scrollLeft;
        if ((offset.left + sLeft - 48) < 0) $('#neoCMSEditWrap').css('left', '0px'); else $('#neoCMSEditWrap').css('left', offset.left - sLeft - 48)
    }
};
editorCleanUp = function () {
    var tinyDoc = getTinyDoc();
    fixImgs();
    $('body', tinyDoc).find('.neoCMSIcon').remove().end().find('img').each(function () {
        $(this, tinyDoc).removeAttr('_mce_src')
    }).end().find('a').each(function () {
        if ($(this, tinyDoc).attr('href')) {
            var eleAhref = $(this, tinyDoc).attr('href');
            if (eleAhref.match(window.location)) $(this, tinyDoc).attr('href', eleAhref.replace(window.location + "#", "#"));
            $(this, tinyDoc).removeAttr('_mce_href')
        }
    }).end().find('*').each(function () {
        if (getCurStyle(this, 'display') == 'none' || getCurStyle(this, 'visibility') == 'hidden') $(this, tinyDoc).css({
            display: 'block',
            visibility: 'visible'
        })
    }).end().bind('mouseup', function () {
        var ed = tinyMCE.activeEditor.selection.getNode();
        if (ed.tagName == 'EMBED' && $(ed, tinyDoc).parent()[0].tagName == 'OBJECT') tinyMCE.execCommand('mceSelectNodeDepth', false, 1)
    })
};
editorFullscreen = function (prompt) {
    $(prompt).height($(document).height()).css({
        opacity: '1',
        top: '0',
        left: '0'
    }).find('a.fullscreen').addClass('neoCMSMinimize').unbind('click').bind('click', function () {
        if (prompt == '#neoCMSEditWrap') windowOrient(); else htmlPrompt()
    }).attr('title', 'Minimize this window').html('Minimize this window');
    if (prompt == '#neoCMSEditWrap') {
        $(prompt).width($(window).width());
        $('.mceIframeContainer').height($(document).height() - 84).width($(window).width());
        $('#neoCMSEditContent_ifr').css({float: 'none', margin: '0 auto'})
    } else {
        $(prompt).width($(window).width() - 40).css('margin', '0');
        $('#neoCMSHtmlEditAreaTA, #neoCMSCodeWrap').height($(window).height() - 130).width($(prompt).width() - 28).focus()
    }
};
neocmsGlobals = function () {
    $('#neoCMSRestore a').attr('title', 'Restore the last published version').bind('click', restorePrompt);
    if (!$('#neoCMSGlobalUndo').length || undoC == 0) $('#neoCMSUndo a').css({
        opacity: '0.5',
        backgroundPosition: '-62px 0',
        cursor: 'default'
    }).unbind('click', undo).attr('title', 'Cannot Undo at this time'); else $('#neoCMSUndo a').css('opacity', '1.0').bind('click', undo).attr('title', 'Undo').removeAttr('style');
    if (!$('#neoCMSGlobalRedo').length || !$('#neoCMSGlobalRedo .neoCMSEle' + undoC).length) $('#neoCMSRedo a').css({
        opacity: '0.5',
        backgroundPosition: '-134px 0',
        cursor: 'default'
    }).unbind('click', redo).attr('title', 'Cannot Redo at this time'); else $('#neoCMSRedo a').css('opacity', '1.0').bind('click', redo).attr('title', 'Redo').removeAttr('style');
    if (!$('#neoCMSPageEdits textarea', ifrDoc).length || undoC == 0) {
        $('#neoCMSCxl a').css('opacity', '0.5').unbind('click', cancelAllPrompt).attr('title', 'Cannot Cancel All at this time').css({
            backgroundPosition: '-206px 0',
            cursor: 'default'
        });
        $('#neoCMSPublish a').css('opacity', '0.5').unbind('click', publish).attr('title', 'You must make edits before publishing').css({
            backgroundPosition: '-385px 0',
            cursor: 'default'
        })
    } else {
        $('#neoCMSCxl a').css('opacity', '1.0').bind('click', cancelAllPrompt).attr('title', 'Clear all of these changes').removeAttr('style');
        $('#neoCMSPublish a').css('opacity', '1.0').bind('click', publish).attr('title', 'Publish these changes to your site').removeAttr('style')
    }
};
changePageEdits = function (ele, eleEditNum) {
    var eleEditNum = (eleEditNum) ? eleEditNum : $(ele, ifrDoc).attr('neocmsid').replace('neoCMSEditArea', '');
    if ($('#neoCMSEditTA' + eleEditNum, ifrDoc).length) {
        if (ele.tagName == 'IMG') {
            var eleClass = $(ele, ifrDoc).attr('class').replace(/(\s)*\bneoCMSEle[0-9]+\b(\s)*/gi, '');
            var changeContent = "src::" + $(ele, ifrDoc).attr('src') + ",alt::" + $(ele, ifrDoc).attr('alt') + ",class::" + eleClass + ",width::" + $(ele, ifrDoc).attr('width') + ",height::" + $(ele, ifrDoc).attr('height') + ",link::" + $(ele.parentNode, ifrDoc).attr('href') + ",rel::" + $(ele.parentNode, ifrDoc).attr('rel');
            if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = changeContent; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = changeContent
        } else {
            var content = $(ele, ifrDoc).find('a.neoCMSRepCopyDrag, a.neoCMSRepDelete').remove().end().html();
            content = cleanContent(content, true);
            if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = content; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = content
        }
    }
};
cleanContent = function (c, t) {
    if (!t) {
        c = c.replace(/<p[^>]*>/gi, '');
        c = c.replace(/<\/p>/gi, '<br />')
    }
    if ($.browser.msie) {
        c = c.replace(/(\s[a-zA-Z^>\s]+=)([^\"\s\'>]+)(\s*)/gi, '$1"$2"$3');
        c = c.replace(/<\/*[^\s>]+/gi, function (w) {
            return w.toLowerCase()
        });
        c = c.replace(/(<(dt|dd|li)[^>]*>[^<\n\r]*)/gi, "$1</$2>");
        c = c.replace(/[\s\r\n]*<\/(dt|dd|li)><\/(dt|dd|li)>/gi, "</$1>")
    }
    c = c.replace(/(style|_mce_[^\s=]*|unselectable|contentEditable|neocmsid|jQuery[0-9]*)=\"[^\"]*\"\s*/gi, '');
    c = c.replace(/(<\/*[^>]+)\s>/gi, '$1>');
    c = c.replace(/(neoCMSEditArea|neoCMSRep|ui-draggable)\s*/gi, '');
    c = c.replace(/<(\/?)b>|<b(\s[^>]+)>/gi, '<$1strong$2>');
    c = c.replace(/<(\/?)i>|<i(\s[^>]+)>/gi, '<$1em$2>');
    c = c.replace(window.location + "#", "#");
    return c
};
getCurStyle = function (ele, cur) {
    var tinyDoc = getTinyDoc(), doc = (tinyDoc) ? tinyDoc : ifrDoc, gs, cs = $(ele, doc)[0].currentStyle;
    if (cs) {
        if (cur.match(/-/)) cur = cur.replace(/-([A-z])/gi, function (a, b) {
            return b.toUpperCase()
        });
        gs = (cs[cur]) ? cs[cur] : $(ele, doc).css(cur);
        if (gs == undefined) gs = ''
    } else gs = doc.defaultView.getComputedStyle(ele, null).getPropertyValue(cur);
    return gs
};
contrast = function () {
    var tinyDoc = getTinyDoc();
    $('.mceIframeContainer').css('background-color', 'rgb(255,255,255)');
    $('#neoCMSEditorContrast').bind('mousewheel', function (event, delta) {
        var vel = Math.round(Math.abs(delta) * 20);
        var color = $('.mceIframeContainer').css('background-color');
        var rgb = color.replace(/(rgb\(\s*|\))/, '');
        rgb = rgb.split(',');
        var r = parseInt(rgb[0]);
        var g = parseInt(rgb[1]);
        var b = parseInt(rgb[2]);
        if (delta > 0) {
            r += vel;
            g += vel;
            b += vel
        } else {
            r -= vel;
            g -= vel;
            b -= vel
        }
        var newcolor;
        if (r > 255) newcolor = 'rgb(255,255,255)'; else if (r < 0) newcolor = 'rgb(0,0,0)'; else newcolor = 'rgb(' + r + ',' + g + ',' + b + ')';
        $(this).css('background-color', newcolor);
        $('.mceIframeContainer').css('background-color', newcolor);
        return false
    }).css('background-color', 'rgb(255,255,255)')
};
folderLoader = function (type) {
    var folderIframe;
    try {
        folderIframe = document.createElement('<iframe name="neoCMSFolderFrame" >')
    } catch (ex) {
        folderIframe = document.createElement('iframe')
    }
    $(folderIframe).attr({
        id: 'neoCMSFolderFrame',
        name: 'neoCMSFolderFrame',
        src: 'core/folderload.php?type=' + type,
        application: 'yes'
    }).css('display', 'none').appendTo('body')
};
folderOption = function (path, type) {
    if (type == 'img') $('#neoCMSImgFolder').append($('<option/>').html(path).val(path)); else $('#neoCMSFolder').append($('<option/>').html(path).val(path))
};
destroyFolderLoader = function () {
    $('#neoCMSFolderFrame').remove();
    $('#neoCMSImgFolderDefault, #neoCMSFolderDefault').html('Please select a folder')
};
fixImgs = function (paste) {
    var tinyDoc = getTinyDoc();
    $('img', tinyDoc).each(function () {
        var eleSrc = $(this, tinyDoc).attr('_mce_src') || $(this, tinyDoc).attr('src'),
            eleSrc = (paste) ? eleSrc.replace(/^(\.\.\/)/, '') : eleSrc.replace(/^(\/)/, ''),
            eleSrc = (eleSrc.match(/^http(s*):\/\//)) ? eleSrc : fpath() + eleSrc;
        $(this, tinyDoc).attr('src', eleSrc)
    })
};
destroyIcons = function () {
    $('.neoCMSIcon, .neoCMSRepIcon', ifrDoc).remove()
};
eleHTML = function (ele) {
    var vid = $(ele, ifrDoc).clone().wrap($('<div/>', ifrDoc)), vidHTML = $(vid, ifrDoc).parent().html(),
        vidHTML = cleanContent(vidHTML, true);
    return vidHTML
};
createTextArea = function (ele) {
    if (!$('#neoCMSEditWrap').length) {
        hideFlash();
        $(ele, ifrDoc).addClass('neoCMSEle').css('visibility', 'hidden');
        $('body').prepend($('<div/>').attr('id', 'neoCMSEditWrapTemp').load('templates/editor.html', function () {
            $('#neoCMSEditWrapTemp').replaceWith($('#neoCMSEditWrapTemp').html());
            var neoCMSEditContent = $('<span/>', ifrDoc).addClass('neoCMSEditContent').attr('id', 'neoCMSEditContent').append($(ele, ifrDoc).clone().attr({
                neocmsid: $(ele, ifrDoc).attr('neocmsid'),
                style: 'float: none !important; visibility: visible; position: relative !important; margin: 0 !important; top: 0 !important; left: 0 !important; *zoom: 1; overflow: hidden;'
            }).addClass($(ele, ifrDoc).attr('class')).removeClass('neocms').removeClass('neoCMSEle').removeClass('neoCMSEditArea').find('.neoCMSFlash').removeClass('neoCMSFlash').end().find('a.neoCMSRepCopyDrag, a.neoCMSRepDelete').remove().end().find('*').enableSelection().end());
            var c = 0, bp = parentBP(ele, false), t = ($(ele, ifrDoc)[0].tagName.match(/(h[0-9]|p)/i)) ? false : true,
                html5 = 'abbr article aside audio canvas details figcaption figure footer header hgroup mark meter nav output progress section summary time video';
            if ((!html5.match($(ele, ifrDoc)[0].tagName) && $.browser.msie) || !$.browser.msie) {
                $(ele, ifrDoc).parents().not('body,html').each(function () {
                    var styles = 'margin: 0 !important; width: auto !important; height: auto !important; float: none !important; position: relative !important; top: 0 !important; left: 0 !important; border: none !important; -moz-box-shadow: 0 !important; -webkit-box-shadow: 0 !important; box-shadow: 0 !important;';
                    if (c == 0) {
                        styles += ' padding: 20px !important;';
                        if (bp.img) {
                            bp.left += 20;
                            bp.top += 20;
                            styles += ' background-position: ' + bp.left + 'px ' + bp.top + 'px !important;'
                        }
                    } else {
                        styles += ' padding: 0 !important;';
                        if (bp.img) styles += ' background-position: ' + bp.left + 'px ' + bp.top + 'px !important;'
                    }
                    bp = parentBP(this, bp);
                    $(neoCMSEditContent, ifrDoc).wrapInner($(this, ifrDoc).clone().empty().addClass('clearfix').attr('style', styles).removeAttr('neocmsid'));
                    c++
                })
            } else neoCMSEditContent = neoCMSEditContent.innerHTML;
            var neoCMSDumm = $('<div/>', ifrDoc).append(neoCMSEditContent);
            $('#neoCMSEditWrap').css('opacity', '0').html($('#neoCMSEditWrap').html() + $(neoCMSDumm, ifrDoc).html());
            tinyMCEBuilder(ele, t)
        }))
    }
};
parentBP = function (t, bp) {
    var par = $(t, ifrDoc).parent()[0];
    if (!bp) {
        bp = {top: 0, left: 0, offleft: 0, offtop: 0, img: false}
    }
    var off = $(t, ifrDoc).offset(), offpar = $(par, ifrDoc).offset(),
        pbp = getCurStyle($(par, ifrDoc)[0], 'background-position').split(' '), l = '', t = '', loff = '', toff = '';
    loff = 0 - (off.left - offpar.left);
    toff = 0 - (off.top - offpar.top);
    if (!pbp[0].match(/%/) || pbp[0] == '0%') l = bp.offleft + loff; else {
        var p = 0.01 * parseInt(pbp[0]);
        l = Math.round(p * (loff * -1))
    }
    if (pbp[1] && (!pbp[1].match(/%/) || pbp[1] == '0%')) t = bp.offtop + toff; else {
        var p = 0.01 * parseInt(pbp[1]);
        t = Math.round(p * (toff * -1))
    }
    var bp = {
        left: l,
        top: t,
        offleft: bp.offleft + loff,
        offtop: bp.offtop + toff,
        img: (par.tagName != 'BODY' && getCurStyle(par, 'background-image') != 'none') ? true : false
    };
    return bp
};
tinyMCEBuilder = function (ele, t) {
    var tools = null, blocks = "p,pre,blockquote,h1,h2,h3,h4,h5,h6,dd,dt",
        blockreg = new RegExp('^(' + blocks.replace(/,/gi, '|') + ')$', 'i');
    brs = (ele.tagName.match(blockreg)) ? true : false;
    tinyMCE.init({
        mode: "exact",
        theme: "advanced",
        plugins: "deflist,file,video,images,links,paste,tables",
        theme_advanced_buttons1: (!t) ? ((neoCMSFiles == 'Y') ? 'bold,italic,link,image,file' : 'bold,italic,link,image') : ((neoCMSFiles == 'Y') ? 'formatselect,bold,italic,link,image,video,file,bullist,numlist,deflist,table' : 'formatselect,bold,italic,link,image,video,bullist,numlist,deflist,table'),
        theme_advanced_buttons2: "",
        theme_advanced_buttons3: "",
        theme_advanced_buttons4: "",
        theme_advanced_blockformats: blocks,
        theme_advanced_toolbar_location: "top",
        theme_advanced_toolbar_align: "left",
        height: 'auto',
        width: 'auto',
        apply_source_formatting: true,
        remove_linebreaks: true,
        entity_encoding: '',
        cleanup: true,
        cleanup_on_startup: ($.browser.msie) ? false : true,
        remove_trailing_nbsp: true,
        use_native_selects: true,
        extended_valid_elements: "@[neocmsid|id|dir|class|style],li,dt,dd,div,abbr,article,aside,audio,canvas,datalist,details,figure,figcaption,footer,header,hgroup,mark,menu,meter,nav,output,progress,section,summary,time,video,pre,code",
        invalid_elements: "script",
        skin: "default",
        convert_urls: false,
        elements: "neoCMSEditContent",
        verify_html: true,
        object_resizing: false,
        auto_resize: false,
        paste_auto_cleanup_on_paste: true,
        setup: function (ed) {
            ed.onPaste.add(function (ed, e) {
                setTimeout(function () {
                    fixImgs(true)
                }, 100)
            })
        },
        oninit: "tinyInit"
    });
    tinyInit = function () {
        var tinyDoc = getTinyDoc();
        editorStyles(tinyDoc);
        if ($('body', ifrDoc).attr('class')) $('body', tinyDoc).addClass($('body', ifrDoc).attr('class'));
        if ($('body', ifrDoc).attr('id')) $('body', tinyDoc).attr('id', $('body', ifrDoc).attr('id'));
        if (neoCMSHTMLEdit == 'Y') {
            $('<a/>').attr({
                id: 'neoCMSEditHTML',
                href: 'javascript:',
                title: 'View and edit the HTML'
            }).html('html').prependTo('td.mceToolbar')
        }
        $('#neoCMSEditContent_toolbar1 a').each(function () {
            $(this).find('span').html($(this).attr('id').replace('neoCMSEditContent_', ''))
        });
        if ($.browser.msie) ieBind();
        contrast();
        editorCleanUp();
        keyHandler();
        setTimeout(windowOrient, 0)
    }
};
ieBind = function () {
    var tinyDoc = getTinyDoc();
    $('#neoCMSEditContent_ifr').attr('allowTransparency', 'true').css({background: 'transparent'});
    $('[neocmsid]', $('body', tinyDoc)).bind('mousedown', function (e) {
        getInnerEle(e)
    })
};
getInnerEle = function (e) {
    var tinyDoc = getTinyDoc(), range = tinyDoc.body.createTextRange();
    range.moveToElementText($(e.target, tinyDoc)[0]);
    range.collapse(false);
    range.select();
    $('[neocmsid]', tinyDoc).unbind('mousedown').bind('blur', function (e) {
        $(this, tinyDoc).bind('mousedown', function (e) {
            getInnerEle(e)
        })
    }).find('*').unbind('mousedown').bind('blur', function (e) {
        $(this, tinyDoc).bind('mousedown', function (e) {
            getInnerEle(e)
        })
    })
};
tinyEvents = function (e) {
    var tinyDoc = getTinyDoc();
    if (e.type == 'paste') {
        e.stopPropagation()
    }
};
createUndo = function (ele) {
    if (!$('#neoCMSGlobalUndo').length) {
        $('<div/>').attr('id', 'neoCMSGlobalUndo').appendTo('body');
        $('<div/>').attr('id', 'neoCMSUndoNum' + undoC).appendTo('#neoCMSGlobalUndo');
        $('#neoCMSUndoNum' + undoC).html($('<div/>', ifrDoc).append($(ele, ifrDoc).clone().addClass('neoCMSEle' + undoC)).html())
    } else {
        if (!$('#neoCMSUndoNum' + undoC).length) {
            $('<div/>').attr('id', 'neoCMSUndoNum' + undoC).appendTo('#neoCMSGlobalUndo');
            $('#neoCMSUndoNum' + undoC).html($('<div/>', ifrDoc).append($(ele, ifrDoc).clone().addClass('neoCMSEle' + undoC)).html())
        } else {
            $('#neoCMSUndoNum' + undoC).html('');
            $('#neoCMSUndoNum' + undoC).html($('<div/>', ifrDoc).append($(ele, ifrDoc).clone().addClass('neoCMSEle' + undoC)).html())
        }
    }
};
createRedo = function (neoCMSUndoEle) {
    if (!$('#neoCMSGlobalRedo').length) {
        $('<div/>').attr('id', 'neoCMSGlobalRedo').appendTo('body');
        $('<div/>').attr('id', 'neoCMSRedoNum' + undoC).appendTo('#neoCMSGlobalRedo');
        $('#neoCMSRedoNum' + undoC).html($('<div/>', ifrDoc).append($(neoCMSUndoEle, ifrDoc).clone()).html())
    } else {
        if (!$('#neoCMSRedoNum' + undoC).length) {
            $('<div/>').attr('id', 'neoCMSRedoNum' + undoC).appendTo('#neoCMSGlobalRedo');
            $('#neoCMSRedoNum' + undoC).html($('<div/>', ifrDoc).append($(neoCMSUndoEle, ifrDoc).clone()).html())
        } else {
            $('#neoCMSRedoNum' + undoC).html('');
            $('#neoCMSRedoNum' + undoC).html($('<div/>', ifrDoc).append($(neoCMSUndoEle, ifrDoc).clone()).html())
        }
    }
    $('#neoCMSRedo').css('opacity', '1.0').find('a').attr('title', 'Redo last action').bind('click', redo)
};
showPrompt = function (noDrag, noAnim) {
    hideFlash();
    if (!noAnim) {
        $('#neoCMSPrompt:hidden').css({display: "block", opacity: "0"}).fadeTo(300, 1.0, function () {
            if ($.browser.msie) $(this, ifrDoc)[0].style.removeAttribute('filter')
        })
    } else $('#neoCMSPrompt:hidden').css('display', 'block');
    if ($('#neoCMSPromptCancel').length) $('#neoCMSPromptCancel').bind('click', cancel);
    if (!$('#neoCMSEditWrap').length) keyHandler(); else {
        $(document).unbind('keydown');
        $('#neoCMSPrompt input:first, #neoCMSPrompt textarea:first').focus();
        keyHandler()
    }
    if (!noDrag) dragOn($('#neoCMSPrompt')[0], $('#neoCMSPromptTitle, #neoCMSPromptTop'));
    if ($('#neoCMSEditWrap').length) overlay(null)
};
getEd = function () {
    if ($('#neoCMSEditWrap').length) {
        if (tinyMCE.activeEditor.selection.getNode().tagName == 'IMG' || tinyMCE.activeEditor.selection.getNode().tagName == 'EMBED' || tinyMCE.activeEditor.selection.getNode().tagName == 'OBJECT') return tinyMCE.activeEditor.selection.getNode(); else return false
    } else return false
};
imageEditBuilder = function (ele) {
    if (getTinyDoc()) getBookmark('simple');
    if (!$('#neoCMSPromptWrap').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/image-editor.html', function () {
            if (ele) {
                $(ele, ifrDoc).addClass('neoCMSEle');
                $('#neoCMSImgAltTxt').val($(ele, ifrDoc).attr('alt'));
                var neoCMSEleClass = $(ele, ifrDoc).attr('class').replace(/(?:\s)*\b(neocms|neoCMSEle|neoCMSEle[0-9]+|neocmsRepeat|neoCMSEditArea)\b(?:\s)*/gi, '');
                $('#neoCMSImgClass').val(neoCMSEleClass);
                if (ele.parentNode.tagName == 'A') {
                    $('#neoCMSImgLink').val($(ele.parentNode, ifrDoc).attr('href'));
                    $('#neoCMSImgRel').val($(ele.parentNode, ifrDoc).attr('rel'))
                }
                $('#neoCMSPromptBtn').attr('title', 'Apply these changes').html('done').bind('click', function () {
                    if ($(ele, ifrDoc).attr('class').match(/neocmsRepeat\b/g)) repImageDone(ele); else imageDone(ele)
                });
                imageStatBuilder(ele)
            } else {
                var ed = getEd();
                if (ed) {
                    $('#neoCMSImgAltTxt').val($(ed).attr('alt'));
                    $('#neoCMSImgClass').val($(ed).attr('class'));
                    if (ed.parentNode.tagName == 'A') {
                        $('#neoCMSImgLink').val($(ed.parentNode).attr('href'));
                        $('#neoCMSImgRel').val($(ed.parentNode).attr('rel'))
                    }
                    imageStatBuilder(ed)
                } else {
                    $('#neoCMSPromptTitle').html('Insert Image');
                    imageStatBuilder()
                }
            }
            folderLoader('img');
            showPrompt()
        }))
    }
};
origImgSet = function (ele) {
    var ed = getEd(), ele = (ed) ? ed : (ele) ? ele : $('[neocmsid]', getTinyDoc())[0];
    var elePad = {
        t: parseInt(getCurStyle(ele, 'padding-top')),
        r: parseInt(getCurStyle(ele, 'padding-right')),
        l: parseInt(getCurStyle(ele, 'padding-left')),
        b: parseInt(getCurStyle(ele, 'padding-bottom'))
    };
    origImg = {width: ele.offsetWidth - elePad.l - elePad.r, height: ele.offsetHeight - elePad.t - elePad.b}
};
imageStatBuilder = function (ele, fileSize) {
    var origSize = true;
    if ($('#neoCMSImgStatArea').length) {
        origSize = ($('#neoCMSOrigSize:checked').length) ? true : false;
        $('#neoCMSImgStatArea').remove()
    }
    if (ele) {
        var eleSrc = $(ele, ifrDoc).attr('src'),
            eleSrc = (eleSrc.match(/^http(s*):\/\//)) ? eleSrc : fpath() + eleSrc.replace(/^(\/)/, ''),
            imgName = eleSrc.split('/'), imgName = imgName[imgName.length - 1],
            statArea = '<h4 id="neoCMSImageTitle" class="neoCMSImageTitle">' + imgName + '<span>' + eleSrc + '</span></h4><img alt="' + $(ele).attr('alt') + '" src="' + eleSrc + '" class="neoCMSImgPreview"><form action="javascript:" method="post" id="neoCMSImgStats" class="neoCMSImgStats"><label><input id="neoCMSOrigSize" class="neoCMSOrigSize" type="checkbox"';
        if (origSize) statArea += ' checked="checked"';
        statArea += '>Resize image to fit</label><label><input id="neoCMSImgStatsWidth" name="neoCMSImgStatsWidth" type="text" value="' + $(ele, ifrDoc).width() + '">width:</label><label><input id="neoCMSImgStatsHeight" name="neoCMSImgStatsHeight" type="text" value="' + $(ele, ifrDoc).height() + '">height:</label><label class="neoCMSConstrainLabel"><input id="neoCMSConstrain" class="neoCMSConstrain" type="checkbox">Keep proportions</label></form>';
        $('<div/>').addClass('neoCMSImgStatArea clearfix').attr('id', 'neoCMSImgStatArea').html(statArea).insertAfter('#neoCMSImgChoose');
        $('ul.neoCMSImgProperties').css('display', 'inline');
        $('#neoCMSPrompt').addClass('neoCMSImgVidSelect');
        if (fileSize) {
            $('#neoCMSImgStats').prepend($('<label/>').attr('id', 'neoCMSImgSize').html('file size:').prepend($('<span/>')));
            if (fileSize.length > 3) $('#neoCMSImgSize span').html(fileSize.slice(0, fileSize.length - 3) + ' kb'); else $('#neoCMSImgSize span').html(fileSize + ' bytes')
        }
        var eleIns = ($('img.neoCMSEle', ifrDoc).length || getEd()) ? ele : false;
        if (!$('img.neoCMSEle', ifrDoc).length) {
            if ($('#neoCMSPromptBtn').html() == 'apply') $('#neoCMSPromptBtn').attr('title', 'Insert image').html('insert');
            $('#neoCMSPromptBtn').unbind('click').bind('click', function () {
                imageInsert()
            })
        }
        $('#neoCMSApplyBtn').css('display', 'block');
        if ($(ele, ifrDoc).attr('id') != 'neoCMSUploadImgClone') origImgSet(ele); else if ($('.neoCMSEle', ifrDoc).length) origImgSet($('.neoCMSEle', ifrDoc)[0]); else origImgSet($('[neocmsid]', getTinyDoc())[0]);
        if (origImg) imageOrigSize(ele); else imageOrigSize()
    } else {
        $('#neoCMSPrompt').removeClass('neoCMSImgVidSelect');
        $('ul.neoCMSImgProperties').css('display', 'none');
        $('#neoCMSPromptBtn').attr('title', 'Insert new image').html('apply').bind('click', imageSrc);
        $('#neoCMSApplyBtn').css('display', 'none');
        imageBind()
    }
};
imageOrigSize = function (ele) {
    $('#neoCMSOrigSize').unbind('click').bind('click', function () {
        imageOrigSize(ele)
    });
    if ($('#neoCMSOrigSize:checked').length) {
        var eleHeight = origImg.height;
        if ($('#neoCMSUploadImgClone', ifrDoc).length) {
            var cloneWidth = $('#neoCMSUploadImgClone', ifrDoc)[0].offsetWidth,
                cloneHeight = $('#neoCMSUploadImgClone', ifrDoc)[0].offsetHeight;
            eleHeight = parseInt(cloneHeight) * (parseInt(origImg.width) / parseInt(cloneWidth));
            eleHeight = Math.round(eleHeight)
        }
        $('#neoCMSImgStatsWidth').replaceWith($('<input/>').attr({
            name: 'neoCMSImgStatsWidth',
            id: 'neoCMSImgStatsWidth',
            type: 'text',
            disabled: 'disabled'
        }).css({backgroundColor: '#bbb', color: '#666'}).val(origImg.width));
        $('#neoCMSImgStatsHeight').replaceWith($('<input/>').attr({
            name: 'neoCMSImgStatsHeight',
            id: 'neoCMSImgStatsHeight',
            type: 'text',
            disabled: 'disabled'
        }).css({backgroundColor: '#bbb', color: '#666'}).val(eleHeight));
        $('.neoCMSConstrainLabel').css('color', '#666').find('input').attr({disabled: 'disabled', checked: 'checked'})
    } else {
        var hVal = $('#neoCMSImgStatsHeight').val(), wVal = $('#neoCMSImgStatsWidth').val();
        $('#neoCMSImgStatsWidth').replaceWith($('<input/>').attr({
            name: 'neoCMSImgStatsWidth',
            id: 'neoCMSImgStatsWidth',
            type: 'text'
        }));
        $('#neoCMSImgStatsHeight').replaceWith($('<input/>').attr({
            name: 'neoCMSImgStatsHeight',
            id: 'neoCMSImgStatsHeight',
            type: 'text'
        }));
        if ($('#neoCMSUploadImgClone', ifrDoc).length) {
            $('#neoCMSImgStatsWidth').val($('#neoCMSUploadImgClone', ifrDoc)[0].offsetWidth);
            $('#neoCMSImgStatsHeight').val($('#neoCMSUploadImgClone', ifrDoc)[0].offsetHeight)
        } else {
            $('#neoCMSImgStatsWidth').val(wVal);
            $('#neoCMSImgStatsHeight').val(hVal)
        }
        $('.neoCMSConstrainLabel').css('color', '#ccc').find('input').removeAttr('disabled')
    }
    imageConstrain(ele);
    imageBind()
};
imageConstrain = function (ele) {
    $('#neoCMSConstrain').unbind('click').bind('click', function () {
        imageConstrain(ele)
    });
    if ($('#neoCMSConstrain:checked').length && !$('#neoCMSOrigSize:checked').length) {
        $('#neoCMSImgStatsWidth, #neoCMSImgStatsHeight').bind('keyup', function () {
            var field = (this.id == 'neoCMSImgStatsWidth') ? 'neoCMSImgStatsWidth' : 'neoCMSImgStatsHeight';
            changeSize(field, ele)
        })
    } else {
        $('#neoCMSImgStatsWidth, #neoCMSImgStatsHeight').unbind('keyup')
    }
};
changeSize = function (f, ele) {
    var ele = (ele) ? ele : $('.neoCMSEle', ifrDoc)[0], fval = $('#' + f).val(),
        fWidth = $('#neoCMSImgStatsWidth').val(),
        fHeight = $('#neoCMSImgStatsHeight').val();
    if ($('#neoCMSUploadImgClone', ifrDoc).length) ele = $('#neoCMSUploadImgClone', ifrDoc);
    var eleWidth = $(ele, ifrDoc).width();
    var eleHeight = $(ele, ifrDoc).height();
    var eleRatio = (f == 'neoCMSImgStatsHeight') ? eleWidth / eleHeight : eleHeight / eleWidth;
    if ((fWidth / fHeight) != eleRatio) {
        var f2 = (f == 'neoCMSImgStatsHeight') ? 'neoCMSImgStatsWidth' : 'neoCMSImgStatsHeight';
        var size = $('#' + f).val() * eleRatio;
        size = Math.round(size);
        $('#' + f2).val(size)
    }
};
imageBind = function () {
    imageRel();
    $('#neoCMSImgLink').unbind('keyup').bind('keyup', imageRel);
    fBrowseBind();
    $('#neoCMSImgUpload').unbind('change').bind('change', function () {
        imageLoader(false)
    });
    $('#neoCMSApplyBtn').unbind('click').bind('click', imageSrc)
};
imageRel = function () {
    if ($('#neoCMSImgLink').val() != '') $('.neoCMSImgRel').removeClass('disabled').find('input').removeAttr('disabled'); else $('.neoCMSImgRel').addClass('disabled').find('input').attr('disabled', 'disabled')
};
imageLoader = function (over) {
    if ($('#neoCMSImgUpload').val() != '') {
        $('#neoCMSImgUploadAlert').remove();
        $('#neoCMSImgAddress').val('http://');
        $('#neoCMSImgStatArea').css('min-height', $('#neoCMSImgStatArea').height()).addClass('neoCMSUploadingImg').find('*').css('display', 'none');
        var imgIframe;
        try {
            imgIframe = document.createElement('<iframe name="neoCMSImgUploadIframe" >')
        } catch (ex) {
            imgIframe = document.createElement('iframe')
        }
        $(imgIframe).attr({
            id: 'neoCMSImgUploadIframe',
            name: 'neoCMSImgUploadIframe',
            application: 'yes'
        }).appendTo('body');
        if (over) $('#neoCMSImgLoc').attr('action', 'core/upload.php?type=img&over=true');
        $('#neoCMSImgLoc')[0].submit()
    }
};
imageOverwritePrompt = function () {
    $('<p/>').addClass('neoCMSUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSUploadAlert').html('This file already exists. <a href="javascript:" id="neoCMSFileOver">overwrite</a> &middot; <a href="javascript:" id="neoCMSFileFind">find</a> &middot; <a href="javascript:" id="neoCMSFileCancel">cancel</a>').insertAfter('#neoCMSPromptTitle').css({
        backgroundColor: '#fff8dd',
        color: '#333'
    }).fadeTo(300, 1.0);
    $('#neoCMSFileOver').bind('click', function () {
        imageLoader(true)
    });
    $('#neoCMSFileCancel').bind('click', function () {
        imageOverwriteCancel();
        imageStatBuilder()
    });
    $('#neoCMSFileFind').bind('click', function () {
        imageOverwriteCancel();
        linkFiles('#neoCMSImgAddress')
    })
};
imageOverwriteCancel = function () {
    $('#neoCMSImgUpload').val('');
    $('#neoCMSUploadAlert, #neoCMSImgUploadIframe').remove();
    $('#neoCMSImgLoc').attr('action', 'core/upload.php?type=img')
};
imageSrc = function () {
    if ($('#neoCMSImgAddress').val() != '' && $('#neoCMSImgAddress').val() != 'http://') {
        $('#neoCMSUploadImgClone', ifrDoc).remove();
        $('#neoCMSImgUploadAlert').remove();
        $('#neoCMSImgChoose').addClass('neoCMSImgChooseInline');
        $('#neoCMSImgUpload').val('');
        $('body', ifrDoc).append($('<img/>', ifrDoc).attr({
            src: $('#neoCMSImgAddress').val(),
            id: 'neoCMSUploadImgClone'
        }));
        setTimeout(function () {
            imageStatBuilder($('#neoCMSUploadImgClone', ifrDoc)[0])
        }, 100)
    }
};
imageInfo = function (fileSize, fileWidth, fileHeight, alertText, name, imgPath) {
    setTimeout(function () {
        if ($('#neoCMSPrompt').length) {
            $('#neoCMSUploadImgClone', ifrDoc).remove();
            $('#neoCMSImgUploadAlert').remove();
            if (alertText.match(/successfully/gi)) {
                if (name != '') {
                    var imgPathName = (fpath() != '/') ? fpath() + imgPath + name : imgPath + name;
                    $('body', ifrDoc).append($('<img/>', ifrDoc).attr({
                        src: imgPathName,
                        id: 'neoCMSUploadImgClone',
                        height: fileHeight,
                        width: fileWidth
                    }));
                    imageStatBuilder($('#neoCMSUploadImgClone', ifrDoc)[0], fileSize)
                }
                $('<p/>').addClass('neoCMSImgUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSImgUploadAlert').html(alertText).insertAfter('#neoCMSPromptTitle').css('background-color', '#82ac3f').fadeTo(300, 1.0)
            } else {
                $('#neoCMSImgStatArea').removeClass('neoCMSUploadingImg').find('*').css('display', 'block');
                $('<p/>').addClass('neoCMSImgUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSImgUploadAlert').html(alertText).insertAfter('#neoCMSPromptTitle').css('background-color', '#c6242b').fadeTo(300, 1.0)
            }
        }
        $('#neoCMSImgUpload').val('');
        $('#neoCMSUploadAlert').remove();
        $('#neoCMSImgLoc').attr('action', 'core/upload.php?type=img');
        $('#neoCMSImgUploadIframe').remove()
    }, 100)
};
imageDone = function (ele) {
    var imgClassReg = new RegExp('((\s)*\bneoCMSEle\b(\s)*|(\s)*\bneoCMSEle[0-9]+\b(\s)*)', 'gi'),
        eleEditNum = $(ele, ifrDoc).attr('neocmsid').replace('neoCMSEditArea', ''),
        imgSrc = $('#neoCMSImgStatArea img').attr('src'), imgClass = $('#neoCMSImgClass').val(), neoCMSImgClass = null;
    if ($('#neoCMSImgClass').val() != '') neoCMSImgClass = $('#neoCMSImgClass').val() + ' neocms'; else neoCMSImgClass = 'neocms';
    var content = "src::" + imgSrc + ",alt::" + $('#neoCMSImgAltTxt').val() + ",class::" + neoCMSImgClass + ",width::" + $('#neoCMSImgStatsWidth').val() + ",height::" + $('#neoCMSImgStatsHeight').val() + ",link::" + $('#neoCMSImgLink').val() + ",rel::" + $('#neoCMSImgRel').val();
    $(ele, ifrDoc).addClass('neoCMSEle' + undoC);
    createUndo(ele);
    var imgAttr = {
        src: imgSrc,
        alt: $('#neoCMSImgAltTxt').val(),
        width: $('#neoCMSImgStatsWidth').val(),
        height: $('#neoCMSImgStatsHeight').val()
    };
    var newImg = $(ele, ifrDoc).clone().addClass(imgClass).attr(imgAttr);
    if ($('#neoCMSImgLink').val() != $(ele.parentNode, ifrDoc).attr('href')) $(ele.parentNode, ifrDoc).attr({href: $('#neoCMSImgLink').val()});
    if ($('#neoCMSImgRel').val() && $('#neoCMSImgRel').val() != $(ele.parentNode, ifrDoc).attr('rel')) $(ele.parentNode, ifrDoc).attr({rel: $('#neoCMSImgRel').val()});
    $(ele, ifrDoc).replaceWith(newImg);
    if (!$('#neoCMSEditTA' + eleEditNum, ifrDoc).length) {
        $('#neoCMSPageEdits', ifrDoc).append($('<textarea/>', ifrDoc).addClass('edit').attr({
            name: 'editImg' + eleEditNum,
            id: 'neoCMSEditTA' + eleEditNum,
            rows: '1',
            cols: '1'
        }));
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = content; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = content
    } else {
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = content; else $('#neoCMSEditTA' + undoC, ifrDoc)[0].innerText = content
    }
    undoC++;
    redoOff();
    origImg = null;
    cancel()
};
imageInsert = function () {
    var tinyDoc = getTinyDoc(), ed = getEd(), imgSrc = $('#neoCMSImgStatArea img').attr('src'),
        content = '<img src="' + imgSrc + '" alt="' + $('#neoCMSImgAltTxt').val() + '" class="' + $('#neoCMSImgClass').val() + '" width="' + $('#neoCMSImgStatsWidth').val() + '" height="' + $('#neoCMSImgStatsHeight').val() + '" />';
    if (ed) {
        if ($('#neoCMSImgLink').val()) {
            if ($(ed, tinyDoc).parent('a').length) {
                $(ed, tinyDoc).parent('a').attr('href', $('#neoCMSImgLink').val());
                if ($('#neoCMSImgRel').val()) $(ed, tinyDoc).parent('a').attr('rel', $('#neoCMSImgRel').val())
            } else {
                conlink = '<a href="' + $('#neoCMSImgLink').val() + '" ';
                if ($('#neoCMSImgRel').val()) conlink += 'rel="' + $('#neoCMSImgRel').val() + '" >';
                content = conlink + content + '</a>'
            }
        }
        $(ed, tinyDoc).replaceWith(content)
    } else {
        if (tinyDoc) setBookmark();
        if ($('#neoCMSImgLink').val()) {
            conlink = '<a href="' + $('#neoCMSImgLink').val() + '" ';
            if ($('#neoCMSImgRel').val()) conlink += 'rel="' + $('#neoCMSImgRel').val() + '" >';
            content = conlink + content + '</a>'
        }
        tinyMCE.execCommand('mceInsertRawHTML', true, content, {skip_undo: 1})
    }
    cancel()
};
videoPrompt = function (ele) {
    if (getTinyDoc()) getBookmark('simple');
    if (!$('#neoCMSPromptWrap').length) {
        $(ele, ifrDoc).addClass('neoCMSEle');
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/video-editor.html', function () {
            if (ele) {
                changeEmbed($(ele, ifrDoc));
                $('#neoCMSPromptTitle').html('Edit Video Embed');
                $('#neoCMSPromptBtn').bind('click', function () {
                    vidDone(ele)
                });
                vidStatBuilder(ele)
            } else {
                if (getEd()) {
                    var tinyDoc = getTinyDoc(), ed = getEd();
                    if ($(ed, tinyDoc).parent()[0].tagName == 'OBJECT') ed = $(ed, tinyDoc).parent();
                    changeEmbed($(ed, tinyDoc));
                    $('#neoCMSPromptTitle').html('Edit Video Embed');
                    vidStatBuilder(ed)
                } else {
                    vidStatBuilder()
                }
            }
            showPrompt()
        }))
    }
};
vidStatBuilder = function (ele) {
    var ele = (ele != undefined) ? ele || $('#neoCMSVidClone', ifrDoc) : false, origSize = true, constrain = true;
    if ($('#neoCMSOrigSize').length) {
        origSize = ($('#neoCMSOrigSize:checked').length) ? true : false;
        constrain = ($('#neoCMSConstrain:checked').length) ? true : false
    }
    $('#neoCMSVidStatArea').remove();
    if (ele) {
        var eleSrc = ($(ele, ifrDoc).find('param[name=movie]').length) ? $(ele, ifrDoc).find('param[name=movie]').attr('value') : $(ele, ifrDoc).attr('src'),
            src = '', vid = '', spl = eleSrc.split('/'),
            eleW = parseInt($(ele, ifrDoc).attr('width')) || parseInt($(ele, ifrDoc).width()),
            eleH = parseInt($(ele, ifrDoc).attr('height')) || parseInt($(ele, ifrDoc).height());
        if (eleSrc.match(/^http(s*):\/\//)) {
            vid = 'via ' + spl[2].replace(/www\./, '');
            src = spl[spl.length - 1]
        } else {
            vid = spl[spl.length - 1];
            src = eleSrc
        }
        $('#neoCMSPrompt').addClass('neoCMSImgVidSelect');
        $('<div/>').addClass('neoCMSVidStatArea clearfix').attr('id', 'neoCMSVidStatArea').insertAfter('#neoCMSVidChoose');
        $('#neoCMSVidStatArea').append('<h4 id="neoCMSVidTitle" class="neoCMSVidTitle">' + vid + '<span>' + src + '</span></h4><div id="neoCMSEmbedWrap">' + eleHTML(ele) + '</div><form action="javascript:" method="post" id="neoCMSVidStats" class="neoCMSVidStats"><label><input id="neoCMSOrigSize" class="neoCMSOrigSize" type="checkbox">Resize embed to fit</label><label><input id="neoCMSVidStatsWidth" name="neoCMSVidStatsWidth" type="text" value="' + eleW + '">width:</label><label><input id="neoCMSVidStatsHeight" name="neoCMSVidStatsHeight" type="text" value="' + eleH + '">height:</label><label class="neoCMSConstrainLabel"><input id="neoCMSConstrain" class="neoCMSConstrain" type="checkbox">Keep proportions</label></form>');
        $('#neoCMSEmbedWrap').html($('#neoCMSEmbedWrap').html().replace(/width=(\"|\')*[^\"\'\s]*(\"|\')*/g, 'width="145"').replace(/height=(\"|\')*[^\"\'\s]*(\"|\')*/g, 'height="' + (eleH * (145 / eleW) + 20) + '"'));
        var eleIns = ($('object.neoCMSEle, embed.neoCMSEle', ifrDoc).length || getEd()) ? ele : false;
        $('#neoCMSPromptBtn').attr('title', 'Insert new video').html('insert').bind('click', function () {
            vidInsert(eleIns)
        });
        if (origSize) $('#neoCMSOrigSize').attr('checked', 'checked');
        if (constrain) $('#neoCMSConstrain').attr('checked', 'checked');
        setTimeout(function () {
            vidOrigSize()
        }, 100)
    } else {
        $('#neoCMSPrompt').removeClass('neoCMSImgVidSelect');
        $('#neoCMSPromptBtn').attr('title', 'Load embed').html('apply').bind('click', vidClone);
        $('#neoCMSApplyBtn').css('display', 'none');
        vidBind()
    }
};
changeEmbed = function (ele) {
    $('#neoCMSVidEmbed').val(eleHTML(ele))
};
vidOrigSize = function () {
    $('#neoCMSOrigSize').bind('click', vidOrigSize);
    if ($('#neoCMSOrigSize:checked').length) {
        var tinyDoc = getTinyDoc(), tinyEle = (tinyDoc) ? tinyMCE.activeEditor.selection.getNode() : null,
            tinyEle = (tinyEle != null && tinyEle.tagName != 'EMBED' && tinyEle.tagName != 'OBJECT') ? $(tinyEle, tinyDoc).find('object')[0] || $(tinyEle, tinyDoc).find('embed')[0] : tinyEle,
            ele = tinyEle || $('.neoCMSEle', ifrDoc)[0], elePadT = parseInt(getCurStyle(ele, 'padding-top')),
            elePadR = parseInt(getCurStyle(ele, 'padding-right')), elePadL = parseInt(getCurStyle(ele, 'padding-left')),
            elePadB = parseInt(getCurStyle(ele, 'padding-bottom')),
            eleWidth = ($(ele, ifrDoc).attr('width')) ? parseInt($(ele, ifrDoc).attr('width')) : parseInt($(ele, ifrDoc).width()),
            eleHeight = ($(ele, ifrDoc).attr('height')) ? parseInt($(ele, ifrDoc).attr('height')) : parseInt($(ele, ifrDoc).height());
        if (eleWidth == '' || eleWidth == '0') eleWidth = (ele.clientWidth) ? parseInt(ele.clientWidth) : parseInt(ele.offsetWidth);
        if (eleHeight == '' || eleHeight == '0') eleHeight = (ele.clientHeight) ? parseInt(ele.clientHeight) : parseInt(ele.offsetHeight);
        if (elePadT != 0) eleHeight = eleHeight - elePadT;
        if (elePadB != 0) eleHeight = eleHeight - elePadB;
        if (elePadR != 0) eleWidth = eleWidth - elePadR;
        if (elePadL != 0) eleWidth = eleWidth - elePadL;
        if ($('#neoCMSVidClone', ifrDoc).length) {
            var cloneWidth = $('#neoCMSVidClone', ifrDoc).attr('width') || ($('#neoCMSVidClone', ifrDoc)[0].clientWidth) ? $('#neoCMSVidClone', ifrDoc)[0].clientWidth : $('#neoCMSVidClone', ifrDoc)[0].offsetWidth;
            var cloneHeight = $('#neoCMSVidClone', ifrDoc).attr('height') || ($('#neoCMSVidClone', ifrDoc)[0].clientHeight) ? $('#neoCMSVidClone', ifrDoc)[0].clientHeight : $('#neoCMSVidClone', ifrDoc)[0].offsetHeight;
            eleHeight = parseInt(cloneHeight) * (eleWidth / parseInt(cloneWidth));
            eleHeight = Math.round(eleHeight)
        }
        $('#neoCMSVidStatsWidth').replaceWith($('<input/>').attr({
            name: 'neoCMSVidStatsWidth',
            id: 'neoCMSVidStatsWidth',
            type: 'text',
            disabled: 'disabled'
        }).css({backgroundColor: '#bbb', color: '#666'}));
        $('#neoCMSVidStatsHeight').replaceWith($('<input/>').attr({
            name: 'neoCMSVidStatsHeight',
            id: 'neoCMSVidStatsHeight',
            type: 'text',
            disabled: 'disabled'
        }).css({backgroundColor: '#bbb', color: '#666'}));
        $('#neoCMSVidStatsWidth').val(eleWidth);
        $('#neoCMSVidStatsHeight').val(eleHeight);
        $('.neoCMSConstrainLabel').css('color', '#666').find('input').attr({disabled: 'disabled', checked: 'checked'})
    } else {
        var hVal = $('#neoCMSVidStatsHeight').val(), wVal = $('#neoCMSVidStatsWidth').val();
        $('#neoCMSVidStatsWidth').replaceWith($('<input/>').attr({
            name: 'neoCMSVidStatsWidth',
            id: 'neoCMSVidStatsWidth',
            type: 'text'
        }));
        $('#neoCMSVidStatsHeight').replaceWith($('<input/>').attr({
            name: 'neoCMSVidStatsHeight',
            id: 'neoCMSVidStatsHeight',
            type: 'text'
        }));
        if ($('#neoCMSVidClone', ifrDoc).length) {
            $('#neoCMSVidStatsWidth').val($('#neoCMSVidClone', ifrDoc)[0].offsetWidth);
            $('#neoCMSVidStatsHeight').val($('#neoCMSVidClone', ifrDoc)[0].offsetHeight)
        } else {
            $('#neoCMSVidStatsWidth').val(wVal);
            $('#neoCMSVidStatsHeight').val(hVal)
        }
        $('.neoCMSConstrainLabel').css('color', '#ccc').find('input').removeAttr('disabled')
    }
    vidBind();
    if ($('#neoCMSVidEmbed').length) {
        $('#neoCMSVidEmbed').val($('#neoCMSVidEmbed').val().replace(/width=(\"|\')[^\"\']*(\"|\')/g, 'width="' + $('#neoCMSVidStatsWidth').val().replace(/px/, '') + '"').replace(/height=(\"|\')[^\"\']*(\"|\')/g, 'height="' + $('#neoCMSVidStatsHeight').val().replace(/px/, '') + '"'))
    }
};
vidClone = function () {
    if ($('#neoCMSVidEmbed').val() != '') {
        $('#neoCMSVidClone', ifrDoc).remove();
        var newVid = $('#neoCMSVidEmbed').val();
        newVid = $(newVid, ifrDoc).attr('id', 'neoCMSVidClone');
        $('body', ifrDoc).append(newVid);
        vidStatBuilder($('#neoCMSVidClone', ifrDoc))
    }
};
vidConstrain = function () {
    $('#neoCMSConstrain').unbind('click').bind('click', vidConstrain);
    if ($('#neoCMSConstrain:checked').length && !$('#neoCMSOrigSize:checked').length) {
        $('#neoCMSVidStatsWidth, #neoCMSVidStatsHeight').bind('keyup', function () {
            var field = $(this).attr('id');
            changeVidSize(field)
        })
    } else {
        $('#neoCMSVidStatsWidth, #neoCMSVidStatsHeight').unbind('keyup').bind('keyup', function () {
            $('#neoCMSVidEmbed').val($('#neoCMSVidEmbed').val().replace(/width=(\"|\')[^\"\']*(\"|\')/g, 'width="' + $('#neoCMSVidStatsWidth').val().replace(/px/, '') + '"').replace(/height=(\"|\')[^\"\']*(\"|\')/g, 'height="' + $('#neoCMSVidStatsHeight').val().replace(/px/, '') + '"'))
        })
    }
};
changeVidSize = function (f) {
    var tinyDoc = getTinyDoc(), tinyEle = (tinyDoc) ? tinyMCE.activeEditor.selection.getNode() : null,
        tinyEle = (tinyEle != null && tinyEle.tagName != 'EMBED' && tinyEle.tagName != 'OBJECT') ? $(tinyEle, tinyDoc).find('object')[0] || $(tinyEle, tinyDoc).find('embed')[0] : tinyEle,
        ele = $(tinyEle, tinyDoc)[0] || $('.neoCMSEle', ifrDoc)[0], fval = $('#' + f).val(),
        fWidth = parseInt($('#neoCMSVidStatsWidth').val()), fHeight = parseInt($('#neoCMSVidStatsHeight').val()),
        doc = (tinyDoc) ? tinyDoc : ifrDoc;
    if ($('#neoCMSVidClone', ifrDoc).length) {
        ele = $('#neoCMSVidClone', ifrDoc);
        doc = ifrDoc
    }
    var eleWidth = parseInt($(ele, doc).attr('width')), eleHeight = parseInt($(ele, doc).attr('height'));
    if (eleWidth == '' || eleWidth == '0') eleWidth = ($(ele, doc)[0].clientWidth) ? $(ele, doc)[0].clientWidth : $(ele, doc)[0].offsetWidth;
    if (eleHeight == '' || eleHeight == '0') eleHeight = ($(ele, doc)[0].clientHeight) ? $(ele, doc)[0].clientHeight : $(ele, doc)[0].offsetHeight;
    var eleRatio = (f == 'neoCMSVidStatsHeight') ? eleWidth / eleHeight : eleHeight / eleWidth;
    if ((fWidth / fHeight) != eleRatio) {
        var f2 = (f == 'neoCMSVidStatsHeight') ? 'neoCMSVidStatsWidth' : 'neoCMSVidStatsHeight';
        var size = $('#' + f).val() * eleRatio;
        size = Math.round(size);
        $('#' + f2).val(size)
    }
};
vidBind = function () {
    vidConstrain();
    $('#neoCMSApplyBtn').unbind('click').bind('click', vidClone);
    $('#neoCMSVidStatsWidth, #neoCMSVidStatsHeight').bind('keyup', function () {
        $('#neoCMSVidEmbed').val($('#neoCMSVidEmbed').val().replace(/width=(\"|\')*[^\"\'\s]*(\"|\')*/g, 'width="' + $('#neoCMSVidStatsWidth').val().replace(/px/, '') + '"').replace(/height=(\"|\')*[^\"\'\s]*(\"|\')*/g, 'height="' + $('#neoCMSVidStatsHeight').val().replace(/px/, '') + '"'))
    })
};
vidDone = function (ele) {
    var eleEditNum = $(ele, ifrDoc).attr('neocmsid').replace('neoCMSEditArea', ''),
        content = $('#neoCMSVidEmbed').val();
    createUndo(ele);
    content = $(content, ifrDoc).addClass('neocms');
    var constring = eleHTML(content);
    content = $(content, ifrDoc).addClass('neoCMSEle neoCMSEle' + undoC);
    $(ele, ifrDoc).replaceWith($(content, ifrDoc));
    if (!$('#neoCMSEditTA' + eleEditNum, ifrDoc).length) {
        $('#neoCMSPageEdits', ifrDoc).append($('<textarea/>', ifrDoc).addClass('edit').attr({
            name: 'editVid' + eleEditNum,
            id: 'neoCMSEditTA' + eleEditNum,
            rows: '1',
            cols: '1'
        }));
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = constring; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = constring
    } else {
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = constring; else $('#neoCMSEditTA' + undoC, ifrDoc)[0].innerText = constring
    }
    undoC++;
    $('#neoCMSGlobalRedo').remove();
    $('#neoCMSGlobalUndo div').each(function () {
        var futureId = $(this).attr('id').replace('neoCMSUndoNum', '');
        if (futureId >= undoC) {
            $(this).remove();
            $('.neoCMSEle' + i, ifrDoc).removeClass('neoCMSEle' + i);
            $('.neoCMSEle' + i).removeClass('neoCMSEle' + i)
        }
    });
    $('.neoCMSEle' + (undoC - 1), ifrDoc).removeClass('neoCMSEle' + (undoC - 1));
    $('.neoCMSEle', ifrDoc).addClass('neoCMSEle' + (undoC - 1));
    neocmsGlobals();
    cancel()
};
vidInsert = function (ed) {
    var tinyDoc = getTinyDoc(), content = $('<div/>', tinyDoc).append($('#neoCMSVidEmbed').val());
    if (ed) {
        if ($(ed, tinyDoc).parent()[0].tagName == 'OBJECT') ed = $(ed, tinyDoc).parent();
        $(ed, tinyDoc).replaceWith($(content, tinyDoc).html())
    } else {
        if (tinyDoc) setBookmark();
        tinyMCE.execCommand('mceInsertContent', true, $(content, tinyDoc).html(), {skip_undo: 1})
    }
    fixImgs();
    cancel()
};
uploadPrompt = function () {
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/upload.html', function () {
            $('#neoCMSPromptBtn, #neoCMSCancelBtn').bind('click', cancel);
            folderLoader('file');
            showPrompt();
            $('#neoCMSPrompt input:first').focus();
            $('#neoCMSUpload').unbind('change').bind('change', function () {
                fileLoader(false)
            })
        }))
    }
};
fileLoader = function (over) {
    if ($('#neoCMSUpload').val() != '') {
        $('#neoCMSUploadAlert, #neoCMSFileInfo').remove();
        var fileIframe;
        try {
            fileIframe = document.createElement('<iframe name="neoCMSUploadIframe" >')
        } catch (ex) {
            fileIframe = document.createElement('iframe')
        }
        $(fileIframe).attr({
            id: 'neoCMSUploadIframe',
            name: 'neoCMSUploadIframe',
            application: 'yes'
        }).css('display', 'none').appendTo('body');
        if (over) $('#neoCMSUploadForm').attr('action', 'core/upload.php?over=true');
        $('#neoCMSUploadForm')[0].submit()
    }
};
fileInfo = function (fileSize, alertText, name, path) {
    setTimeout(function () {
        if ($('#neoCMSPrompt').length) {
            $('#neoCMSUploadAlert, #neoCMSFileInfo').remove();
            if (alertText.match(/successfully/gi)) {
                if (name != '') {
                    var pathName = path + name;
                    var size = (fileSize.length > 3) ? fileSize.slice(0, fileSize.length - 3) + ' kb' : fileSize + ' bytes';
                    $('<div/>').attr('id', 'neoCMSFileInfo').addClass('neoCMSFileInfo').append('<p>last upload:</p><h4>' + name + '<span>path: /' + path + '</span></h4>' + '<label>file size:<span>' + size + '</span></label>').insertAfter('#neoCMSPromptTitle');
                    $('#neoCMSUploadTxt').html('To upload <em>another</em> file, select the folder you want to upload to:')
                }
                $('<p/>').addClass('neoCMSUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSUploadAlert').html(alertText).insertAfter('#neoCMSPromptTitle').css('background-color', '#82ac3f').fadeTo(300, 1.0);
                $('#neoCMSUpload').val('')
            } else {
                $('<p/>').addClass('neoCMSUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSUploadAlert').html(alertText).insertAfter('#neoCMSPromptTitle').css('background-color', '#c6242b').fadeTo(300, 1.0)
            }
        }
        $('#neoCMSUploadForm').attr('action', 'core/upload.php');
        $('#neoCMSUploadIframe').remove()
    }, 100)
};
filePrompt = function () {
    $('<p/>').addClass('neoCMSUploadAlert neoCMSPromptAlert').attr('id', 'neoCMSUploadAlert').html('This file already exists. <a href="javascript:" id="neoCMSFileOver">overwrite</a> &middot; <a href="javascript:" id="neoCMSFileCancel">cancel</a>').insertAfter('#neoCMSPromptTitle').css({
        backgroundColor: '#fff8dd',
        color: '#333'
    }).fadeTo(300, 1.0);
    $('#neoCMSFileOver').bind('click', function () {
        fileLoader(true)
    });
    $('#neoCMSFileCancel').bind('click', fileCancel)
};
fileCancel = function () {
    $('#neoCMSUpload').val('');
    $('#neoCMSUploadAlert').remove();
    $('#neoCMSUploadIframe').remove()
};
defListBuilder = function () {
    getBookmark('simple');
    var tinyDoc = getTinyDoc(), ed = tinyMCE.activeEditor.selection.getNode(), defStart = '<dl><dt id="caret">',
        con = $(ed, tinyDoc).html(), defEnd = '</dt></dl>';
    $(ed, tinyDoc).replaceWith(defStart + con + defEnd);
    setCaret($('#caret', tinyDoc)[0], tinyDoc)
};
htmlPrompt = function () {
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/html-editor.html', function () {
            var tinyDoc = getTinyDoc(), eleHTML = $('body', tinyDoc).find('[neocmsid]').html();
            eleHTML = cleanContent(eleHTML, true);
            codeEditor = new CodeMirror(CodeMirror.replace("neoCMSHtmlEditAreaTA"), {
                parserfile: ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js", "parsehtmlmixed.js"],
                path: "js/codemirror/js/",
                stylesheet: "js/codemirror/css/xmlcolors.css",
                height: Math.round($(window).height() * 0.6) + 'px',
                width: '780px',
                tabMode: "shift",
                content: eleHTML
            });
            $('#neoCMSPromptBtn').bind('click', function () {
                htmlInsert(codeEditor)
            });
            $('#neoCMSCancelBtn').bind('click', cancel);
            $('a.fullscreen').unbind('click').bind('click', function () {
                editorFullscreen($(this).attr('rel'))
            }).removeClass('neoCMSMinimize').attr({title: 'Maximize this window'}).html('Maximize this window');
            $('.CodeMirror-wrapping').attr('id', 'neoCMSCodeWrap');
            showPrompt()
        }))
    } else {
        $('#neoCMSPrompt, #neoCMSPromptContent').removeAttr('style');
        $('#neoCMSCodeWrap, #neoCMSHtmlEditAreaTA').width('780px').height(Math.round($(window).height() * 0.6));
        $('#neoCMSPrompt a.fullscreen').unbind('click').bind('click', function () {
            editorFullscreen($(this).attr('rel'))
        }).removeClass('neoCMSMinimize').attr({title: 'Maximize this window'}).html('Maximize this window');
        showPrompt(false, true)
    }
};
htmlInsert = function (codeEditor) {
    var tinyDoc = getTinyDoc(),
        content = ($('#neoCMSCodeWrap').length) ? codeEditor.getCode() : $('#neoCMSHtmlEditAreaTA').val();
    $('#neoCMSEditContent')[0].innerHTML = content;
    $('body', tinyDoc).find('[neocmsid]')[0].innerHTML = content;
    fixImgs();
    if ($.browser.msie) ieBind();
    $('#neoCMSEditContent_parent').css('display', 'block');
    cancel()
};
makeTable = function () {
    var tinyDoc = getTinyDoc(), ed = tinyMCE.activeEditor.selection.getNode();
    if (tinyDoc) getBookmark('simple');
    if (!$('#neoCMSPromptWrap').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/table-editor.html', function () {
            $('#neoCMSTableRows').unbind('keyup').bind('keyup', insertRows).unbind('change').bind('change', function () {
                if ($('#neoCMSTableRows').val() == '' || $('#neoCMSTableRows').val() == '0' || !$('#neoCMSTableRows').val().match(/^\d+$/)) {
                    $('#neoCMSTableRows').val('1');
                    insertRows()
                }
            });
            $('#neoCMSTableCols').unbind('keyup').bind('keyup', insertCols).unbind('change').bind('change', function () {
                if ($('#neoCMSTableCols').val() == '' || $('#neoCMSTableCols').val() == '0' || !$('#neoCMSTableCols').val().match(/^\d+$/)) {
                    $('#neoCMSTableCols').val('1');
                    insertCols()
                }
            });
            $('#neoCMSTableThead').unbind('click').bind('click', insertThead);
            $('#neoCMSTableTfoot').unbind('click').bind('click', insertTfoot);
            $('#neoCMSTableID').unbind('keydown').bind('keydown', tableID);
            $('#neoCMSTableClass').unbind('keydown').bind('keydown', function () {
                $('#neoCMSTablePrev table').attr('class', $(this).val())
            });
            $('#neoCMSPrompt #neoCMSPromptBtn').unbind('click').bind('click', function () {
                insertTable(ed)
            });
            showPrompt()
        }))
    }
};
insertRows = function () {
    var r = Math.round($('#neoCMSTableRows').val()), rows = $('#neoCMSTablePrev tbody tr');
    if (r != '' && r != '0' && r != rows.length && r.toString().match(/^\d+$/)) {
        var row = rows[0];
        $('#neoCMSTablePrev tbody tr').remove();
        while (r > '0') {
            row = $(row).clone();
            $('#neoCMSTablePrev tbody').append(row[0]);
            r--
        }
    }
};
insertCols = function () {
    var c = Math.round($('#neoCMSTableCols').val()), cols = $('#neoCMSTablePrev tbody tr:first td');
    if (c != '' && c != '0' && c != cols.length && c.toString().match(/^\d+$/)) {
        $('#neoCMSTablePrev tbody td').remove();
        $('#neoCMSTablePrev tbody tr').each(function () {
            var n = c;
            while (n > '0') {
                $(this).append($('<td/>').html('&nbsp;'));
                n--
            }
        });
        insertThead();
        insertTfoot()
    }
};
insertThead = function () {
    var cols = $('#neoCMSTablePrev tbody tr:first td');
    $('#neoCMSTablePrev thead').remove();
    if ($('#neoCMSTableThead:checked').length) {
        $('#neoCMSTablePrev table').prepend('<thead><tr></tr></thead>');
        cols.each(function () {
            $('#neoCMSTablePrev thead tr').append($('<th/>').html('&nbsp;'))
        })
    }
};
insertTfoot = function () {
    var cols = $('#neoCMSTablePrev tbody tr:first td');
    $('#neoCMSTablePrev tfoot').remove();
    if ($('#neoCMSTableTfoot:checked').length) {
        $('#neoCMSTablePrev table').append('<tfoot><tr></tr></tfoot>');
        cols.each(function () {
            $('#neoCMSTablePrev tfoot tr').append($('<td/>').html('&nbsp;'))
        })
    }
};
tableID = function () {
    if (!$('#neoCMSTableID').val().match(/\s/gi)) $('#neoCMSTablePrev table').attr('id', $('#neoCMSTableID').val()); else $('#neoCMSTableID').val($('#neoCMSTablePrev table').attr('id'))
};
insertTable = function (ed) {
    var tinyDoc = getTinyDoc(), con = $(ed, tinyDoc).html();
    $('#neoCMSTablePrev td:first').attr('id', 'caret').append(con).html();
    $(ed, tinyDoc).replaceWith($('#neoCMSTablePrev').html());
    setCaret($('#caret', tinyDoc)[0], tinyDoc);
    cancel()
};
makeLink = function () {
    var tinyDoc = getTinyDoc();
    if (tinyDoc) getBookmark('simple');
    if (tinyMCE.activeEditor.selection.getNode().nodeName == 'A') var ed = tinyMCE.activeEditor.selection.getNode(); else if (tinyMCE.activeEditor.selection.getNode().parentNode.nodeName == 'A') var ed = tinyMCE.activeEditor.selection.getNode().parentNode;
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/link-editor.html', function () {
            if (ed) {
                $('#neoCMSPromptBtn').html('done').bind('click', function () {
                    linkReplace(ed)
                });
                $('#neoCMSPromptTitle').html('Edit Link');
                $('#neoCMSLinkID').val($(ed, tinyDoc).attr('id'));
                $('#neoCMSLinkURL').val($(ed, tinyDoc).attr('href'));
                $('#neoCMSLinkClass').val($(ed, tinyDoc).attr('class'));
                $('#neoCMSLinkTitle').val($(ed, tinyDoc).attr('title'));
                $('#neoCMSLinkRel').val($(ed, tinyDoc).attr('rel'));
                $('<p/>').addClass('neoCMSLinkRemove').prependTo('#neoCMSLinkProperties').append($('<a/>').attr({
                    href: 'javascript:',
                    title: 'Unlink this selection'
                }).html('remove this link').bind('click', function () {
                    linkRemove(ed)
                }))
            } else {
                $('#neoCMSPromptBtn').html('insert').bind('click', linkInsert);
                $('#neoCMSPromptTitle').html('Create Link')
            }
            $('#neoCMSCancelBtn').bind('click', cancel);
            fBrowseBind();
            showPrompt()
        }))
    }
};
linkInsert = function () {
    var tinyDoc = getTinyDoc(),
        newlink = (!$('#neoCMSLinkURL').val().match(/^http(s*):\/\/|@/)) ? fpath() + $('#neoCMSLinkURL').val() : $('#neoCMSLinkURL').val();
    if ($('#neoCMSLinkURL').val() != '' && $('#neoCMSLinkURL').val() != 'http://') {
        $('#neoCMSLinkAlert').remove();
        setBookmark();
        var orig = tinyMCE.activeEditor.selection.getContent({format: 'text'});
        var ed = tinyMCE.activeEditor.selection.getNode();
        if ($(ed, tinyDoc)[0].tagName == 'IMG') {
            var edCloneDiv = $('<div/>', tinyDoc).append($(ed, tinyDoc).clone());
            orig = $(edCloneDiv).html()
        }
        var linkContent = '<a href="' + newlink + '" ';
        if ($('#neoCMSLinkID').val()) linkContent += 'id="' + $('#neoCMSLinkID').val() + '" ';
        if ($('#neoCMSLinkClass').val()) linkContent += 'class="' + $('#neoCMSLinkClass').val() + '" ';
        if ($('#neoCMSLinkTitle').val()) linkContent += 'title="' + $('#neoCMSLinkTitle').val() + '" ';
        if ($('#neoCMSLinkRel').val()) linkContent += 'rel="' + $('#neoCMSLinkRel').val() + '" ';
        linkContent += '>' + orig + '</a>';
        tinyMCE.execCommand('mceInsertContent', true, linkContent, {skip_undo: 1});
        cancel()
    } else $('<p/>').addClass('neoCMSLinkAlert').attr('id', 'neoCMSLinkAlert').html('You must enter a URL').prependTo('#neoCMSLinkContent').fadeTo(300, 1.0);
    $('a', tinyDoc).removeAttr('_mce_href')
};
linkReplace = function (ed) {
    var tinyDoc = getTinyDoc(),
        newlink = (!$('#neoCMSLinkURL').val().match(/^http(s*):\/\/|@/)) ? fpath() + $('#neoCMSLinkURL').val() : $('#neoCMSLinkURL').val();
    if ($(ed, tinyDoc)[0].tagName == 'IMG') ed = $(ed, tinyDoc).parent();
    if (newlink != '' && newlink != 'http://') {
        $(ed, tinyDoc).removeAttr('class');
        if ($('#neoCMSLinkClass').val()) $(ed, tinyDoc).addClass($('#neoCMSLinkClass').val());
        if (newlink != $(ed, tinyDoc).attr('href')) $(ed, tinyDoc).attr({href: newlink, _mce_href: newlink});
        if ($('#neoCMSLinkRel').val() != $(ed, tinyDoc).attr('rel')) $(ed, tinyDoc).attr({rel: $('#neoCMSLinkRel').val()});
        if ($('#neoCMSLinkID').val() != '') $(ed, tinyDoc).attr({id: $('#neoCMSLinkID').val()});
        $(ed, tinyDoc).attr('title', $('#neoCMSLinkTitle').val());
        cancel()
    } else linkRemove();
    $('a', tinyDoc).removeAttr('_mce_href')
};
linkRemove = function (ed) {
    var edhtml = $(ed).html();
    $(ed).replaceWith(edhtml);
    fixImgs();
    cancel()
};
logoutPrompt = function () {
    if ($('#neoCMSPageEdits textarea.edit', ifrDoc).length) {
        overlay();
        if (!$('#neoCMSPrompt').length) {
            $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/prompt.html', function () {
                $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Logout');
                $('#neoCMSPrompt p').html('You have unpublished changes that will be lost. Do you still wish to log out?');
                $('#neoCMSPromptBtn').attr('title', 'logout').html('logout').bind('click', logout);
                $('#neoCMSCancelBtn').bind('click', cancel);
                showPrompt('nodrag')
            }))
        }
    } else logout()
};
logout = function () {
    $.ajax({
        type: "POST", url: "core/logout.php", success: function () {
            window.location = retUrl()
        }
    })
};
restorePrompt = function () {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Restore');
            $('#neoCMSPrompt p').html('Restore will change all files associated with this page back to their last published version. This cannot be undone. Do you still wish to restore?');
            $('#neoCMSPromptBtn').attr('title', 'Restore this page to last published version').html('restore').bind('click', restoreSubmit);
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt('nodrag');
            $('body', ifrDoc).append($('<form/>', ifrDoc).attr({
                name: 'neoCMSRestoreForm',
                id: 'neoCMSRestoreForm',
                action: neoCMSGlobalPath + '/core/restore.php',
                method: 'POST',
                target: 'neoCMSRestIframe'
            }).css('display', 'none'))
        }))
    }
};
restoreSubmit = function () {
    $('#neoCMSPromptWrap').remove();
    var restoreIframe;
    try {
        restoreIframe = ifrDoc.createElement('<iframe name="neoCMSRestIframe" >')
    } catch (ex) {
        restoreIframe = ifrDoc.createElement('iframe')
    }
    $(restoreIframe, ifrDoc).attr({id: 'neoCMSRestIframe', name: 'neoCMSRestIframe', application: 'yes'});
    $('body', ifrDoc).append(restoreIframe);
    $('#neoCMSRestoreForm', ifrDoc)[0].submit()
};
cancelAllPrompt = function () {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Cancel All');
            $('#neoCMSPrompt p').html('Are you sure you want to clear all changes since your last publish?');
            $('#neoCMSPromptBtn').attr('title', 'Clear all changes since last publish').html('clear').bind('click', cancelAllSubmit);
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt('nodrag')
        }))
    }
};
cancelAllSubmit = function () {
    $('#neoCMSPromptWrap').remove();
    ifrReload()
};
done = function (ele) {
    var eleEditNum = $(ele, ifrDoc).attr('neocmsid').replace('neoCMSEditArea', ''), tinyDoc = getTinyDoc(),
        t = (ele.tagName.match(/(h[0-9]|p)/i)) ? false : true,
        content = (!t && $('[neocmsid]', tinyDoc).find(ele.tagName).length) ? $('[neocmsid]', tinyDoc).parent().html() : $('[neocmsid]', tinyDoc).html();
    $(ele, ifrDoc).find('.neoCMSFlash').removeClass('neoCMSFlash');
    if (content) {
        content = cleanContent(content, t);
        var clone = $(ele, ifrDoc).clone();
        if ($(ele, ifrDoc)[0].tagName.match(/TR|THEAD|TBODY/)) clone.html(content); else clone[0].innerHTML = content;
        $(ele, ifrDoc).replaceWith(clone)
    } else $(ele, ifrDoc).html('&nbsp;');
    createUndo(ele);
    var ncon = cleanContent($('.neoCMSEle', ifrDoc).html(), t);
    if (!$('#neoCMSEditTA' + eleEditNum, ifrDoc).length) {
        $('<textarea/>', ifrDoc).addClass('edit').attr({
            name: 'edit' + eleEditNum,
            id: 'neoCMSEditTA' + eleEditNum,
            rows: '1',
            cols: '1'
        }).appendTo($('#neoCMSPageEdits', ifrDoc));
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = ncon; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = ncon
    } else {
        if (typeof (document.textContent) != 'undefined') $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].textContent = ncon; else $('#neoCMSEditTA' + eleEditNum, ifrDoc)[0].innerText = ncon
    }
    undoC++;
    redoOff();
    linkBind();
    cancel()
};
undo = function () {
    destroyIcons();
    if (undoC <= 0) undoC = 0; else undoC--;
    var neoCMSUndoEle = '.neoCMSEle' + undoC;
    if ($(neoCMSUndoEle, ifrDoc).length) {
        createRedo(neoCMSUndoEle);
        if ($(neoCMSUndoEle, ifrDoc)[0].tagName != 'IMG') {
            var rep = $('#neoCMSGlobalUndo #neoCMSUndoNum' + undoC + ' ' + neoCMSUndoEle).find('a.neoCMSIcon').css({opacity: '1'}).end().html();
            $(neoCMSUndoEle, ifrDoc).replaceWith($(neoCMSUndoEle, ifrDoc).clone().html(rep))
        } else {
            var rep = $('#neoCMSGlobalUndo #neoCMSUndoNum' + undoC).html();
            $(neoCMSUndoEle, ifrDoc).replaceWith(rep)
        }
        if ($(neoCMSUndoEle, ifrDoc).hasClass('neocmsRepeatArea')) {
            var content = $(neoCMSUndoEle, ifrDoc).clone().find('.neoCMSPlaceholder').remove().end()
        } else var content = $(neoCMSUndoEle, ifrDoc)[0];
        var eleEditNum = $(neoCMSUndoEle, ifrDoc).parent().attr('id').replace('neoCMSEditArea', '');
        changePageEdits(content, eleEditNum)
    }
    neocmsGlobals();
    if ($(neoCMSUndoEle, ifrDoc).attr('class').match(/neocmsRepeatArea/g)) {
        neocmsRepBind();
        $('.neoCMSRep .neoCMSIcon', ifrDoc).css({display: 'block', opacity: '1'})
    }
    createIcons()
};
redo = function () {
    destroyIcons();
    var neoCMSRedoEle = '.neoCMSEle' + undoC;
    if ($(neoCMSRedoEle, ifrDoc).length) {
        if ($(neoCMSRedoEle, ifrDoc)[0].tagName != 'IMG') {
            var rep = $('#neoCMSGlobalRedo #neoCMSRedoNum' + undoC + ' ' + neoCMSRedoEle).find('a.neoCMSIcon').css({opacity: '1'}).end().html();
            $(neoCMSRedoEle, ifrDoc).replaceWith($(neoCMSRedoEle, ifrDoc).clone().html(rep))
        } else {
            var rep = $('#neoCMSGlobalRedo #neoCMSRedoNum' + undoC).html();
            $(neoCMSRedoEle, ifrDoc).replaceWith(rep)
        }
        if ($(neoCMSRedoEle, ifrDoc).hasClass('neocmsRepeatArea')) {
            var content = $(neoCMSRedoEle, ifrDoc).clone().find('.neoCMSPlaceholder').remove().end()
        } else var content = $(neoCMSRedoEle, ifrDoc)[0];
        var eleEditNum = $(neoCMSRedoEle, ifrDoc).parent().attr('id').replace('neoCMSEditArea', '');
        changePageEdits(content, eleEditNum);
        undoC++
    }
    neocmsGlobals();
    if ($(neoCMSRedoEle, ifrDoc).attr('class').match(/neocmsRepeatArea/g)) {
        neocmsRepBind();
        $('.neoCMSRep .neoCMSIcon', ifrDoc).css({display: 'block', opacity: '1'})
    }
    createIcons()
};
redoOff = function () {
    $('#neoCMSGlobalRedo').remove();
    var i = 0;
    $('#neoCMSGlobalUndo div').each(function () {
        var futureId = $(this).attr('id').replace('neoCMSUndoNum', '');
        if (futureId >= undoC) {
            $(this).remove();
            $('.neoCMSEle' + i, ifrDoc).removeClass('neoCMSEle' + i);
            $('.neoCMSEle' + i).removeClass('neoCMSEle' + i)
        }
    });
    $('.neoCMSEle' + (undoC - 1), ifrDoc).removeClass('neoCMSEle' + (undoC - 1));
    $('.neoCMSEle', ifrDoc).addClass('neoCMSEle' + (undoC - 1));
    neocmsGlobals()
};
publish = function () {
    if ($('#neoCMSPageEdits textarea.edit', ifrDoc).length) {
        var pubIframe;
        try {
            pubIframe = ifrDoc.createElement('<iframe name="neoCMSPubIframe" >')
        } catch (ex) {
            pubIframe = ifrDoc.createElement('iframe')
        }
        $(pubIframe, ifrDoc).attr({
            id: 'neoCMSPubIframe',
            name: 'neoCMSPubIframe',
            application: 'yes'
        }).css('display', 'none').appendTo($('body', ifrDoc));
        $('#neoCMSPageEdits', ifrDoc)[0].submit();
        $('#neoCMSGlobalUndo, #neoCMSGlobalRedo').remove();
        undoC = 0;
        neocmsGlobals()
    }
};
dragOn = function (ele, hand) {
    $(ele).draggable({
        handle: hand, start: function () {
            $(window).unbind('resize')
        }
    });
    if ($('#neoCMSEditWrap').length) $('#neoCMSEditWrap').resizable({
        handles: 'all',
        alsoResize: '.mceIframeContainer',
        start: frameShim
    })
};