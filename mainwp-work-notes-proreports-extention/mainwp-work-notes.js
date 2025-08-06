jQuery(document).ready(function ($) {
    function fixDatePicker() {
        const dateInput = $('input[name="work_notes_date"]');
        dateInput.focus().blur();
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
                wpid,
                note_id: noteId
            }, function (response) {
                if (response.success) {
                    $('input[name="note_id"]').val(noteId);
                    
                    // ✅ Set button to "Update"
                    $('#save-work-note').text('Update Work Note');

                    // Set date
                    if (window.workNotesFlatpickrInstance) {
                        window.workNotesFlatpickrInstance.setDate(response.data.date, true);
                    } else {
                        $('input[name="work_notes_date"]').val(response.data.date);
                    }

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
                wpid,
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
            if (response.success && response.data?.html) {
                $('.ui.celled.table tbody').replaceWith(response.data.html);
                bindWorkNotesEvents(); // Rebind events for new DOM
            } else {
                alert(response.data?.message || 'Failed to reload notes.');
            }
        });
    }

    function resetForm() {
        $('input[name="note_id"]').val('-1');

        // ✅ Set button label back to "Save"
        $('#save-work-note').text('Save Work Note');

        // Reset date to today
        if (mainwpWorkNotes.today && window.workNotesFlatpickrInstance) {
            window.workNotesFlatpickrInstance.setDate(mainwpWorkNotes.today, true);
        } else {
            $('input[name="work_notes_date"]').val(mainwpWorkNotes.today || '');
        }

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
            wpid,
            note_id: noteId,
            work_notes_date: $('input[name="work_notes_date"]').val(),
            work_notes_content: content
        }, function (response) {
            if (response.success) {
                alert(response.data.message);
                reloadNotesTable(wpid);
                resetForm(); // ✅ this also resets the button label
            } else {
                alert(response.data.message);
            }
        });
    });

    if (typeof flatpickr !== 'undefined') {
        window.workNotesFlatpickrInstance = flatpickr("#work_notes_date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: mainwpWorkNotes.date_format || "d/m/Y",
            defaultDate: $("#work_notes_date").val(),
            allowInput: true
        });
    }

    fixDatePicker();
    bindWorkNotesEvents();
});
