<?php
/*
Plugin Name: Mailgun Email Validator
Plugin URI: https://websistent.com/wordpress-plugins/mailgun-email-validator/
Description: Kick spam with an highly advanced email validation in comment forms, user registration forms and contact forms using <a href="http://blog.mailgun.com/post/free-email-validation-api-for-web-forms/" target="_blank">Mailgun's Email validation</a> service.
Author: Jesin
Version: 2.0
Requires PHP: 5.6
Author URI: https://websistent.com/
*/

if ( ! class_exists( 'Email_Validation_Mailgun' ) ) {
	class Email_Validation_Mailgun {
		private $options = NULL;
		var $slug;
		var $basename;

		public function __construct() {
			$this->options = get_option( 'jesin_mailgun_email_validator' );
			$this->basename = plugin_basename( __FILE__ );
			$this->slug = str_replace( array( basename( __FILE__ ), '/' ), '', $this->basename);

			add_action('init', array( $this, 'plugin_init') );
		}

		public function plugin_init() {

			// Load the text domain for translation
			load_plugin_textdomain( $this->slug, false, $this->slug . '/languages' );

			// Add a filter to validate email addresses with Mailgun
			add_filter( 'is_email', array( $this, 'validate_email' ) );
		}

		// Function which sends the email to Mailgun to check it
		public function validate_email($emailID) {

			global $pagenow, $wp;

			// If the format of the email itself is wrong return false without further checking
			if ( ! filter_var( $emailID, FILTER_VALIDATE_EMAIL ) ) {
				return false;
			}

			// If no API was entered don't do anything
			if ( ! isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] ) ) {
				return true;
			}

			if ( "edit.php" == $pagenow && "shop_order" == $wp->query_vars['post_type'] ) {
				return true;
			}

			// Prepare the headers for API call
			$args = array(
				'sslverify' => true,
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( "api:" . $this->options['mailgun_pubkey_api'] ) ),
			);

			//Send the email to Mailgun's email validation service
			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=" . urlencode( $emailID ), $args );

			//If there was a HTTP or connection error pass the validation so that the website visitor doesn't know anything
			if ( is_wp_error( $response ) || isset( $response['error'] ) || '200' != $response['response']['code'] ) {
				return true;
			}

			//Extract the JSON response and return the result
			$result = json_decode( $response['body'], true );
			return $result['result'] === "deliverable" ? $emailID : false;
		}
	}

	$email_validation_mailgun = new Email_Validation_Mailgun();
}

if ( is_admin() ) {
	require_once  plugin_dir_path( __FILE__ ). 'admin/class-email-validation-mg-admin.php';
}
