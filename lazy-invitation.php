<?php
/*
Plugin Name: BAW Slack Lazy invitation
Description: Slack Lazy Invitation lets you auto invite anyone to your Slack Group.
Author: Julio Potier
Author URI: http://wp-rocket.me
Plugin URI: http://boiteaweb.fr/?p=8611
Version: 1.1
Licence: GPLv2
Domain: bawsi
*/

define( 'BAWSI_VERSION', '1.1' );

/**
 * Load plugin textdomain.
 *
 * @since 1.0
 */
add_action( 'plugins_loaded', 'bawsi_load_textdomain' );
function bawsi_load_textdomain() {
	load_plugin_textdomain( 'bawsi', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' ); 
}

/*
* Prints the form, do the request or print messages
*
* @since 1.0
*/
add_action( 'login_form_slack-invitation', 'bawsi_do_invit' );
function bawsi_do_invit() {
	$bawsi_options = get_site_option( 'bawsi' );
	$message = '';
	if ( ! isset( $bawsi_options['groupname'] ) || false == trim( $bawsi_options['groupname'] ) ) {
		$message .= '<p class="message">' . __( '<b>ERROR</b> GroupName is not set!', 'bawsi' ) . '</p><br>';
		$response = true;
	}
	if ( ! isset( $bawsi_options['token'] ) || false == trim( $bawsi_options['token'] ) ) {
		$message .= '<p class="message">' . __( '<b>ERROR</b> Token is not set!', 'bawsi' ) . '</p>';
		$response = true;
	}
	if ( empty( $message ) ) {
		$message = '<p class="message">';
		$message .= sprintf( __( 'Enter your email address to join the <a href="http://%1$s.slack.com"><b>%1$s</b></a> group on Slack.', 'bawsi' ), $bawsi_options['groupname'] );
		$message .= '</p>';
	}
	if ( ! empty( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
		if ( class_exists( 'ReCAPTCHAPlugin' ) ) {
			$recaptcha = new ReCAPTCHAPlugin('recaptcha_options');
			$errors = new WP_Error();
			$errors = $recaptcha->validate_recaptcha_response( $errors );
			if ( count( $errors->errors ) ) {
				wp_die( 'Captcha Error' );
			}
		} elseif ( function_exists( 'gglcptch_login_check' ) ) {
			global $gglcptch_options;
			$privatekey = $gglcptch_options['private_key'];
			require_once( WP_PLUGIN_DIR . '/google-captcha/lib_v2/recaptchalib.php' );
			$reCaptcha = new ReCaptcha( $privatekey );
			$gglcptch_g_recaptcha_response = isset( $_POST["g-recaptcha-response"] ) ? $_POST["g-recaptcha-response"] : '';
			$resp = $reCaptcha->verifyResponse( $_SERVER["REMOTE_ADDR"], $gglcptch_g_recaptcha_response );
			if ( $resp != null && ! $resp->success ) {
				wp_die( 'Captcha Error' );
			}
		}
		$data = array( 
			'email' => $_POST['email'], 
			'channels' => '',
			'first_name' => '', 
			'token' => $bawsi_options['token'], 
			'set_active' => 'true',
			'_attempts' => '1',
		);
		$slack_url = esc_url( 'https://' . $bawsi_options['groupname'] .'.slack.com' );
		$dom = '<p class="message" style="min-height:64px"><img src="https://slack-assets2.s3-us-west-2.amazonaws.com/10068/img/slackbot_192.png" style="float:left;height:64px;width=64px" heigt="64" width="64"> ';
		$response = wp_remote_post( $slack_url . '/api/users.admin.invite?t=1', array( 'body' => $data ) );
		if( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$return = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $return->ok ) {
				$message = $dom . sprintf( __( 'Invitation launched, thank you.<br>See you soon on <a href="%1$s">%2$s</a>.<br><i>- Slackbot</i>', 'bawsi' ), $slack_url, esc_html( $bawsi_options['groupname'] ) );
			} 
			if ( isset( $return->error ) ) {
				switch( $return->error ) {
					case 'already_in_team' :
						$message = $dom . __( 'You are already in this team!', 'bawsi' );
					break;
					case 'already_invited' :
					case 'sent_recently' :
						$message = $dom . __( 'You have already been invited in this team!', 'bawsi' );
					break;
					default:
						$message = $dom . _( 'Unknow error.', 'bawsi' );
					break;
				}
			}
		} else {
			$message = $dom . __( 'Unknow error.', 'bawsi' );
		}
		$message .= '</p>';
	}

	login_header( __( 'SlackBot Invitation', 'bawsi' ), $message );
	if ( ! isset( $response ) ) {
	?>
		<style>
		.message{}
		</style>
		<form action="" method="post">
			<p><?php _e( 'Email: ', 'bawsi' ); ?><input type="text" name="email" value="" title="<?php esc_attr_e( 'Email address', 'bawsi' ); ?>" /></p>
			<?php do_action( 'slack-invitation-before-submit', '' ); ?>
			<p><input type="submit" value="<?php esc_attr_e( 'Get an invitation', 'bawsi' ); ?>" name="submit" id="submit" class="button button-primary"/></p>
		</form>
	<?php
	} elseif ( true !== $response ) {
	?>
		<p id="backtoblog"><a href="<?php echo site_url( 'wp-login.php?action=slack-invitation' ); ?>"><?php _e( 'Ask for another invitation?', 'bawsi' ); ?></a></p>
	<?php 
	}
	login_footer( 'email' );
	die();
}

/*
* Add the default option
*
* @since 1.0
*/
register_activation_hook( __FILE__, 'bawsi_activation' );
function bawsi_activation() {
	if ( ! get_option( 'bawsi' ) ) {
		add_option( 'bawsi', 
			array(	'groupname' => false, 
					'token' => false,
				), 
		'', 'no' );
	}
}

/*
* Remove the option
*
* @since 1.0
*/
register_uninstall_hook( __FILE__, 'bawsi_uninstall' );
function bawsi_uninstall() {
	delete_option( 'bawsi' );
}

/*
* Add the menu
*
* @since 1.0
*/
add_action( 'admin_menu', 'bawsi_setting_menu' );
function bawsi_setting_menu() {
	add_options_page( 'Slack Invit', 'Slack Invit', 'manage_options', 'slackinvit_settings', 'slackinvit_settings_page' );
	register_setting( 'bawsi_settings', 'bawsi' );
}

/*
* Settings page callback
*
* @since 1.0
*/
function slackinvit_settings_page() {
	add_settings_section( 'bawsi_settings_page', __( 'General', 'bawsi' ), 'bawsi_info', 'bawsi_settings' );
		add_settings_field( 'bawsi_field_groupname', __( 'GroupName?', 'bawsi' ), 'bawsi_field_groupname', 'bawsi_settings', 'bawsi_settings_page' );
		add_settings_field( 'bawsi_field_token', __( 'Token?', 'bawsi' ), 'bawsi_field_token', 'bawsi_settings', 'bawsi_settings_page' );
?>
	<div class="wrap">
		<h2>Slack Lazy Invitation <small>v<?php echo BAWSI_VERSION; ?></small></h2>

		<form action="options.php" method="post">
			<?php settings_fields( 'bawsi_settings' ); ?>
			<?php do_settings_sections( 'bawsi_settings' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
<?php
}

/*
* Settings section callback
*
* @since 1.0
*/
function bawsi_info() {
	_e( '<p>Remember that your username will be used for the invitation mail.</p>', 'bawsi' );
	printf( __( '<p>Here comes your %s to share!</p>', '' ), '<a href="' . site_url( 'wp-login.php?action=slack-invitation' ) . '">' . __( 'Invitation Page', 'bawsi' ) . '</a>' );
}

/*
* Print the groupname input
*
* @since 1.0
*/
function bawsi_field_groupname() {
	$bawsi_options = get_option( 'bawsi' );
	?>
	<label><input type="text" required="required" name="bawsi[groupname]" value="<?php echo esc_attr( $bawsi_options['groupname'] ); ?>"></label>
	<p class="description">
	<?php _e( 'This is the name of your slack group.', 'bawsi' ); ?>
	</p>
	<?php
}

/*
* Print the token input + bookmarklet
*
* @since 1.0
*/
function bawsi_field_token() {
	$bawsi_options = get_option( 'bawsi' );
	?>
	<label><input type="text" required="required" name="bawsi[token]" value="<?php echo esc_attr( $bawsi_options['token'] ); ?>" size="50"></label>
	<p class="description">
	<?php _e( 'This is the security token of your slack invitation group.', 'bawsi' ); ?>
	</p>
	<br>
	<p class="description">
		<?php _e( 'To find your token you have to use the bookmarklet below (drag/drop in your brower toolbar) on your invitation page like <code>https://YOURGROUP.slack.com/admin/invites</code>', 'bawsi' ); ?>
	</p>
	<p><a class="button button-small button-secondary" href="javascript:prompt('Slack Invit API Token', boot_data.api_token);" onclick="alert('<?php echo esc_js( __( 'Drag/drop me in our browser toolbar before!', 'bawsi' ) ); ?>');return false;"><?php _e( 'SlackInvit Token Api', 'bawsi' ); ?></a></p>
	<p class="description">
		<?php _e( 'Why do i have to do that?', 'bawsi' ); ?><br>
		<?php _e( 'Because this is the only available token to auto invite people on your Slack.', 'bawsi' ); ?>
	</p>
<?php
}

/*
* Add the settings links on plugins page
*
* @since 1.0
*/
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bawsi_settings_action_links' );
function bawsi_settings_action_links( $links ) {
	array_unshift( $links, '<a href="' . site_url( 'wp-login.php?action=slack-invitation' ) . '">' . __( 'Invitation Page', 'bawsi' ) . '</a>' );
	array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=slackinvit_settings' ) . '">' . __( 'Settings' ) . '</a>' );
	return $links;
}

/*
* Add the slug into sf move login
*
* @since 1.0
*/
add_filter( 'sfml_additional_slugs', 'bawsi_slackinvit_slug' );
function bawsi_slackinvit_slug( $slugs ) {
	$slugs['slack-invitation'] = 'SlackInvit';
	return $slugs;
}


add_action( 'slack-invitation-before-submit', 'bawsi_captcha_support', 9 );
function bawsi_captcha_support() {
	if ( class_exists( 'ReCAPTCHAPlugin' ) ) {
		$recaptcha = new ReCAPTCHAPlugin( 'recaptcha_options' );
		echo '<p>' . $recaptcha->get_recaptcha_html() . '</p>';
	} elseif( function_exists( 'gglcptch_display' ) ) {
		echo '<p>' . gglcptch_display() . '</p>';
	}
}