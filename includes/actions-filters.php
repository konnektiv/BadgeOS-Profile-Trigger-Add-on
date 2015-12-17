<?php

add_action( 'bp_before_member_body', function(){

	if (bp_displayed_user_id() != bp_loggedin_user_id())
		do_action('visit_others_profile');
} );

add_filter('badgeos_activity_triggers', function ($triggers) {
	$triggers['visit_others_profile'] = __('Visit another users profile page', 'badgeos-profile-triggert');
	return $triggers;
});