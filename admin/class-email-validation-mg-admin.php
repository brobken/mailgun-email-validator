<?php
if ( ! class_exists('Email_Validation_Mailgun_Admin') ) {
	class Email_Validation_Mailgun_Admin {
		private $options = NULL;

		public function __construct() {
			$this->options = get_option( 'jesin_mailgun_email_validator' );

			add_action( 'admin_menu', array($this, 'plugin_menu') );
			add_action( 'admin_init', array($this, 'plugin_settings') );
			add_action( 'admin_notices', array($this, 'admin_messages') );

			// Debugging function
			function log_to_console( $data, bool $quotes = true ) {
				$output = json_encode( $data );
				if ( $quotes ) {
					echo "<script>console.log('{ $output }' );</script>";
				} else {
					echo "<script>console.log({ $output } );</script>";
				}
			}
		}

		// Display admin notices
		public function admin_messages() {

			global $email_validation_mailgun;

			// Displayed if no API key is entered
			if (! isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] )) {
				echo '<div class="updated"><p>' . sprintf( __( 'The %s will not work until a %s is entered.', $email_validation_mailgun->slug), '<a href="' . admin_url( 'options-general.php?page=' . $email_validation_mailgun->slug ) . '">Mailgun Email Validator plugin</a>', 'Mailgun Public API key' ) . '</p></div>';
			}
		}

		public function settings_link($links) {

			global $email_validation_mailgun;

			array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=' . $email_validation_mailgun->slug ) . '">' . __( 'Settings', $email_validation_mailgun->slug ) . '</a>' );
			$links[] = '<a href="http://websistent.com/wordpress-plugins/" target="_blank" title="' . sprintf( __( 'More Plugins by %s', $email_validation_mailgun->slug ), 'Jesin' ) . '">' . __( 'More Plugins', $email_validation_mailgun->slug ) . '</a>';
			return $links;
		}

		// Hook in and create a menu
		public function plugin_menu() {

			global $email_validation_mailgun;

			add_filter( 'plugin_action_links_' . $email_validation_mailgun->basename, array( $this, 'settings_link' ) );
			$plugin_page = add_options_page( __( 'Email Validation Settings', $email_validation_mailgun->slug ), __( 'Email Validation', $email_validation_mailgun->slug ), 'manage_options', $email_validation_mailgun->slug, array( $this, 'plugin_options' ) );
			//Add AJAX to the footer of the options page
			add_action( 'admin_footer-' . $plugin_page, array( $this, 'plugin_panel_scripts' ) ); 
		}

		// Create the options page
		public function plugin_settings() {
			//AJAX to verify the API key
			add_action( 'wp_ajax_mailgun_api', array( $this, 'mailgun_api_ajax_callback' ) ); 
			//AJAX for demo email validation
			add_action( 'wp_ajax_test_email', array( $this, 'test_email_ajax_callback' ) ); 

			global $email_validation_mailgun;
			register_setting( $email_validation_mailgun->slug . '_options', 'jesin_mailgun_email_validator', array( $this, 'sanitize_input' ) );
			add_settings_section( $email_validation_mailgun->slug . '_settings', '', array( $this, 'dummy_cb' ), $email_validation_mailgun->slug );
			add_settings_field( 'mailgun_pubkey_api', 'Mailgun Public API', array( $this, 'api_field' ), $email_validation_mailgun->slug, $email_validation_mailgun->slug . '_settings', array( 'label_for' => 'mailgun_pubkey_api') ); //Public API key field
		}

		// Add AJAX to the footer
		public function plugin_panel_scripts() {
			global $email_validation_mailgun;

			require_once plugin_dir_path(__FILE__) . 'js/email-validation-mg-ajax.js';
		}

		// AJAX Callback function for validating the Public API key
		public function mailgun_api_ajax_callback() {
			global $email_validation_mailgun;

			$args = array(
				'sslverify' => false,
				'headers' => array('Authorization' => 'Basic ' . base64_encode("api:" . $_POST['api'])),
			);

			// We are using a static email here as only the API is validated
			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=foo%40mailgun.net", $args );

			// A Network error has occurred
			if ( is_wp_error( $response ) ) {
				// Display error message on general error
				echo '<span style="color:red">' . $response->get_error_message() . '</span>';
			} elseif ( isset($response->errors['http_request_failed']) ) {
				// Display errors regarding HTTP connection failure
				echo '<span style="color:red">' . __('The following error occurred when validating the key.', $email_validation_mailgun->slug) . '<br />';
				foreach ( $response->errors['http_request_failed'] as $http_errors )
					echo $http_errors;
				echo '</span>';
			} elseif ( '200' == $response['response']['code'] ) {
				// Display success message
				echo '<span style="color:green">' . __( 'API Key is valid', $email_validation_mailgun->slug ) . '</span>';
			} elseif ( '401' == $response['response']['code'] ) {
				// Invalid API as Mailgun returned 401 Unauthorized
				echo '<span style="color:red">' . sprintf( __( 'Invalid API Key. Error code: %s %s', $email_validation_mailgun->slug ), $response['response']['code'], $response['response']['message'] ) . '</span>';
			} else {
				// A HTTP error other than 401 has occurred
				echo '<span style="color:red">' . sprintf( __( 'A HTTP error occurred when validating the API. Error code: %s %s', $email_validation_mailgun->slug ), $response['response']['code'], $response['response']['message'] ) . '</span>';
				die();
			}
		}

		//AJAX Callback function for demo email validation
		public function test_email_ajax_callback() {
			global $email_validation_mailgun;

			if ( ! filter_var($_POST['email_id'], FILTER_VALIDATE_EMAIL) ) {
				echo '<span style="color:red">' . __( 'The format of the email address is invalid.', $email_validation_mailgun->slug ) . '</span>';
				die();
			}

			//Someone tries validating without entering the Public API key
			if ( ! isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] ) ) {
				echo '<span style="color:red">' . __( 'Please enter a Mailgun Public API and click Save Settings.', $email_validation_mailgun->slug ) . '</span>';
				die();
			}

			$args = array(
				'sslverify' => false,
				'headers' => array('Authorization' => 'Basic ' . base64_encode( "api:" . $this->options['mailgun_pubkey_api'] ) ),
			);
			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=" . urlencode( $_POST['email_id'] ), $args );

			if ( is_wp_error( $response ) ) {
				echo '<span style="color:red">' . $response->get_error_message() . '</span>';
				die();
			}

			$result = json_decode( $response['body'], true );

			if ( isset( $response->errors['http_request_failed'] ) ) {
				// A Network error has occurred
				echo '<span style="color:red">' . __( 'The following error occured', $email_validation_mailgun->slug ) . '<br />';
				foreach ( $response->errors['http_request_failed'] as $http_errors )
					echo $http_errors;
				echo '</span>';
			} elseif ( '200' == $response['response']['code'] ) {
				// Display success message
				if ( $result['result'] === "deliverable" ) {
					echo '<span style="color:green">' . __( 'Address is valid', $email_validation_mailgun->slug ) . '</span>';
				} else {
					echo '<span style="color:red">' . __( 'Address is invalid', $email_validation_mailgun->slug ) . '</span>';
				}
			} elseif ( '401' == $response['response']['code'] ) {
				// API key is invalid so email couldn't be verified
				echo '<span style="color:red">' . sprintf( __( 'Invalid API Key.%sError code: %s %s', $email_validation_mailgun->slug ), '<br />', $response['response']['code'], $response['response']['message'] ) . '</span>';
				die();
			}
		}

		// Validate user input in the admin panel
		public function sanitize_input($input) {
			$input['mailgun_pubkey_api'] = trim($input['mailgun_pubkey_api']);
			if ( ! empty( $input['mailgun_pubkey_api'] ) ) {
				preg_match_all( '/[0-9a-z-]/', $input['mailgun_pubkey_api'], $matches );
				$input['mailgun_pubkey_api'] = implode( $matches[0] );
			}
			return $input;
		}

		// Create the Public API field
		public function api_field() {
			global $email_validation_mailgun;

			$api_key = ( ( isset( $this->options['mailgun_pubkey_api'] ) && ! empty( $this->options['mailgun_pubkey_api'] ) ) ? $this->options['mailgun_pubkey_api'] : '' );
			echo '<input class="regular_text code" id="mailgun_pubkey_api" name="jesin_mailgun_email_validator[mailgun_pubkey_api]" size="40" type="password" value="' . $api_key . '" required />
				<input id="mailgun_api_verify" class="button button-secondary" type="button" value="Verify API Key" /><br />
				<div id="api_output"></div>
				<p class="description">' . sprintf( __( 'Enter your Mailgun Public API key which is shown at the left under %s after you %slogin%s', $email_validation_mailgun->slug ), '<strong>Account Information</strong>', '<a href="https://mailgun.com/sessions/new">', '</a>' ) . '</p>';
		}

		// HTML of the plugin options page
		public function plugin_options() {
			global $email_validation_mailgun;
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">
					<?php _e( 'Email Validation Settings', $email_validation_mailgun->slug ); ?>
				</h1>
				<p>
					<?php printf( __( 'This plugin requires a Mailgun account which is totally free. %sSignup for a free account%s', $email_validation_mailgun->slug ), '<a href="https://mailgun.com/signup" target="_blank">', '</a>' ); ?>
				</p>
				<form method="post" action="options.php">
					<?php
					settings_fields( $email_validation_mailgun->slug . '_options' );
					do_settings_sections( $email_validation_mailgun->slug );
					submit_button();
					?>
				</form>
				<?php if ( isset( $this->options['mailgun_pubkey_api'] ) && ! empty( $this->options['mailgun_pubkey_api'] ) ) : ?>
				<div>
					<h2 class="title">
						<?php _e( 'Email Validation Demo', $email_validation_mailgun->slug ); ?>
					</h2>
					<p>
						<?php _e( 'You can use this form to see how mailgun validates email addresses.', $email_validation_mailgun->slug ); ?>
					</p>
					<label for="sample_email">Email:</label><input style="margin-left: 20px" class="regular_text code" type="text" id="sample_email" size="40" />
					<input type="button" class="button button-secondary" id="validate_email" value="Validate Email" />
					<div id="email_output" style="font-size:20px;padding:10px 0 0 50px"></div>
					<div>
						<p style="font-size:24px"><?php printf( __( 'If you find this plugin useful please consider giving it a %sfive star%s rating.', $email_validation_mailgun->slug ), '<a href="http://wordpress.org/support/view/plugin-reviews/' . $email_validation_mailgun->slug . '?rate=5#postform" target="_blank">', '</a>' ); ?></p>
					</div>
				<?php endif; ?>
				</div>
			</div>
			<?php
		}

		public function dummy_cb() {
			//Empty callback for the add_settings_section() function
		}
	}

	$email_validation_mailgun_admin = new Email_Validation_Mailgun_Admin();
}
