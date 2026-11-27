<?php

defined( 'ABSPATH' ) || exit;

add_shortcode( 'school_communication', 'sc21_render_form' );
add_action( 'wp_enqueue_scripts', 'sc21_public_assets' );
add_action( 'template_redirect', 'sc21_handle_public_submission', 1 );
add_action( 'init', 'sc21_ensure_submission_claim_cleanup' );
add_action( 'sc21_cleanup_expired_submission_claims', 'sc21_cleanup_expired_submission_claims' );

function sc21_public_assets(): void {
	wp_register_style( 'sc21-public', SC21_PLUGIN_URL . 'assets/style.css', array(), (string) filemtime( __DIR__ . '/assets/style.css' ) );
	wp_register_script( 'sc21-public', SC21_PLUGIN_URL . 'assets/public.js', array(), (string) filemtime( __DIR__ . '/assets/public.js' ), true );
}

function sc21_text_length( string $value ): int {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
}

function sc21_public_recipients(): array {
	$options = sc21_get_options();
	$groups  = array();
	$admin   = isset( $options['admin_email'] ) && is_string( $options['admin_email'] ) ? sanitize_email( $options['admin_email'] ) : '';
	if ( is_email( $admin ) ) {
		$groups['administration'] = array(
			'label' => __( 'Διεύθυνση', 'school-contact' ),
			'items' => array( array( 'id' => 'administration', 'label' => '' === trim( (string) $options['principal_name'] ) ? __( 'Διεύθυνση σχολείου', 'school-contact' ) : __( 'Διεύθυνση σχολείου', 'school-contact' ) . ' - ' . sanitize_text_field( $options['principal_name'] ), 'addresses' => array( $admin ) ) ),
		);
	}

	foreach ( sc21_categories() as $category => $group_label ) {
		$items = array();
		foreach ( $options['staff'] as $staff ) {
			if ( ! is_array( $staff ) || $category !== ( $staff['category'] ?? '' ) || ! sc21_valid_staff( $staff ) ) {
				continue;
			}
			$name      = (string) $staff['full_name'];
			$specialty = trim( (string) ( $staff['specialty'] ?? '' ) );
			$classroom = trim( (string) ( $staff['classroom'] ?? '' ) );
			$prefix    = 'classroom' === $category ? $classroom : $specialty;
			$items[]   = array(
				'id'    => 'staff:' . $staff['id'],
				'label' => '' === $prefix ? $name : $prefix . ' - ' . $name,
				'addresses' => sc21_staff_addresses( $staff ),
				'sort'  => strtolower( $prefix . '|' . $name ),
			);
		}
		usort( $items, static fn( array $a, array $b ): int => strcmp( $a['sort'], $b['sort'] ) );
		foreach ( $items as &$item ) {
			unset( $item['sort'] );
		}
		unset( $item );
		if ( $items ) {
			$groups[ $category ] = array( 'label' => $group_label, 'items' => $items );
		}
	}

	return $groups;
}

function sc21_recipient_by_id( string $id ): ?array {
	foreach ( sc21_public_recipients() as $group ) {
		foreach ( $group['items'] as $recipient ) {
			if ( hash_equals( $recipient['id'], $id ) ) {
				return $recipient;
			}
		}
	}

	return null;
}

function sc21_public_post_string( string $key ): ?string {
	if ( ! array_key_exists( $key, $_POST ) || ! is_string( $_POST[ $key ] ) ) {
		return null;
	}
	return wp_unslash( $_POST[ $key ] );
}

function sc21_rate_key( string $window ): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	return 'sc21_rate_' . $window . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 24 );
}

function sc21_record_public_attempt(): bool {
	$short_key = sc21_rate_key( '5m' );
	$hour_key  = sc21_rate_key( '1h' );
	$short     = (int) get_transient( $short_key );
	$hour      = (int) get_transient( $hour_key );
	if ( $short >= 3 || $hour >= 10 ) {
		return false;
	}
	set_transient( $short_key, $short + 1, 5 * MINUTE_IN_SECONDS );
	set_transient( $hour_key, $hour + 1, HOUR_IN_SECONDS );
	return true;
}

function sc21_submission_key( string $id ): string {
	return 'sc21_submission_' . substr( hash_hmac( 'sha256', $id, wp_salt( 'nonce' ) ), 0, 24 );
}

