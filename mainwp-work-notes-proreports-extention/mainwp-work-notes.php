<?php
namespace MainWP\Dashboard;
class MainWP_Work_Notes {

    public static function init() {
        // Hook to add a submenu item under each child site for Work Notes
        //add_filter('mainwp_getsubpages_sites', array(__CLASS__, 'add_sub_menu'));
        add_filter( 'mainwp_getsubpages_sites', array( __CLASS__, 'add_sub_menu' ), 10, 1 );

        // Hook to enqueue styles/scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        // Handle AJAX actions for saving, deleting, and loading notes
        add_action('wp_ajax_save_work_note', array(__CLASS__, 'ajax_save_work_note_action'));
        add_action('wp_ajax_delete_work_note', array(__CLASS__, 'ajax_delete_work_note_action'));
        add_action('wp_ajax_load_work_note', array(__CLASS__, 'ajax_load_work_note_action'));
    }

    // Enqueue scripts and styles
    public static function enqueue_assets() {
        //wp_enqueue_style('mainwp-work-notes', plugins_url('mainwp-work-notes.css', __FILE__));
        wp_enqueue_script('mainwp-work-notes-js', plugins_url('mainwp-work-notes.js', __FILE__), array('jquery'), null, true);

        // Localize script for AJAX calls
        wp_localize_script('mainwp-work-notes-js', 'mainwpWorkNotes', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('work_notes_nonce')
        ));
    }

    // Adds the Work Notes tab in the child site menu
    public static function add_sub_menu($subArray) {
        $subArray[] = array(
            'title' => 'Work Notes',
            'slug'  => 'WorkNotes',
            'sitetab'  => true,  // This allows it to render in the right panel
            'menu_hidden' => true,            
            'callback' => array(__CLASS__, 'render'),
        );
        return $subArray;
    }

    // Handle saving or updating the note
    public static function ajax_save_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $current_wpid = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

        if (!$current_wpid) {
            wp_send_json_error(array('message' => 'Invalid site ID.'));
        }

        $work_date = sanitize_text_field($_POST['work_notes_date']);
        $work_content = sanitize_textarea_field($_POST['work_notes_content']);

        $notes_key = 'mainwp_work_notes_' . $current_wpid;
        $notes = get_option($notes_key, array());

        if ($note_id > 0 && isset($notes[$note_id])) {
            // Update the existing note
            $notes[$note_id]['date'] = $work_date;
            $notes[$note_id]['content'] = $work_content;
        } else {
            // Add a new note
            $notes[] = array('date' => $work_date, 'content' => $work_content, 'timestamp' => current_time('timestamp'));
        }

        update_option($notes_key, $notes);
        wp_send_json_success(array('message' => 'Note saved successfully.'));
    }

    // Handle loading the note for editing
    public static function ajax_load_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $current_wpid = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

        if (!$current_wpid || $note_id === false) {
            wp_send_json_error(array('message' => 'Invalid site or note ID.'));
        }

        $notes_key = 'mainwp_work_notes_' . $current_wpid;
        $notes = get_option($notes_key, array());

        if (isset($notes[$note_id])) {
            $note = $notes[$note_id];
            wp_send_json_success(array('date' => $note['date'], 'content' => $note['content']));
        } else {
            wp_send_json_error(array('message' => 'Note not found.'));
        }
    }

    // Handle deleting the note
    public static function ajax_delete_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $current_wpid = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : false;

        if (!$current_wpid || $note_id === false) {
            wp_send_json_error(array('message' => 'Invalid site ID or note ID.'));
        }

        $notes_key = 'mainwp_work_notes_' . $current_wpid;
        $notes = get_option($notes_key, array());

        if (isset($notes[$note_id])) {
            unset($notes[$note_id]);
            update_option($notes_key, $notes);
            wp_send_json_success(array('message' => 'Note deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Note not found.'));
        }
    }

    // Renders the actual content of the Work Notes page
    public static function render() {
        $current_wpid = MainWP_System_Utility::get_current_wpid();
        if (!MainWP_Utility::ctype_digit($current_wpid)) {
            return;
        }

        // Fetch existing work notes for the child site
        $notes_key = 'mainwp_work_notes_' . $current_wpid;
        $notes = get_option($notes_key, array());

        // Display the entire Work Notes section inside a container div
echo '<div id="mainwp_tab_WorkNotes_container">';

    // Display the form to add/edit a note
    echo '<form id="work-notes-form" class="ui form" style="padding: 20px; max-width: 95%; margin: 0 auto;">';
    echo '<input type="hidden" name="wpid" value="' . esc_attr($current_wpid) . '">';
    echo '<input type="hidden" name="note_id" value="0">'; // Hidden input for note ID
    echo '<div class="field">
            <label for="work_notes_date">Work Date:</label>
            <input type="date" name="work_notes_date" required style="width: 100%;">
          </div>';
    echo '<div class="field">
            <label for="work_notes_content">Work Details:</label>
            <textarea name="work_notes_content" rows="5" required></textarea>';
    echo '</div>';
    echo '<button type="button" id="save-work-note" class="ui button green">Save Work Note</button>';
    echo '</form>';

    // Display existing work notes
    echo '<h3 class="ui dividing header">Existing Work Notes</h3>';
    echo '<table class="ui celled table">';
    echo '<thead><tr><th>Date</th><th>Details</th><th>Actions</th></tr></thead><tbody>';
    foreach ($notes as $index => $note) {
        echo '<tr>';
        echo '<td>' . esc_html($note['date']) . '</td>';
        echo '<td>' . esc_html($note['content']) . '</td>';
        echo '<td>
                <button class="ui button blue edit-note" data-note-id="' . $index . '">Edit</button>
                <button class="ui button red delete-note" data-note-id="' . $index . '">Delete</button>
              </td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

echo '</div>';  // Close the container div

    }
}

