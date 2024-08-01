var ifrDoc;
$(function () {
    if (!$('#neoCMSDashScrollWrap').length) tempCache();
    $('#neoCMSConfigRetry').bind('click', function () {
        window.location.reload(true)
    });
    $('#neoCMSLoginWrap').css({display: "block", opacity: "0"}).fadeTo(600, 1.0, function () {
        if ($.browser.msie) $(this, ifrDoc)[0].style.removeAttribute('filter');
        overlay()
    });
    $(window).bind('resize', windowResize);
    $('#neoCMSLoginForm').bind('submit', loginSubmit);
    $('#neoCMSUsername input').focus();
    $('#neoCMSHome').bind('click', function () {
        checkURL(fpath())
    });
    fBrowseBind()
});
tempCache = function () {
    $.ajax({url: "templates/editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/image-editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/video-editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/html-editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/link-editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/table-editor.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/prompt.html", cache: true, dataType: "html"});
    $.ajax({url: "templates/upload.html", cache: true, dataType: "html"})
};
fBrowseBind = function () {
    $('.neoCMSFBrowse').unbind('click').bind('click', function () {
        linkFiles($(this).attr('rel'))
    })
};
getIfrDoc = function () {
    ifrDoc = $('#neoCMSPageSrc')[0];
    ifrDoc = (ifrDoc.contentDocument) ? ifrDoc.contentDocument : ifrDoc.contentWindow.document
};
loginSubmit = function () {
    if (loginValid()) {
        var loginIframe;
        try {
            loginIframe = document.createElement('<iframe name="neoCMSLoginIframe" >')
        } catch (ex) {
            loginIframe = document.createElement('iframe')
        }
        $(loginIframe).attr({
            id: 'neoCMSLoginIframe',
            name: 'neoCMSLoginIframe',
            application: 'yes'
        }).css('display', 'none');
        $('body').append(loginIframe);
        $('#neoCMSLoginForm')[0].submit()
    } else return false
};
loginValid = function () {
    var user = $('#neoCMSUsername input').val(), ret = false;
    if (user == '') $('#neoCMSUsername p').css('display', 'inline'); else $('#neoCMSUsername p').css('display', 'none');
    if ($('#neoCMSPassword input').length) {
        var pass = $('#neoCMSPassword input').val();
        if (pass == '') $('#neoCMSPassword p').css('display', 'inline'); else $('#neoCMSPassword p').css('display', 'none');
        if (user != '' && pass != '') ret = true;
        return ret
    } else {
        if (user != '') ret = true;
        return ret
    }
};
loginIncorr = function (message) {
    $('#neoCMSEnter p.error').html(message).css('display', 'block');
    $('#neoCMSLoginIframe').remove()
};
windowResize = function () {
    if ($('.neoCMSIcon, .neoCMSRepIcon', ifrDoc).length) destroyIcons();
    if ($('#overlay').length) $('#overlay').css({width: $(window).width(), height: $(window).height()});
    if ($('.neoCMSEle', ifrDoc).length) {
        if ($('#neoCMSEditWrap').length) {
            $('#neoCMSEditWrap').css('opacity', '0');
            if (typeof (windowOrient == 'function')) windowOrient()
        }
    }
    if (!$('#neoCMSLoginWrap, #neoCMSEditWrap, #neoCMSPrompt').length) {
        if (!$('.neoCMSIcon, .neoCMSRepIcon', ifrDoc).length && $('[neocmsid]', ifrDoc).length) createIcons(true);
        $('#neoCMSPageSrc').height($(window).height() - 60)
    }
};
overlay = function (ele) {
    if (!$('#overlay').length) {
        $('<div/>').attr('id', 'overlay').height($(document).height()).width($(document).width()).appendTo('body').css({
            display: 'block',
            opacity: '0'
        }).fadeTo(300, 0.55, function () {
            if (ifrDoc) {
                if ($('.neoCMSIcon, .neoCMSRepIcon', ifrDoc).length) destroyIcons();
                if (ele) {
                    if (ele.tagName == 'IMG') imageEditBuilder(ele); else if (ele.tagName == 'OBJECT' || ele.tagName == 'EMBED') videoPrompt(ele); else createTextArea(ele)
                }
            }
        })
    } else if ($('#neoCMSEditWrap').length) $('#neoCMSEditWrap').addClass('disabled').prepend($('<div id="overlayEditWrap"></div>'))
};
promptOverlay = function () {
    if ($('#neoCMSPrompt').length) $('#neoCMSPrompt').addClass('disabled').prepend($('<div id="overlayEditWrap"></div>'))
};
removeOverlay = function () {
    $('#overlay').remove();
    $('.neoCMSEle', ifrDoc).css('visibility', 'visible').removeClass('neoCMSEle');
    $('.neoCMSEle').css('visibility', 'visible').removeClass('neoCMSEle')
};
cancel = function () {
    var tinyDoc = getTinyDoc();
    if ($('#neoCMSEditWrap').length && !$('#neoCMSPromptWrap').length) {
        $('object, embed', tinyDoc).remove();
        tinyMCE.execCommand('mceRemoveControl', false, 'neoCMSEditContent');
        $(tinyDoc).remove();
        $('#neoCMSEditWrap').remove();
        $(window).unbind('keypress, keydown');
        if ($('.neocmsRepeatArea', ifrDoc).length) neocmsRepBind();
        removeOverlay();
        $('object, embed', ifrDoc).removeClass('neoCMSFlash');
        createIcons(true)
    } else if ($('#neoCMSEditWrap').length && $('#neoCMSPromptWrap').length) {
        $('#neoCMSPromptWrap, #neoCMSFilesWrap, #overlayEditWrap').remove();
        keyHandler(false);
        $('#neoCMSEditWrap').removeClass('disabled');
        $('object, embed', tinyDoc).removeClass('neoCMSFlash')
    } else {
        $('#neoCMSPromptWrap, #neoCMSFilesWrap, #overlayEditWrap').remove();
        $(window).unbind('keypress, keydown');
        if (!$('.neoCMSIcon, .neoCMSRepIcon', ifrDoc).length && $('[neocmsid]', ifrDoc).length) createIcons(true);
        removeOverlay();
        $('object, embed', ifrDoc).removeClass('neoCMSFlash')
    }
    $('#neoCMSUploadImgClone, #neoCMSVidClone', ifrDoc).remove();
    $(window).bind('resize', windowResize)
};
fpath = function () {
    var preg = new RegExp('(\/' + neoCMSPath + '(\/[^\/]*|))$'), p = neoCMSGlobalPath.replace(preg, '/');
    return p
};
linkBind = function () {
    $('a', ifrDoc).not('.neoCMSIcon, .neoCMSRepIcon, [href="javascript:"], [href="javascript:void(0);"], [href="#"]').each(function () {
        if (!$(this, ifrDoc).attr('rel').match(/lightbox/)) {
            $(this, ifrDoc).unbind('click').bind('click', function () {
                checkURL($(this, ifrDoc)[0].href);
                return false
            })
        }
    })
};
checkURL = function (linky) {
    var linkHref = (linky.match('://')) ? linky.split('://') : linky;
    $('#neoCMSFilesWrap').remove();
    if ((linkHref[0] == 'http' || linkHref[0] == 'https') && linkHref[1]) {
        if (linkHref[1].match('/')) linkHref = linkHref[1].split('/');
        linkHref = linkHref[0].split(':');
        linkHref = linkHref[0];
        var neoCMSLoc = window.location.href.split('//');
        neoCMSLoc = neoCMSLoc[1].split('/');
        neoCMSLoc = neoCMSLoc[0];
        if (linkHref == neoCMSLoc || linkHref == 'www.' + neoCMSLoc) {
            if ($('#neoCMSPageEdits .edit', ifrDoc).length) {
                console.log(linkHref);
                navAwayPrompt(linky, 'loc')
            } else {
                console.log($('#neoCMSPageSrc').length);
                if ($('#neoCMSPageSrc').length) {
                    $.ajax({
                        type: "POST", url: "core/getpage.php", data: "page=" + linky, success: function () {
                            if (frames['neoCMSPageSrc'].contentWindow) frames['neoCMSPageSrc'].contentWindow.location.href = linky; else frames['neoCMSPageSrc'].location.href = linky
                        }
                    })
                } else {
                    $.ajax({
                        type: "POST", url: "../core/getpage.php", data: "page=" + linky, success: function () {
                            window.location = '../'
                        }
                    })
                }
            }
        } else navAwayPrompt(linky)
    }
};
navAwayPrompt = function (lnk, loc) {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('templates/prompt.html', function () {
            var navTitle = (loc) ? 'You Have Unpublished Changes On This Page' : 'You Are Leaving neocms',
                navTitle = (loc) ? 'Continue' : 'Leave neocms', navBtn = (loc) ? 'continue' : 'leave';
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html(navTitle);
            $('#neoCMSPrompt p').html('Are you sure you want proceed? You will lose all your unpublished changes.');
            $('#neoCMSPromptBtn').attr('title', navTitle).html(navBtn).bind('click', function () {
                navAwaySubmit(lnk, loc)
            });
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt('nodrag')
        }))
    }
};
navAwaySubmit = function (lnk, loc) {
    if (loc) {
        if (lnk == 'settings/') {
            window.location = lnk
        } else {
            if (frames['neoCMSPageSrc'].contentWindow) frames['neoCMSPageSrc'].contentWindow.location.href = lnk; else frames['neoCMSPageSrc'].location.href = lnk
        }
        cancel()
    } else window.location = lnk
};
linkFiles = function (target) {
    $('body').prepend($('<div/>').attr('id', 'neoCMSFilesWrap').load(fpath() + neoCMSPath + '/templates/file-browser.html', function () {
        overlay();
        $('#neoCMSFilesWrap').css('display', 'block');
        fileTarget = target;
        fileBrowser(target);
        promptOverlay()
    }))
};
fileBrowser = function (target) {
    var fileIframe,
        type = (target.match(/#neoCMSImgAddress/)) ? 'img' : (target.match(/#neoCMSPages/)) ? 'pages' : 'all';
    try {
        fileIframe = document.createElement('<iframe name="neoCMSFileFrame" >')
    } catch (ex) {
        fileIframe = document.createElement('iframe')
    }
    $(fileIframe).attr({
        id: 'neoCMSFileFrame',
        name: 'neoCMSFileFrame',
        src: fpath() + neoCMSPath + '/core/fileload.php?type=' + type,
        application: 'yes'
    }).css('display', 'none').appendTo('body')
};
fileOption = function (matches) {
    $('#neoCMSFileULs').append(matches)
};
destroyFileLoader = function (type) {
    $('#neoCMSFileLoading').remove();
    if (type == 'img') $('#neoCMSFiles h2').html('Select the image you are looking for:'); else if (type == 'pages') $('#neoCMSFiles h2').html('Select the page you are looking for:'); else $('#neoCMSFiles h2').html('Select the file you are looking for:');
    $('#neoCMSFClose').bind('click', linkClose);
    if (type == 'pages') $('#neoCMSPagesUL li').unbind('click').bind('click', function () {
        checkURL($(this).attr('href'));
        return false
    }); else if (type != 'none') $('#neoCMSFileULs li').unbind('click').bind('click', function () {
        linkSel(this)
    });
    if (type == 'all') {
        $('#filesTab').css('display', 'block');
        $('#neoCMSFileULs ul').css('display', 'none');
        $('#neoCMSPagesUL').css('display', 'block');
        $('#pages').addClass('on');
        $('#filesTab a').unbind('click').bind('click', function () {
            $('#filesTab li.on').removeClass('on');
            $(this).parent().addClass('on');
            $('#neoCMSFileULs ul').css('display', 'none');
            $($(this).attr('rel')).css('display', 'block')
        })
    } else $('#filesTab').css('display', 'none');
    $('#neoCMSFileFrame').remove()
};
linkSel = function (t) {
    var v = ($(t).find('p').length) ? $(t).find('p').html() : $(t).html();
    $(fileTarget).val(v);
    if (fileTarget == '#neoCMSImgAddress') imageSrc();
    linkClose()
};
linkClose = function () {
    $('#neoCMSFilesWrap, #neoCMSPrompt #overlayEditWrap').remove();
    if (!$('#neoCMSEditWrap, #neoCMSPrompt').length) $('#overlay').remove();
    $('#neoCMSPrompt').removeClass('disabled');
    if ($('[neocmsid]', ifrDoc).length) createIcons(true)
};