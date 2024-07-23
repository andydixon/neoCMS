(function () {

    tinymce.create('tinymce.plugins.file', {
        init: function (ed, url) {
            ed.addCommand('mceFile', function () {

                uploadPrompt();

            });

            ed.addButton('file', {
                title: 'Upload a File',
                cmd: 'mceFile'
            });
            //
//			ed.onNodeChange.add(function(ed, cm, n) {
//				cm.setActive('deflist', n.nodeName == 'DT' || n.nodeName == 'DD');
//			});

        },

        createControl: function (n, cm) {
            return null;
        },

        getInfo: function () {
            return {
                longname: 'File Upload Interface',
                author: '',
                authorurl: '',
                infourl: '',
                version: "1.0"
            };
        }
    });

    tinymce.PluginManager.add('file', tinymce.plugins.file);
})();
