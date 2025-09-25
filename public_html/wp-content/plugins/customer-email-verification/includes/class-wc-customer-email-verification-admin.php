<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Customer_Email_Verification_Admin_Pro
 * Handles the addition of custom endpoints, styles, and scripts.
 */
class WC_Customer_Email_Verification_Admin_Pro {
	
	/**
	 * Stores the My Account page ID.
	 *
	 * @var int
	 */
	public $my_account_id;

	/**
	 * Instance of this class.
	 *
	 * @var Woo_Customer_Email_Verification_Admin
	 */
	private static $instance;

	/**
	 * Initialize the main plugin function.
	 */
	public function __construct() {
		add_action('init', array($this, 'init'));
	}

	/**
	 * Get the class instance.
	 *
	 * Ensures that only one instance of the class is created.
	 *
	 * @return Woo_Customer_Email_Verification_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	
	/**
	 * Initialize the hooks and filters for the plugin.
	 */
	public function init() {

		/** WooCommerce Integration **/
		// Add a custom submenu under WooCommerce.
		add_action('admin_menu', array($this, 'register_woocommerce_menu'), 99);
		
		// Enqueue admin-specific styles and scripts.
		add_action('admin_enqueue_scripts', array($this, 'admin_styles'), 4);

		// Ajax handler for updating the settings form.
		add_action('wp_ajax_cev_settings_form_update', array($this, 'cev_settings_form_update_fun'));

		/** User List Customization **/
		// Add custom columns to the user list in the admin area.
		add_filter('manage_users_columns', array($this, 'add_column_users_list'), 10, 1);
		add_filter('manage_users_custom_column', array($this, 'add_details_in_custom_users_list'), 10, 3);

		// Display email verification fields in user profiles (single user edit page).
		add_action('show_user_profile', array($this, 'show_cev_fields_in_single_user'));
		add_action('edit_user_profile', array($this, 'show_cev_fields_in_single_user'));

		// Allow manual verification/unverification of users from the admin interface.
		add_action('admin_head', array($this, 'cev_manual_verify_user'));

		/** Sorting and Filtering Users **/
		// Add a filter dropdown for user verification status.
		add_action('restrict_manage_users', array($this, 'filter_user_by_verified'));
		
		// Adjust the user query based on the selected verification status.
		add_filter('pre_get_users', array($this, 'filter_users_by_user_by_verified_section'));

		/** Bulk Actions for Users **/
		// Add custom bulk actions for email verification.
		add_filter('bulk_actions-users', array($this, 'add_custom_bulk_actions_for_user'));
		
		// Handle custom bulk actions for email verification.
		add_filter('handle_bulk_actions-users', array($this, 'users_bulk_action_handler'), 10, 3);
		
		// Display admin notices for bulk action results.
		add_action('admin_notices', array($this, 'user_bulk_action_notices'));

		/** Ajax Actions **/
		// Ajax handler for manually verifying a user from the user menu.
		add_action('wp_ajax_cev_manualy_user_verify_in_user_menu', array($this, 'cev_manualy_user_verify_in_user_menu'));

		// Add a custom body class on the settings page.
		$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
		if ('customer-email-verification-for-woocommerce' === $page) {
			add_filter('admin_body_class', array($this, 'cev_post_admin_body_class'), 100);
		}

		/** User Deletion via Ajax **/
		// Ajax handler for deleting a single user log entry.
		add_action('wp_ajax_delete_user', array($this, 'cev_delete_user'), 4);
		
		// Ajax handler for deleting multiple user log entries.
		add_action('wp_ajax_delete_users', array($this, 'cev_delete_users'), 4);

		/** Other Filters **/
		// Modify the verification code length via filter.
		add_filter('cev_verification_code_length', array($this, 'cev_verification_code_length_callback'), 10, 1);
		
		
	}
	
	/**
	 * Handles the deletion of a user log entry via AJAX.
	 */
	public function cev_delete_user() {
		// Verify the nonce for security and validate the request
		check_ajax_referer('delete_user_nonce', 'nonce');

		// Ensure the `id` parameter is set
		if (isset($_POST['id'])) {
			global $wpdb;

			// Sanitize and validate the user ID
			$id = intval($_POST['id']);
			$table_name = $wpdb->prefix . 'cev_user_log';

			// Attempt to delete the user log entry
			$result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

			// Respond with a success or failure message
			if (false !== $result) {
				wp_send_json_success(array('message' => 'User log deleted successfully.'));
			} else {
				wp_send_json_error(array('message' => 'Failed to delete user log.'));
			}
		} else {
			wp_send_json_error(array('message' => 'Invalid request. Missing user ID.'));
		}

		// Ensure the script terminates correctly
		wp_die();
	}

	/**
	 * Handles the deletion of multiple user log entries via AJAX.
	 */
	public function cev_delete_users() {
		// Verify the nonce for security
		check_ajax_referer('delete_user_nonce', 'nonce');

		global $wpdb;
		$table_name = $wpdb->prefix . 'cev_user_log';

		// Initialize results array to track outcomes
		$results = [
			'success' => 0,    // Number of successful deletions
			'failure' => 0,    // Number of failed deletions
			'messages' => []   // Array of error or status messages
		];

		// Validate and sanitize input
		if (isset($_POST['ids']) && is_array($_POST['ids'])) {
			// Sanitize IDs
			$ids = array_map('intval', $_POST['ids']);

			foreach ($ids as $id) {
				// Skip invalid IDs
				if ($id <= 0) {
					$results['failure']++;
					$results['messages'][] = "Invalid ID: $id";
					continue;
				}

				// Attempt to delete the user log entry
				$result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
				if ( false !== $result ) {
					$results['success']++;
				} else {
					$results['failure']++;
					$results['messages'][] = "Failed to delete ID: $id";
				}
			}
		} else {
			// Handle case where 'ids' parameter is missing or invalid
			$results['failure']++;
			$results['messages'][] = 'Invalid or missing IDs.';
		}

		// Respond with the results as JSON
		wp_send_json($results);

		// Terminate script execution properly
		wp_die();
	}
	
	/**
	 * Registers a custom submenu under the WooCommerce menu in the WordPress admin.
	 */
	public function register_woocommerce_menu() {
		add_submenu_page(
			'woocommerce', // Parent menu slug
			__('Customer Verification', 'customer-email-verification'), // Page title
			__('Email Verification', 'customer-email-verification'), // Menu title
			'manage_woocommerce', // Capability required to access this menu
			'customer-email-verification-for-woocommerce', // Menu slug
			array($this, 'render_email_verification_page') // Callback function to render the page
		);
	}

