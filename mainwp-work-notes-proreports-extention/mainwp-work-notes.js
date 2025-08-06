jQuery(document).ready(function ($) {
    function fixDatePicker() {
        var dateInput = $('input[name="work_notes_date"]');
        dateInput.focus();
        dateInput.blur();
    }

    function renderNoteRow(index, note) {
        return `
            <tr data-note-id="${index}">
                <td>${note.date}</td>
                <td>${note.content}</td>
                <td>
                    <button class="ui button blue edit-note" data-note-id="${index}">Edit</button>
                    <button class="ui button red delete-note" data-note-id="${index}">Delete</button>
                </td>
            </tr>`;
    }

    function bindWorkNotesEvents() {
        $(document).off('click', '.edit-note').on('click', '.edit-note', function () {
            const noteId = $(this).data('note-id');
            const wpid = $('input[name="wpid"]').val();

            $.post(mainwpWorkNotes.ajax_url, {
                action: 'load_work_note',
                nonce: mainwpWorkNotes.nonce,
                wpid: wpid,
                note_id: noteId
            }, function (response) {
                if (response.success) {
                    $('input[name="note_id"]').val(noteId);
                    $('input[name="work_notes_date"]').val(response.data.date);

                    const editor = tinyMCE.get('work_notes_content');
                    if (editor) {
                        editor.setContent(response.data.content);
                    } else {
                        $('textarea[name="work_notes_content"]').val(response.data.content);
                    }

                    fixDatePicker();
                } else {
                    alert(response.data.message);
                }
            });
        });

        $(document).off('click', '.delete-note').on('click', '.delete-note', function () {
            if (!confirm('Are you sure you want to delete this note?')) return;

            const noteId = $(this).data('note-id');
            const wpid = $('input[name="wpid"]').val();

            $.post(mainwpWorkNotes.ajax_url, {
                action: 'delete_work_note',
                nonce: mainwpWorkNotes.nonce,
                wpid: wpid,
                note_id: noteId
            }, function (response) {
                if (response.success) {
                    alert(response.data.message);
                    reloadNotesTable(wpid);
                    resetForm();
                } else {
                    alert(response.data.message);
                }
            });
        });
    }

    function reloadNotesTable(wpid) {
        $.post(mainwpWorkNotes.ajax_url, {
            action: 'load_work_notes_form',
            nonce: mainwpWorkNotes.nonce,
            site_id: wpid
        }, function (response) {
            console.log("Reload AJAX response:", response);
            if (response.success && response.data && response.data.html) {
                $('.ui.celled.table tbody').replaceWith(response.data.html);
                bindWorkNotesEvents(); // Re-bind handlers
            } else {
                alert(response.data?.message || 'Failed to reload notes.');
            }
        });
    }

    jQuery(document).ready(function ($) {
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#work_notes_date", {
            dateFormat: "Y-m-d", // This is the value that will be submitted
            altInput: true,
            altFormat: mainwpWorkNotes.date_format || "d/m/Y", // WP display format
            defaultDate: $("#work_notes_date").val(),
            allowInput: true // optional: lets user type in date manually
        });
    }
});




    function resetForm() {
        $('input[name="note_id"]').val('-1'); // ensure new notes work
        $('input[name="work_notes_date"]').val('');
        const editor = tinyMCE.get('work_notes_content');
        if (editor) {
            editor.setContent('');
        }
        $('textarea[name="work_notes_content"]').val('');
        fixDatePicker();
    }

    $('#save-work-note').on('click', function () {
        const noteId = $('input[name="note_id"]').val();
        const wpid = $('input[name="wpid"]').val();
        const content = tinyMCE.get('work_notes_content')?.getContent() || $('textarea[name="work_notes_content"]').val();

        $.post(mainwpWorkNotes.ajax_url, {
            action: 'save_work_note',
            nonce: mainwpWorkNotes.nonce,
            wpid: wpid,
            note_id: noteId,
            work_notes_date: $('input[name="work_notes_date"]').val(),
            work_notes_content: content
        }, function (response) {
            if (response.success) {
                alert(response.data.message);
                reloadNotesTable(wpid);
                resetForm();
            } else {
                alert(response.data.message);
            }
        });
    });

    fixDatePicker();
    bindWorkNotesEvents();
});