function sc21_submission_token_action( string $id, string $issued ): string {
	return 'sc21_submission_token_' . $id . '_' . $issued;
}

function sc21_submission_claim_expires_at( int $issued_at ): int {
	return $issued_at + HOUR_IN_SECONDS + 5 * MINUTE_IN_SECONDS;
}

function sc21_claim_submission( string $id, int $issued_at ): bool {
	global $wpdb;
	$key        = sc21_submission_key( $id );
	$expires_at = sc21_submission_claim_expires_at( $issued_at );
	$query      = $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
		$key,
		(string) $expires_at,
		'off'
	);
	$inserted = $wpdb->query( $query );
	if ( 1 !== $inserted ) {
		return false;
	}
	sc21_maybe_cleanup_expired_submission_claims();
	return true;
}

function sc21_ensure_submission_claim_cleanup(): void {
	$cron = _get_cron_array();
	$has_legacy_claim_cleanup = false;
	foreach ( $cron as $events ) {
		if ( isset( $events['sc21_cleanup_submission_claim'] ) ) {
			$has_legacy_claim_cleanup = true;
			break;
		}
	}
	if ( $has_legacy_claim_cleanup ) {
		wp_unschedule_hook( 'sc21_cleanup_submission_claim' );
	}
	if ( ! wp_next_scheduled( 'sc21_cleanup_expired_submission_claims' ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'sc21_cleanup_expired_submission_claims' );
	}
}

function sc21_maybe_cleanup_expired_submission_claims(): void {
	if ( false === get_transient( 'sc21_claim_cleanup_due' ) ) {
		set_transient( 'sc21_claim_cleanup_due', 1, 5 * MINUTE_IN_SECONDS );
		sc21_cleanup_expired_submission_claims();
	}
}

function sc21_cleanup_expired_submission_claims(): void {
	global $wpdb;
	$now  = time();
	$like = $wpdb->esc_like( 'sc21_submission_' ) . '%';
	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value REGEXP '^[0-9]+$' AND CAST(option_value AS UNSIGNED) < %d ORDER BY option_id ASC LIMIT 100",
			$like,
			$now
		)
	);
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $key ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value REGEXP '^[0-9]+$' AND CAST(option_value AS UNSIGNED) < %d",
				$key,
				$now
			)
		);
	}
}

function sc21_public_error( string $message, array $values = array() ): array {
	return array( 'ok' => false, 'message' => $message, 'values' => $values );
}

