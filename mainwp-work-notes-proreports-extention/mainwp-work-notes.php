<?php
namespace MainWP\Dashboard;

/**
 * Handles Work Notes functionality: UI, DB storage, AJAX, and migration.
 */
class MainWP_Work_Notes {

    const CLEANUP_REMOVE_MIGRATION_LOGIC_VERSION = '1.3.2';
    const CLEANUP_DELETE_LEGACY_OPTIONS_VERSION = '1.3.4';

    /**
     * Initialize plugin hooks.
     */
    public static function init() {
        // Add subpage for each child site
        add_filter('mainwp_getsubpages_sites', [__CLASS__, 'add_sub_menu'], 10, 1);

        // Enqueue assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Core AJAX handlers
        add_action('wp_ajax_save_work_note', [__CLASS__, 'ajax_save_work_note_action']);
        add_action('wp_ajax_delete_work_note', [__CLASS__, 'ajax_delete_work_note_action']);
        add_action('wp_ajax_load_work_note', [__CLASS__, 'ajax_load_work_note_action']);
        add_action('wp_ajax_load_work_notes_form', [__CLASS__, 'ajax_load_work_notes_form']);

        // DB table creation
        register_activation_hook(__FILE__, [__CLASS__, 'create_work_notes_table']);


        /**
         * 
         *  Delete from version 1.3.1 onwards This is a future clean up logic
         * 
         */

        /**
         * === Migration Hooks (Temporary, can be removed in a future version) ===
         * Delay check until after pluggable functions are available
         */
        add_action('admin_init', function () {
        // Only run if we're in admin and can manage
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // Handle first-run migration (until 1.2.8)
        if (
            version_compare(RUP_MAINWP_CLIENT_NOTES_VERSION, self::CLEANUP_REMOVE_MIGRATION_LOGIC_VERSION, '<') &&
            !get_option('mainwp_work_notes_migrated')
        ) {
            self::maybe_auto_migrate_legacy_notes();
        }

        // Cleanup legacy options in 1.3.0+
        if (
            version_compare(RUP_MAINWP_CLIENT_NOTES_VERSION, self::CLEANUP_DELETE_LEGACY_OPTIONS_VERSION, '>=') &&
            get_option('mainwp_work_notes_migrated')
        ) {
            self::maybe_delete_legacy_options();
        }
    });       
      



    /**
    * === Migration Logic (Temporary, can be removed in a future version) ===
    */


    private static function maybe_auto_migrate_legacy_notes() {
        self::create_work_notes_table();
        global $wpdb;
        $legacy_notes_exist = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'mainwp_work_notes_%'");
        if ($legacy_notes_exist > 0) {
            self::migrate_work_notes_to_db();
        }
        update_option('mainwp_work_notes_migrated', true);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_migration_js']);
        add_action('admin_bar_menu', [__CLASS__, 'maybe_add_migration_toolbar_link'], 100);
        add_action('wp_ajax_mainwp_migrate_work_notes', [__CLASS__, 'ajax_migrate_work_notes']);
    }


    /**
     * Show migration button in admin bar if migration hasn't run.
     */
    public static function maybe_add_migration_toolbar_link($wp_admin_bar) {
        if (!current_user_can('manage_options') || get_option('mainwp_work_notes_migrated')) return;

        $wp_admin_bar->add_node([
            'id'    => 'mainwp_migrate_notes',
            'title' => 'Migrate Work Notes',
            'href'  => '#',
            'meta'  => ['title' => 'Migrate Work Notes', 'class' => 'mainwp-migrate-notes-toolbar']
        ]);
    }

    /**
     * Enqueue JS to handle migration button click.
     */
    public static function enqueue_migration_js() {
        if (!current_user_can('manage_options') || get_option('mainwp_work_notes_migrated')) return;

        wp_add_inline_script('jquery-core', "
            jQuery(document).ready(function($) {
                $('.mainwp-migrate-notes-toolbar a').on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to migrate existing work notes to the database?')) return;

                    $.post(ajaxurl, {
                        action: 'mainwp_migrate_work_notes',
                        nonce: '" . wp_create_nonce('work_notes_migrate') . "'
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Migration failed: ' + response.data.message);
                        }
                    });
                });
            });
        ");
    }

    /**
     * Handle AJAX-based work note migration.
     */
    public static function ajax_migrate_work_notes() {
        check_ajax_referer('work_notes_migrate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Call static migrate method from work notes class
        if (class_exists('\MainWP\Dashboard\MainWP_Work_Notes')) {
            \MainWP\Dashboard\MainWP_Work_Notes::migrate_work_notes_to_db();
            update_option('mainwp_work_notes_migrated', true);
            wp_send_json_success(['message' => 'Work notes migrated successfully.']);
        }

        wp_send_json_error(['message' => 'Migration class not found.']);
    } 

     //Legacy Clean up
    public static function maybe_delete_legacy_options() {
    global $wpdb;

    $prefix = 'mainwp_work_notes_%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix
        )
    );
}


    

    /**
     * === End of Migration Logic ===
     */






    /**
     * Create the custom database table for storing work notes.
     */
    public static function create_work_notes_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'mainwp_work_notes';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            content LONGTEXT NOT NULL,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (site_id),
            INDEX (work_date)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Migrate old work notes from wp_options into the dedicated database table.
     */
    public static function migrate_work_notes_to_db() {
    global $wpdb;

    // Ensure table exists before inserting data
    self::create_work_notes_table();

    $prefix = 'mainwp_work_notes_';
    $table = $wpdb->prefix . 'mainwp_work_notes';
    $options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'");

    foreach ($options as $option) {
        $site_id = (int) str_replace($prefix, '', $option->option_name);
        $notes = maybe_unserialize($option->option_value);

        if (!is_array($notes)) continue;

        foreach ($notes as $note) {
            if (empty($note['date']) || empty($note['content'])) continue;

            // Prevent duplicate migrations
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE site_id = %d AND work_date = %s AND content = %s",
                $site_id, $note['date'], $note['content']
            ));

            if (!$exists) {
                $wpdb->insert($table, [
                    'site_id'   => $site_id,
                    'work_date' => sanitize_text_field($note['date']),
                    'content'   => wp_kses_post($note['content']),
                    'timestamp' => isset($note['timestamp']) ? date('Y-m-d H:i:s', $note['timestamp']) : current_time('mysql'),
                ]);
            }
        }
    }
}


    /**
     * Add "Work Notes" tab to each child site.
     */
    public static function add_sub_menu($subArray) {
        $subArray[] = [
            'title' => 'Work Notes',
            'slug' => 'WorkNotes',
            'sitetab' => true,
            'menu_hidden' => true,
            'callback' => [__CLASS__, 'render']
        ];
        return $subArray;
    }

    /**
     * Enqueue editor, Flatpickr, and JS assets.
     */
    public static function enqueue_assets() {
        wp_enqueue_editor();
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);

        wp_enqueue_script('mainwp-work-notes-js', plugins_url('mainwp-work-notes.js', __FILE__), ['jquery', 'flatpickr-js'], null, true);
        wp_localize_script('mainwp-work-notes-js', 'mainwpWorkNotes', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('work_notes_nonce'),
            'date_format' => self::get_js_date_format(),
            'today' => current_time('Y-m-d')

        ]);
    }

    /**
     * Convert WP date format to Flatpickr-compatible format.
     */
    private static function get_js_date_format() {
        $php_format = get_option('date_format');
        $map = ['F' => 'F', 'M' => 'M', 'm' => 'm', 'n' => 'n', 'd' => 'd', 'j' => 'j', 'Y' => 'Y', 'y' => 'y'];
        return strtr($php_format, $map);
    }

    /**
     * AJAX: Save or update a work note.
     */
    public static function ajax_save_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Insufficient permissions.']);

        global $wpdb;
        $table = $wpdb->prefix . 'mainwp_work_notes';

        $site_id = isset($_POST['wpid']) ? intval($_POST['wpid']) : 0;
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : -1;
        $date = sanitize_text_field($_POST['work_notes_date']);
        $content = wp_kses_post($_POST['work_notes_content']);

        if (!$site_id || !$date) wp_send_json_error(['message' => 'Missing data.']);

        if ($note_id > 0) {
            $wpdb->update($table, [
                'work_date' => $date,
                'content' => $content
            ], ['id' => $note_id]);
        } else {
            $wpdb->insert($table, [
                'site_id' => $site_id,
                'work_date' => $date,
                'content' => $content,
                'timestamp' => current_time('mysql')
            ]);
            $note_id = $wpdb->insert_id;
        }

        wp_send_json_success(['message' => 'Note saved successfully.', 'note_id' => $note_id]);
    }

    /**
     * AJAX: Delete a work note.
     */
    public static function ajax_delete_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Insufficient permissions.']);

        global $wpdb;
        $id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'mainwp_work_notes', ['id' => $id]);
            wp_send_json_success(['message' => 'Note deleted successfully.']);
        }

        wp_send_json_error(['message' => 'Invalid note ID.']);
    }

    /**
     * AJAX: Load a specific note for editing.
     */
    public static function ajax_load_work_note_action() {
        check_ajax_referer('work_notes_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Insufficient permissions.']);

        global $wpdb;
        $id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mainwp_work_notes WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($note) {
            wp_send_json_success(['date' => $note['work_date'], 'content' => $note['content']]);
        } else {
            wp_send_json_error(['message' => 'Note not found.']);
        }
    }

    /**
     * AJAX: Load the full table of notes (after save/delete).
     */
    public static function ajax_load_work_notes_form() {
        check_ajax_referer('work_notes_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Insufficient permissions.']);

        global $wpdb;
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;

        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mainwp_work_notes WHERE site_id = %d ORDER BY work_date DESC",
            $site_id
        ));

        ob_start();
        echo '<tbody>';
        foreach ($notes as $note) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($note->work_date));
            echo '<tr data-note-id="' . esc_attr($note->id) . '">';
            echo '<td>' . esc_html($formatted_date) . '</td>';
            echo '<td>' . wp_kses_post($note->content) . '</td>';
            echo '<td>';
            echo '<button class="ui button blue edit-note" data-note-id="' . esc_attr($note->id) . '">Edit</button> ';
            echo '<button class="ui button red delete-note" data-note-id="' . esc_attr($note->id) . '">Delete</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';

        wp_send_json_success(['html' => ob_get_clean()]);
    }

    /**
     * Render the full Work Notes UI.
     */
    public static function render() {
        do_action('mainwp_pageheader_sites');

        $current_wpid = MainWP_System_Utility::get_current_wpid();
        if (!MainWP_Utility::ctype_digit($current_wpid)) return;

        global $wpdb;
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mainwp_work_notes WHERE site_id = %d ORDER BY work_date DESC",
            $current_wpid
        ));

        echo '<div id="mainwp_tab_WorkNotes_container" class="ui segment">';
        echo '<div class="mainwp-work-note-message" style="display:none;"></div>';
        echo '<form id="work-notes-form" class="ui form" style="padding: 20px; max-width: 95%; margin: 0 auto;">';
        echo '<input type="hidden" name="wpid" value="' . esc_attr($current_wpid) . '">';
        echo '<input type="hidden" name="note_id" value="-1">';

        $current_date = current_time('Y-m-d');
        echo '<div class="field"><label for="work_notes_date">Work Date:</label>';
        echo '<input type="text" id="work_notes_date" name="work_notes_date" value="' . esc_attr($current_date) . '" required style="width: 100%;"></div>';

        echo '<div class="field"><label for="work_notes_content">Work Details:</label>';
        ob_start();
        wp_editor('', 'work_notes_content', [
            'textarea_name' => 'work_notes_content',
            'textarea_rows' => 10,
            'media_buttons' => true,
            'tinymce' => true,
            'quicktags' => true,
        ]);
        echo ob_get_clean();
        echo '</div>';
        echo '<button type="button" id="save-work-note" class="ui button green">Save Work Note</button>';
        echo '</form>';

        echo '<h3 class="ui dividing header">Existing Work Notes</h3>';
        echo '<table class="ui celled table"><thead><tr><th>Date</th><th>Details</th><th>Actions</th></tr></thead><tbody>';
        foreach ($notes as $note) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($note->work_date));
            echo '<tr data-note-id="' . esc_attr($note->id) . '">';
            echo '<td>' . esc_html($formatted_date) . '</td>';
            echo '<td>' . wp_kses_post($note->content) . '</td>';
            echo '<td>';
            echo '<button class="ui button blue edit-note" data-note-id="' . esc_attr($note->id) . '">Edit</button> ';
            echo '<button class="ui button red delete-note" data-note-id="' . esc_attr($note->id) . '">Delete</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        do_action('mainwp_pagefooter_sites');
    }
}

