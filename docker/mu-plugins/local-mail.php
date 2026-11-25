<?php
/**
 * Development-only mail transport. All wp_mail() messages are delivered to
 * the internal Mailpit service and never use an external relay.
 */

defined( 'ABSPATH' ) || exit;

use PHPMailer\PHPMailer\PHPMailer;

if ( 'local' === wp_get_environment_type() ) {
	add_filter(
		'wp_mail_from',
		static function ( string $from_email ): string {
			return 'wordpress@example.test';
		}
	);

	add_filter(
		'wp_mail_from_name',
		static function ( string $from_name ): string {
				return 'SchoolContact Development';
		}
	);
}

add_action(
	'phpmailer_init',
	static function ( PHPMailer $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host       = 'mailpit';
		$phpmailer->Port       = 1025;
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
		$phpmailer->SMTPDebug  = 0;
	}
);
