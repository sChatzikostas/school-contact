<?php

defined( 'ABSPATH' ) || exit;

function sc21_send_communication( array $recipient, string $parent_name, string $parent_email, string $subject, string $message ): bool {
	$options     = sc21_get_options();
	$admin_email = isset( $options['admin_email'] ) && is_string( $options['admin_email'] ) ? sanitize_email( $options['admin_email'] ) : '';
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

	$body = implode(
		"\n",
		array(
			__( 'Ονοματεπώνυμο γονέα/κηδεμόνα:', 'school-contact' ) . ' ' . $parent_name,
			__( 'Email γονέα/κηδεμόνα:', 'school-contact' ) . ' ' . $parent_email,
			__( 'Παραλήπτης:', 'school-contact' ) . ' ' . $recipient['label'],
			__( 'Θέμα:', 'school-contact' ) . ' ' . $subject,
			'',
			__( 'Μήνυμα:', 'school-contact' ),
			$message,
		)
	);

	return wp_mail( $to, $subject, $body, $headers );
}
