<?php
/**
 * Read-only stable-release availability checks for the Help administration tab.
 */

defined( 'ABSPATH' ) || exit;

define( 'SC21_RELEASE_API_URL', 'https://api.github.com/repos/sChatzikostas/school-contact/releases/latest' );
define( 'SC21_RELEASE_PAGE_URL', 'https://github.com/sChatzikostas/school-contact/releases' );
define( 'SC21_RELEASE_CHECK_TRANSIENT', 'sc21_release_check_latest' );

function sc21_release_check_normalize_version( $value ): ?string {
	if ( ! is_string( $value ) ) {
		return null;
	}
	$version = trim( $value );
	if ( str_starts_with( $version, 'v' ) || str_starts_with( $version, 'V' ) ) {
		$version = substr( $version, 1 );
	}
	return preg_match( '/^(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*)){1,3}$/', $version ) ? $version : null;
}

function sc21_release_check_unavailable(): array {
	return array( 'state' => 'unavailable' );
}

function sc21_release_check_cached_release( $cached ): ?string {
	if ( ! is_array( $cached ) || 'verified' !== ( $cached['state'] ?? null ) || ! isset( $cached['version'] ) ) {
		return null;
	}
	return sc21_release_check_normalize_version( $cached['version'] );
}

function sc21_release_check_has_package( array $release, string $version ): bool {
	if ( ! isset( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
		return false;
	}
	$expected_name = 'school-contact-' . $version . '.zip';
	foreach ( $release['assets'] as $asset ) {
		if ( ! is_array( $asset ) || ! isset( $asset['name'], $asset['size'], $asset['state'] ) || ! is_string( $asset['name'] ) || ! is_int( $asset['size'] ) || ! is_string( $asset['state'] ) ) {
			continue;
		}
		if ( hash_equals( $expected_name, $asset['name'] ) && $asset['size'] > 0 && 'uploaded' === $asset['state'] ) {
			return true;
		}
	}
	return false;
}

function sc21_release_check_validate_release( $release ): ?string {
	if ( ! is_array( $release ) || true !== array_key_exists( 'draft', $release ) || true !== array_key_exists( 'prerelease', $release ) || false !== $release['draft'] || false !== $release['prerelease'] ) {
		return null;
	}
	if ( ! isset( $release['tag_name'], $release['published_at'] ) || ! is_string( $release['published_at'] ) || false === strtotime( $release['published_at'] ) ) {
		return null;
	}
	$version = sc21_release_check_normalize_version( $release['tag_name'] );
	return $version && sc21_release_check_has_package( $release, $version ) ? $version : null;
}

function sc21_release_check_store( array $value, int $expiration ): array {
	set_transient( SC21_RELEASE_CHECK_TRANSIENT, $value, $expiration );
	return $value;
}

function sc21_release_check_fetch(): array {
	$response = wp_remote_get(
		SC21_RELEASE_API_URL,
		array(
			'timeout'     => 3,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'SchoolContact release checker',
			),
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return sc21_release_check_store( sc21_release_check_unavailable(), HOUR_IN_SECONDS );
	}
	$release = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return sc21_release_check_store( sc21_release_check_unavailable(), HOUR_IN_SECONDS );
	}
	$version = sc21_release_check_validate_release( $release );
	if ( null === $version ) {
		return sc21_release_check_store( sc21_release_check_unavailable(), HOUR_IN_SECONDS );
	}
	return sc21_release_check_store( array( 'state' => 'verified', 'version' => $version ), DAY_IN_SECONDS );
}

function sc21_release_check_result(): array {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return sc21_release_check_unavailable();
	}
	$installed = sc21_release_check_normalize_version( SC21_VERSION );
	if ( null === $installed ) {
		return sc21_release_check_unavailable();
	}
	$cached = get_transient( SC21_RELEASE_CHECK_TRANSIENT );
	if ( false !== $cached ) {
		if ( is_array( $cached ) && 'unavailable' === ( $cached['state'] ?? null ) && 1 === count( $cached ) ) {
			return sc21_release_check_unavailable();
		}
		$remote = sc21_release_check_cached_release( $cached );
		if ( null === $remote ) {
			delete_transient( SC21_RELEASE_CHECK_TRANSIENT );
			return sc21_release_check_store( sc21_release_check_unavailable(), HOUR_IN_SECONDS );
		}
	} else {
		$remote = sc21_release_check_cached_release( sc21_release_check_fetch() );
		if ( null === $remote ) {
			return sc21_release_check_unavailable();
		}
	}
	$comparison = version_compare( $installed, $remote );
	return array(
		'state'   => $comparison < 0 ? 'update' : ( $comparison > 0 ? 'installed_newer' : 'current' ),
		'version' => $remote,
	);
}

function sc21_render_release_check( array $result ): void {
	$state = isset( $result['state'] ) && is_string( $result['state'] ) ? $result['state'] : 'unavailable';
	if ( 'update' === $state && isset( $result['version'] ) && is_string( $result['version'] ) ) {
		printf( '<section class="sc21-release-check is-update"><p>%1$s</p><a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></section>', esc_html( sprintf( __( 'Διαθέσιμη νέα έκδοση: %s', 'school-contact' ), $result['version'] ) ), esc_url( SC21_RELEASE_PAGE_URL ), esc_html__( 'Προβολή και λήψη νέας έκδοσης', 'school-contact' ) );
		return;
	}
	if ( 'current' === $state ) {
		echo '<p class="sc21-release-check is-current">' . esc_html__( 'Έχετε την τελευταία διαθέσιμη έκδοση.', 'school-contact' ) . '</p>';
		return;
	}
	if ( 'installed_newer' === $state ) {
		echo '<p class="sc21-release-check is-current">' . esc_html__( 'Η εγκατεστημένη έκδοση είναι νεότερη από την τελευταία επαληθευμένη δημοσιευμένη έκδοση.', 'school-contact' ) . '</p>';
		return;
	}
	printf( '<section class="sc21-release-check is-unavailable"><p>%1$s</p><a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></section>', esc_html__( 'Ο έλεγχος νέων εκδόσεων δεν είναι προσωρινά διαθέσιμος.', 'school-contact' ), esc_url( SC21_RELEASE_PAGE_URL ), esc_html__( 'Προβολή διαθέσιμων εκδόσεων', 'school-contact' ) );
}
