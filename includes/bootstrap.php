<?php
defined( 'ABSPATH' ) || exit;

require_once WPFV_PATH . 'includes/core/class-wpfv-autoloader.php';

WPFVariables\Core\Autoloader::register();
WPFVariables\Core\Plugin::instance()->boot();
