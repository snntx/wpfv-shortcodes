<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpfv_variables' );
delete_option( 'wpfv_settings' );
