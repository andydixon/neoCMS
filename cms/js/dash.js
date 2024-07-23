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
neocmsUsers = function () {
    var usersIframe;
    try {
        usersIframe = document.createElement('<iframe name="neoCMSUsersFrame" >')
    } catch (ex) {
        usersIframe = document.createElement('iframe')
    }
    $(usersIframe).attr({
        id: 'neoCMSUsersFrame',
        name: 'neoCMSUsersFrame',
        src: '../core/getsaveusers.php',
        application: 'yes'
    }).css('display', 'none');
    $('body').append(usersIframe)
};
adminUser = function (name, user, email) {
    $('#neoCMSAcctFormSuperAdmin').find('h3').html(name + ' <strong>Admin</strong>').prepend($('<span/>').append($('<a/>').addClass('neoCMSAcctModify').attr({
        href: 'javascript:',
        title: 'edit your login information'
    }).html('modify'))).end().find('.neoCMSAcctUserLi input').val(user).end().find('.neoCMSAcctEmailLi input').val(email);
    $('#neoCMSAcctFormSuperAdmin a.neoCMSAcctModify').unbind('click').bind('click', userModify)
};
buildUser = function (name, user, email, uid) {
    if (name && user && email) {
        if (!$('#user' + uid).length) {
            var userArea = '<div id="user' + uid + '" class="neoCMSDashElement"><form class="neoCMSAcctForm neoCMSAcctDisplay" action="../core/getsaveusers.php" method="post" target="neoCMSSaveUserFrame" ><h3></h3><ul><li class="neoCMSAcctUserLi"><label>Username </label><input class="neoCMSLocked neoCMSAcctUser" name="neoCMSAcctUser" type="text" value="" readonly="true" /><input class="neoCMSLocked neoCMSAcctUser-orig" name="neoCMSAcctUser-orig" type="hidden" value="" readonly="true" /></li><li class="neoCMSAcctPassLi"><label>Password </label><input id="neoCMSAcctPass" class="neoCMSLocked neoCMSAcctPass" name="neoCMSAcctPass" type="text" value="TOTALLY SECURE, REALLY" readonly="true" /></li></ul><ul class="neoCMSAcctMeta"><li class="neoCMSAcctEmailLi"><label>Email </label><input class="neoCMSLocked neoCMSAcctEmail" name="neoCMSAcctEmail" type="text" value="" readonly="true" /><input class="neoCMSLocked neoCMSAcctEmail-orig" name="neoCMSAcctEmail-orig" type="hidden" value="" readonly="true" /></li></ul></form></div>';
            userArea = $(userArea);
            $(userArea).find('h3').html(name).prepend($('<span/>').append($('<a/>').addClass('neoCMSAcctModify').attr({
                href: 'javascript:',
                title: 'edit this user'
            }).html('modify')).append('|').append($('<a/>').addClass('neoCMSAcctDelete').attr({
                href: 'javascript:',
                title: 'delete this user'
            }).html('delete'))).end().find('.neoCMSAcctUserLi input').val(user).end().find('.neoCMSAcctEmailLi input').val(email);
            $(userArea).find('a.neoCMSAcctModify').bind('click', userModify).end().find('a.neoCMSAcctDelete').bind('click', function () {
                userDeletePrompt(this)
            }).end().insertAfter('.neoCMSDashElement:last')
        } else {
            $('#user' + uid).find('h3').html(name).prepend($('<span/>').append($('<a/>').addClass('neoCMSAcctModify').attr({
                href: 'javascript:',
                title: 'edit this user'
            }).html('modify')).append('|').append($('<a/>').addClass('neoCMSAcctDelete').attr({
                href: 'javascript:',
                title: 'delete this user'
            }).html('delete'))).end().find('.neoCMSAcctUserLi input').val(user).end().find('.neoCMSAcctEmailLi input').val(email).end().find('a.neoCMSAcctModify').unbind('click').bind('click', userModify).end().find('a.neoCMSAcctDelete').unbind('click').bind('click', function () {
                userDeletePrompt(this)
            })
        }
    }
};
removeFrames = function () {
    $('#neoCMSUsersFrame, #neoCMSSaveUserFrame, #neoCMSDelUserFrame, #neoCMSSaveFTPFrame, #neoCMSClearFTPFrame').remove();
    $('#neoCMSTopBanner').css('background', 'none');
    cancel()
};
newUser = function () {
    userModifyCancel();
    $('#neoCMSNewUser').css('display', 'block').find('input:first').focus().end().find('#neoCMSAcctFormNewUser').addClass('neoCMSNewAcctEditing').end().find('#neoCMSNewUserSave').bind('click', userModifySave);
    $('#neoCMSNewUserLink').unbind('click').bind('click', newUserCancel);
    $('#neoCMSNewAcctPass').unbind('keyup').bind('keyup', function () {
        runPassword(this)
    });
    setTimeout(function () {
        $('#neoCMSNewName').focus()
    }, 0)
};
newUserCancel = function () {
    $('#neoCMSNewUser').css('display', 'none').find('input').val('');
    $('#pwStrengthNew').removeClass().html('n/a <span class="advice">&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required.</span>');
    $('#neoCMSNewUserLink').unbind('click', newUserCancel).bind('click', newUser)
};
userModify = function () {
    newUserCancel();
    if (!$('.neoCMSAcctPassConLi').length) {
        $(this.parentNode.parentNode.parentNode).addClass('neoCMSAcctEditing').find('input').removeAttr('readonly').removeClass('neoCMSLocked').addClass('neoCMSUnlocked').end().find('.neoCMSAcctUserLi').append($('<span/>').addClass('error').html('Please create a username')).end().find('.neoCMSAcctPassLi').append($('<span/>').addClass('error').html('&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required.')).after($('<li/>').addClass('neoCMSAcctPassConLi').append($('<label/>').html('Confirm Password')).append($('<input/>').addClass('neoCMSUnlocked neoCMSAcctPassCon').attr({
            name: 'neoCMSAcctPassConfirm',
            type: 'password'
        })).append($('<span/>').addClass('error').html('Must match password'))).after($('<li/>').addClass('neoCMSAcctPassInfo').attr('id', 'pwStrength').html('n/a <span class="advice">&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required. Leave blank if you don&rsquo;t want a new password.</span>')).end().find('.neoCMSAcctPassLi label').html('New Password ').end().find('.neoCMSAcctPassLi input').replaceWith($('<input/>').attr('id', 'neoCMSAcctPass').addClass('neoCMSUnlocked neoCMSAcctPass').attr({
            type: 'password',
            name: 'neoCMSAcctPass'
        }).val('')).end().find('.neoCMSAcctEmailLi').append($('<span/>').addClass('error').html('Please enter a valid email')).end().find('.neoCMSAcctMeta').append($('<li/>').addClass('neoCMSFormSave').append($('<a/>').addClass('userSave').attr({
            href: 'javascript:',
            title: 'save'
        }).html('save').bind('click', userModifySave)).append($('<span/>').addClass('neoCMSCancel').html('or ').append($('<a/>').attr('href', 'javascript:').html('cancel').bind('click', userModifyCancel))))
    }
    $('#neoCMSAcctPass').unbind('keyup').bind('keyup', function () {
        runPassword(this)
    });
    $('a.neoCMSAcctModify').unbind('click', userModify).bind('click', userModifyCancel);
    $('#neoCMSAcctFormSuperAdmin .neoCMSAcctUser input:first').focus()
};
userModifySave = function () {
    if (userModifyValid()) {
        $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
        var saveUserIframe;
        try {
            saveUserIframe = document.createElement('<iframe name="neoCMSSaveUserFrame" >')
        } catch (ex) {
            saveUserIframe = document.createElement('iframe')
        }
        $(saveUserIframe).attr({
            id: 'neoCMSSaveUserFrame',
            name: 'neoCMSSaveUserFrame',
            application: 'yes'
        }).css('display', 'none');
        $('body').append(saveUserIframe);
        $(this).parents('.neoCMSAcctForm')[0].submit();
        userModifyCancel()
    }
};
userModifyValid = function (f) {
    $('.neoCMSAcctEditing li.error').removeClass('error');
    if (f) validCheck(f); else {
        v = 0;
        var fields = $('.neoCMSAcctEditing input, .neoCMSNewAcctEditing input').not('.neoCMSAcctUser-orig, .neoCMSAcctEmail-orig');
        $(fields).each(function () {
            validCheck(this)
        });
        $('.neoCMSAcctEditing input, .neoCMSNewAcctEditing input').not('.neoCMSAcctUser-orig, .neoCMSAcctEmail-orig').unbind('change').bind('change', function () {
            validCheck(this)
        });
        if (v == $(fields).length) return true
    }
};
validCheck = function (f) {
    var emailreg = /^[a-zA-Z0-9,!#\$%&'\*\+\/=\?\^_`\{\|}~-]+(\.[a-z0-9,!#\$%&'\*\+\/=\?\^_`\{\|}~-]+)*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/,
        passreg = /^\w*(?=\w*\d)(?=\w*[a-z])(?=\w*[A-Z])\w{5,}$/, c = false;
    var tval = $(f).val(), fclass = $(f).attr('class'), fpar = $(f).parent();
    if (fclass.match(/neoCMSAcctEmail/g)) {
        if (emailreg.test(tval) == true) {
            c = true;
            fpar.removeClass('error')
        } else fpar.addClass('error')
    } else if (fclass.match(/neoCMSAcctPass\b/)) {
        if ($(f).parents('.neoCMSAcctEditing').length) {
            if ($('#pwStrength').attr('class').match(/strong|medium/) || tval == '') {
                c = true;
                fpar.removeClass('error')
            } else fpar.addClass('error')
        } else {
            if ($('#pwStrengthNew').attr('class').match(/strong|medium/)) {
                c = true;
                fpar.removeClass('error')
            } else fpar.addClass('error')
        }
    } else if (fclass.match(/neoCMSAcctPassCon/)) {
        if ($(f).parents('.neoCMSAcctEditing').length) {
            if (tval == $('.neoCMSAcctPass', '.neoCMSAcctEditing').val()) {
                c = true;
                fpar.removeClass('error')
            } else fpar.addClass('error')
        } else {
            if (tval == $('.neoCMSAcctPass', '.neoCMSNewAcctEditing').val()) {
                c = true;
                fpar.removeClass('error')
            } else fpar.addClass('error')
        }
    }
    if (fclass.match(/neoCMSFTPServer/g)) {
        if (!tval.match(/^ftp\./)) {
            c = true;
            fpar.removeClass('error')
        } else fpar.addClass('error')
    } else {
        if (tval != '') {
            c = true;
            fpar.removeClass('error')
        } else fpar.addClass('error')
    }
    if (c) v++
};
userModifyCancel = function () {
    $('.neoCMSAcctEditing li.error').removeClass('error');
    $('.neoCMSAcctEditing').removeClass('neoCMSAcctEditing').find('.neoCMSAcctPassInfo').remove().end().find('.neoCMSAcctPassLi label').html('Password ').end().find('.neoCMSAcctPassLi input').replaceWith($('<input/>').addClass('neoCMSLocked').attr({
        type: 'text',
        name: 'neoCMSAcctPass'
    }).val('TOTALLY SECURE, REALLY')).end().find('#pwStrength').remove().end().find('input').removeClass('neoCMSUnlocked').addClass('neoCMSLocked').attr('readonly', 'true').end().find('.neoCMSAcctPassConLi').remove().end().find('.neoCMSFormSave').remove().end().find('li span').remove();
    $('#neoCMSAcctPass').unbind('keyup');
    $('a.neoCMSAcctModify').unbind('click', userModifyCancel).bind('click', userModify)
};
userDeletePrompt = function (user) {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('../templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Delete User');
            $('#neoCMSPrompt p').html('Are you sure you want to delete this user? You cannot undo this action.');
            $('#neoCMSPromptBtn').addClass('neoCMSDeleteBtn').attr('title', 'delete this user').html('delete').bind('click', function () {
                var uid = $(user).parents('.neoCMSDashElement').attr('id').replace('user', '');
                userDelete(uid)
            });
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt()
        }))
    }
};
userDelete = function (user) {
    $('.neoCMSDashElement').not('#neoCMSSuperAdmin').each(function () {
        $('#user' + user).remove()
    });
    var delUserIframe;
    try {
        delUserIframe = document.createElement('<iframe name="neoCMSDelUserFrame" >')
    } catch (ex) {
        delUserIframe = document.createElement('iframe')
    }
    $(delUserIframe).attr({
        id: 'neoCMSDelUserFrame',
        name: 'neoCMSDelUserFrame',
        src: '../core/getsaveusers.php?neoCMSDelUser=' + user,
        application: 'yes'
    }).css('display', 'none');
    $('body').append(delUserIframe)
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
checkPassword = function (pwd) {
    var nScore = 0, nLength = 0, nAlphaUC = 0, nAlphaLC = 0, nNumber = 0, nSymbol = 0, nMidChar = 0, nAlphasOnly = 0,
        nNumbersOnly = 0, nRepChar = 0, nConsecAlphaUC = 0, nConsecAlphaLC = 0, nConsecNumber = 0, nConsecSymbol = 0,
        nConsecCharType = 0, nSeqAlpha = 0, nSeqNumber = 0, nSeqChar = 0, nMultLength = 4, nMultNumber = 4,
        nMultSymbol = 6, nMultMidChar = 2, nMultConsecAlphaUC = 2, nMultConsecAlphaLC = 2, nMultConsecNumber = 2,
        nMultSeqAlpha = 3, nMultSeqNumber = 3, nTmpAlphaUC = "", nTmpAlphaLC = "", nTmpNumber = "", nTmpSymbol = "",
        sAlphas = "abcdefghijklmnopqrstuvwxyz", sNumerics = "01234567890";
    nScore = parseInt(pwd.length * nMultLength);
    nLength = pwd.length;
    var arrPwd = pwd.replace(/\s+/g, "").split(/\s*/), arrPwdLen = arrPwd.length;
    for (var a = 0; a < arrPwdLen; a++) {
        if (arrPwd[a].match(new RegExp(/[A-Z]/g))) {
            if (nTmpAlphaUC !== "") {
                if ((nTmpAlphaUC + 1) == a) {
                    nConsecAlphaUC++;
                    nConsecCharType++
                }
            }
            nTmpAlphaUC = a;
            nAlphaUC++
        } else if (arrPwd[a].match(new RegExp(/[a-z]/g))) {
            if (nTmpAlphaLC !== "") {
                if ((nTmpAlphaLC + 1) == a) {
                    nConsecAlphaLC++;
                    nConsecCharType++
                }
            }
            nTmpAlphaLC = a;
            nAlphaLC++
        } else if (arrPwd[a].match(new RegExp(/[0-9]/g))) {
            if (a > 0 && a < (arrPwdLen - 1)) {
                nMidChar++
            }
            if (nTmpNumber !== "") {
                if ((nTmpNumber + 1) == a) {
                    nConsecNumber++;
                    nConsecCharType++
                }
            }
            nTmpNumber = a;
            nNumber++
        } else if (arrPwd[a].match(new RegExp(/[^a-zA-Z0-9_]/g))) {
            if (a > 0 && a < (arrPwdLen - 1)) {
                nMidChar++
            }
            if (nTmpSymbol !== "") {
                if ((nTmpSymbol + 1) == a) {
                    nConsecSymbol++;
                    nConsecCharType++
                }
            }
            nTmpSymbol = a;
            nSymbol++
        }
        for (var b = 0; b < arrPwdLen; b++) {
            if (arrPwd[a].toLowerCase() == arrPwd[b].toLowerCase() && a != b) {
                nRepChar++
            }
        }
    }
    for (var s = 0; s < 23; s++) {
        var sFwd = sAlphas.substring(s, parseInt(s + 3));
        var sRev = sFwd.strReverse();
        if (pwd.toLowerCase().indexOf(sFwd) != -1 || pwd.toLowerCase().indexOf(sRev) != -1) {
            nSeqAlpha++;
            nSeqChar++
        }
    }
    for (var s = 0; s < 8; s++) {
        var sFwd = sNumerics.substring(s, parseInt(s + 3));
        var sRev = sFwd.strReverse();
        if (pwd.toLowerCase().indexOf(sFwd) != -1 || pwd.toLowerCase().indexOf(sRev) != -1) {
            nSeqNumber++;
            nSeqChar++
        }
    }
    if (nAlphaUC > 0 && nAlphaUC < nLength) {
        nScore = parseInt(nScore + ((nLength - nAlphaUC) * 2));
        sAlphaUC = "+ " + parseInt((nLength - nAlphaUC) * 2)
    }
    if (nAlphaLC > 0 && nAlphaLC < nLength) {
        nScore = parseInt(nScore + ((nLength - nAlphaLC) * 2));
        sAlphaLC = "+ " + parseInt((nLength - nAlphaLC) * 2)
    }
    if (nNumber > 0 && nNumber < nLength) {
        nScore = parseInt(nScore + (nNumber * nMultNumber));
        sNumber = "+ " + parseInt(nNumber * nMultNumber)
    }
    if (nSymbol > 0) {
        nScore = parseInt(nScore + (nSymbol * nMultSymbol));
        sSymbol = "+ " + parseInt(nSymbol * nMultSymbol)
    }
    if (nMidChar > 0) {
        nScore = parseInt(nScore + (nMidChar * nMultMidChar));
        sMidChar = "+ " + parseInt(nMidChar * nMultMidChar)
    }
    if ((nAlphaLC > 0 || nAlphaUC > 0) && nSymbol === 0 && nNumber === 0) {
        nScore = parseInt(nScore - nLength);
        nAlphasOnly = nLength;
        sAlphasOnly = "- " + nLength
    }
    if (nAlphaLC === 0 && nAlphaUC === 0 && nSymbol === 0 && nNumber > 0) {
        nScore = parseInt(nScore - nLength);
        nNumbersOnly = nLength;
        sNumbersOnly = "- " + nLength
    }
    if (nRepChar > 0) {
        nScore = parseInt(nScore - (nRepChar * nRepChar));
        sRepChar = "- " + nRepChar
    }
    if (nConsecAlphaUC > 0) {
        nScore = parseInt(nScore - (nConsecAlphaUC * nMultConsecAlphaUC));
        sConsecAlphaUC = "- " + parseInt(nConsecAlphaUC * nMultConsecAlphaUC)
    }
    if (nConsecAlphaLC > 0) {
        nScore = parseInt(nScore - (nConsecAlphaLC * nMultConsecAlphaLC));
        sConsecAlphaLC = "- " + parseInt(nConsecAlphaLC * nMultConsecAlphaLC)
    }
    if (nConsecNumber > 0) {
        nScore = parseInt(nScore - (nConsecNumber * nMultConsecNumber));
        sConsecNumber = "- " + parseInt(nConsecNumber * nMultConsecNumber)
    }
    if (nSeqAlpha > 0) {
        nScore = parseInt(nScore - (nSeqAlpha * nMultSeqAlpha));
        sSeqAlpha = "- " + parseInt(nSeqAlpha * nMultSeqAlpha)
    }
    if (nSeqNumber > 0) {
        nScore = parseInt(nScore - (nSeqNumber * nMultSeqNumber));
        sSeqNumber = "- " + parseInt(nSeqNumber * nMultSeqNumber)
    }
    return nScore
};
runPassword = function (t) {
    var nScore = checkPassword(t.value), strength, strText, helper;
    if (nScore < 0) nScore = 0; else if (nScore > 100) nScore = 100;
    if (nScore >= 60) strength = "strong", strText = strength + " (" + nScore + "%)", helper = "Great password! Now just confirm in the next field."; else if (nScore >= 30 && nScore <= 59) strength = "medium", strText = strength + " (" + nScore + "%)", helper = "That will do! Now just confirm in the next field."; else strText = strength = "weak", strText = strength + " (" + nScore + "%)", helper = "&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required. Varied lettercase, numbers, & special characters help.";
    if ($(t).val() == '') {
        $('#pwStrength').removeClass().html('n/a <span class="advice">&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required. Leave blank if you don&rsquo;t want a new password.</span>');
        if ($(t).parents('#neoCMSAcctFormNewUser').length) $('#pwStrengthNew').removeClass().html('n/a <span class="advice">&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required.</span>')
    } else {
        var pid = ($(t).parents('#neoCMSAcctFormNewUser').length) ? '#pwStrengthNew' : '#pwStrength';
        $(pid).removeClass().addClass(strength).html(strText + ' <span class="advice">' + helper + '</span>')
    }
};
countContain = function (strPassword, strCheck) {
    var nCount = 0;
    for (i = 0; i < strPassword.length; i++) {
        if (strCheck.indexOf(strPassword.charAt(i)) > -1) nCount++
    }
    return nCount
};
formatTweet = function (txt) {
    var urls = txt.match(/http[^\s]+/gi);
    if (urls) for (var i = 0; i < urls.length; i++) txt = txt.replace(urls[i], '<a href="' + urls[i] + '">' + urls[i] + '</a>');
    var users = txt.match(/^@[^\s]+|\s@[^\s]+\b/gi);
    if (users) {
        for (var i = 0; i < users.length; i++) {
            users[i] = users[i].replace(' ', '');
            users[i] = users[i].replace(':', '');
            var user = users[i].replace(/@/, '');
            txt = txt.replace(users[i], '@<a href="http://twitter.com/' + user + '">' + user + '</a>')
        }
    }
    var hashes = txt.match(/#[^\s]+/gi);
    if (hashes) {
        for (var i = 0; i < hashes.length; i++) {
            hashes[i] = hashes[i].replace(' ', '');
            hashes[i] = hashes[i].replace(':', '');
            var hash = hashes[i].replace(/#/, '');
            txt = txt.replace(hashes[i], '#<a href="http://twitter.com/search?q=#' + hash + '">' + hash + '</a>')
        }
    }
    return txt
};
twitterFeed = function () {
    $.ajax({
        type: "GET",
        crossDomain: true,
        url: "http://twitter.com/statuses/user_timeline/neogateTechnologies.json?count=100",
        dataType: "jsonp",
        success: function (data) {
            var ts = '', c = 0;
            $(data).each(function (i) {
                var txt = this.text;
                if (txt.match(/#neocms/gi)) {
                    c++;
                    ts += '<li>' + formatTweet(txt) + '</li>';
                    if (c == 5 || i == data.length - 1) {
                        $('#twitter_update_list').html(ts);
                        return false
                    }
                }
            })
        }
    })
};
saveFTP = function () {
    if (FTPvalid()) {
        $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
        var saveFTPIframe;
        try {
            saveFTPIframe = document.createElement('<iframe name="neoCMSSaveFTPFrame" >')
        } catch (ex) {
            saveFTPIframe = document.createElement('iframe')
        }
        $(saveFTPIframe).attr({
            id: 'neoCMSSaveFTPFrame',
            name: 'neoCMSSaveFTPFrame',
            application: 'yes'
        }).css('display', 'none');
        $('body').append(saveFTPIframe);
        $('#neoCMSFTPForm')[0].submit()
    }
};
clearFTPPrompt = function (user) {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('../templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Clear FTP Info');
            $('#neoCMSPrompt p').html('Are you sure you want to clear your FTP info? You cannot undo this.');
            $('#neoCMSPromptBtn').addClass('neoCMSPromptBtn').attr('title', 'clear').html('clear').bind('click', function () {
                clearFTP()
            });
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt()
        }))
    }
};
clearFTP = function () {
    $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
    var clearFTPIframe;
    try {
        clearFTPIframe = document.createElement('<iframe name="neoCMSClearFTPFrame" >')
    } catch (ex) {
        clearFTPIframe = document.createElement('iframe')
    }
    $(clearFTPIframe).attr({
        id: 'neoCMSClearFTPFrame',
        name: 'neoCMSClearFTPFrame',
        application: 'yes',
        src: '../core/ftpclear.php'
    }).css('display', 'none');
    $('body').append(clearFTPIframe)
};
clearFTPInfo = function () {
    $('#neoCMSFTPForm input').val('')
};
FTPvalid = function (f) {
    $('#neoCMSFTPForm li.error').removeClass('error');
    if (f) validCheck(f); else {
        v = 0;
        var fields = $('#neoCMSFTPForm input');
        $(fields).each(function () {
            validCheck(this)
        });
        $('#neoCMSFTPForm input').unbind('change').bind('change', function () {
            validCheck(this)
        });
        if (v == $(fields).length) return true
    }
};
autoUpdatePrompt = function () {
    overlay();
    if (!$('#neoCMSPrompt').length) {
        $('body').append($('<div/>').attr('id', 'neoCMSPromptWrap').load('../templates/prompt.html', function () {
            $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Auto-Update neocms');
            $('#neoCMSPrompt p').addClass('simple').html('Are you sure you want to update? Your preferences, FTP info, and restore states will be preserved. You cannot undo or cancel once you start the auto-update process. It should take less than 3 minutes.');
            $('#neoCMSPromptBtn').addClass('neoCMSPromptBtn').attr('title', 'Auto-update neocms to the latest version').html('update').bind('click', autoUpdate);
            $('#neoCMSCancelBtn').bind('click', cancel);
            showPrompt()
        }))
    }
};
autoUpdate = function () {
    $('#neoCMSTopBanner').css('background', 'url(../images/loading.gif) repeat-x bottom');
    $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Auto-Updating&hellip; Please Wait');
    $('#neoCMSPrompt p').html('Uploading the new files now. Please do not refresh your browser or stop loading.');
    $('#neoCMSPromptBtn,#neoCMSCancelBtn').remove();
    var updateIframe;
    try {
        updateIframe = document.createElement('<iframe name="neoCMSUpdateFrame" >')
    } catch (ex) {
        updateIframe = document.createElement('iframe')
    }
    $(updateIframe).attr({
        id: 'neoCMSUpdateFrame',
        name: 'neoCMSUpdateFrame',
        application: 'yes',
        src: '../core/updatestart.php'
    }).css('display', 'none');
    $('body').append(updateIframe)
};
autoUpdateSuccess = function () {
    $('#neoCMSPrompt h4.neoCMSPromptTitle').html('Auto-Update was Successful!');
    $('#neoCMSPrompt p').html('You are now updated to the latest version of neocms. Please click &ldquo;done&rdquo;.');
    $('#neoCMSPromptInsert').append($('<a/>').attr({
        id: 'neoCMSPromptBtn',
        href: 'javascript:'
    }).addClass('neoCMSPromptBtn').attr('title', 'Start using the latest neocms!').html('done').bind('click', reloadPage))
};
reloadPage = function () {
    window.location.reload(true);
    window.location.reload(true)
};