function sc21_process_public_submission(): ?array {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['sc21_public_submit'] ) ) {
		return null;
	}
	if ( ! sc21_record_public_attempt() ) {
		return sc21_public_error( __( 'Έγιναν πολλές προσπάθειες. Παρακαλούμε περιμένετε λίγα λεπτά και δοκιμάστε ξανά.', 'school-contact' ) );
	}

	$nonce = sc21_public_post_string( 'sc21_nonce' );
	if ( null === $nonce || ! wp_verify_nonce( $nonce, 'sc21_public_form' ) ) {
		return sc21_public_error( __( 'Η φόρμα δεν ήταν έγκυρη. Παρακαλούμε δοκιμάστε ξανά.', 'school-contact' ) );
	}
	$honeypot = sc21_public_post_string( 'sc21_website' );
	if ( null === $honeypot || '' !== trim( $honeypot ) ) {
		return sc21_public_error( __( 'Δεν ήταν δυνατή η αποστολή του μηνύματος.', 'school-contact' ) );
	}
	$issued = sc21_public_post_string( 'sc21_issued' );
	$submission_id = sc21_public_post_string( 'sc21_submission_id' );
	$submission_token = sc21_public_post_string( 'sc21_submission_token' );
	$age = null !== $issued && ctype_digit( $issued ) ? time() - (int) $issued : null;
	if ( null === $issued || null === $submission_id || null === $submission_token || ! ctype_digit( $issued ) || strlen( $issued ) > 12 || ! preg_match( '/^[a-f0-9-]{36}$/i', $submission_id ) || ! wp_verify_nonce( $submission_token, sc21_submission_token_action( $submission_id, $issued ) ) || null === $age || $age < 2 || $age > HOUR_IN_SECONDS ) {
		return sc21_public_error( __( 'Η φόρμα έληξε ή υποβλήθηκε πολύ γρήγορα. Παρακαλούμε δοκιμάστε ξανά.', 'school-contact' ) );
	}
	if ( ! sc21_claim_submission( $submission_id, (int) $issued ) ) {
		return sc21_public_error( __( 'Αυτό το μήνυμα έχει ήδη υποβληθεί.', 'school-contact' ) );
	}

	$raw_name      = sc21_public_post_string( 'sc21_parent_name' );
	$raw_email     = sc21_public_post_string( 'sc21_parent_email' );
	$recipient_id  = sc21_public_post_string( 'sc21_recipient' );
	$raw_subject   = sc21_public_post_string( 'sc21_subject' );
	$raw_message   = sc21_public_post_string( 'sc21_message' );
	if ( null === $raw_name || null === $raw_email || null === $recipient_id || null === $raw_subject || null === $raw_message ) {
		return sc21_public_error( __( 'Παρακαλούμε συμπληρώστε σωστά όλα τα υποχρεωτικά πεδία.', 'school-contact' ) );
	}
	$raw_email = trim( $raw_email );
	$values = array( 'name' => sanitize_text_field( $raw_name ), 'email' => sanitize_email( $raw_email ), 'recipient' => sanitize_text_field( $recipient_id ), 'subject' => sanitize_text_field( $raw_subject ), 'message' => sanitize_textarea_field( $raw_message ) );
	if ( preg_match( '/[\r\n]/', $raw_name . $raw_email . $raw_subject ) || '' === $values['name'] || '' === $values['email'] || '' === $values['recipient'] || '' === $values['subject'] || '' === $values['message'] || sc21_text_length( $values['name'] ) > 120 || sc21_text_length( $raw_email ) > 254 || sc21_text_length( $values['subject'] ) > 150 || sc21_text_length( $values['message'] ) > 5000 || ! is_email( $raw_email ) || $values['email'] !== $raw_email ) {
		return sc21_public_error( __( 'Ελέγξτε τα στοιχεία της φόρμας και δοκιμάστε ξανά.', 'school-contact' ), $values );
	}
	$recipient = sc21_recipient_by_id( $values['recipient'] );
	if ( null === $recipient ) {
		return sc21_public_error( __( 'Ο επιλεγμένος παραλήπτης δεν είναι διαθέσιμος.', 'school-contact' ), $values );
	}
	if ( ! sc21_send_communication( $recipient, $values['name'], $values['email'], $values['subject'], $values['message'] ) ) {
		return sc21_public_error( __( 'Η αποστολή δεν ολοκληρώθηκε. Παρακαλούμε δοκιμάστε ξανά αργότερα.', 'school-contact' ), $values );
	}

	$success_id = wp_generate_uuid4();
	set_transient( 'sc21_success_' . hash_hmac( 'sha256', $success_id, wp_salt( 'nonce' ) ), 1, 10 * MINUTE_IN_SECONDS );
	$url = remove_query_arg( 'sc21_sent' );
	wp_safe_redirect( add_query_arg( 'sc21_sent', $success_id, $url ) );
	exit;
}

function sc21_handle_public_submission(): void {
	global $sc21_public_result;
	$sc21_public_result = sc21_process_public_submission();
}

