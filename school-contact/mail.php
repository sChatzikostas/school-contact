<?php

defined( 'ABSPATH' ) || exit;

function sc21_send_communication( array $recipient, string $parent_name, string $parent_email, string $subject, string $message ): bool {
	$options     = sc21_get_options();
	$admin_email = isset( $options['admin_email'] ) && is_string( $options['admin_email'] ) ? sanitize_email( $options['admin_email'] ) : '';
	$school_name = isset( $options['school_name'] ) && is_string( $options['school_name'] ) ? trim( $options['school_name'] ) : '';
	$addresses = isset( $recipient['addresses'] ) && is_array( $recipient['addresses'] ) ? $recipient['addresses'] : array();
	$unique    = array();
	foreach ( $addresses as $address ) {
		$address = is_string( $address ) ? sanitize_email( $address ) : '';
		if ( is_email( $address ) ) {
			$unique[ strtolower( $address ) ] = $address;
		}
	}
	$to = array_values( $unique );
	if ( empty( $to ) ) {
		return false;
	}

	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: ' . $parent_email,
	);
	if ( 'administration' !== $recipient['id'] ) {
		if ( ! is_email( $admin_email ) ) {
			return false;
		}
		if ( ! in_array( strtolower( $admin_email ), array_map( 'strtolower', $to ), true ) ) {
			$headers[] = 'Cc: ' . $admin_email;
		}
	}

	$body_lines = array();
	if ( '' !== $school_name ) {
		$body_lines[] = $school_name;
	}
	$body_lines = array_merge(
		$body_lines,
		array(
			__( 'Επικοινωνία γονέα/κηδεμόνα', 'school-contact' ),
			'',
			__( 'Αποστολέας:', 'school-contact' ) . ' ' . $parent_name,
			__( 'Email:', 'school-contact' ) . ' ' . $parent_email,
			__( 'Παραλήπτης:', 'school-contact' ) . ' ' . $recipient['label'],
			__( 'Θέμα:', 'school-contact' ) . ' ' . $subject,
			'',
			'────────────────────────',
			__( 'ΜΗΝΥΜΑ', 'school-contact' ),
			'',
			$message,
			'',
			'────────────────────────',
			__( 'Για να απαντήσετε στον γονέα, χρησιμοποιήστε την επιλογή «Απάντηση» του email σας.', 'school-contact' ),
			'',
			__( 'Απεστάλη μέσω της φόρμας επικοινωνίας της σχολικής μονάδας.', 'school-contact' ),
			'SchoolContact',
		)
	);
	$body = implode( "\n", $body_lines );

	return wp_mail( $to, $subject, $body, $headers );
}
