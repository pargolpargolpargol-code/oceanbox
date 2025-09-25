<?php
/**
 * HTML Code for Settings Tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly to prevent unauthorized access
}
?>

<section id="cev_content_settings" class="cev_tab_section">	
	<form method="post" id="cev_settings_form" action="" enctype="multipart/form-data"><?php #nonce ?>

		<div class="settings_accordion accordion_container">

			<!-- Email Verification Settings Section -->
			<div class="accordion_set">
				<div class="accordion heading cev-main-settings">
					<div class="accordion-open accordion-label first_label">
						<?php esc_html_e( 'Email verification settings', 'customer-email-verification' ); ?>						
					</div>
				</div>
				<div class="panel">
					<?php 
					// Render email verification-related settings fields
					$this->render_settings_fields( $this->get_cev_settings_data_new() );
					?>
				</div>
			</div>

			<!-- General Settings Section -->
			<div class="accordion_set">
				<div class="accordion heading cev-main-settings">
					<?php 
					// Check if login authentication is enabled
					$checked = ( get_option( 'cev_enable_login_authentication', 0 ) ) ? 'checked' : ''; 
					$disable_toggle_class = get_option( 'cev_enable_login_authentication', 0 ) ? '' : 'disable_toggle';
					?>
					<div class="accordion-open accordion-label second_label">						
						<?php esc_html_e( 'General Settings', 'customer-email-verification' ); ?>						
					</div>
				</div>
				<div class="panel">
					<?php 
					// Render general settings fields
					$this->render_settings_fields( $this->get_cev_general_settings_data() ); 
					?>
				</div>
			</div>	

			<!-- Login Authentication Section -->
			<div class="accordion_set">
				<div class="accordion heading cev-main-settings">
					<?php 
					// Re-check if login authentication is enabled (this logic is repeated, consider optimizing)
					$checked = ( get_option( 'cev_enable_login_authentication', 0 ) ) ? 'checked' : ''; 
					$disable_toggle_class = get_option( 'cev_enable_login_authentication', 0 ) ? '' : 'disable_toggle';
					?>
					<div class="accordion-open accordion-label second_label">						
						<?php esc_html_e( 'Login Authentication', 'customer-email-verification' ); ?>						
					</div>
				</div>
				<div class="panel">
					<?php 
					// Render login authentication settings fields
					$this->render_settings_fields( $this->get_cev_settings_data_login_otp() ); 
					?>
				</div>
			</div>			
		</div>

		<!-- Save Button -->
		<div class="save_btn">
			<button name="save" class="button-primary woocommerce-save-button cev_settings_save" type="submit" value="Save changes">
				<?php esc_html_e( 'Save', 'customer-email-verification' ); ?>
			</button>				
		</div>			

		<?php 
		// Security nonce field for form validation
		wp_nonce_field( 'cev_settings_form_nonce', 'cev_settings_form_nonce' ); 
		?>
		<input type="hidden" name="action" value="cev_settings_form_update">					
	</form>	
</section>