MainWP_Work_Notes::init();

/**
 * Handles Pro Reports integration for work notes.
 */
class MainWP_Work_Notes_Pro_Reports {

    /**
     * Register custom token filters.
     */
    public static function init() {
        add_filter('mainwp_pro_reports_custom_tokens', [__CLASS__, 'generate_work_notes_tokens'], 10, 4);
        add_filter('mainwp_client_reports_custom_tokens', [__CLASS__, 'client_reports_custom_tokens'], 10, 3);
    }

    /**
     * Register token replacement for client reports.
     */
    public static function client_reports_custom_tokens($tokensValues, $report, $site) {
        $tokensValues['[client.customwork.notes]'] = self::generate_work_notes_tokens($tokensValues, $report, $site, null);
        return $tokensValues['[client.customwork.notes]'];
    }

    /**
     * Generate HTML table for work notes within a date range.
     */
    public static function generate_work_notes_tokens($tokensValues, $report, $site, $templ_email) {
        global $wpdb;

        $site_id = isset($site['id']) ? (int)$site['id'] : 0;
        if (!$site_id) return $tokensValues;

        $from_date = isset($report->date_from) ? date('Y-m-d', $report->date_from) : '';
        $to_date = isset($report->date_to) ? date('Y-m-d', $report->date_to) : '';

        if (!$from_date || !$to_date) return $tokensValues;

        $notes = self::get_work_notes($site_id, $from_date, $to_date);

        if (empty($notes)) {
            $tokensValues['[client.customwork.notes]'] = __('No work notes found within the selected date range.', 'mainwp-client-notes-pro-reports-extention');
        } else {
            $output = '<table style="width: 100%; border-collapse: collapse;" border="1">';
            $output .= '<thead><tr><th>' . __('Date', 'mainwp-client-notes-pro-reports-extention') . '</th><th>' . __('Work Details', 'mainwp-client-notes-pro-reports-extention') . '</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($notes as $note) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($note->work_date));
                $output .= '<tr><td>' . esc_html($formatted_date) . '</td><td>' . wp_kses_post($note->content) . '</td></tr>';
            }
            $output .= '</tbody></table>';
            $tokensValues['[client.customwork.notes]'] = $output;
        }

        return $tokensValues;
    }

    /**
     * Get work notes from DB in a date range.
     */
    public static function get_work_notes($site_id, $date_from, $date_to) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mainwp_work_notes WHERE site_id = %d AND work_date BETWEEN %s AND %s ORDER BY work_date ASC",
            $site_id, $date_from, $date_to
        ));
    }
}

MainWP_Work_Notes_Pro_Reports::init();

