jQuery(document).ready(function ($) {
  // ---- Cached selectors
  const $noteId = $('input[name="note_id"]');
  const $siteId = $('input[name="wpid"]');
  const $date = $('#work_notes_date');
  const $contentTA = $('textarea[name="work_notes_content"]');
  const $saveBtn = $('#save-work-note');

  // ---- Helpers
  function getEditorContent() {
    const ed = window.tinyMCE ? tinyMCE.get('work_notes_content') : null;
    return ed ? ed.getContent() : $contentTA.val();
  }

  function setEditorContent(html) {
    const ed = window.tinyMCE ? tinyMCE.get('work_notes_content') : null;
    if (ed) ed.setContent(html || '');
    $contentTA.val(html || '');
  }

  function setDateInput(value, triggerChange = true) {
    // Prefer Flatpickr API when available
    if (window.workNotesFlatpickrInstance) {
      window.workNotesFlatpickrInstance.setDate(value || null, true); // true => triggerChange
    } else {
      $date.val(value || '');
      if (triggerChange) $date.trigger('change');
    }
  }

  // Replaces deprecated .focus()/.blur() event shorthands that jQuery Migrate warns about
  function fixDatePicker() {
    // If an altInput exists, ensure it's in sync, but don't use deprecated shorthands
    const val = $date.val();
    setDateInput(val, true);
  }

  function resetForm() {
    $noteId.val('-1');
    $saveBtn.text('Save Work Note');

    // Reset date to today where possible
    const today = (window.mainwpWorkNotes && mainwpWorkNotes.today) ? mainwpWorkNotes.today : '';
    setDateInput(today, true);

    // Clear editor
    setEditorContent('');

    fixDatePicker();
  }

  function reloadNotesTable(wpid) {
    $.post(window.mainwpWorkNotes.ajax_url, {
      action: 'load_work_notes_form',
      nonce: window.mainwpWorkNotes.nonce,
      site_id: wpid
    }).done(function (response) {
      if (response && response.success && response.data && response.data.html) {
        $('.ui.celled.table tbody').replaceWith(response.data.html);
        // Using delegated handlers below, so no need to rebind, but call just in case
        bindWorkNotesEvents();
      } else {
        alert((response && response.data && response.data.message) || 'Failed to reload notes.');
      }
    }).fail(function () {
      alert('Failed to reload notes.');
    });
  }

  function bindWorkNotesEvents() {
    // EDIT note
    $(document).off('click', '.edit-note').on('click', '.edit-note', function () {
      const noteIdVal = $(this).data('note-id');
      const wpid = $siteId.val();

      $.post(window.mainwpWorkNotes.ajax_url, {
        action: 'load_work_note',
        nonce: window.mainwpWorkNotes.nonce,
        wpid: wpid,
        note_id: noteIdVal
      }).done(function (response) {
        if (response && response.success) {
          $noteId.val(noteIdVal);
          $saveBtn.text('Update Work Note');

          // Date
          setDateInput(response.data.date, true);

          // Content
          setEditorContent(response.data.content);

          fixDatePicker();
        } else {
          alert(response && response.data ? response.data.message : 'Failed to load the note.');
        }
      }).fail(function () {
        alert('Failed to load the note.');
      });
    });

    // DELETE note
    $(document).off('click', '.delete-note').on('click', '.delete-note', function () {
      if (!window.confirm('Are you sure you want to delete this note?')) return;

      const noteIdVal = $(this).data('note-id');
      const wpid = $siteId.val();

      $.post(window.mainwpWorkNotes.ajax_url, {
        action: 'delete_work_note',
        nonce: window.mainwpWorkNotes.nonce,
        wpid: wpid,
        note_id: noteIdVal
      }).done(function (response) {
        if (response && response.success) {
          alert(response.data.message || 'Note deleted.');
          reloadNotesTable(wpid);
          resetForm();
        } else {
          alert(response && response.data ? response.data.message : 'Failed to delete the note.');
        }
      }).fail(function () {
        alert('Failed to delete the note.');
      });
    });
  }

  // SAVE / UPDATE
  $saveBtn.off('click').on('click', function () {
    const noteIdVal = $noteId.val();
    const wpid = $siteId.val();
    const dateVal = $date.val(); // Flatpickr keeps the original input value in sync
    const content = getEditorContent();

    $.post(window.mainwpWorkNotes.ajax_url, {
      action: 'save_work_note',
      nonce: window.mainwpWorkNotes.nonce,
      note_id: noteIdVal,
      wpid: wpid,
      work_notes_date: dateVal,
      work_notes_content: content
    }).done(function (response) {
      if (response && response.success) {
        alert(response.data.message || 'Note saved.');
        reloadNotesTable(wpid);
        resetForm();
      } else {
        alert(response && response.data ? response.data.message : 'Failed to save the note.');
      }
    }).fail(function () {
      alert('Failed to save the note.');
    });
  });

  // ---- Init Flatpickr if present
  if (typeof window.flatpickr !== 'undefined') {
    window.workNotesFlatpickrInstance = flatpickr('#work_notes_date', {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: (window.mainwpWorkNotes && mainwpWorkNotes.date_format) || 'd/m/Y',
      defaultDate: $date.val() || (window.mainwpWorkNotes && mainwpWorkNotes.today) || null,
      allowInput: true
    });
  }

  // ---- Kick things off
  fixDatePicker();
  bindWorkNotesEvents();
});