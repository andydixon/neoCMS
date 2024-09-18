$(document).ready(function () {

    var currentDiv;
    var iframeDoc;

    $('#frameContainer').on('load', function () {

        // Get the iframe document
        var iframe = document.getElementById('frameContainer');
        iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

        // Set up click handler on divs with class 'editable' in the iframe
        $(iframeDoc).find('.editable').css('cursor', 'pointer').click(function (event) {
            event.preventDefault();

            currentDiv = $(this);

            // Get the content of the div
            var content = currentDiv.html();

            // Set the content in TinyMCE
            $('#editor').val(content);

            // Open the modal
            $('#editModal').modal('show');

            // Initialize TinyMCE when the modal is shown
            $('#editModal').on('shown.bs.modal', function () {
                tinymce.init({
                    promotion: false,
                    branding: false,
                    selector: '#editor',
                    height: 300,
                    plugins: 'print preview paste importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime advlist lists wordcount textpattern noneditable help charmap quickbars emoticons',
                    toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media template link anchor codesample | ltr rtl',
                    images_upload_url: '/cms/image_upload.php', // URL of your server-side script
                    automatic_uploads: true,
                    images_reuse_filename: false,
                    images_upload_credentials: true,
                    force_br_newlines: false,
                    force_p_newlines: false,
                    forced_root_block: '',
                    setup: function (editor) {
                        editor.on('init', function (e) {
                            editor.setContent(content);
                        });
                    }
                });
            });

        });
    });

    $('#saveBtn').click(function () {
        // Get the data from TinyMCE
        var data = tinymce.get('editor').getContent();

        // Set the data back to the div in the iframe
        $(currentDiv).html(data);

        // Close the modal
        $('#editModal').modal('hide');
    });

    // Clean up TinyMCE when modal is closed
    $('#editModal').on('hidden.bs.modal', function () {
        if (tinymce.get('editor')) {
            tinymce.get('editor').remove();
        }
    });

    $('#savePage').click(() => {
        $.post("./controller/", {
            action: "save",
            uri: $('#frameContainer').contents().get(0).location.pathname,
            content: new XMLSerializer().serializeToString($('#frameContainer').contents().get(0))
        }, function (data) {
            alert(data)
        });
    });
});