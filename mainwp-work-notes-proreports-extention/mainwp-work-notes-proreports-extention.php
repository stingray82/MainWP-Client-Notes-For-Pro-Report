<?php
/**
 * Plugin Name:       MainWP Client Notes Pro Report Extension
 * Description:       This adds client notes to your pro report
 * Tested up to:      6.8.2
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Version:           1.3.0-rc.1
 * Author:            reallyusefulplugins.com
 * Author URI:        https://reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mainwp-client-notes-pro-reports-extention
 * Website:           https://reallyusefulplugins.com
 */

require_once __DIR__ . '/mainwp-work-notes.php';
class MainWP_Client_Notes_Proreport_Extension {



	public function __construct() {
		//add_filter( 'mainwp_getsubpages_sites', array( &$this, 'managesites_subpage' ), 10, 1 );
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
				// load textdomain
				add_action( 'plugins_loaded', array( &$this,'load_textdomain'));
	}

	// Load plugin textdomain
	// ---------------
	public function load_textdomain() {
		load_plugin_textdomain( 'mainwp-client-notes-pro-reports-extention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function admin_init() {
    	register_setting('mainwp_client_notes_proreport_options_group', 'mainwp_client_notes_proreport_allow_prerelease');
	}


	public function managesites_subpage( $subPage ) {
    $subPage[] = array(
        'title'       => 'Work Notes',
        'slug'        => 'ClientNotesProReport',
        'sitetab'     => true, // Correctly tells MainWP to load it in the sitetab
        'menu_hidden' => true, // Hides it from the regular main menu
        'callback'    => array( static::class, 'renderPage' ),
    );
    return $subPage;
}

	/*
	* Create your extension page
	*/
	public static function renderPage() {
    ?>
    <div class="ui segment">
        <div class="inside">
            <form method="post" action="options.php">
                <?php
                settings_fields('mainwp_client_notes_proreport_options_group');
                ?>
                <h3><?php _e('Update Preferences', 'mainwp-client-notes-pro-reports-extention'); ?></h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('Enable Pre-Releases', 'mainwp-client-notes-pro-reports-extention'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mainwp_client_notes_proreport_allow_prerelease" value="yes" <?php checked(get_option('mainwp_client_notes_proreport_allow_prerelease'), 'yes'); ?> />
                                <?php _e('Allow updates from pre-release versions', 'mainwp-client-notes-pro-reports-extention'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    </div>
    <?php
}


    /**
	 * Method settings().
	 */
	public function render_site_page_settings() {
		do_action( 'mainwp_pageheader_sites', 'ClientNotesProReport' );
		do_action( 'mainwp_pagefooter_sites', 'ClientNotesProReport' );
	}


}




/*
* Activator Class is used for extension activation and deactivation
*/

class MainWP_Client_Pro_Report_Notes_Activator {

	protected $mainwpClientNotesProReportActivated = false;
	protected $childEnabled                  = false;
	protected $childKey                      = false;
	protected $childFile;
	protected $plugin_handle = 'mainwp-work-notes-proreports-extention';



	private function mainwp_tables_ready(): bool {
	    global $wpdb;
	    $need = [
	        $wpdb->prefix . 'mainwp_wp',
	        $wpdb->prefix . 'mainwp_api_keys',
	    ];
	    foreach ($need as $table) {
	        $exists = $wpdb->get_var( $wpdb->prepare(
	            "SHOW TABLES LIKE %s", $table
	        ) );
	        if ($exists !== $table) {
	            return false;
	        }
	    }
	    return true;
	}



	public function __construct() {
		$this->childFile = __FILE__;
		add_filter( 'mainwp_getextensions', array( &$this, 'get_this_extension' ) );

		// This filter will return true if the main plugin is activated
		$this->mainwpClientNotesProReportActivated = apply_filters( 'mainwp_activated_check', false );

		if ( $this->mainwpClientNotesProReportActivated !== false ) {
			$this->activate_this_plugin();
		} else {
			// Because sometimes our main plugin is activated after the extension plugin is activated we also have a second step,
			// listening to the 'mainwp_activated' action. This action is triggered by MainWP after initialisation.
			add_action( 'mainwp_activated', array( &$this, 'activate_this_plugin' ) );
		}
		add_action( 'admin_notices', array( &$this, 'mainwp_error_notice' ) );
	}

	function get_this_extension( $pArray ) {
		$pArray[] = array(
			'plugin'   => __FILE__,
			'api'      => $this->plugin_handle,
			'mainwp'   => true,
			'callback' => array( &$this, 'settings' ),
			'sitetab'  => true,  // This makes sure it renders in the right-hand side
		);
		return $pArray;
	}

	function settings() {
		// The "mainwp_pageheader_extensions" action is used to render the tabs on the Extensions screen.
		// It's used together with mainwp_pagefooter_extensions and mainwp_getextensions
		do_action( 'mainwp_pageheader_extensions', __FILE__ );
		if ( $this->childEnabled ) {
			MainWP_Client_Notes_Proreport_Extension::renderPage();
		} else {
			?>
			<div class="mainwp_info-box-yellow"><?php _e( 'The Extension has to be enabled to change the settings.' ); ?></div>
															<?php
		}
		do_action( 'mainwp_pagefooter_extensions', __FILE__ );
	}

	// The function "activate_this_plugin" is called when the main is initialized.
	function activate_this_plugin() {
	    // Is MainWP active?
	    $this->mainwpClientNotesProReportActivated = apply_filters(
	        'mainwp_activated_check', $this->mainwpClientNotesProReportActivated
	    );

	    // If MainWP isn’t active, wait for mainwp_activated action as you already do.
	    if ( $this->mainwpClientNotesProReportActivated === false ) {
	        add_action( 'mainwp_activated', [ $this, 'activate_this_plugin' ] );
	        return;
	    }

	    // Is the extension enabled?
	    $this->childEnabled = apply_filters( 'mainwp_extension_enabled_check', __FILE__ );
	    if ( empty( $this->childEnabled ) || empty( $this->childEnabled['key'] ) ) {
	        return; // not enabled yet
	    }
	    $this->childKey = $this->childEnabled['key'];

	    // NEW: Make sure MainWP core tables exist before continuing
	    if ( ! $this->mainwp_tables_ready() ) {
	        add_action( 'admin_notices', function () {
	            echo '<div class="error"><p>'
	                . esc_html__( 'MainWP core database tables have not been created yet. Please (re)activate the MainWP Dashboard plugin to let it install its tables, then activate this extension.', 'mainwp-client-notes-pro-reports-extention' )
	                . '</p></div>';
	        } );
	        return;
	    }

	    // Safe to initialize the extension
	    new MainWP_Client_Notes_Proreport_Extension();
	}

	function mainwp_error_notice() {
		global $current_screen;
		if ( $current_screen->parent_base == 'plugins' && $this->mainwpClientNotesProReportActivated == false ) {
			echo '<div class="error"><p>MainWP Client Notes Extension ' . __( 'requires ' ) . '<a href="http://mainwp.com/" target="_blank">MainWP</a>' . __( ' Plugin to be activated in order to work. Please install and activate' ) . '<a href="http://mainwp.com/" target="_blank">MainWP</a> ' . __( 'first.' ) . '</p></div>';
		}
	}

	public function getChildKey() {
		return $this->childKey;
	}

	public function getChildFile() {
		return $this->childFile;
	}
}

global $mainwpclientnotesproreportExtensionActivator;
$mainwpclientnotesproreportExtensionActivator = new MainWP_Client_Pro_Report_Notes_Activator();

// Define plugin constants
define('RUP_MAINWP_CLIENT_NOTES_VERSION', '1.3.0-rc.1');


// ──────────────────────────────────────────────────────────────────────────
//  Updater bootstrap (plugins_loaded priority 1):
// ──────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
    // 1) Load our universal drop-in. Because that file begins with "namespace UUPD\V1;",
    //    both the class and the helper live under UUPD\V1.
    require_once __DIR__ . '/inc/updater.php';

    // 2) Build a single $updater_config array:
    $updater_config = [
        'plugin_file' => plugin_basename( __FILE__ ),             // e.g. "simply-static-export-notify/simply-static-export-notify.php"
        'slug'        => 'mainwp-client-notes-pro-reports-extention',           // must match your updater‐server slug
        'name'        => 'MainWP Client Notes Pro Report Extension',         // human‐readable plugin name
        'version'     => RUP_MAINWP_CLIENT_NOTES_VERSION, // same as the VERSION constant above
        'key'         => '',                 // your secret key for private updater
        'server'      => 'https://raw.githubusercontent.com/stingray82/MainWP-Client-Notes-For-Pro-Report/main/uupd/index.json',
    ];

    // 3) Call the helper in the UUPD\V1 namespace:
    \RUP\Updater\Updater_V1::register( $updater_config );
}, 20 );



// Old Filter
add_filter('uupd/allow_prerelease/mainwp-client-notes-pro-reports-extention', function ($allow) {
    return get_option('mainwp_client_notes_proreport_allow_prerelease') === 'yes';
}, 5);


//activation gate: fail early with a message instead of a fatal.
register_activation_hook(__FILE__, function ($network_wide) {
    if ( ! function_exists('apply_filters') ) {
        return; // shouldn't happen, but be defensive
    }

    // Is MainWP Dashboard even active?
    $mainwp_active = apply_filters('mainwp_activated_check', false);
    if ( ! $mainwp_active ) {
        wp_die(
            esc_html__('Please activate the MainWP Dashboard plugin first, then activate this extension.', 'mainwp-client-notes-pro-reports-extention'),
            400
        );
    }

    // Are MainWP core tables present?
    global $wpdb;
    $need = [
        $wpdb->prefix . 'mainwp_wp',
        $wpdb->prefix . 'mainwp_api_keys',
    ];

    // Don’t fatal on broken DB; just check quietly.
    $wpdb->suppress_errors(true);
    foreach ($need as $table) {
        $exists = $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $table) );
        if ($exists !== $table) {
            wp_die(
                sprintf(
                    /* translators: %s is a table name */
                    esc_html__('MainWP table missing: %s. Please (re)activate the MainWP Dashboard so it can create its database tables, then try activating this extension again.', 'mainwp-client-notes-pro-reports-extention'),
                    esc_html($table)
                ),
                400
            );
        }
    }
    $wpdb->suppress_errors(false);

    // ok to create here; harmless if exists
    if (is_multisite() && $network_wide) {
        $sites = get_sites(['fields' => 'ids']);
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            \MainWP\Dashboard\MainWP_Work_Notes::create_work_notes_table();
            restore_current_blog();
        }
    } else {
        \MainWP\Dashboard\MainWP_Work_Notes::create_work_notes_table();
    }
});