// Initialize the Work Notes functionality
MainWP_Work_Notes::init();
class MainWP_Work_Notes_Pro_Reports {

    public static function init() {
        // Hook into Pro Reports to add custom tokens
        add_filter('mainwp_pro_reports_custom_tokens', array(__CLASS__, 'generate_work_notes_tokens'), 10, 4);
    }

    // Generate the custom token for work notes
    public static function generate_work_notes_tokens($tokensValues, $report, $site, $templ_email) {
        // Ensure the site ID is set
        $site_id = isset($site['id']) ? $site['id'] : 0;
        if (!$site_id) {
            return $tokensValues;  // Return early if the site ID is missing
        }

        // Retrieve the date range from the report object and convert to readable format
        $from_date = isset($report->date_from) ? date('Y-m-d', $report->date_from) : '';
        $to_date = isset($report->date_to) ? date('Y-m-d', $report->date_to) : '';

        // Check if valid date ranges are available
        if (!$from_date || !$to_date) {
            return $tokensValues;  // Return early if date ranges are invalid
        }

        // Log date ranges (for debugging)
        error_log('Work Notes - From Date: ' . $from_date);
        error_log('Work Notes - To Date: ' . $to_date);

        // Get the work notes for the client site within the date range
        $work_notes = self::get_work_notes($site_id, $from_date, $to_date);

        // If no work notes are found, return a default message
        if (empty($work_notes)) {
            $tokensValues['[client.customwork.notes]'] = 'No work notes found within the selected date range.';
        } else {
            // Build a table of work notes
            $output = '<table style="width: 100%; border-collapse: collapse;" border="1">';
            $output .= '<thead><tr><th>Date</th><th>Work Details</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($work_notes as $note) {
                $output .= '<tr>';
                $output .= '<td>' . esc_html($note['date']) . '</td>';
                $output .= '<td>' . esc_html($note['content']) . '</td>';
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';

            // Replace the token with the work notes table
            $tokensValues['[client.customwork.notes]'] = $output;
        }

        return $tokensValues;
    }

    // Retrieve work notes between the date range for a specific site
    public static function get_work_notes($site_id, $date_from, $date_to) {
        $notes_key = 'mainwp_work_notes_' . $site_id;
        $work_notes = get_option($notes_key, array());

        // Filter notes by the date range
        $filtered_notes = array_filter($work_notes, function($note) use ($date_from, $date_to) {
            $note_date = strtotime($note['date']);
            $date_from_ts = strtotime($date_from);
            $date_to_ts = strtotime($date_to);

            return ($note_date >= $date_from_ts && $note_date <= $date_to_ts);
        });

        // Sort the notes by date
        usort($filtered_notes, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $filtered_notes;
    }
}

// Initialize the Pro Reports integration for Work Notes
MainWP_Work_Notes_Pro_Reports::init();


