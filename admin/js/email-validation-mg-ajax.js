<script type="text/javascript">
jQuery(document).ready(
	jQuery('#mailgun_api_verify').click (function($) 
	{
		if (jQuery.trim(jQuery('#mailgun_pubkey_api').val()).length == 0) {
			jQuery('#api_output').html('<?php _e( 'This field cannot be empty', $email_validation_mailgun->slug ); ?>');
			return;
		}

		var data = {
			action: 'mailgun_api',
			api: jQuery('#mailgun_pubkey_api').val()
		};

		jQuery('#api_output').html('<?php _e( 'Checking', $email_validation_mailgun->slug ); ?>...');
		jQuery('#api_output').css("cursor","wait");
		jQuery('#mailgun_api_verify').attr("disabled","disabled");
		jQuery.post(ajaxurl, data, function(response) {
			jQuery('#api_output').html(response);
			jQuery('#api_output').css("cursor","default");
			jQuery('#mailgun_api_verify').removeAttr("disabled");
		}
		);
	}
));

jQuery(document).ready(
	jQuery('#validate_email').click (function($)
	{
		if (jQuery.trim(jQuery('#sample_email').val()).length == 0) {
			jQuery('#email_output').html('<?php _e( 'Please enter an email address to validate', $email_validation_mailgun->slug ); ?>');
			return;
		}

		var data = {
			action: 'test_email',
			email_id: jQuery('#sample_email').val()
		};
		jQuery('#email_output').html('<?php _e( 'Checking', $email_validation_mailgun->slug ); ?>...');
		jQuery('#email_output').css("cursor","wait");
		jQuery('#validate_email').attr("disabled","disabled");
		jQuery.post(ajaxurl, data, function(response) {
			jQuery('#email_output').html(response);
			jQuery('#email_output').css("cursor","default");
			jQuery('#validate_email').removeAttr("disabled");
		}
		);
	}
));
</script>