(function () {

    tinymce.create('tinymce.plugins.video', {
        init: function (ed, url) {
            ed.addCommand('mceVideo', function () {

                videoPrompt();

            });

            ed.addButton('video', {
                title: 'Embed Video',
                cmd: 'mceVideo'
            });

            ed.onNodeChange.add(function (ed, cm, n) {
                cm.setActive('video', n.nodeName == 'EMBED' || n.nodeName == 'OBJECT');
            });

        },

        createControl: function (n, cm) {
            return null;
        },

        getInfo: function () {
            return {
                longname: 'Video Embed',
                author: '',
                authorurl: '',
                infourl: '',
                version: "1.0"
            };
        }
    });

    tinymce.PluginManager.add('video', tinymce.plugins.video);
})();
