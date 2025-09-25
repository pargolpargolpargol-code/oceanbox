<?php
$email = 'johny@example.com';
$Try_again = __('Try again', 'customer-email-verification');

$CEV_Customizer_Options = new CEV_Customizer_Options();
$defaults = is_object($CEV_Customizer_Options) && isset($CEV_Customizer_Options->defaults) && is_array($CEV_Customizer_Options->defaults) ? $CEV_Customizer_Options->defaults : [];

$cev_widget_header_image_width = get_option('cev_widget_header_image_width', '150');
$cev_button_text_header_font_size = get_option('cev_button_text_header_font_size', '22');

$$cev_verification_hide_otp_section = get_option(
	'cev_verification_hide_otp_section',
	isset( $defaults['cev_verification_hide_otp_section'] ) ? $defaults['cev_verification_hide_otp_section'] : 'no'
);

$sample_toggle_switch_cev = get_option(
	'sample_toggle_switch_cev',
	isset( $defaults['sample_toggle_switch_cev'] ) ? $defaults['sample_toggle_switch_cev'] : '0'
);

$cev_button_text_size_widget_header = ( 'large' == $verification_popup_button_size ) ? 18 : 16 ;
$button_padding = ( 'large' == $verification_popup_button_size ) ? '15px 25px' : '12px 20px' ;

$cev_button_color_widget_header = get_option('cev_button_color_widget_header', '#212121');
$cev_button_text_color_widget_header = get_option('cev_button_text_color_widget_header', '#ffffff');
$cev_verification_hide_otp_section = get_option(
	'cev_verification_hide_otp_section',
	isset( $defaults['cev_verification_hide_otp_section'] ) ? $defaults['cev_verification_hide_otp_section'] : 'no'
);

$sample_toggle_switch_cev = get_option(
	'sample_toggle_switch_cev',
	isset( $defaults['sample_toggle_switch_cev'] ) ? $defaults['sample_toggle_switch_cev'] : '0'
);

$width = ( '1' == $sample_toggle_switch_cev ) ? '100%' : 'auto';
$verification_type = get_option('cev_verification_type');
$cev_enable_email_verification = get_option('cev_enable_email_verification');
$password_setup_link_enabled = get_option('woocommerce_registration_generate_password', 'no');
?>

<div id="otp-popup" style="display: none;" class="cev-authorization-grid__visual">
	<div class="otp_popup_inn">
		<div class="otp_content">
			<div class="cev-authorization-grid__visual">
				<div class="cev-authorization-grid__holder">
					<div class="cev-authorization-grid__inner" style="max-width: <?php echo esc_attr(get_option('cev_widget_content_width', '460')); ?>;">
						<?php
							$popup_background_color = get_option(
								'cev_verification_popup_background_color',
								isset( $defaults['cev_verification_popup_background_color'] ) ? $defaults['cev_verification_popup_background_color'] : '#ffffff'
							);
							?>
							<div class="cev-authorization" style="background: <?php echo esc_attr( $popup_background_color ); ?>;">

							<section class="cev-authorization__holder" style="text-align: <?php echo esc_attr(get_option('cev_content_align', 'center')); ?>; padding: <?php echo esc_attr(get_option('cev_widget_content_padding', '40')); ?>px;">
								<div class="back_btn">
									<svg fill="#000000" version="1.1" viewBox="-6.07 -6.07 72.87 72.87" xmlns="http://www.w3.org/2000/svg"><polygon points="0,30.365 29.737,60.105 29.737,42.733 60.731,42.729 60.731,18.001 29.737,17.999 29.737,0.625"></polygon></svg>
								</div>
								<div class="popup_image">
									<?php 
									$image = get_option('cev_verification_image', cev_pro()->plugin_dir_url() . 'assets/css/images/email-verification-icon.svg'); 
									if ( !empty($image) ) {
										?>
										<img src="<?php echo esc_url( $image ); ?>" style="width:<?php echo esc_attr($cev_widget_header_image_width); ?>px;">
									<?php } ?>
								</div>

								<div class="cev-authorization__heading">
									<span class="cev-authorization__title" style="font-size: <?php echo esc_attr($cev_button_text_header_font_size); ?>px;">
										<?php
											$heading_default = __('Verify its you.', 'customer-email-verification');
											$heading = get_option( 'cev_verification_header' ); 
											echo wp_kses_post( !empty($heading) ? $heading : $heading_default );
										?>
									</span>
									<span class="cev-authorization__description" style="text-align:<?php echo esc_attr(get_option('cev_content_align', 'center')); ?>;">
										<?php
											$message = sprintf(__('We sent verification code. To verify your email address, please check your inbox and enter the code below.', 'customer-email-verification')); 
											$message = apply_filters('cev_verification_popup_message', $message, $email);
											echo wp_kses_post($message);
										?>
									</span>
								</div>

								<?php 
								$code_length = get_option('cev_verification_code_length', 4);
								$digits = ( '2' === $code_length ) ? 6 : 4;
								?>
								<div class="otp-container">
									<?php for ( $i = 1; $i <= $digits; $i++ ) : ?>
										<input type="text" maxlength="1" class="otp-input" id="otp_input_<?php echo esc_attr($i); ?>" />
									<?php endfor; ?>
								</div>

								<div class="error_mesg"></div>	
								<input type="hidden" value="<?php echo esc_attr( $cev_enable_email_verification ); ?>" name="cev_enable_email_verification_popup" id="cev_enable_email_verification_popup">
								<input type="hidden" value="<?php echo esc_attr( $password_setup_link_enabled ); ?>" name="password_setup_link_enabled" id="password_setup_link_enabled">

								<button id="verify-otp-button" style="display: none;"><?php esc_html_e('Verify', 'customer-email-verification'); ?></button>
								<p class="resend_sucsess" style="color: green; display:none"><?php esc_html_e('Otp Send Sucsessfully', 'customer-email-verification'); ?></p>

								<footer class="cev-authorization__footer" style="text-align: <?php echo esc_attr(get_option('cev_content_align', 'center')); ?>;">
									<?php
									$footer_message = get_option(
										'cev_verification_widget_footer',
										isset( $defaults['cev_verification_widget_footer'] ) ? $defaults['cev_verification_widget_footer'] : ''
									);

									if ( method_exists(cev_pro()->WC_customer_email_verification_email_Common, 'maybe_parse_merge_tags') ) {
										$footer_message = cev_pro()->WC_customer_email_verification_email_Common->maybe_parse_merge_tags($footer_message);
									}
									echo wp_kses_post($footer_message);
									?>
								</footer>
							</section>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="cev_loading_overlay"></div>

<?php 
$cev_verification_overlay_color = get_option(
	'cev_verification_popup_overlay_background_color',
	isset( $defaults['cev_verification_popup_overlay_background_color'] ) ? $defaults['cev_verification_popup_overlay_background_color'] : '#000000'
);
?>


<style>
	.cev-authorization-grid__visual {
		background-color: <?php echo esc_attr( cev_pro()->hex2rgba($cev_verification_overlay_color) ); ?> !important;
	}
	html {
		background: none;
	}
	.customize-partial-edit-shortcut-button {
		display: none;
	}
</style>