	/**
	 * Callback function to render the Customer Email Verification page in the admin area.
	 */
	public function render_email_verification_page() {

		// Enqueue necessary scripts for the settings page.
		wp_enqueue_script('customer_email_verification_table_rows');
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'email-verification';
		$breadcrumb_text = __( 'Settings', 'customer-email-verification' );
		if ( 'unverified-users' === $tab ) {
			$breadcrumb_text = __( 'Unverified Users', 'customer-email-verification' );
		} elseif ( 'add-ons' === $tab ) {
			$breadcrumb_text = __( 'Go Pro', 'customer-email-verification' );
		}
		?>
		<div class="zorem-layout-cev__header">
			<!-- Page header with navigation breadcrumbs -->
			<h1 class="page_heading">
				<a href="javascript:void(0)">
					<?php esc_html_e('Customer Email Verification', 'customer-email-verification'); ?>
				</a>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
				<span class="breadcums_page_heading">
					<?php echo esc_html( $breadcrumb_text ); ?>
				</span>
			</h1>
			<!-- Optional: Uncomment if logo needs to be displayed -->
			<img class="zorem-layout-cev__header-logo" src="<?php echo esc_url(cev_pro()->plugin_dir_url()); ?>assets/images/zorem-logo.png" alt="Logo">
		</div>

		<div class="woocommerce cev_admin_layout">
			<div class="cev_admin_content">
				<!-- Include the activity panel -->
				<?php include 'views/activity_panel.php'; ?>

				<!-- Navigation menu and tabs -->
				<div class="cev_nav_div">
					<?php
					// Render the menu tabs.
					$this->render_menu_tabs($this->get_cev_tab_settings_data());
					?>
					<div class="menu_devider"></div>

					<!-- Include admin settings views -->
					<?php
					require_once('views/admin_options_settings.php');
					require_once('views/cev_users_tab.php');
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Retrieve tab configuration for the Email Verification menu.
	 *
	 * This function returns an array of tab settings used to display menu tabs
	 * for the Email Verification plugin in the WordPress admin area.
	 *
	 * @return array Tab configuration data.
	 */
	public function get_cev_tab_settings_data() {
		$setting_data = [
			// Settings tab configuration.
			'setting_tab' => [
				'title'     => __( 'Settings', 'customer-email-verification' ), // Tab title.
				'show'      => true, // Whether the tab is displayed.
				'class'     => 'cev_tab_label first_label', // CSS class for styling.
				'data-tab'  => 'email-verification', // Data attribute for tab functionality.
				'data-label'=> __( 'Settings', 'customer-email-verification' ), // Label for accessibility.
				'name'      => 'tabs', // Input name attribute.
			],
	
			// Customize tab configuration.
			'customize' => [
				'title'     => __( 'Customize', 'customer-email-verification' ), // Tab title.
				'type'      => 'link', // Tab type ('link' indicates a clickable link).
				'link'      => admin_url( 'admin.php?page=cev_customizer&preview=email_registration' ), // URL for the tab.
				'show'      => true, // Whether the tab is displayed.
				'class'     => 'tab_label', // CSS class for styling.
				'data-tab'  => 'trackship', // Data attribute for tab functionality.
				'data-label'=> __( 'Customize', 'customer-email-verification' ), // Label for accessibility.
				'name'      => 'tabs', // Input name attribute.
			],
	
			// Unverified Users tab configuration.
			'user_tab' => [
				'title'     => __( 'Unverified Users', 'customer-email-verification' ), // Tab title.
				'show'      => true, // Whether the tab is displayed.
				'class'     => 'cev_tab_label', // CSS class for styling.
				'data-tab'  => 'unverified-users', // Data attribute for tab functionality.
				'data-label'=> __( 'Unverified Users', 'customer-email-verification' ), // Label for accessibility.
				'name'      => 'tabs', // Input name attribute.
			],

				
		];
		return $setting_data;
	}	

	/**
	 * Retrieves settings data for new email verification configurations.
	 *
	 * @return array Array of configuration options for email verification.
	 */
	public function get_cev_settings_data_new() {
		
		// Define checkout verification types
		$checkout_verification_types = [
			1 => __('Popup', 'customer-email-verification'),
			2 => __('Inline', 'customer-email-verification'),
		];

		// Build the settings form data
		$form_data = [
			// Enable signup verification
			'cev_enable_email_verification' => [
				'type'    => 'toogel',
				'title'   => __('Enable Signup Verification', 'customer-email-verification'),
				'show'    => true,
				'class'   => 'toogel',
				'name'    => 'cev_enable_email_verification',
				'id'      => 'cev_enable_email_verification',
				'Default'	  => 1,
			],

			// Separator for visual separation
			'cev_settings_separator_1' => [
				'type' => 'separator',
				'id'   => 'cev_settings_separator_1',
				'show' => true,
			],

			// Enable checkout verification
			'cev_enable_email_verification_checkout' => [
				'type'    => 'toogel',
				'title'   => __('Enable Checkout Verification', 'customer-email-verification'),
				'show'    => true,
				'class'   => 'toogel',
				'name'    => 'cev_enable_email_verification_checkout',
				'id'      => 'cev_enable_email_verification_checkout',
				'Default'	  => 0,
			],
			
			// Checkout verification type dropdown
			'cev_verification_checkout_dropdown_option' => [
				'type'    => 'dropdown',
				'title'   => __('Checkout Verification Type', 'customer-email-verification'),
				'options' => $checkout_verification_types,
				'show'    => true,
				'class'   => 'cev-skip-bottom-padding',
			],
			'cev_create_an_account_during_checkout' => [
				'type'    => 'checkbox',
				'title'   => __('Require email verification only when Create an account during checkout is selected', 'customer-email-verification'),
				'show'    => true,
				'class'   => 'toogel',
				'name'    => 'cev_create_an_account_during_checkout',
				'id'      => 'cev_create_an_account_during_checkout',
				'Default'	  => 0,
			],

			// Enable verification on cart page
			'cev_enable_email_verification_cart_page' => [
				'type'    => 'checkbox',
				'title'   => __('Enable the email verification on the cart page', 'customer-email-verification'),
				'show'    => true,
				'id'      => 'cev_enable_email_verification_cart_page',
			],

			// Require checkout verification for free orders
			'cev_enable_email_verification_free_orders' => [
				'type'    => 'checkbox',
				'title'   => __('Require checkout verification only for free orders', 'customer-email-verification'),
				'show'    => true,
				'id'      => 'cev_enable_email_verification_free_orders',
				'Default'	  => 0,
			],
			// Separator for visual separation
			'cev_settings_separator_10' => [
				'type' => 'separator',
				'id'   => 'cev_settings_separator_10',
				'show' => true,
			],
			'cev_disable_wooCommerce_store_api' => [
				'type'    => 'toogel',
				'title'   => __('Disable WooCommerce Store API Checkout', 'customer-email-verification'),
				'show'    => true,
				'class'   => 'toogel',
				'name'    => 'cev_disable_wooCommerce_store_api',
				'id'      => 'cev_disable_wooCommerce_store_api',
				'Default'	  => 0,
			],

		];

		return $form_data;
	}

	/**
	 * Retrieves settings data for new email verification configurations.
	 *
	 * @return array Array of configuration options for email verification.
	 */

	public function get_cev_general_settings_data() {
		global $wp_roles;

		// Get all roles except administrator
		$all_roles = $wp_roles->roles;
		$roles_options = [];
		foreach ($all_roles as $key => $role) {
			if ('administrator' !== $key) {
				$roles_options[$key] = $role['name'];
			}
		}
		// Define OTP length options
		$otp_lengths = [
			'1' => __('4-digits', 'customer-email-verification'),
			'2' => __('6-digits', 'customer-email-verification'),
		];

		// Define OTP expiration options
		$otp_expirations = [
			'never'   => __('Never', 'customer-email-verification'),
			'600'     => __('10 min', 'customer-email-verification'),
			'900'     => __('15 min', 'customer-email-verification'),
			'1800'    => __('30 min', 'customer-email-verification'),
			'3600'    => __('1 Hour', 'customer-email-verification'),
			'86400'   => __('24 Hours', 'customer-email-verification'),
			'259200'  => __('72 Hours', 'customer-email-verification'),
		];

		// Define resend limit options
		$resend_limits = [
			'1' => __('Allow 1 Attempt', 'customer-email-verification'),
			'3' => __('Allow 3 Attempts', 'customer-email-verification'),
			'0' => __('Disable Resend', 'customer-email-verification'),
		];
		$formdata = [
			// OTP length dropdown
			'cev_verification_code_length' => [
				'type'    => 'dropdown',
				'title'   => __('OTP Length', 'customer-email-verification'),
				'options' => $otp_lengths,
				'show'    => true,
			],

			// OTP expiration dropdown
			'cev_verification_code_expiration' => [
				'type'    => 'dropdown',
				'title'   => __('OTP Expiration', 'customer-email-verification'),
				'options' => $otp_expirations,
				'show'    => true,
				'tooltip' => __('Choose if you wish to set expiry time to the OTP / link.', 'customer-email-verification'),
			],

			// Separator for visual separation
			'cev_settings_separator_3' => [
				'type' => 'separator',
				'id'   => 'cev_settings_separator_3',
				'show' => true,
			],

			// Resend limit dropdown
			'cev_redirect_limit_resend' => [
				'type'    => 'dropdown',
				'title'   => __('Verification Email Resend Limit', 'customer-email-verification'),
				'options' => $resend_limits,
				'show'    => true,
				'class'   => 'redirect_page',
			],

			// Resend limit message textarea
			'cev_resend_limit_message' => [
				'type'        => 'textarea',
				'title'       => __('Resend Limit Message', 'customer-email-verification'),
				'show'        => true,
				'tooltip'     => __('Limit the number of resend attempts.', 'customer-email-verification'),
				'placeholder' => __('Too many attempts, please contact us for further assistance', 'customer-email-verification'),
				'Default'	  => '',
				'class'       => 'cev_text_design top',
			],

			// Email verification success message
			'cev_verification_success_message' => [
				'type'        => 'textarea',
				'title'       => __('Email Verification Success Message', 'customer-email-verification'),
				'show'        => true,
				'tooltip'     => __('Message shown after successful verification.', 'customer-email-verification'),
				'placeholder' => __('Your email is verified!', 'customer-email-verification'),
				'Default'	  => ''
			],
		];

		return $formdata;
		
	}

	/**
	 * Retrieves the settings data for login OTP configuration.
	 *
	 * @return array Array of settings configuration for login OTP.
	 */
	public function get_cev_settings_data_login_otp() {
		// Define form data for login OTP settings
		$form_data = [
			// Toggle for enabling login authentication
			'cev_enable_login_authentication' => [
				'type'    => 'toogel',
				'title'   => __( 'Enable Login Authentication', 'customer-email-verification' ),
				'show'    => true,
				'class'   => 'toogel cev_enable_login_authentication',
				'name'    => 'cev_enable_login_authentication',
				'id'      => 'cev_enable_login_authentication',
				'value'   => '1',
			],

			// Checkbox for requiring OTP verification for unrecognized logins
			'enable_email_otp_for_account' => [
				'type'    => 'checkbox',
				'title'   => __( 'Require OTP verification for unrecognized login', 'customer-email-verification' ),
				'show'    => true,
				'id'      => '',
				'Default' => 1,
			],

			// Separator for visual separation in the settings UI
			'cev_settings_separator_4' => [
				'type' => 'separator',
				'id'   => 'cev_settings_separator_4',
				'show' => true,
			],

			// Description for unrecognized login conditions
			'login_auth_desc' => [
				'type'  => 'desc',
				'title' => __( 'Unrecognized Login Conditions:', 'customer-email-verification' ),
				'show'  => true,
				'id'    => '',
			],

			// Checkbox for login authentication based on a new device
			'enable_email_auth_for_new_device' => [
				'type'    => 'checkbox',
				'title'   => __( 'Login from a new device', 'customer-email-verification' ),
				'show'    => true,
				'id'      => '',
				'Default' => 1,
			],

			// Checkbox for login authentication based on a new location
			'enable_email_auth_for_new_location' => [
				'type'    => 'checkbox',
				'title'   => __( 'Login from a new location', 'customer-email-verification' ),
				'show'    => true,
				'id'      => '',
				'Default' => 1,
			],

			// Checkbox select for login authentication based on last login time
			'enable_email_auth_for_login_time' => [
				'type'   => 'checkbox_select',
				'title'  => __( 'Last login more than', 'customer-email-verification' ),
				'select' => [
					'options' => [
						15 => __( '15 Days', 'customer-email-verification' ),
						30 => __( '30 Days', 'customer-email-verification' ),
						60 => __( '60 Days', 'customer-email-verification' ),
					],
					'id' => 'cev_last_login_more_then_time',
				],
				'show'    => true,
				'id'      => '',
				'Default' => 1,
				'class'   => 'enable_email_auth_for_login_time',
			],
		];

		return $form_data;
	}	

	/**
	 * Generate HTML for menu tabs in the admin panel.
	 *
	 * @param array $tabs Array of menu tab configurations.
	 */
	public function render_menu_tabs( $tabs ) {
		// Get the currently selected tab from the URL, default to 'email-verification'.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'email-verification';
	
		// Loop through the provided tabs array.
		foreach ( (array) $tabs as $id => $tab ) {
			// Check if the tab type is a 'link' and render it accordingly.
			if ( isset( $tab['type'] ) && 'link' === $tab['type'] ) {
				?>
				<a class="menu_cev_link" href="<?php echo esc_url( $tab['link'] ); ?>">
					<?php echo esc_html( $tab['title'] ); ?>
				</a>
				<?php
			} else {
				// Determine if the current tab is selected.
				$checked = ( $current_tab === $tab['data-tab'] ) ? 'checked' : '';
				?>
				<!-- Render the radio input for the tab -->
				<input 
					class="cev_tab_input" 
					id="<?php echo esc_attr( $id ); ?>" 
					name="<?php echo esc_attr( $tab['name'] ); ?>" 
					type="radio" 
					data-tab="<?php echo esc_attr( $tab['data-tab'] ); ?>" 
					data-label="<?php echo esc_attr( $tab['data-label'] ); ?>" 
					<?php echo esc_attr( $checked ); ?> 
				/>
				<!-- Render the label for the tab -->
				<label 
					class="<?php echo esc_attr( $tab['class'] ); ?>" 
					for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $tab['title'] ); ?>
				</label>
				<?php
			}
		}
	}

