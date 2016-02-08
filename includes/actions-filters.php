<?php
/**
 * Version: 0.0.1
 * Author: Konnektiv
 * Author URI: http://konnektiv.de/
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class BadgeOS_Profile_Trigger_Rules {

	/**
	 * @var BadgeOS_Profile_Trigger_Rules
	 */
	private static $instance;

	/**
	 * Main BadgeOS_Profile_Trigger_Rules Instance
	 *
	 * Insures that only one instance of BadgeOS_Profile_Trigger_Rules exists in memory at
	 * any one time. Also prevents needing to define globals all over the place.
	 *
	 * @since BadgeOS_Profile_Trigger_Rules (0.0.1)
	 *
	 * @staticvar array $instance
	 *
	 * @return BadgeOS_Profile_Trigger_Rules
	 */
	public static function instance( ) {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new BadgeOS_Profile_Trigger_Rules;
			self::$instance->setup_filters();
			self::$instance->setup_actions();
		}

		return self::$instance;
	}

	/**
	 * A dummy constructor to prevent loading more than one instance
	 *
	 * @since BadgeOS_Profile_Trigger_Rules (0.0.1)
	 */
	private function __construct() { /* Do nothing here */
	}


	/**
	 * Setup the filters
	 *
	 * @since BadgeOS_Profile_Trigger_Rules (0.0.1)
	 * @access private
	 *
	 * @uses remove_filter() To remove various filters
	 * @uses add_filter() To add various filters
	 */
	private function setup_filters() {
		add_filter( 'badgeos_activity_triggers', array($this, 'badgeos_triggers'));
    }

	/**
	 * Setup the actions
	 *
	 * @since BadgeOS_Profile_Trigger_Rules (0.0.1)
	 * @access private
	 *
	 * @uses remove_action() To remove various actions
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		add_action( 'bp_before_member_body', array( $this, 'maybe_trigger_visit_profile') );

        add_action( 'xprofile_data_after_save', array( $this, 'maybe_trigger_profile_complete') );

        add_action( 'xprofile_avatar_uploaded', array( $this, 'maybe_trigger_profile_complete') );
	}

    public function maybe_trigger_visit_profile() {
        if (bp_displayed_user_id() != bp_loggedin_user_id())
            do_action('visit_others_profile');
    }

    public function maybe_trigger_profile_complete($profile_data) {
        $groups = bp_xprofile_get_groups(array(
            'hide_empty_groups'  => true,
            'hide_empty_fields'  => false,
            'fetch_fields'       => true,
            'fetch_field_data'   => true,
        ));

        // see if all fields in all groups have data
        $complete = true;
        foreach($groups as $group){
            foreach($group->fields as $field){
                // Empty fields may contain a serialized empty array.
                $maybe_value = maybe_unserialize( $profile_data && $field->id == $profile_data->field_id?$profile_data->value:$field->data->value );

                if ( ( empty( $maybe_value ) && '0' != $maybe_value ) ) {
                    $complete = false;
                    break;
                }
            }
        }

        // check if user uploaded a profile picture or has a gravatar
        $complete &= $this->current_user_has_avatar();

        if ($complete)
            do_action('bp_profile_completed');
    }

    public function filter_default_gravatar($default_grav) {
        return 404;
    }

    public function filter_default_avatar($default_avatar) {
        return false;
    }

	function badgeos_triggers($triggers) {
		$triggers['visit_others_profile'] = __('Visit another users profile page', 'badgeos-profile-trigger');
        $triggers['bp_profile_completed'] = __('The user has completed their profile', 'badgeos-profile-trigger');
		return $triggers;
	}

    function current_user_has_avatar() {
        $current_user = wp_get_current_user();

        add_filter('bp_core_mysteryman_src', array($this, 'filter_default_gravatar'));
        add_filter('bp_core_default_avatar_user', array($this, 'filter_default_avatar'));


        $avatar = null;

        // first try w/o gravatar
        foreach(array(true, false) as $no_grav){
            $avatar = bp_core_fetch_avatar(array(
                'item_id'	=> $current_user->ID,
                'object'	=> 'user',
                'email'		=> $current_user->user_email,
                'html'		=> false,
                'no_grav'	=> $no_grav,
            ));

            if ($avatar && $no_grav) break;
            $avatar = html_entity_decode($avatar);

            // check if gravatar exists
            if (!$no_grav) {
                $headers = get_headers((is_ssl()?'https:':'http:') . $avatar);
                if (substr($headers[0], 9, 3) === "404")
                    $avatar = null;
            }
        }

        remove_filter('bp_core_mysteryman_src', array($this, 'filter_default_gravatar'));
        remove_filter('bp_core_default_avatar_user', array($this, 'filter_default_avatar'));

        return $avatar != null;
    }
}

BadgeOS_Profile_Trigger_Rules::instance();