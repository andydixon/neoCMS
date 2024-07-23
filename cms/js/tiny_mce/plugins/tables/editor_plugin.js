(function () {

    tinymce.create('tinymce.plugins.tables', {
        init: function (ed, url) {
            ed.addCommand('mceTables', function () {

                makeTable();

            });

            ed.addButton('table', {
                title: 'Add a Table',
                cmd: 'mceTables'
            });
//			
//			ed.onNodeChange.add(function(ed, cm, n) {
//				cm.setActive('link', n.nodeName == 'A');
//			});

        },

        createControl: function (n, cm) {
            return null;
        },

        getInfo: function () {
            return {
                longname: 'neocms Table Creator',
                author: '',
                authorurl: '',
                infourl: '',
                version: "1.0"
            };
        }
    });

    tinymce.PluginManager.add('tables', tinymce.plugins.tables);
})();