	/**
	 * Generate HTML for the settings fields based on the provided settings array.
	 *
	 * @param array $fields Settings fields array.
	 */
	public function render_settings_fields( $fields ) {
		?>
		<ul class="settings_ul">
			<?php
			foreach ( (array) $fields as $id => $field ) {
				// Skip rendering if the field is not set to 'show'.
				if ( empty( $field['show'] ) ) {
					continue;
				}

				$class    = isset( $field['class'] ) ? esc_attr( $field['class'] ) : '';
				$disabled = '';

				// Handle conditional disabling of fields based on other options.
				if ( in_array( $id, [ 'cev_verification_code_length', 'cev_verification_code_expiration' ], true ) ) {
					$disabled = ( get_option( 'cev_verification_type' ) === 'link' ) ? 'disabled' : '';
				}

				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<?php if ( ! in_array( $field['type'], [ 'desc', 'checkbox', 'checkbox_select', 'toogel', 'separator' ], true ) ) : ?>
						<label class="settings_label <?php echo esc_attr( $disabled ); ?>">
							<?php echo esc_html( $field['title'] ); ?>
							<?php if ( isset( $field['tooltip'] ) ) : ?>
								<span class="woocommerce-help-tip tipTip" title="<?php echo esc_attr( $field['tooltip'] ); ?>"></span>
							<?php endif; ?>
						</label>
					<?php endif; ?>

					<?php
					// Render field types based on the 'type' key.
					switch ( $field['type'] ) {
						case 'dropdown':
							$this->render_dropdown_field( $id, $field, $disabled );
							break;

						case 'multiple_select':
							$this->render_multiple_select_field( $id, $field );
							break;

						case 'checkbox':
							$this->render_checkbox_field( $id, $field, $disabled );
							break;

						case 'toogel':
							$this->render_toggle_field( $id, $field );
							break;

						case 'textarea':
							$this->render_textarea_field( $id, $field );
							break;

						case 'checkbox_select':
							$this->render_checkbox_select_field( $id, $field, $disabled  );
							break;	

						case 'desc':
							echo '<p class="section_desc ' . esc_attr( $disabled ) . '" id="' . esc_attr( $id ) . '">' . esc_html( $field['title'] ) . '</p>';
							break;

						case 'separator':
							echo '<div class="cev_separator"></div>';
							break;

						default:
							$this->render_text_input_field( $id, $field );
							break;
					} 
					?>
				</li>
				<?php
			} 
			?>
		</ul>
		<?php
	}

