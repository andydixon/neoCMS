(function () {

    tinymce.create('tinymce.plugins.images', {
        init: function (ed, url) {
            ed.addCommand('mceImages', function () {

                imageEditBuilder(false);

            });

            ed.addButton('image', {
                title: 'Add or Edit an Image',
                cmd: 'mceImages'
            });

            ed.onNodeChange.add(function (ed, cm, n) {
                cm.setActive('image', n.nodeName == 'IMG');
            });

        },

        createControl: function (n, cm) {
            return null;
        },

        getInfo: function () {
            return {
                longname: 'neocms Image Editor',
                author: '',
                authorurl: '',
                infourl: '',
                version: "1.0"
            };
        }
    });

    tinymce.PluginManager.add('images', tinymce.plugins.images);
})();
