<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://efraim.cat
 * @since      1.0.0
 *
 * @package    Nicappcrono
 * @subpackage Nicappcrono/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package Nicappcrono
 * @subpackage Nicappcrono/public
 * @author Efraim Bayarri <efraim@efraim.cat>
 */
class Nicappcrono_Public
{

    /**
     * The ID of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name
     *            The name of the plugin.
     * @param string $version
     *            The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_shortcode('NicappAuth', array(
            $this,
            'NicappAuthShortcode'
        ));
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/nicappcrono-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/nicappcrono-public.js', array(
            'jquery'
        ), $this->version, false);
    }

    /**
     * Authorization shortcode
     *
     * @since 1.0.0
     */
    public function NicappAuthShortcode($atts, $content = "")
    {
        if (strlen(get_option($this->plugin_name . '_clientId')) < 25)
            return;
        if (! get_post_status(get_option($this->plugin_name . '_AuthorizationPageId')))
            return;
        $redirect_uri = get_permalink(get_option($this->plugin_name . '_AuthorizationPageId'));
        if (isset($_POST['access_token'])) {
            $args = array(
                'post_type' => 'nicappcronocalendars',
                'meta_query' => array(
                    array(
                        'key' => $this->plugin_name . '_calendarID',
                        'value' => sanitize_text_field($_POST['cal'][0]),
                        'compare' => 'LIKE'
                    )
                )
            );
            query_posts($args);
            // The Loop
            $exist_calendar = false;
            while (have_posts()) :
                the_post();
                $exist_calendar = true;
            endwhile
            ;
            // Reset Query
            wp_reset_query();
            if (! $exist_calendar) {
                foreach ($_POST['calendar_id'] as $key => $calID) {
                    if ($calID == $_POST['cal'][0]) {
                        $calName = sanitize_text_field($_POST['calendar_name'][$key]);
                        $proName = sanitize_text_field($_POST['profile_name'][$key]);
                        $proID = sanitize_text_field($_POST['profile_id'][$key]);
                        $provName = sanitize_text_field($_POST['provider_name'][$key]);
                    }
                }
                $newCalendar = wp_insert_post(array(
                    'post_type' => 'nicappcronocalendars',
                    'post_title' => $proName . '-' . $calName,
                    'post_content' => '',
                    'post_status' => 'publish'
                ));
                update_post_meta($newCalendar, $this->plugin_name . '_calendarID', sanitize_text_field($_POST['cal'][0]));
                update_post_meta($newCalendar, $this->plugin_name . '_calendarName', $calName);
                update_post_meta($newCalendar, $this->plugin_name . '_AccessToken', sanitize_text_field($_POST['access_token']));
                update_post_meta($newCalendar, $this->plugin_name . '_RefreshToken', sanitize_text_field($_POST['refresh_token']));
                update_post_meta($newCalendar, $this->plugin_name . '_ProfileName', $proName);
                update_post_meta($newCalendar, $this->plugin_name . '_ProfileID', $proID);
                update_post_meta($newCalendar, $this->plugin_name . '_ProviderID', $provName);
            }
            // Client Output
            ?>
<div class="wrap">
	<div class="nicappcrono-auth-container-goodbye">
		<h2><?php _e( 'Authorization Processed', $this->plugin_name ); ?></h2>
		<p><?php _e( 'Thank you for authorizing access to your calendar.', $this->plugin_name ); ?></p>
		<p><?php ( $exist_calendar ) ? _e( 'Your calendar was already authorized.', $this->plugin_name ) : '' ; ?></p>
		<p><?php _e( 'Your inputs have been saved.', $this->plugin_name ); ?></p>
	</div>
</div>
<?php
        } elseif (! isset($_GET['code']) && ! isset($_POST['access_token'])) {
            $params = array(
                "client_id" => get_option($this->plugin_name . '_clientId')
            );
            if (get_option($this->plugin_name . '_DataCenter'))
                $params["data_center"] = 'de';
            $cronofy = new Cronofy\Cronofy($params);
            $auth = $cronofy->getAuthorizationURL(array(
                'redirect_uri' => $redirect_uri,
                'scope' => array(
                    'read_account',
                    'list_calendars',
                    'read_events',
                    'create_event',
                    'delete_event'
                )
            ));
            header('location: ' . $auth);
            exit();
        } else {
            $params = array(
                "client_id" => get_option($this->plugin_name . '_clientId'),
                "client_secret" => get_option($this->plugin_name . '_clientSecret')
            );
            if (get_option($this->plugin_name . '_DataCenter'))
                $params["data_center"] = 'de';
            $cronofy = new Cronofy\Cronofy($params);
            $cronofy->requestToken(array(
                'code' => sanitize_text_field($_GET['code']),
                'redirect_uri' => $redirect_uri
            ));
            $obj = json_decode(json_encode($cronofy));

            $params = array(
                "client_id" => get_option($this->plugin_name . '_clientId'),
                "client_secret" => get_option($this->plugin_name . '_clientSecret'),
                "access_token" => $obj->accessToken,
                "refresh_token" => $obj->refreshToken
            );
            if (get_option($this->plugin_name . '_DataCenter'))
                $params["data_center"] = 'de';
            $calendar = new Cronofy\Cronofy($params);
            $calendar->refreshToken();
            $calendars = $calendar->listCalendars();
            ?>
<div class="wrap">
	<div class="nicappcrono-auth-container">
		<h2><?php _e( 'Calendars', $this->plugin_name ); ?></h2>
		<p><?php _e( 'Please choose the calendar you wish to share', $this->plugin_name ); ?></p>
		<form action="" method="post">
			<table>
							<?php foreach( $calendars['calendars'] as $entry ){ ?>
								<tr>
					<td><input type="radio" name="cal[]"
						value="<?php esc_html_e( $entry['calendar_id'] ); ?>"> <input
						type="hidden" name="calendar_id[]"
						value="<?php esc_html_e( $entry['calendar_id'] ); ?>"> <input
						type="hidden" name="calendar_name[]"
						value="<?php esc_html_e( $entry['calendar_name'] ); ?>"> <input
						type="hidden" name="profile_name[]"
						value="<?php esc_html_e( $entry['profile_name'] ); ?>"> <input
						type="hidden" name="profile_id[]"
						value="<?php esc_html_e( $entry['profile_id'] ); ?>"> <input
						type="hidden" name="provider_name[]"
						value="<?php esc_html_e( $entry['provider_name'] ); ?>"></td>
					<td>
										<?php esc_html_e( $entry['calendar_name'] ); ?>
									</td>
				</tr>
							<?php }?>
						</table>
			<input type="hidden" name="access_token"
				value="<?php esc_html_e( $obj->accessToken ); ?>"> <input
				type="hidden" name="refresh_token"
				value="<?php esc_html_e( $obj->refreshToken ); ?>">
			<div class="nicappcrono-send-calendar">
				<input type="submit"
					value="<?php _e( 'Send', $this->plugin_name ); ?>">
			</div>
		</form>
	</div>
</div>
<?php
        }
    }
}
