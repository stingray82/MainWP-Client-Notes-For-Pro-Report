<?php
namespace MainWP\Dashboard;

class MainWP_Work_Notes {

    // Initialize all hooks
    public static function init() {
        // Add a submenu tab for Work Notes under each child site
        add_filter('mainwp_getsubpages_sites', array(__CLASS__, 'add_sub_menu'), 10, 1);

        // Enqueue required scripts and editor
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_save_work_note', array(__CLASS__, 'ajax_save_work_note_action'));
        add_action('wp_ajax_delete_work_note', array(__CLASS__, 'ajax_delete_work_note_action'));
        add_action('wp_ajax_load_work_note', array(__CLASS__, 'ajax_load_work_note_action'));
        add_action('wp_ajax_load_work_notes_form', array(__CLASS__, 'ajax_load_work_notes_form'));

    }

    // Load WordPress editor and custom JavaScript
    public static function enqueue_assets() {
    wp_enqueue_editor(); // TinyMCE

    // Flatpickr CSS & JS
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), null, true);

    wp_enqueue_script('mainwp-work-notes-js', plugins_url('mainwp-work-notes.js', __FILE__), array('jquery', 'flatpickr-js'), null, true);

    wp_localize_script('mainwp-work-notes-js', 'mainwpWorkNotes', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('work_notes_nonce'),
        'date_format' => self::get_js_date_format(), // We'll define this
        ));
    }


    private static function get_js_date_format() {
        $php_format = get_option('date_format'); // e.g., 'F j, Y'

        // Map PHP date format to Flatpickr-compatible format
        $replacements = array(
            'F' => 'F',   // Full month name
            'M' => 'M',   // Short month name
            'm' => 'm',   // 2-digit month
            'n' => 'n',   // 1 or 2-digit month
            'd' => 'd',   // 2-digit day
            'j' => 'j',   // 1 or 2-digit day
            'Y' => 'Y',   // 4-digit year
            'y' => 'y',   // 2-digit year
        );

        return strtr($php_format, $replacements);
    }


    // Add Work Notes tab to each child site
    public static function add_sub_menu($subArray) {
        $subArray[] = array(
            'title' => 'Work Notes',
            'slug'  => 'WorkNotes',
            'sitetab'  => true,
            'menu_hidden' => true,
            'callback' => array(__CLASS__, 'render'),
        );
        return $subArray;
    }

    // Handle saving or updating a work note
    public static function ajax_save_work_note_action() {
    check_ajax_referer('work_notes_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $_POST = stripslashes_deep($_POST);
    $current_wpid = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : -1;

    if (!$current_wpid) {
        wp_send_json_error(['message' => 'Invalid site ID.']);
    }

    $work_date = sanitize_text_field($_POST['work_notes_date']);
    $work_content = wp_kses_post($_POST['work_notes_content']);

    $notes_key = 'mainwp_work_notes_' . $current_wpid;
    $notes = get_option($notes_key, []);

    if ($note_id >= 0 && isset($notes[$note_id])) {
        $notes[$note_id]['date'] = $work_date;
        $notes[$note_id]['content'] = $work_content;
    } else {
        $notes[] = [
            'date' => $work_date,
            'content' => $work_content,
            'timestamp' => current_time('timestamp'),
        ];
        $note_id = array_key_last($notes);
    }

    update_option($notes_key, $notes);

    wp_send_json_success([
        'message' => 'Note saved successfully.',
        'note_id' => $note_id
    ]);
}



        public static function render_notes_table() {
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        if (!$site_id) {
            wp_send_json_error(['message' => 'Invalid site ID']);
        }

        $notes_key = 'mainwp_work_notes_' . $site_id;
        $notes = get_option($notes_key, array());

        ob_start(); ?>
        <table class="ui celled table">
            <thead><tr><th>Date</th><th>Details</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($notes as $index => $note) : ?>
                <tr data-note-id="<?php echo esc_attr($index); ?>">
                    <?php $formatted_date = date_i18n(get_option('date_format'), strtotime($note['date'])); ?>
                    <td><?php echo esc_html($formatted_date); ?></td>
                    <td><?php echo wp_kses_post($note['content']); ?></td>
                    <td>
                        <button class="ui button blue edit-note" data-note-id="<?php echo esc_attr($index); ?>">Edit</button>
                        <button class="ui button red delete-note" data-note-id="<?php echo esc_attr($index); ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        echo ob_get_clean();
        wp_die();
    }


        public static function ajax_load_work_notes_form() {
    error_log('AJAX: load_work_notes_form hit');

    check_ajax_referer('work_notes_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
    if (!$site_id) {
        wp_send_json_error(['message' => 'Invalid site ID']);
    }

    $notes_key = 'mainwp_work_notes_' . $site_id;
    $notes = get_option($notes_key, array());

    ob_start();
    echo '<tbody>'; // FIX: wrap the rows in <tbody>
    foreach ($notes as $index => $note) {
        echo '<tr data-note-id="' . esc_attr($index) . '">';
        $formatted_date = date_i18n(get_option('date_format'), strtotime($note['date']));
        echo '<td>' . esc_html($formatted_date) . '</td>';

        echo '<td>' . wp_kses_post($note['content']) . '</td>';
        echo '<td>
                <button class="ui button blue edit-note" data-note-id="' . esc_attr($index) . '">Edit</button>
                <button class="ui button red delete-note" data-note-id="' . esc_attr($index) . '">Delete</button>
              </td>';
        echo '</tr>';
    }
    echo '</tbody>';
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}





        




    // Handle retrieving a note for editing
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

    // Handle deleting a work note
    public static function ajax_delete_work_note_action() {
    check_ajax_referer('work_notes_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
    }

    $current_wpid = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : -1;

    if (!$current_wpid || $note_id < 0) {
        wp_send_json_error(array('message' => 'Invalid site ID or note ID.'));
    }

    $notes_key = 'mainwp_work_notes_' . $current_wpid;
    $notes = get_option($notes_key, array());

    if (isset($notes[$note_id])) {
        unset($notes[$note_id]);
        $notes = array_values($notes); // Re-Index
        update_option($notes_key, $notes);
        wp_send_json_success(array('message' => 'Note deleted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Note not found.'));
    }
}


    // Render the UI for Work Notes tab
    public static function render() {
        do_action('mainwp_pageheader_sites');

        $current_wpid = MainWP_System_Utility::get_current_wpid();
        if (!MainWP_Utility::ctype_digit($current_wpid)) return;

        $notes_key = 'mainwp_work_notes_' . $current_wpid;
        $notes = get_option($notes_key, array());

        echo '<div id="mainwp_tab_WorkNotes_container" class="ui segment">';

        // Work Notes form
		echo '<div class="mainwp-work-note-message" style="display:none;"></div>';
        echo '<form id="work-notes-form" class="ui form" style="padding: 20px; max-width: 95%; margin: 0 auto;">';
        echo '<input type="hidden" name="wpid" value="' . esc_attr($current_wpid) . '">';
        echo '<input type="hidden" name="note_id" value="-1">';
        //echo '<input type="text" id="work_notes_date" name="work_notes_date" value="' . esc_attr($current_date) . '" required style="width: 100%;">';

        // Testing date
        $current_date = current_time('Y-m-d');
        echo '<div class="field"><label for="work_notes_date">Work Date:</label>';
        echo '<input type="text" id="work_notes_date" name="work_notes_date" value="' . esc_attr($current_date) . '" required style="width: 100%;"></div>';

        echo '<div class="field"><label for="work_notes_content">Work Details:</label>';
        ob_start();
        wp_editor('', 'work_notes_content', array(
            'textarea_name' => 'work_notes_content',
            'textarea_rows' => 10,
            'media_buttons' => true,
            'tinymce'       => true,
            'quicktags'     => true,
        ));
        echo ob_get_clean();
        echo '</div>';
        echo '<button type="button" id="save-work-note" class="ui button green">Save Work Note</button>';
        echo '</form>';

        // Existing Notes Table
        echo '<h3 class="ui dividing header">Existing Work Notes</h3>';
        echo '<table class="ui celled table"><thead><tr><th>Date</th><th>Details</th><th>Actions</th></tr></thead><tbody>';
        foreach ($notes as $index => $note) {
            echo '<tr>';
            $formatted_date = date_i18n(get_option('date_format'), strtotime($note['date']));
            echo '<td>' . esc_html($formatted_date) . '</td>';

            echo '<td>' . wp_kses_post($note['content']) . '</td>';
            echo '<td>
                    <button class="ui button blue edit-note" data-note-id="' . esc_attr($index) . '">Edit</button>
                    <button class="ui button red delete-note" data-note-id="' . esc_attr($index) . '">Delete</button>
                  </td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        do_action('mainwp_pagefooter_sites');
    }
}

// Initialize class
MainWP_Work_Notes::init();


class MainWP_Work_Notes_Pro_Reports {

    public static function init() {
        add_filter('mainwp_pro_reports_custom_tokens', array(__CLASS__, 'generate_work_notes_tokens'), 10, 4);
        add_filter('mainwp_client_reports_custom_tokens', array(__CLASS__, 'client_reports_custom_tokens'), 10, 3);
    }

    public static function client_reports_custom_tokens($tokensValues, $report, $site) {
        $tokensValues['[client.customwork.notes]'] = self::generate_work_notes_tokens($tokensValues, $report, $site, $templ_email);
        return $tokensValues['[client.customwork.notes]'];
    }

    public static function generate_work_notes_tokens($tokensValues, $report, $site, $templ_email) {
        $site_id = isset($site['id']) ? $site['id'] : 0;
        if (!$site_id) {
            return $tokensValues;
        }

        $from_date = isset($report->date_from) ? date('Y-m-d', $report->date_from) : '';
        $to_date = isset($report->date_to) ? date('Y-m-d', $report->date_to) : '';

        if (!$from_date || !$to_date) {
            return $tokensValues;
        }

        error_log('Work Notes - From Date: ' . $from_date);
        error_log('Work Notes - To Date: ' . $to_date);

        $work_notes = self::get_work_notes($site_id, $from_date, $to_date);

        if (empty($work_notes)) {
            $tokensValues['[client.customwork.notes]'] = __('No work notes found within the selected date range.','mainwp-client-notes-pro-reports-extention');
        } else {
            $output = '<table style="width: 100%; border-collapse: collapse;" border="1">';
            $output .= '<thead><tr><th>'.__('Date', 'mainwp-client-notes-pro-reports-extention').'</th><th>'.__('Work Details', 'mainwp-client-notes-pro-reports-extention').'</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($work_notes as $note) {
                $output .= '<tr>';
                $formatted_date = date_i18n(get_option('date_format'), strtotime($note['date']));
                $output .= '<td>' . esc_html($formatted_date) . '</td>';
                $output .= '<td>' . wp_kses_post($note['content']) . '</td>';  // updated to show formatted HTML
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';

            $tokensValues['[client.customwork.notes]'] = $output;
        }

        return $tokensValues;
    }

    public static function get_work_notes($site_id, $date_from, $date_to) {
        $notes_key = 'mainwp_work_notes_' . $site_id;
        $work_notes = get_option($notes_key, array());

        $filtered_notes = array_filter($work_notes, function($note) use ($date_from, $date_to) {
            $note_date = strtotime($note['date']);
            $date_from_ts = strtotime($date_from);
            $date_to_ts = strtotime($date_to);

            return ($note_date >= $date_from_ts && $note_date <= $date_to_ts);
        });

        usort($filtered_notes, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $filtered_notes;
    }
}

MainWP_Work_Notes_Pro_Reports::init();
