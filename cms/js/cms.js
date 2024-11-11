$(document).ready(function () {

    let currentDiv;
    let iframeDoc;

    $('#frameContainer').on('load', function () {

        // Get the iframe document
        const iframe = document.getElementById('frameContainer');
        iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

        // Set up click handler on divs with class 'editable' in the iframe
        $(iframeDoc).find('.editable').css('cursor', 'pointer').click(function (event) {
            editElement(event,$(this));
        });

        // Edit Element Shared functionality

        function editElement(event,element) {
            event.preventDefault();

            currentDiv = element;

            // Get the content of the div
            const content = currentDiv.html();

            // Set the content in TinyMCE
            $('#editor').val(content);

            // Open the modal and init TinyMCE
            $('#editModal').modal('show')
                .on('shown.bs.modal', function () {
                    tinymce.init({
                        promotion: false,
                        branding: false,
                        selector: '#editor',
                        height: 300,
                        plugins: 'print preview paste importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime advlist lists wordcount textpattern noneditable help charmap quickbars emoticons',
                        toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media template link anchor codesample | ltr rtl',
                        images_upload_url: '/cms/image_upload.php',
                        automatic_uploads: true,
                        images_reuse_filename: false,
                        images_upload_credentials: true,
                        force_br_newlines: false,
                        force_p_newlines: false,
                        forced_root_block: 'aaa',
                        newline_behavior: 'linebreak'
                    });
                });
        }

        regenerateButtons();

        // Set up event handlers for duplication buttons inside the iframe
        $(iframeDoc).on('click', '.duplicate-btn-before', function() {
            var parentElement = $(this).closest('.neo-dupe');
            var clonedElement = $(parentElement).clone();

            // Remove buttons from the cloned element
            clonedElement.find('.button-container').remove();

            // Add system classes
            clonedElement.addClass("neo-dupe");
            clonedElement.addClass("editable");

            // Insert the cloned element before the original parent element
            clonedElement.insertBefore(parentElement);

            // Trigger an event on the new duplicated element
            clonedElement.trigger('elementDuplicated');
        });

        $(iframeDoc).on('click', '.duplicate-btn-after', function() {
            var parentElement = $(this).closest('.neo-dupe');
            var clonedElement = $(parentElement).clone();

            // Remove buttons from the cloned element
            clonedElement.find('.button-container').remove();

            // Add system classes
            clonedElement.addClass("neo-dupe");
            clonedElement.addClass("editable");

            // Insert the cloned element after the original parent element
            clonedElement.insertAfter(parentElement);

            // Trigger an event on the new duplicated element
            clonedElement.trigger('elementDuplicated');
        });

        // Handle the custom 'elementDuplicated' event
        $(iframeDoc).on('elementDuplicated', '.neo-dupe', function(event) {
            // Example action: Animate the new element
            $(this).hide().fadeIn('slow');

            // Remove buttons if they are there
            $(this).find('.button-container').remove();

            editElement(event,$(this));
        });
        // Here for addButtonsForNeoDupe
    });


    // Save changes made to a div, but not save to the file on the server
    $('#saveBtn').click(function () {
        // Get the data from TinyMCE
        const data = tinymce.get('editor').getContent();

        // Set the data back to the div in the iframe
        $(currentDiv).html(data);

        // Close the modal
        $('#editModal').modal('hide');

        if (tinymce.get('editor')) {
            tinymce.get('editor').remove();
        }

    });

    // Clean up TinyMCE when modal is closed
    $('#editModal').on('hidden.bs.modal', function () {
        if (tinymce.get('editor')) {
            tinymce.get('editor').remove();
        }
    });

    // Handle Saving changes to the page
    $('#savePage').click(() => {

        // Remove the buttons container as not to make things awks
        $(iframeDoc).find('.button-container').remove();

        let frameContainerElement = $('#frameContainer').contents().get(0);
        $.post("/cms/controller/", {
            action: "save",
            uri: frameContainerElement.location.pathname,
            content: new XMLSerializer().serializeToString(frameContainerElement)
        }, function (data) {
            if (typeof data.error == "undefined") {
                showMessage(data.message, "success");
            } else {
                showMessage(data.message, "error");
            }
        });
        regenerateButtons();
    });

    // Show dialog for page templates
    $("#newPage").click(function () {
        loadListOfTemplates(); // Call the function to load items when the modal is opened
        $("#newPageDialog").dialog({
            modal: true,
            width: 500,
            buttons: {
                Close: function () {
                    $(this).dialog("close");
                }
            }
        });
    });

    // Handle form submission for creating a new page
    $("#newPageForm").submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        let selectedItemId = $('input[name="item"]:checked').val();
        let filename = $("#filename").val();

        $.ajax({
            url: "/cms/controller/?action=newPage",
            method: "POST",
            data: {
                template: selectedItemId,
                filename: filename
            },
            success: function (response) {
                if (typeof response.error != "undefined") {
                    showMessage(response.error, "error");
                } else {
                    showMessage("New page Created", "success");
                    $('#frameContainer').contents().get(0).location.href = response.url;
                    $('.ui-dialog-content').dialog('close');
                }
            },
            error: function () {
                showMessage("Failed to create the new page (API error)", "error");
            }
        });
    });

    // Function to load the items via an AJAX GET request and populate the radio buttons for page templates
    function loadListOfTemplates() {
        $.ajax({
            url: "/cms/controller/?action=getTemplates",
            method: "GET",
            dataType: "json",
            success: function (data) {
                let radioList = $('#radioList');
                radioList.empty(); // Clear previous content
                data.forEach(function (item, index) {
                    radioList.append(`
                <label>
                  <input type="radio" name="item" value="${item.id}" ${index === 0 ? 'checked' : ''}>
                  ${item.name}
                </label>
              `);
                });
            },
            error: function () {
                alert('Failed to load templates.');
            }
        });
    }

    // Function to load the list of filenames via AJAX
    function loadListOfPages() {
        $.ajax({
            url: "/cms/controller/?action=getPages", // Replace with your actual API endpoint
            method: "GET",
            dataType: "json",
            success: function (data) {
                let fileList = $('#fileList');
                fileList.empty(); // Clear previous list

                data.forEach(function (file) {
                    fileList.append(`<li data-url="${file.url}">${file.name}</li>`);
                });

                // Add click event to list items to redirect
                $("#fileList li").click(function () {
                    $('#frameContainer').contents().get(0).location.href = $(this).data("url"); // Redirect to the clicked file's URL
                    $('.ui-dialog-content').dialog('close');
                });
            },
            error: function () {
                alert('Failed to load file list.');
            }
        });
    }

    // Handle 'Select Page' button
    $("#selectPage").click(function () {
        loadListOfPages(); // Call function to load the file list
        $("#fileList").dialog({
            modal: true,
            width: 500,
            buttons: {
                Close: function () {
                    $('.ui-dialog-content').dialog('close');
                }
            }
        });
    });

    // Add buttons to all existing neo-dupe elements inside the iframe
    function regenerateButtons() {
        $(iframeDoc).find('.neo-dupe').each(function () {
            $(this).find('.buttonContainer').remove();
            addButtonsToNeoDupe(this);
        });
    }

    // Function to add buttons to a neo-dupe element inside the iframe
    function addButtonsToNeoDupe(element) {

        /**
         * DANGER WILL ROBINSON: This may break stuff, but is needed for the buttons to be positioned properly
         * Since this is alpha, lets see how we go.
         */
        if ($(element).css('position') === 'static') {
            $(element).css('position', 'relative');
        }

        var $buttonContainer = $(iframeDoc.createElement('div'));
        $buttonContainer.addClass('button-container');
        $buttonContainer.css("position","absolute");
        $buttonContainer.css("top","5px");
        $buttonContainer.css("right","5px");

        var $duplicateBeforeButton = $(iframeDoc.createElement('button'));
        $duplicateBeforeButton.addClass('duplicate-btn-before').text('◀️').prop('title','Clone before');

        var $duplicateAfterButton = $(iframeDoc.createElement('button'));
        $duplicateAfterButton.addClass('duplicate-btn-after').text('▶️').prop('title','Clone after');

        // Append buttons to the container
        $buttonContainer.append($duplicateBeforeButton, $duplicateAfterButton);

        // Prepend the container to the neo-dupe element
        $(element).prepend($buttonContainer);
    }

});

/**
 * Show a message bar across the top of the screen for a few seconds
 * @param message - string - message to be shown
 * @param type - string "success" or "error"
 */
function showMessage(message, type) {
    const messageBar = $('#message-bar');

    // Set the text message
    messageBar.text(message);

    // Set the color based on the type
    if (type === 'error') {
        messageBar.css('background-color', 'red');
    } else if (type === 'success') {
        messageBar.css('background-color', 'green');
    }

    // Set the full width and position
    messageBar.css({
        'width': '100%',
        'position': 'fixed',
        'top': '0',
        'left': '0',
        'padding': '10px',
        'color': 'white',
        'text-align': 'center',
        'z-index': '9999'
    });

    // Show the message bar
    messageBar.slideDown();

    // Hide the bar after 5 seconds
    setTimeout(function () {
        messageBar.slideUp();
    }, 5000);
}