	/**
	 * Render `checkbox_select` field.
	 */
	public function render_checkbox_select_field( $id, $field, $disabled ) {
		$checked = get_option( $id, 1 ) ? 'checked' : '';
		?>
		<label class="<?php echo esc_attr( $disabled ); ?>" for="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="0"/>
			<input 
				type="checkbox" 
				id="<?php echo esc_attr( $id ); ?>" 
				name="<?php echo esc_attr( $id ); ?>" 
				value="1" 
				<?php echo esc_attr( $checked ); ?> 
				<?php echo esc_attr( $disabled ); ?>
			/>
			<span class="label">
				<?php echo esc_html( $field['title'] ); ?>
				<?php if ( ! empty( $field['select'] ) ) : ?>
					<select name="<?php echo esc_attr( $field['select']['id'] ); ?>" id="<?php echo esc_attr( $field['select']['id'] ); ?>" style="width: auto;" <?php echo esc_attr( $disabled ); ?>>
						<?php
						foreach ( $field['select']['options'] as $key => $val ) {
							$selected = ( get_option( $field['select']['id'], '' ) == $key ) ? 'selected' : '';
							echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $val ) . '</option>';
						} 
						?>
					</select>
				<?php endif; ?>
			</span>
		</label>
		<?php
	}

	public function render_dropdown_field( $id, $field, $disabled ) {
		?>
		<fieldset>
			<select 
				class="select select2" 
				id="<?php echo esc_attr( $id ); ?>" 
				name="<?php echo esc_attr( $id ); ?>" 
				<?php echo esc_attr( $disabled ); ?>
			>
				<?php
				foreach ( $field['options'] as $key => $value ) {
					$default_value = isset( $field['Default'] ) ? $field['Default'] : ''; 
					$selected = ( get_option( $id, $default_value ) === (string) $key ) ? 'selected' : '';
					echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
				}
				?>
			</select>
		</fieldset>
		<?php
	}

	public function render_multiple_select_field( $id, $field ) {
		$multi_values = get_option( $id );
		?>
		<div class="multiple_select_container">
			<select multiple class="wc-enhanced-select" name="<?php echo esc_attr( $id ); ?>[]" id="<?php echo esc_attr( $id ); ?>">
				<?php
				foreach ( $field['options'] as $key => $value ) {
					$selected = isset( $multi_values[ $key ] ) && 1 === $multi_values[ $key ] ? 'selected' : '';
					echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
				}
				?>
			</select>
		</div>
		<?php
	}

	public function render_checkbox_field( $id, $field, $disabled ) {
		$checked = get_option( $id, 1 ) ? 'checked' : '';
		?>
		<label class="<?php echo esc_attr( $disabled ); ?>" for="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="0"/>
			<input 
				type="checkbox" 
				id="<?php echo esc_attr( $id ); ?>" 
				name="<?php echo esc_attr( $id ); ?>" 
				value="1" 
				<?php echo esc_attr( $checked ); ?>
				<?php echo esc_attr( $disabled ); ?>
			/>
			<span class="label"><?php echo esc_html( $field['title'] ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render `toogel` field with label.
	 */
	public function render_toggle_field( $id, $field ) {
		$default = isset( $field['Default'] ) ? $field['Default'] : '';
		$checked = get_option( $id, $default ) ? 'checked' : '';

		?>
		<div class="accordion-toggle">
			<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="0"/>
			<input 
				class="tgl tgl-flat-cev" 
				id="<?php echo esc_attr( $id ); ?>" 
				name="<?php echo esc_attr( $id ); ?>" 
				type="checkbox" 
				value="1" 
				<?php echo esc_attr( $checked ); ?>
			/>
			<label class="tgl-btn tgl-panel-label" for="<?php echo esc_attr( $id ); ?>"></label>
		</div>
		<label class="settings_label">
			<?php echo esc_html( $field['title'] ); ?>
		</label>
		<?php
	}

	public function render_textarea_field( $id, $field ) {
		?>
		<fieldset>
			<textarea 
				placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" 
				class="input-text regular-input" 
				name="<?php echo esc_attr( $id ); ?>" 
				id="<?php echo esc_attr( $id ); ?>"
			><?php echo esc_textarea( get_option( $id, $field['Default'] ) ); ?></textarea>
		</fieldset>
		<?php
	}

	public function render_text_input_field( $id, $field ) {
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		?>
		<fieldset>
			<input 
				class="input-text regular-input" 
				type="text" 
				name="<?php echo esc_attr( $id ); ?>" 
				id="<?php echo esc_attr( $id ); ?>" 
				value="<?php echo esc_attr( get_option( $id, $field['Default'] ) ); ?>" 
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
			/>
		</fieldset>
		<?php
	}

	/**
	 * Handles the update of Customer Email Verification settings form.
	 */
	public function cev_settings_form_update_fun() {
		// Verify nonce for security.
		if ( empty( $_POST ) || ! check_admin_referer( 'cev_settings_form_nonce', 'cev_settings_form_nonce' ) ) {
			return;
		}

		// Fetch settings data.
		$general_settings = $this->get_cev_settings_data_new();
		$login_otp_settings = $this->get_cev_settings_data_login_otp();
		$get_cev_general_settings_data = $this->get_cev_general_settings_data();
		// Update toggle options.
		$toggle_fields = [
			'cev_enable_email_verification',
			'cev_enable_email_verification_checkout',
			'cev_enable_login_authentication',
			'cev_verification_type',
		];

		foreach ( $toggle_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, wc_clean( $_POST[ $field ] ) );
			}
		}

		// Update general settings.
		$this->update_settings( $general_settings, $_POST );

		// Update login OTP settings.
		$this->update_settings( $login_otp_settings, $_POST );

		$this->update_settings( $get_cev_general_settings_data, $_POST );
		// Update verification email body based on verification type.
		if ( isset( $_POST['cev_verification_type'] ) ) {
			$verification_type = wc_clean( $_POST['cev_verification_type'] );
			$email_body = $this->get_verification_email_body( $verification_type );
			update_option( 'cev_verification_email_body', $email_body );
		}
	}

	/**
	 * Updates settings from a given settings array and request data.
	 *
	 * @param array $settings The settings data.
	 * @param array $request  The request data.
	 */
	private function update_settings( $settings, $request ) {
		foreach ( $settings as $key => $field ) {
			if ( isset( $request[ $key ] ) ) {
				// Handle multiple select fields.
				if ( isset( $field['type'] ) && 'multiple_select' === $field['type'] ) {
					$this->update_multiple_select_field( $key, $field, $request );
				} elseif ( isset( $field['type'] ) && 'checkbox_select' === $field['type'] ) {
					$this->update_checkbox_select_field( $key, $field, $request );
				} else {
					// Update other fields.
					update_option( $key, wc_clean( $request[ $key ] ) );
				}
			} elseif ( isset( $field['type'] ) && 'multiple_select' === $field['type'] ) {
				update_option( $key, '' ); // Clear multiple select field if not set.
			}
		}
	}

	/**
	 * Updates a multiple select field.
	 *
	 * @param string $key      The field key.
	 * @param array  $field    The field data.
	 * @param array  $request  The request data.
	 */
	private function update_multiple_select_field( $key, $field, $request ) {
		if ( isset( $request[ $key ] ) ) {
			$roles = [];
			foreach ( $field['options'] as $option_key => $option_val ) {
				$roles[ $option_key ] = 0; // Default to unselected.
			}

			foreach ( wc_clean( $request[ $key ] ) as $selected_option ) {
				$roles[ $selected_option ] = 1; // Mark selected options.
			}
			update_option( $key, $roles );
		}
	}

	/**
	 * Updates a checkbox_select field.
	 *
	 * @param string $key      The field key.
	 * @param array  $field    The field data.
	 * @param array  $request  The request data.
	 */
	private function update_checkbox_select_field( $key, $field, $request ) {
		if ( isset( $field['select']['id'] ) && isset( $request[ $field['select']['id'] ] ) ) {
			update_option( $field['select']['id'], wc_clean( $request[ $field['select']['id'] ] ) );
		}
	}

	/**
	 * Returns the verification email body based on the verification type.
	 *
	 * @param string $verification_type The type of verification (e.g., 'otp', 'both', 'link').
	 * @return string The email body content.
	 */
	private function get_verification_email_body( $verification_type ) {
		switch ( $verification_type ) {
			case 'otp':
				return __( 'Thank you for signing up for {site_title}, to activate your account, we need to verify your email address.<p>Your verification code: <strong>{cev_user_verification_pin}</strong></p>', 'customer-email-verification' );
			case 'both':
				return __( 'Thank you for signing up for {site_title}, to activate your account, we need to verify your email address.<p>Your verification code: <strong>{cev_user_verification_pin}</strong></p><p>Or, verify your account by clicking on the button below: {cev_user_verification_link}</p>', 'customer-email-verification' );
			default:
				return __( 'Thank you for signing up for {site_title}, to activate your account, we need to verify your email address.<p>Verify your account by clicking on the button below: {cev_user_verification_link}	</p>', 'customer-email-verification' );
		}
	}

	/**
	 * Enqueue admin styles and scripts for the Email Verification settings page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function admin_styles( $hook ) {
		// Ensure the styles and scripts are loaded only on the Email Verification settings page.
		if ( !isset( $_GET['page'] ) || 'customer-email-verification-for-woocommerce' != $_GET['page'] ) {
			return;
		}
		
		// Determine script suffix based on debug mode.
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
		// Enqueue jQuery (required for many scripts).
		wp_enqueue_script( 'jquery' );
		
		// Enqueue select2 and related scripts for enhanced dropdown functionality.
		wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full' . $suffix . '.js', array( 'jquery' ), '4.0.3' );
		wp_enqueue_script( 'selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js', array( 'jquery' ), '1.0.4' );
		wp_enqueue_script( 'wc-enhanced-select', WC()->plugin_url() . '/assets/js/admin/wc-enhanced-select' . $suffix . '.js', array( 'jquery', 'selectWoo' ), WC_VERSION );

		// Enqueue BlockUI script for loading spinners.
		wp_enqueue_script( 'jquery-blockui', WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array( 'jquery' ), '2.70', true );
	
		// Enqueue WooCommerce admin styles.
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), time() );
	
		// Enqueue tooltips functionality.
		wp_enqueue_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), time(), true );
	
		// Enqueue DataTables library for enhanced table management.
		wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), time(), true);
		wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', array(), time());

		// Enqueue custom admin scripts and styles for the plugin.
		wp_enqueue_script( 'cev_pro_admin_js', cev_pro()->plugin_dir_url() . 'assets/js/admin.js', array('jquery', 'wp-util', 'datatables-js'), time(), true );		
		wp_enqueue_style( 'cev-pro-admin-css', cev_pro()->plugin_dir_url() . 'assets/css/admin.css', array(), time() );
		
		// Localize script to pass variables and data to JavaScript.
		wp_localize_script( 'cev_pro_admin_js', 'cev_pro_admin_js', array() );
		wp_localize_script('cev_pro_admin_js', 'iconUrls', array(
			'verified' => cev_pro()->plugin_dir_url() . 'assets/css/images/checked.png',
			'unverified' => cev_pro()->plugin_dir_url() . 'assets/css/images/cross.png',
		));
		wp_localize_script('cev_pro_admin_js', 'cev_vars', array(
			'delete_user_nonce' => wp_create_nonce('delete_user_nonce'),
			'ajax_url' => admin_url('admin-ajax.php')
		)); 

		// Enqueue additional scripts and media uploader.
		wp_enqueue_script( 'media-lib-uploader-js' );
		wp_enqueue_media();
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
	}

	/**
	 * Adds custom columns to the user listing screen in the WP Admin area.
	 *
	 * @param array $columns Existing columns in the user listing screen.
	 * @return array Modified columns with custom entries.
	 */
	public function add_column_users_list( $columns ) {
		// Add the 'Email verification' column.
		$columns['cev_verified'] = __( 'Email Verification', 'customer-email-verification' );

		// Add the 'Actions' column.
		$columns['cev_action'] = __( 'Actions', 'customer-email-verification' );

		return $columns;
	}

	/**
	 * Adds custom values to the custom columns in the user listing screen in WP Admin.
	 *
	 * @param string $val The current column content.
	 * @param string $column_name The name of the column being rendered.
	 * @param int $user_id The ID of the user for the current row.
	 * @return string HTML content to be displayed in the custom columns.
	 */
	public function add_details_in_custom_users_list( $val, $column_name, $user_id ) {

		// Define plugin version dynamically.
		$plugin_version = cev_pro()->version;

		// Enqueue required scripts and styles for the admin user table.
		wp_enqueue_script( 'jquery-blockui' );
		wp_enqueue_style(
			'customer_email_verification_user_admin_styles',
			cev_pro()->plugin_dir_url() . 'assets/css/user-admin.css',
			array(),
			$plugin_version
		);
		wp_enqueue_script(
			'customer_email_verification_user_admin_script',
			cev_pro()->plugin_dir_url() . 'assets/js/user-admin.js',
			array( 'jquery', 'wp-util' ),
			$plugin_version,
			true
		);

		// Get user details and meta.
		$user_role = get_userdata( $user_id );
		$verified  = get_user_meta( $user_id, 'customer_email_verified', true );

		// Render content for the 'Email Verification' column.
		if ( 'cev_verified' === $column_name ) {
			if ( ! $this->is_admin_user( $user_id ) && ! $this->is_verification_skip_for_user( $user_id ) ) {
				$verified_btn_css   = ( 'true' == $verified ) ? 'display:none' : '';
				$unverified_btn_css = ( 'true' != $verified ) ? 'display:none' : '';

				$html  = '<span style="' . esc_attr( $unverified_btn_css ) . '" class="dashicons dashicons-yes cev_5 cev_verified_admin_user_action" title="' . esc_attr__( 'Verified', 'customer-email-verification' ) . '"></span>';
				$html .= '<span style="' . esc_attr( $verified_btn_css ) . '" class="dashicons dashicons-no no-border cev_unverified_admin_user_action cev_5" title="' . esc_attr__( 'Unverify', 'customer-email-verification' ) . '"></span>';

				return $html;
			}
			return '-';
		}

		// Render content for the 'Actions' column.
		if ( 'cev_action' === $column_name ) {
			if ( ! $this->is_admin_user( $user_id ) && ! $this->is_verification_skip_for_user( $user_id ) ) {
				$verify_btn_css   = ( 'true' == $verified ) ? 'display:none' : '';
				$unverify_btn_css = ( 'true' != $verified ) ? 'display:none' : '';

				$html  = '<span style="' . esc_attr( $unverify_btn_css ) . '" class="dashicons dashicons-no cev_dashicons_icon_unverify_user" id="' . esc_attr( $user_id ) . '" wp_nonce="' . esc_attr( wp_create_nonce( 	'wc_cev_email' ) ) . '"></span>';
				$html .= '<span style="' . esc_attr( $verify_btn_css ) . '" class="dashicons dashicons-yes small-yes cev_dashicons_icon_verify_user cev_10" id="' . esc_attr( $user_id ) . '" wp_nonce="' . esc_attr( 	wp_create_nonce( 'wc_cev_email' ) ) . '"></span>';
				$html .= '<span style="' . esc_attr( $verify_btn_css ) . '" class="dashicons dashicons-image-rotate cev_dashicons_icon_resend_email" id="' . esc_attr( $user_id ) . '" wp_nonce="' . esc_attr( 	wp_create_nonce( 'wc_cev_email' ) ) . '"></span>';

				return $html;
			}
		}

		return $val;
	}

	/**
	 * Displays customer email verification fields in the single user profile in wp-admin.
	 *
	 * @param WP_User $user The user object of the current profile being edited.
	 */
	public function show_cev_fields_in_single_user( $user ) {
	
		// Enqueue necessary styles and scripts with the plugin version.
		wp_enqueue_style(
			'customer_email_verification_user_admin_styles',
			cev_pro()->plugin_dir_url() . 'assets/css/user-admin.css',
			[],
			cev_pro()->version
		);
		wp_enqueue_script(
			'customer_email_verification_user_admin_script',
			cev_pro()->plugin_dir_url() . 'assets/js/user-admin.js',
			[ 'jquery', 'wp-util' ],
			cev_pro()->version,
			true
		);
	
		$user_id  = $user->ID;
		$verified = get_user_meta( $user_id, 'customer_email_verified', true );
	
		?>
	
		<table class="form-table cev-admin-menu">
			<tr>
				<th colspan="2">
					<h4 class="cev_admin_user">
						<?php esc_html_e( 'Customer verification', 'customer-email-verification' ); ?>
					</h4>
				</th>
			</tr>
			<tr>
				<th class="cev-admin-padding">
					<label><?php esc_html_e( 'Email verification status:', 'customer-email-verification' ); ?></label>
				</th>
				<td>
					<?php if ( ! $this->is_admin_user( $user_id ) && ! $this->is_verification_skip_for_user( $user_id ) ) : ?>
						<?php 
						$verified_btn_css   = ( 'true' === $verified ) ? 'display:none' : '';
						$unverified_btn_css = ( 'true' !== $verified ) ? 'display:none' : '';
						?>
						<span style="<?php echo esc_attr( $unverified_btn_css ); ?>" 
							  class="dashicons dashicons-yes cev_5 cev_verified_admin_user_action_single" 
							  title="<?php esc_attr_e( 'Verified', 'customer-email-verification' ); ?>">
						</span>
						<span style="<?php echo esc_attr( $verified_btn_css ); ?>" 
							  class="dashicons dashicons-no no-border cev_unverified_admin_user_action_single cev_5" 
							  title="<?php esc_attr_e( 'Unverify', 'customer-email-verification' ); ?>">
						</span>
					<?php else : ?>
						<?php esc_html_e( 'Admin', 'customer-email-verification' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?php if ( ! $this->is_admin_user( $user_id ) && ! $this->is_verification_skip_for_user( $user_id ) ) : ?>
						<?php 
						$verify_btn_css   = ( 'true' === $verified ) ? 'display:none' : '';
						$unverify_btn_css = ( 'true' !== $verified ) ? 'display:none' : '';
						?>
						<a style="<?php echo esc_attr( $verify_btn_css ); ?>" 
						   class="button-primary cev-admin-verify-button cev_dashicons_icon_verify_user" 
						   id="<?php echo esc_attr( $user_id ); ?>" 
						   wp_nonce="<?php echo esc_attr( wp_create_nonce( 'wc_cev_email' ) ); ?>">
							<span class="dashicons dashicons-yes cev-admin-dashicons" style="color:#ffffff; margin-right: 2px;"></span>
							<span><?php esc_html_e( 'Verify email manually', 'customer-email-verification' ); ?></span>
						</a>
						<a style="<?php echo esc_attr( $verify_btn_css ); ?>" 
						   class="button-primary cev-admin-resend-button cev_dashicons_icon_resend_email" 
						   id="<?php echo esc_attr( $user_id ); ?>" 
						   wp_nonce="<?php echo esc_attr( wp_create_nonce( 'wc_cev_email' ) ); ?>">
							<span class="dashicons dashicons-image-rotate cev-admin-dashicons cev-rotate"></span>
							<span><?php esc_html_e( 'Resend verification email', 'customer-email-verification' ); ?></span>
						</a>
						<a style="<?php echo esc_attr( $unverify_btn_css ); ?>" 
						   class="button-primary cev-admin-unverify-button cev_dashicons_icon_unverify_user" 
						   id="<?php echo esc_attr( $user_id ); ?>" 
						   wp_nonce="<?php echo esc_attr( wp_create_nonce( 'wc_cev_email' ) ); ?>">
							<span class="dashicons dashicons-no cev-admin-dashicons"></span>
							<span><?php esc_html_e( 'Un-verify email', 'customer-email-verification' ); ?></span>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		</table>
					
		<?php
	}

	/**
	 * Manually verifies or un-verifies a user's email from the wp-admin area.
	 */
	public function cev_manual_verify_user() {
		// Verify nonce and process verification/unverification.
		if (
			isset( $_GET['user_id'], $_GET['wp_nonce'] ) &&
			wp_verify_nonce( wc_clean( $_GET['wp_nonce'] ), 'wc_cev_email' )
		) {
			$user_id = wc_clean( $_GET['user_id'] );

			if ( isset( $_GET['wc_cev_confirm'] ) && 'true' === $_GET['wc_cev_confirm'] ) {
				// Mark user as verified.
				update_user_meta( $user_id, 'customer_email_verified', 'true' );

				// Trigger new account email if delayed.
				$this->trigger_delay_new_account_email( $user_id );

				// Add success notice for manual verification.
				add_action( 'admin_notices', [ $this, 'manual_cev_verify_email_success_admin' ] );
			} else {
				// Mark user as unverified.
				delete_user_meta( $user_id, 'customer_email_verified' );

				// Add notice for unverification.
				add_action( 'admin_notices', [ $this, 'manual_cev_verify_email_unverify_admin' ] );
			}
		}

		// Verify nonce and process confirmation email resending.
		if (
			isset( $_GET['user_id'], $_GET['wp_nonce'] ) &&
			wp_verify_nonce( wc_clean( $_GET['wp_nonce'] ), 'wc_cev_email_confirmation' )
		) {
			$user_id             = wc_clean( $_GET['user_id'] );
			$current_user        = get_user_by( 'id', $user_id );
			$secret_code_present = get_user_meta( $user_id, 'customer_email_verification_code', true );

			if ( empty( $secret_code_present ) ) {
				// Generate and save a secret code if not present.
				$secret_code = md5( $user_id . time() );
				update_user_meta( $user_id, 'customer_email_verification_code', $secret_code );
			}

			// Set up email sender details.
			cev_pro()->WC_customer_email_verification_email_Common->wuev_user_id = $user_id;
			cev_pro()->WC_customer_email_verification_email_Common->wuev_myaccount_page_id = $this->my_account_id;

			// Send the verification email.
			cev_pro()->WC_customer_email_verification_email_Common->code_mail_sender( $current_user->user_email );

			// Add success notice for email confirmation.
			add_action( 'admin_notices', [ $this, 'manual_confirmation_email_success_admin' ] );
		}
	}

	/**
	 * Adds a dropdown filter for user verification status on the Users listing page in wp-admin.
	 *
	 * @param string $which The position of the filter ("top" or "bottom").
	 */
	public function filter_user_by_verified( $which ) {
		if ( 'top' === $which ) {
			// Get the selected filter value for top or bottom position.
			$top_filter = isset( $_GET['customer_email_verified_top'] ) ? wc_clean( $_GET['customer_email_verified_top'] ) : '';
			$bottom_filter = isset( $_GET['customer_email_verified_bottom'] ) ? wc_clean( $_GET['customer_email_verified_bottom'] ) : '';
		
			$selected_value = ! empty( $top_filter ) ? $top_filter : $bottom_filter;

			// Determine selected options.
			$true_selected = ( 'true' === $selected_value ) ? 'selected' : '';
			$false_selected = ( 'false' === $selected_value ) ? 'selected' : '';
			?>
			<select name="customer_email_verified_<?php echo esc_attr( $which ); ?>" style="float:none; margin-left:10px;">
				<option value=""><?php esc_html_e( 'User verification', 'customer-email-verification-pro' ); ?></option>
				<option value="true" <?php echo esc_attr( $true_selected ); ?>>
					<?php esc_html_e( 'Verified', 'customer-email-verification-pro' ); ?>
				</option>
				<option value="false" <?php echo esc_attr( $false_selected ); ?>>
					<?php esc_html_e( 'Non verified', 'customer-email-verification-pro' ); ?>
				</option>
			</select>
			<?php
			// Add the filter button.
			submit_button( __( 'Filter' ), '', $which, false );
		}
	}

	/**
	 * Filters users by email verification status in the admin Users list.
	 *
	 * @param WP_User_Query $query The current user query.
	 */
	public function filter_users_by_user_by_verified_section( $query ) {
		global $pagenow;

		// Ensure we're on the admin Users page.
		if ( is_admin() && 'users.php' === $pagenow ) {
			// Get the selected filter value for top or bottom position.
			$top_filter = isset( $_GET['customer_email_verified_top'] ) ? wc_clean( $_GET['customer_email_verified_top'] ) : '';
			$bottom_filter = isset( $_GET['customer_email_verified_bottom'] ) ? wc_clean( $_GET['customer_email_verified_bottom'] ) : '';

			// Determine which filter is active.
			$selected_value = ! empty( $top_filter ) ? $top_filter : $bottom_filter;

			if ( ! empty( $selected_value ) ) {
				$meta_query = [];

				if ( 'true' === $selected_value ) {
					// Filter for verified users.
					$meta_query[] = [
						'key'     => 'customer_email_verified',
						'value'   => 'true',
						'compare' => 'LIKE',
					];
				} else {
					// Filter for non-verified users.
					$meta_query = [
						'relation' => 'AND',
						[
							'key'     => 'cev_email_verification_pin',
							'compare' => 'EXISTS',
						],
						[
							'key'     => 'customer_email_verified',
							'compare' => 'NOT EXISTS',
						],
					];
				}

				// Apply the meta query to the user query.
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Adds custom bulk actions to the Users list in the admin panel.
	 *
	 * @param array $bulk_array The existing bulk actions for users.
	 * @return array The updated bulk actions including custom actions.
	 */
	public function add_custom_bulk_actions_for_user( $bulk_array ) {
		// Add custom bulk actions.
		$bulk_array['verify_users_email'] = __( 'Verify users email', 'customer-email-verification' );
		$bulk_array['send_verification_email'] = __( 'Send verification email', 'customer-email-verification' );

		return $bulk_array;
	}

	/**
	 * Handles custom bulk actions for verifying users and sending verification emails.
	 *
	 * @param string $redirect The redirect URL after the bulk action.
	 * @param string $doaction The selected bulk action.
	 * @param array $object_ids The array of user IDs to perform the action on.
	 * @return string The updated redirect URL with action-specific query parameters.
	 */
	public function users_bulk_action_handler( $redirect, $doaction, $object_ids ) {
		// Remove unnecessary query arguments from the redirect URL.
		$redirect = remove_query_arg(
			[ 'user_id', 'wc_cev_confirm', 'wp_nonce', 'wc_cev_confirmation', 'verify_users_emails', 'send_verification_emails' ],
			$redirect
		);

		// Handle the 'verify_users_email' bulk action.
		if ( 'verify_users_email' === $doaction ) {
			foreach ( $object_ids as $user_id ) {
				update_user_meta( $user_id, 'customer_email_verified', 'true' );
			}

			// Add a query parameter to indicate the number of users verified.
			$redirect = add_query_arg( 'verify_users_emails', count( $object_ids ), $redirect );
		}

		// Handle the 'send_verification_email' bulk action.
		if ( 'send_verification_email' === $doaction ) {
			foreach ( $object_ids as $user_id ) {
				$current_user = get_user_by( 'id', $user_id );

				if ( $current_user ) {
					// Set necessary properties for sending the verification email.
					$this->user_id                         = $current_user->ID;
					$this->email_id                        = $current_user->user_email;
					$this->user_login                      = $current_user->user_login;
					$this->user_email                      = $current_user->user_email;
					cev_pro()->WC_customer_email_verification_email_Common->wuev_user_id  = $current_user->ID;
					cev_pro()->WC_customer_email_verification_email_Common->wuev_myaccount_page_id = $this->my_account_id;
					$this->is_user_created                 = true;		
					$is_secret_code_present                = get_user_meta( $this->user_id, 'customer_email_verification_code', true );

					// Generate a new secret code if it doesn't exist.
					if ( empty( $is_secret_code_present ) ) {
						$secret_code = md5( $user_id . time() );
						update_user_meta( $user_id, 'customer_email_verification_code', $secret_code );
					}

					// Send the verification email.
					cev_pro()->WC_customer_email_verification_email_Common->code_mail_sender( $current_user->user_email );
				}
			}

			// Add a query parameter to indicate the number of verification emails sent.
			$redirect = add_query_arg( 'send_verification_emails', count( $object_ids ), $redirect );
		}

		return $redirect;
	}

	/**
	 * Displays admin notices for custom bulk actions on users.
	 */
	public function user_bulk_action_notices() {
		// Handle notice for verified users.
		if ( ! empty( $_REQUEST['verify_users_emails'] ) ) {
			$verify_users_emails = absint( wc_clean( $_REQUEST['verify_users_emails'] ) );
			?>
			<div id="message" class="updated notice is-dismissible">
				<p>
				<?php
					/* translators: %s: Number of users whose verification status was updated */
					echo esc_html( sprintf( _n( 'Verification status updated for %s user.', 'Verification status updated for %s users.', $verify_users_emails, 'customer-email-verification' ), $verify_users_emails ) );
				?>
				</p>
			</div>
			<?php
		}
	
		// Handle notice for sent verification emails.
		if ( ! empty( $_REQUEST['send_verification_emails'] ) ) {
			$send_verification_emails = absint( wc_clean( $_REQUEST['send_verification_emails'] ) );
			?>
			<div id="message" class="updated notice is-dismissible">
				<p>
				<?php
					/* translators: %s: Number of users to whom verification emails were sent */
					echo esc_html( sprintf(_n( 'Verification email sent to %s user.', 'Verification email sent to %s users.', $send_verification_emails, 'customer-email-verification'), $send_verification_emails ) );
				?>
				</p>
			</div>
			<?php
		}
	}
	
	/**
	 * Handles manual user verification actions in the admin user menu.
	 */
	public function cev_manualy_user_verify_in_user_menu() {				
		
		if ( isset( $_POST['wp_nonce'] ) && wp_verify_nonce( wc_clean( $_POST['wp_nonce'] ), 'wc_cev_email' ) ) { 
		
			$user_id = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : '';
			$action_type = isset( $_POST['actin_type'] ) ? wc_clean( $_POST['actin_type'] ) : '';
			
			if ( 'unverify_user' == $action_type ) {
				delete_user_meta( $user_id, 'customer_email_verified' ); 
			}
			
			if ( 'verify_user' == $action_type ) {
				update_user_meta( $user_id, 'customer_email_verified', 'true' );
				$this->trigger_delay_new_account_email( $user_id );	
			}
			
			if ( 'resend_email' == $action_type ) {
				$current_user           = get_user_by( 'id', $user_id );
				$is_secret_code_present = get_user_meta( $user_id, 'customer_email_verification_code', true );
	
				if ( '' === $is_secret_code_present ) {
					$secret_code = md5( $user_id . time() );
					update_user_meta( $user_id, 'customer_email_verification_code', $secret_code );
				}					
				
				cev_pro()->WC_customer_email_verification_email_Common->wuev_user_id = $user_id; // WPCS: input var ok, CSRF ok.
				cev_pro()->WC_customer_email_verification_email_Common->wuev_myaccount_page_id = $this->my_account_id;
				cev_pro()->WC_customer_email_verification_email_Common->code_mail_sender( $current_user->user_email );
			}
		}
		exit;
	}

	/**
	 * Triggers the delayed new account email for a customer after verification.
	 *
	 * @param int $user_id The ID of the user to send the email to.
	 */
	public function trigger_delay_new_account_email( $user_id ) {
		// Check if the delayed email option is enabled.
		if ( '1' === get_option( 'delay_new_account_email_customer' ) ) {
			// Retrieve the WooCommerce email classes.
			$emails = WC()->mailer()->emails;

			if ( isset( $emails['WC_Email_Customer_New_Account'] ) ) {
				// Fetch the user data for the provided user ID.
				$new_customer_data = get_userdata( $user_id );

				// Get the user's password, if available.
				$user_pass = isset( $new_customer_data->user_pass ) ? $new_customer_data->user_pass : '';

				// Trigger the "New Account" email.
				$email = $emails['WC_Email_Customer_New_Account'];
				$email->trigger( $user_id, $user_pass, false );
			}
		}
	}

	/**
	 * Adds a custom class to the admin body tag.
	 *
	 * @param string $body_class Current classes applied to the admin body tag.
	 * @return string Modified classes with the custom class appended.
	 */
	public function cev_post_admin_body_class( $body_class ) {
		// Append the custom class for the plugin to the existing body classes.
		return $body_class . ' customer-email-verification-for-woocommerce';
	}
	/**
	 * Returns verification PIN code length placeholder text.
	 *
	 * @param int $codelength Default code length.
	 * @return string Placeholder text for the code length.
	 */
	public function cev_verification_code_length_callback( $codelength ) {
		// Fetch the code length option or fallback to the provided default.
		$code_length_option = get_option( 'cev_verification_code_length', $codelength );

		// Define placeholder text for each code length value.
		$code_length_texts = [
			'1' => '4-digits code',
			'2' => '6-digits code',
			'3' => '9-digits code',
		];

		// Return the corresponding placeholder text or a default message.
		return isset( $code_length_texts[ $code_length_option ] ) 
			? $code_length_texts[ $code_length_option ] 
			: 'Invalid code length';
	}
	
	/**
	 * Display success notice for manual confirmation email.
	 */
	public function manual_confirmation_email_success_admin() {
		$this->render_admin_notice( __( 'Verification email successfully sent.', 'customer-email-verification' ) );
	}

	/**
	 * Display success notice for manual user verification.
	 */
	public function manual_cev_verify_email_success_admin() {
		$this->render_admin_notice( __( 'User verified successfully.', 'customer-email-verification' ) );
	}

	/**
	 * Display notice for manual user unverification.
	 */
	public function manual_cev_verify_email_unverify_admin() {
		$this->render_admin_notice( __( 'User unverified.', 'customer-email-verification' ) );
	}

	/**
	 * Render a generic admin notice.
	 *
	 * @param string $message The message to display.
	 */
	public function render_admin_notice( $message ) {
		?>
		<div class="updated notice">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add a link to resend the verification email on the WooCommerce login form.
	 */
	public function action_woocommerce_login_form_end() {
		?>
		<p class="woocommerce-LostPassword lost_password">
			<a href="<?php echo esc_url( home_url( '?p=reset-verification-email' ) ); ?>">
				<?php esc_html_e( 'Resend verification email', 'customer-email-verification' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Check if the user is an administrator.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if the user is an administrator, false otherwise.
	 */
	public function is_admin_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		// Return false if the user doesn't exist.
		if ( ! $user ) {
			return false;
		}

		// Check if the user has the 'administrator' role.
		return in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * Check if email verification is skipped for the user based on their role.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if verification is skipped, false otherwise.
	 */
	public function is_verification_skip_for_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		// Return false if the user doesn't exist.
		if ( false === $user ) {
			return false;
		}

		// Retrieve the roles assigned to the user.
		$roles = $user->roles;

		// Get the list of roles for which verification is skipped.
		$cev_skip_verification_roles = get_option( 'cev_skip_verification_for_selected_roles', [] );

		// Check if any of the user's roles match the roles set to skip verification.
		foreach ( (array) $cev_skip_verification_roles as $role => $skip ) {
			if ( true === in_array( $role, (array) $roles, true ) && 1 === (int) $skip ) {
				return true;
			}
		}

		return false;
	}
	
}