function sc21_render_form(): string {
	wp_enqueue_style( 'sc21-public' );
	wp_enqueue_script( 'sc21-public' );
	global $sc21_public_result;
	$result = $sc21_public_result ?? null;
	$values = is_array( $result['values'] ?? null ) ? $result['values'] : array();
	$groups = sc21_public_recipients();
	$issued = (string) time();
	$submission_id = wp_generate_uuid4();
	$tooltip_id = 'sc21-recipient-tooltip-' . $submission_id;
	$field_ids = array(
		'website'         => 'sc21-website-' . $submission_id,
		'recipient'       => 'sc21-recipient-' . $submission_id,
		'recipient_heading' => 'sc21-recipient-heading-' . $submission_id,
		'parent_name'     => 'sc21-parent-name-' . $submission_id,
		'parent_email'    => 'sc21-parent-email-' . $submission_id,
		'contact_heading' => 'sc21-contact-heading-' . $submission_id,
		'subject'         => 'sc21-subject-' . $submission_id,
		'message'         => 'sc21-message-' . $submission_id,
		'message_heading' => 'sc21-message-heading-' . $submission_id,
	);
	ob_start();
	?>
	<section class="sc21-form-wrap">
		<?php $success_id = isset( $_GET['sc21_sent'] ) && is_string( $_GET['sc21_sent'] ) ? wp_unslash( $_GET['sc21_sent'] ) : ''; if ( preg_match( '/^[a-f0-9-]{36}$/i', $success_id ) && get_transient( 'sc21_success_' . hash_hmac( 'sha256', $success_id, wp_salt( 'nonce' ) ) ) ) : ?><div class="sc21-message sc21-success" role="status"><?php esc_html_e( 'Το μήνυμα στάλθηκε. Η παράδοση στον παραλήπτη δεν μπορεί να επιβεβαιωθεί από τη φόρμα.', 'school-contact' ); ?></div><?php endif; ?>
		<?php if ( is_array( $result ) && empty( $result['ok'] ) ) : ?><div class="sc21-message sc21-error" role="alert"><?php echo esc_html( $result['message'] ); ?></div><?php endif; ?>
		<?php if ( empty( $groups ) ) : ?><p class="sc21-message sc21-error"><?php esc_html_e( 'Η φόρμα επικοινωνίας δεν είναι διαθέσιμη αυτή τη στιγμή.', 'school-contact' ); ?></p><?php else : ?>
		<form class="sc21-form" method="post" novalidate>
			<input type="hidden" id="sc21-nonce-<?php echo esc_attr( $submission_id ); ?>" name="sc21_nonce" value="<?php echo esc_attr( wp_create_nonce( 'sc21_public_form' ) ); ?>"><?php wp_referer_field(); ?>
			<input type="hidden" name="sc21_issued" value="<?php echo esc_attr( $issued ); ?>"><input type="hidden" name="sc21_submission_id" value="<?php echo esc_attr( $submission_id ); ?>"><input type="hidden" name="sc21_submission_token" value="<?php echo esc_attr( wp_create_nonce( sc21_submission_token_action( $submission_id, $issued ) ) ); ?>">
			<div class="sc21-hp" aria-hidden="true"><label for="<?php echo esc_attr( $field_ids['website'] ); ?>">Website</label><input id="<?php echo esc_attr( $field_ids['website'] ); ?>" name="sc21_website" type="text" tabindex="-1" autocomplete="off"></div>
			<header class="sc21-form-intro"><h2><?php esc_html_e( 'Αποστολή μηνύματος', 'school-contact' ); ?></h2><p><?php esc_html_e( 'Επιλέξτε τον παραλήπτη σας και συμπληρώστε τα στοιχεία και το μήνυμά σας.', 'school-contact' ); ?></p></header>
			<section class="sc21-form-section" aria-labelledby="<?php echo esc_attr( $field_ids['recipient_heading'] ); ?>"><h3 id="<?php echo esc_attr( $field_ids['recipient_heading'] ); ?>" class="sc21-form-section-heading"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M3 5h18v14H3zM3 7l9 6 9-6"/></svg><?php esc_html_e( 'Παραλήπτης', 'school-contact' ); ?></h3><div class="sc21-recipient-field"><span class="sc21-recipient-label"><label for="<?php echo esc_attr( $field_ids['recipient'] ); ?>"><?php esc_html_e( 'Προς', 'school-contact' ); ?> <span aria-hidden="true">*</span></label><button class="sc21-info" type="button" data-sc21-tooltip-trigger aria-expanded="false" aria-label="<?php esc_attr_e( 'Πληροφορίες κοινοποίησης', 'school-contact' ); ?>" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>">i</button><span id="<?php echo esc_attr( $tooltip_id ); ?>" class="sc21-tooltip" role="tooltip" hidden><?php esc_html_e( 'Τα μηνύματα προς το προσωπικό κοινοποιούνται και στη διεύθυνση του σχολείου.', 'school-contact' ); ?></span></span><select id="<?php echo esc_attr( $field_ids['recipient'] ); ?>" name="sc21_recipient" required><option value=""><?php esc_html_e( 'Επιλέξτε παραλήπτη', 'school-contact' ); ?></option><?php foreach ( $groups as $group ) : ?><optgroup label="<?php echo esc_attr( $group['label'] ); ?>"><?php foreach ( $group['items'] as $recipient ) : ?><option value="<?php echo esc_attr( $recipient['id'] ); ?>" <?php selected( $values['recipient'] ?? '', $recipient['id'] ); ?>><?php echo esc_html( $recipient['label'] ); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><details class="sc21-tooltip-fallback"><summary><?php esc_html_e( 'Πληροφορίες κοινοποίησης', 'school-contact' ); ?></summary><span><?php esc_html_e( 'Τα μηνύματα προς το προσωπικό κοινοποιούνται και στη διεύθυνση του σχολείου.', 'school-contact' ); ?></span></details></div></section>
			<section class="sc21-form-section" aria-labelledby="<?php echo esc_attr( $field_ids['contact_heading'] ); ?>"><h3 id="<?php echo esc_attr( $field_ids['contact_heading'] ); ?>" class="sc21-form-section-heading"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg><?php esc_html_e( 'Στοιχεία επικοινωνίας', 'school-contact' ); ?></h3><div class="sc21-form-row"><p><label for="<?php echo esc_attr( $field_ids['parent_name'] ); ?>"><?php esc_html_e( 'Ονοματεπώνυμο', 'school-contact' ); ?> <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr( $field_ids['parent_name'] ); ?>" name="sc21_parent_name" type="text" maxlength="120" required autocomplete="name" placeholder="<?php esc_attr_e( 'π.χ. Μαρία Παπαδοπούλου', 'school-contact' ); ?>" value="<?php echo esc_attr( $values['name'] ?? '' ); ?>"></p><p><label for="<?php echo esc_attr( $field_ids['parent_email'] ); ?>"><?php esc_html_e( 'Διεύθυνση email', 'school-contact' ); ?> <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr( $field_ids['parent_email'] ); ?>" name="sc21_parent_email" type="email" maxlength="254" required autocomplete="email" placeholder="name@example.gr" value="<?php echo esc_attr( $values['email'] ?? '' ); ?>"></p></div></section>
			<section class="sc21-form-section" aria-labelledby="<?php echo esc_attr( $field_ids['message_heading'] ); ?>"><h3 id="<?php echo esc_attr( $field_ids['message_heading'] ); ?>" class="sc21-form-section-heading"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M4 4h16v12H8l-4 4z"/></svg><?php esc_html_e( 'Το μήνυμά σας', 'school-contact' ); ?></h3><p><label for="<?php echo esc_attr( $field_ids['subject'] ); ?>"><?php esc_html_e( 'Θέμα', 'school-contact' ); ?> <span aria-hidden="true">*</span></label><input id="<?php echo esc_attr( $field_ids['subject'] ); ?>" name="sc21_subject" type="text" maxlength="150" required placeholder="<?php esc_attr_e( 'Σύντομο θέμα', 'school-contact' ); ?>" value="<?php echo esc_attr( $values['subject'] ?? '' ); ?>"></p><p><label for="<?php echo esc_attr( $field_ids['message'] ); ?>"><?php esc_html_e( 'Μήνυμα', 'school-contact' ); ?> <span aria-hidden="true">*</span></label><textarea id="<?php echo esc_attr( $field_ids['message'] ); ?>" name="sc21_message" maxlength="5000" required rows="4" placeholder="<?php esc_attr_e( 'Γράψτε εδώ το μήνυμά σας...', 'school-contact' ); ?>"><?php echo esc_textarea( $values['message'] ?? '' ); ?></textarea></p></section>
			<p class="sc21-privacy"><?php esc_html_e( 'Τα στοιχεία σας χρησιμοποιούνται για τη διαχείριση της επικοινωνίας σας με το σχολείο.', 'school-contact' ); ?><?php if ( get_privacy_policy_url() ) : ?> <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Πολιτική απορρήτου', 'school-contact' ); ?></a><?php endif; ?></p>
			<div class="sc21-form-footer"><p class="sc21-required"><span aria-hidden="true">*</span> <?php esc_html_e( 'Υποχρεωτικό πεδίο', 'school-contact' ); ?></p><p><button name="sc21_public_submit" value="1" type="submit"><?php esc_html_e( 'Αποστολή μηνύματος', 'school-contact' ); ?></button></p></div>
		</form>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}
