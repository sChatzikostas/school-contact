<?php
/**
 * Plugin Name: SchoolContact
 * Plugin URI: https://github.com/sChatzikostas/school-contact
 * Description: Secure communication between schools and families.
 * Version: 1.1.0
 * Author: Sotiris Chatzikostas
 * Author URI: https://www.linkedin.com/in/sotiris-chatzikostas/
 * License: GPL-2.0-or-later
 * Text Domain: school-contact
 */

defined( 'ABSPATH' ) || exit;

$sc21_metadata = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'SC21_VERSION', $sc21_metadata['Version'] );
define( 'SC21_OPTION', 'sc21_options' );
define( 'SC21_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SC21_REPOSITORY_URL', 'https://github.com/sChatzikostas/school-contact' );

function sc21_defaults(): array {
	return array(
		'school_name' => '',
		'admin_email' => '',
		'principal_name' => '',
		'staff'       => array(),
	);
}

function sc21_get_options(): array {
	$options = get_option( SC21_OPTION, array() );
	return wp_parse_args( is_array( $options ) ? $options : array(), sc21_defaults() );
}

function sc21_update_options( array $options ): bool {
	$options = wp_parse_args( $options, sc21_defaults() );
	$current = get_option( SC21_OPTION, array() );
	if ( is_array( $current ) && $current == $options ) {
		return true;
	}
	return update_option( SC21_OPTION, $options );
}

function sc21_categories(): array {
	return array(
		'classroom'  => __( 'Υπεύθυνοι τμημάτων', 'school-contact' ),
		'specialist' => __( 'Εκπαιδευτικοί ειδικοτήτων', 'school-contact' ),
		'other'      => __( 'Λοιπό προσωπικό', 'school-contact' ),
	);
}

function sc21_staff_addresses( array $staff ): array {
	$official = isset( $staff['official_email'] ) && is_string( $staff['official_email'] ) ? $staff['official_email'] : '';
	$alternative = isset( $staff['alternative_email'] ) && is_string( $staff['alternative_email'] ) ? $staff['alternative_email'] : '';
	$addresses = array();
	foreach ( array( $official, $alternative ) as $address ) {
		if ( is_email( $address ) ) {
			$addresses[ strtolower( sanitize_email( $address ) ) ] = sanitize_email( $address );
		}
	}
	return array_values( $addresses );
}

function sc21_valid_staff( array $staff ): bool {
	return ! empty( $staff['active'] ) && ! empty( $staff['id'] ) && ! empty( sc21_staff_addresses( $staff ) );
}

function sc21_activate(): void {
	if ( false === get_option( SC21_OPTION, false ) ) {
		add_option( SC21_OPTION, sc21_defaults(), '', false );
	}
}

function sc21_deactivate(): void {
	wp_unschedule_hook( 'sc21_cleanup_submission_claim' );
	wp_unschedule_hook( 'sc21_cleanup_expired_submission_claims' );
}

register_activation_hook( __FILE__, 'sc21_activate' );
register_deactivation_hook( __FILE__, 'sc21_deactivate' );

require_once __DIR__ . '/release-check.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/form.php';
