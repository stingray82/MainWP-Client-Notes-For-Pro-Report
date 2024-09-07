jQuery(document).ready(function($) {

    // Trigger focus and blur on the date field to force correct rendering on load
    function fixDatePicker() {
        var dateInput = $('input[name="work_notes_date"]');
        dateInput.focus();
        dateInput.blur();
    }

    // Call the function after the document is ready
    fixDatePicker();

    // Handle saving the note (both new and edited)
    $('#save-work-note').on('click', function() {
        var data = {
            action: 'save_work_note',
            nonce: mainwpWorkNotes.nonce,
            wpid: $('input[name="wpid"]').val(),
            note_id: $('input[name="note_id"]').val(),  // Note ID for editing
            work_notes_date: $('input[name="work_notes_date"]').val(),
            work_notes_content: $('textarea[name="work_notes_content"]').val(),
        };

        $.post(mainwpWorkNotes.ajax_url, data, function(response) {
            if (response.success) {
                alert('Note saved successfully!');
                location.reload();  // Reload the page
            } else {
                alert(response.data.message);
            }
        });
    });

    // Handle loading note data into the form for editing
    $('.edit-note').on('click', function() {
        var noteId = $(this).data('note-id');
        var wpid = $('input[name="wpid"]').val();

        var data = {
            action: 'load_work_note',
            nonce: mainwpWorkNotes.nonce,
            wpid: wpid,
            note_id: noteId
        };

        $.post(mainwpWorkNotes.ajax_url, data, function(response) {
            if (response.success) {
                // Load the note data into the form
                $('input[name="note_id"]').val(noteId);  // Set note ID for editing
                $('input[name="work_notes_date"]').val(response.data.date);
                $('textarea[name="work_notes_content"]').val(response.data.content);
                
                // Fix the date picker after setting the date value
                fixDatePicker();
            } else {
                alert(response.data.message);
            }
        });
    });

    // Handle deleting the note
    $('.delete-note').on('click', function() {
        var noteId = $(this).data('note-id');
        var wpid = $('input[name="wpid"]').val();

        var data = {
            action: 'delete_work_note',
            nonce: mainwpWorkNotes.nonce,
            wpid: wpid,
            note_id: noteId
        };

        $.post(mainwpWorkNotes.ajax_url, data, function(response) {
            if (response.success) {
                alert('Note deleted successfully!');
                location.reload();  // Reload the page
            } else {
                alert(response.data.message);
            }
        });
    });

});
jQuery(document).ready(function($) {
    // Handle click event for the Work Notes sitetab
    $('#mainwp_tab_WorkNotes').on('click', function(e) {
    e.preventDefault();

    var siteId = $(this).data('siteid'); // Fetch the current site ID

    // Load the Work Notes form via AJAX into the right panel
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'load_work_notes_form',
            site_id: siteId
        },
        success: function(response) {
            $('#mainwp_tab_WorkNotes_container').html(response);  // Load response into the correct container
        }
    });
});

});


