var neoCMSGlobalPath = null, neoCMSPath = null, neoCMSHTMLEdit = null, neoCMSFiles = null, neoCMSFilePath = null,
    neoCMSImagePath = null;
$(function () {
    neocmsSetup();
    $(window).bind('resize', windowResize);
    $('#logout a').bind('click', logout);
    $('#neoCMSPrefBtn').bind('click', savePrefs);
    $('#neoCMSFTPBtn').bind('click', saveFTP);
    $('#neoCMSFTPClearBtn').bind('click', clearFTPPrompt);
    $('#neoCMSNewUserLink').bind('click', newUser);
    $('#neoCMSNewUserCancel').bind('click', newUserCancel);
    $('#neoCMSDashScrollWrap').height($(window).height() - 60);
    $('#neoCMSAutoUpdate').bind('click', autoUpdatePrompt);
    $('#neoCMSMessage').bind('keyup', function () {
        if (getNoLines(this) > parseInt(this.rows)) this.rows = '' + Math.round((parseInt(this.rows) * 1.2))
    })
});
neocmsSetup = function () {
    $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
    var prefsIframe;
    try {
        prefsIframe = document.createElement('<iframe name="neoCMSPrefsFrame" >')
    } catch (ex) {
        prefsIframe = document.createElement('iframe')
    }
    $(prefsIframe).attr({
        id: 'neoCMSPrefsFrame',
        name: 'neoCMSPrefsFrame',
        src: '../core/sessionConfiguration.php',
        application: 'yes'
    }).css('display', 'none');
    $('body').append(prefsIframe)
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
    changePrefs()
};
savePrefs = function () {
    var html = $('input[name=htmltoggle]:checked').val();
    $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
    var savePrefsIframe;
    try {
        savePrefsIframe = document.createElement('<iframe name="neoCMSSavePrefsFrame" >')
    } catch (ex) {
        savePrefsIframe = document.createElement('iframe')
    }
    $(savePrefsIframe).attr({
        id: 'neoCMSSavePrefsFrame',
        name: 'neoCMSSavePrefsFrame',
        application: 'yes'
    }).css('display', 'none');
    $('body').append(savePrefsIframe);
    $('#neoCMSPrefsForm')[0].submit()
};
changePrefs = function () {
    var winHref = winHref = window.location.href;
    var urlName = winHref.split('//');
    urlName = urlName[1];
    $('#neoCMSPath').empty();
    var linkHref = winHref.split('settings/');
    linkHref = linkHref[0];
    $('#neoCMSPath').html(linkHref);
    if (neoCMSHTMLEdit == 'Y') {
        $('#htmltoggleon').attr({checked: 'checked'});
        $('#htmltoggleoff').removeAttr('checked')
    } else {
        $('#htmltoggleoff').attr({checked: 'checked'});
        $('#htmltoggleon').removeAttr('checked')
    }
    if (neoCMSFiles == 'Y') {
        $('#filetoggleon').attr({checked: 'checked'});
        $('#filetoggleoff').removeAttr('checked')
    } else {
        $('#filetoggleoff').attr({checked: 'checked'});
        $('#filetoggleon').removeAttr('checked')
    }
    if (neoCMSFilePath != '') $('#neoCMSFilePath').val(neoCMSFilePath);
    if (neoCMSImagePath != '') $('#neoCMSImagePath').val(neoCMSImagePath);
    if (neoCMSExFolders != '') $('#neoCMSExFolders').val(neoCMSExFolders);
    $('#neoCMSPrefsFrame, #neoCMSSavePrefsFrame').remove();
    if ($('#neoCMSAcctFormSuperAdmin').length) neocmsUsers(); else $('#neoCMSTopBanner').css('background', 'none');
    twitterFeed()
};
logout = function () {
    $.ajax({
        type: "POST", url: "../functions/logout.php", success: function () {
            window.location = '../../'
        }
    })
};
showPrompt = function () {
    $('#neoCMSPrompt:hidden').css({display: "block", opacity: "0"}).fadeTo(300, 1.0, function () {
        if ($.browser.msie) $(this)[0].style.removeAttribute('filter')
    })
};
neocmsPromptTop = function () {
    $('<div/>').attr('id', 'neoCMSPromptTop').appendTo('#neoCMSPrompt').append($('<img/>').attr({
        src: '../images/img_upload_select_top.png',
        height: '10',
        width: '353',
        alt: 'top'
    }))
};
neocmsPromptBot = function () {
    $('<div/>').attr('id', 'neoCMSPromptBot').appendTo('#neoCMSPrompt').append($('<img/>').attr({
        src: '../images/img_upload_select_bottom.png',
        height: '10',
        width: '353',
        alt: 'top'
    }))
};
cancel = function () {
    if ($('#neoCMSPromptWrap').length > 0) $('#neoCMSPromptWrap').remove();
    $("#overlay").fadeTo(300, 0, function () {
        $('#overlay').remove()
    })
};
windowResize = function () {
    if ($('#overlay').length) $('#overlay').css({width: $(window).width(), height: $(window).height()})
};
overlay = function (ele) {
    $('<div/>').attr('id', 'overlay').height($(document).height()).width($(document).width()).appendTo('body').css({
        display: "block",
        opacity: "0"
    }).fadeTo(300, 0.55)
};
userBar = function (message) {
    if ($('#neoCMSPrompt').length) cancel();
    $('#neoCMSTopBanner').css('background', 'none');
    if (!$('#neoCMSUserBar').length) {
        $('<div/>').addClass('neoCMSUserBar').attr('id', 'neoCMSUserBar').insertAfter('#neoCMSTopBanner').append($('<div/>').addClass('neoCMSUserBarIn').attr('id', 'neoCMSUserBarIn').append($('<a/>').attr('href', 'javascript:').html('Hide This Bar').bind('click', hideUserBar)).append($('<p/>')));
        if (message.match(/successfully/gi)) {
            $('#neoCMSUserBar').find('p').html('<strong>' + message + '</strong>').end().css('opacity', '0.4').animate({opacity: '1'}, 800);
            var userBarFade = setTimeout(function () {
                $('#neoCMSUserBar').animate({opacity: '0'}, 300, function () {
                    $('#neoCMSUserBar').remove()
                })
            }, 3500)
        } else {
            $('#neoCMSUserBar').find('p').html(message).end().hide().css({
                opacity: '1.0',
                backgroundColor: '#c6242b',
                position: 'relative',
                top: '0',
                left: '0'
            }).slideDown(300)
        }
    } else {
        $('#neoCMSUserBar').remove();
        window.clearTimeout(userBarFade);
        userBar(message)
    }
    if ($('#neoCMSNewUser').css('display') == 'block') $('#neoCMSNewUser').find('input').val('').end().find('input:first').focus()
};
hideUserBar = function () {
    if ($('#neoCMSUserBar').css('position') == 'relative') $('#neoCMSUserBar').animate({marginTop: '-26px'}, 300, function () {
        $('#neoCMSUserBar').remove()
    }); else $('#neoCMSUserBar').animate({opacity: '0'}, 200, function () {
        $('#neoCMSUserBar').remove()
    })
};
var v = 0;

removeFrames = function () {
    $('#neoCMSUsersFrame, #neoCMSSaveUserFrame, #neoCMSDelUserFrame, #neoCMSSaveFTPFrame, #neoCMSClearFTPFrame').remove();
    $('#neoCMSTopBanner').css('background', 'none');
    cancel()
};

getNoLines = function (ele) {
    var hardlines = ele.value.split('\n');
    var total = hardlines.length;
    for (var i = 0, len = hardlines.length; i < len; i++) {
        total += Math.max(Math.round(hardlines[i].length / ele.cols), 1) - 1
    }
    return total
};
String.prototype.strReverse = function () {
    var newstring = "";
    for (var s = 0; s < this.length; s++) {
        newstring = this.charAt(s) + newstring
    }
    return newstring
};

countContain = function (strPassword, strCheck) {
    var nCount = 0;
    for (i = 0; i < strPassword.length; i++) {
        if (strCheck.indexOf(strPassword.charAt(i)) > -1) nCount++
    }
    return nCount
};

reloadPage = function () {
    window.location.reload(true);
    window.location.reload(true)
};
