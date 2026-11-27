<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'sc21_admin_menu' );
add_action( 'admin_enqueue_scripts', 'sc21_admin_assets' );
add_action( 'admin_post_sc21_save_general', 'sc21_save_general' );
add_action( 'admin_post_sc21_save_staff', 'sc21_save_staff' );
add_action( 'admin_post_sc21_delete_staff', 'sc21_delete_staff' );
add_action( 'admin_post_sc21_toggle_staff', 'sc21_toggle_staff' );

function sc21_admin_menu(): void {
	add_menu_page( __( 'Επικοινωνία με το σχολείο', 'school-contact' ), __( 'Επικοινωνία με το σχολείο', 'school-contact' ), 'manage_options', 'sc21-school-communication', 'sc21_admin_page', SC21_PLUGIN_URL . 'assets/icon-monochrome.svg', 80 );
}

function sc21_admin_assets( string $hook ): void {
	if ( 'toplevel_page_sc21-school-communication' === $hook ) {
		wp_enqueue_style( 'sc21-admin', SC21_PLUGIN_URL . 'assets/style.css', array(), (string) filemtime( __DIR__ . '/assets/style.css' ) );
		wp_enqueue_script( 'sc21-admin', SC21_PLUGIN_URL . 'assets/admin.js', array(), (string) filemtime( __DIR__ . '/assets/admin.js' ), true );
	}
}

function sc21_require_admin(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα για αυτή την ενέργεια.', 'school-contact' ), 403 );
	}
}

function sc21_admin_tab(): string {
	$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'staff';
	return in_array( $tab, array( 'staff', 'settings', 'about' ), true ) ? $tab : 'staff';
}

function sc21_admin_url( string $tab = 'staff', array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => 'sc21-school-communication', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}

function sc21_admin_redirect( string $notice, string $tab = 'staff', array $args = array() ): void {
	wp_safe_redirect( add_query_arg( array_merge( array( 'sc21_notice' => $notice ), $args ), sc21_admin_url( 'settings' === $tab ? 'settings' : 'staff' ) ) );
	exit;
}

function sc21_admin_post_string( string $key ): ?string {
	if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return null;
	}

	return wp_unslash( $_POST[ $key ] );
}

function sc21_staff_state( array $source ): array {
	$get = static function ( string $key ) use ( $source ): string {
		return isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? wp_unslash( $source[ $key ] ) : '';
	};
	$category = sanitize_key( $get( 'sc21_category' ) );
	$availability = sanitize_key( $get( 'sc21_availability' ) );
	$orderby = sanitize_key( $get( 'sc21_orderby' ) );
	$order = strtoupper( sanitize_key( $get( 'sc21_order' ) ) );
	return array(
		'sc21_search'       => sanitize_text_field( $get( 'sc21_search' ) ),
		'sc21_category'     => array_key_exists( $category, sc21_categories() ) ? $category : 'all',
		'sc21_availability' => in_array( $availability, array( 'all', 'available', 'unavailable' ), true ) ? $availability : 'all',
		'sc21_orderby'      => in_array( $orderby, array( 'name', 'category', 'details', 'email', 'availability' ), true ) ? $orderby : 'name',
		'sc21_order'        => in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'ASC',
	);
}

function sc21_staff_state_args( array $state, bool $include_search = true ): array {
	$args = array( 'sc21_orderby' => $state['sc21_orderby'], 'sc21_order' => strtolower( $state['sc21_order'] ) );
	if ( 'all' !== $state['sc21_category'] ) { $args['sc21_category'] = $state['sc21_category']; }
	if ( 'all' !== $state['sc21_availability'] ) { $args['sc21_availability'] = $state['sc21_availability']; }
	if ( $include_search && '' !== $state['sc21_search'] ) { $args['sc21_search'] = $state['sc21_search']; }
	return $args;
}

function sc21_staff_context_fields( array $state ): void {
	foreach ( sc21_staff_state_args( $state, true ) as $key => $value ) { printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $key ), esc_attr( $value ) ); }
	printf( '<input type="hidden" name="sc21_enhanced" value="0">' );
}

function sc21_staff_redirect( string $notice ): void {
	$state = sc21_staff_state( $_POST );
	$enhanced = '1' === sc21_admin_post_string( 'sc21_enhanced' );
	sc21_admin_redirect( $notice, 'staff', sc21_staff_state_args( $state, ! $enhanced ) );
}

function sc21_staff_validation_messages(): array {
	return array(
		'full_name'         => __( 'Συμπληρώστε το ονοματεπώνυμο.', 'school-contact' ),
		'category'          => __( 'Επιλέξτε έγκυρη κατηγορία.', 'school-contact' ),
		'specialty'         => __( 'Συμπληρώστε την ειδικότητα.', 'school-contact' ),
		'official_email'    => __( 'Εισαγάγετε μια έγκυρη διεύθυνση email.', 'school-contact' ),
		'alternative_email' => __( 'Εισαγάγετε μια έγκυρη διεύθυνση email.', 'school-contact' ),
		'contact_email'     => __( 'Ένα διαθέσιμο μέλος προσωπικού χρειάζεται τουλάχιστον μία έγκυρη διεύθυνση email.', 'school-contact' ),
	);
}

function sc21_staff_validation_codes( WP_Error $errors ): array {
	$allowed = array_keys( sc21_staff_validation_messages() );
	return array_values( array_intersect( $allowed, $errors->get_error_codes() ) );
}

function sc21_staff_validation_redirect( WP_Error $errors, string $id ): void {
	$state = sc21_staff_state( $_POST );
	$enhanced = '1' === sc21_admin_post_string( 'sc21_enhanced' );
	$args = sc21_staff_state_args( $state, ! $enhanced );
	$args['sc21_action'] = '' === $id ? 'add' : 'edit';
	if ( '' !== $id ) {
		$args['staff_id'] = $id;
	}
	$codes = sc21_staff_validation_codes( $errors );
	if ( $codes ) {
		$args['sc21_errors'] = implode( ',', $codes );
	}
	sc21_admin_redirect( 'staff_validation', 'staff', $args );
}

function sc21_staff_form_errors(): array {
	$raw = isset( $_GET['sc21_errors'] ) && is_string( $_GET['sc21_errors'] ) ? wp_unslash( $_GET['sc21_errors'] ) : '';
	$messages = sc21_staff_validation_messages();
	$errors = array();
	foreach ( explode( ',', $raw ) as $code ) {
		$code = sanitize_key( $code );
		if ( isset( $messages[ $code ] ) ) {
			$errors[ $code ] = $messages[ $code ];
		}
	}
	return $errors;
}

function sc21_staff_field_attributes( array $errors, string $field, string $id ): void {
	$describedby = array();
	if ( in_array( $field, array( 'official_email', 'alternative_email' ), true ) ) {
		$describedby[] = 'sc21-contact-email-help';
	}
	if ( isset( $errors[ $field ] ) ) {
		$describedby[] = $id . '-error';
		printf( ' aria-invalid="true"' );
	}
	if ( $describedby ) {
		printf( ' aria-describedby="%s"', esc_attr( implode( ' ', $describedby ) ) );
	}
}

function sc21_staff_field_error( array $errors, string $field, string $id ): void {
	if ( isset( $errors[ $field ] ) ) {
		printf( '<span id="%1$s-error" class="sc21-field-error">%2$s</span>', esc_attr( $id ), esc_html( $errors[ $field ] ) );
	}
}

function sc21_save_general(): void {
	sc21_require_admin();
	check_admin_referer( 'sc21_save_general' );
	$school_name = sc21_admin_post_string( 'school_name' );
	$admin_email = sc21_admin_post_string( 'admin_email' );
	$principal_name = sc21_admin_post_string( 'principal_name' );
	if ( null === $school_name || null === $admin_email || null === $principal_name || sc21_text_length( $school_name ) > 150 || sc21_text_length( $principal_name ) > 120 || ! is_email( $admin_email ) ) {
		sc21_admin_redirect( 'general_error', 'settings' );
	}
	$options                = sc21_get_options();
	$options['school_name'] = sanitize_text_field( $school_name );
	$options['admin_email'] = sanitize_email( $admin_email );
	$options['principal_name'] = sanitize_text_field( $principal_name );
	if ( ! sc21_update_options( $options ) ) { sc21_admin_redirect( 'persistence_error', 'settings' ); }
	sc21_admin_redirect( 'general_saved', 'settings' );
}

function sc21_clean_staff( string $id = '' ) {
	$name        = sc21_admin_post_string( 'full_name' );
	$specialty   = sc21_admin_post_string( 'specialty' );
	$category    = sc21_admin_post_string( 'category' );
	$classroom   = sc21_admin_post_string( 'classroom' );
	$official    = sc21_admin_post_string( 'official_email' );
	$alternative = sc21_admin_post_string( 'alternative_email' );
	$active_raw  = isset( $_POST['active'] ) ? $_POST['active'] : '';
	$errors = new WP_Error();
	if ( null === $name || null === $specialty || null === $category || null === $classroom || null === $official || null === $alternative || ! is_string( $active_raw ) ) {
		return new WP_Error( 'invalid_staff' );
	}
	$name        = sanitize_text_field( $name );
	$specialty   = sanitize_text_field( $specialty );
	$classroom   = sanitize_text_field( $classroom );
	$official    = trim( $official );
	$alternative = trim( $alternative );
	$active      = '1' === $active_raw;
	if ( '' === $name || sc21_text_length( $name ) > 120 ) {
		$errors->add( 'full_name', '' );
	}
	if ( '' === $specialty || sc21_text_length( $specialty ) > 120 ) {
		$errors->add( 'specialty', '' );
	}
	if ( sc21_text_length( $classroom ) > 80 ) {
		$errors->add( 'invalid_staff', '' );
	}
	if ( ! array_key_exists( $category, sc21_categories() ) ) {
		$errors->add( 'category', '' );
	}
	if ( '' !== $official && ! is_email( $official ) ) {
		$errors->add( 'official_email', '' );
	}
	if ( '' !== $alternative && ! is_email( $alternative ) ) {
		$errors->add( 'alternative_email', '' );
	}
	if ( $errors->has_errors() ) {
		return $errors;
	}
	$staff = array(
		'id'                => '' !== $id ? $id : wp_generate_uuid4(),
		'full_name'         => $name,
		'specialty'         => $specialty,
		'category'          => $category,
		'classroom'         => $classroom,
		'official_email'    => sanitize_email( $official ),
		'alternative_email' => sanitize_email( $alternative ),
		'active'            => $active,
	);
	if ( $active && empty( sc21_staff_addresses( $staff ) ) ) {
		return new WP_Error( 'contact_email' );
	}
	return $staff;
}

function sc21_save_staff(): void {
	sc21_require_admin();
	check_admin_referer( 'sc21_save_staff' );
	$id = sc21_admin_post_string( 'staff_id' );
	if ( null === $id || ( '' !== $id && ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) ) ) {
		sc21_staff_redirect( 'staff_error' );
	}
	$options = sc21_get_options();
	$exists  = false;
	foreach ( $options['staff'] as $saved_staff ) {
		if ( is_array( $saved_staff ) && $id === ( $saved_staff['id'] ?? '' ) ) {
			$exists = true;
			break;
		}
	}
	if ( '' !== $id && ! $exists ) {
		sc21_staff_redirect( 'staff_error' );
	}
	$staff = sc21_clean_staff( $id );
	if ( is_wp_error( $staff ) ) {
		if ( sc21_staff_validation_codes( $staff ) ) {
			sc21_staff_validation_redirect( $staff, $id );
		}
		sc21_staff_redirect( $staff->get_error_code() );
	}
	$updated = false;
	foreach ( $options['staff'] as $index => $saved_staff ) {
		if ( is_array( $saved_staff ) && $staff['id'] === ( $saved_staff['id'] ?? '' ) ) {
			$options['staff'][ $index ] = $staff;
			$updated                    = true;
			break;
		}
	}
	if ( ! $updated ) {
		$options['staff'][] = $staff;
	}
	if ( ! sc21_update_options( $options ) ) { sc21_staff_redirect( 'persistence_error' ); }
	sc21_staff_redirect( $updated ? 'staff_updated' : 'staff_added' );
}

function sc21_toggle_staff(): void {
	sc21_require_admin();
	$id     = sc21_admin_post_string( 'staff_id' );
	$active = sc21_admin_post_string( 'active' );
	if ( null === $id || null === $active || ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) || ! in_array( $active, array( '0', '1' ), true ) ) {
		sc21_staff_redirect( 'staff_error' );
	}
	check_admin_referer( 'sc21_toggle_staff_' . $id );
	$options = sc21_get_options();
	foreach ( $options['staff'] as $index => $staff ) {
		if ( ! is_array( $staff ) || $id !== ( $staff['id'] ?? '' ) ) {
			continue;
		}
		if ( '1' === $active && empty( sc21_staff_addresses( $staff ) ) ) {
			sc21_staff_redirect( 'missing_email' );
		}
		$options['staff'][ $index ]['active'] = '1' === $active;
		if ( ! sc21_update_options( $options ) ) { sc21_staff_redirect( 'persistence_error' ); }
		sc21_staff_redirect( '1' === $active ? 'staff_available' : 'staff_unavailable' );
	}
	sc21_staff_redirect( 'staff_error' );
}

function sc21_delete_staff(): void {
	sc21_require_admin();
	$id = sc21_admin_post_string( 'staff_id' );
	if ( null === $id || ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) ) {
		sc21_staff_redirect( 'staff_error' );
	}
	check_admin_referer( 'sc21_delete_staff_' . $id );
	$options          = sc21_get_options();
	$found            = false;
	$options['staff'] = array_values( array_filter( $options['staff'], static function ( $staff ) use ( $id, &$found ): bool {
		if ( is_array( $staff ) && $id === ( $staff['id'] ?? '' ) ) {
			$found = true;
			return false;
		}
		return true;
	} ) );
	if ( ! $found ) {
		sc21_staff_redirect( 'staff_error' );
	}
	if ( ! sc21_update_options( $options ) ) { sc21_staff_redirect( 'persistence_error' ); }
	sc21_staff_redirect( 'staff_deleted' );
}

function sc21_admin_notice(): void {
	$notice = isset( $_GET['sc21_notice'] ) && is_string( $_GET['sc21_notice'] ) ? sanitize_key( wp_unslash( $_GET['sc21_notice'] ) ) : '';
	$messages = array(
		'general_saved'     => __( 'Οι γενικές ρυθμίσεις αποθηκεύτηκαν.', 'school-contact' ),
		'staff_added'       => __( 'Το μέλος του προσωπικού προστέθηκε.', 'school-contact' ),
		'staff_updated'     => __( 'Το μέλος του προσωπικού ενημερώθηκε.', 'school-contact' ),
		'staff_deleted'     => __( 'Το μέλος του προσωπικού διαγράφηκε.', 'school-contact' ),
		'staff_available'   => __( 'Το μέλος είναι πλέον διαθέσιμο ως παραλήπτης.', 'school-contact' ),
		'staff_unavailable' => __( 'Το μέλος δεν είναι πλέον διαθέσιμο ως παραλήπτης.', 'school-contact' ),
		'general_error'     => __( 'Συμπληρώστε έγκυρο όνομα σχολείου και έγκυρη email διεύθυνσης σχολείου.', 'school-contact' ),
		'invalid_staff'     => __( 'Ελέγξτε τα υποχρεωτικά στοιχεία του μέλους προσωπικού.', 'school-contact' ),
		'invalid_email'     => __( 'Ελέγξτε τις διευθύνσεις email του προσωπικού.', 'school-contact' ),
		'missing_email'     => __( 'Ένα διαθέσιμο μέλος προσωπικού χρειάζεται τουλάχιστον μία έγκυρη διεύθυνση email.', 'school-contact' ),
		'staff_validation'  => __( 'Ελέγξτε τα επισημασμένα πεδία πριν αποθηκεύσετε την καταχώριση.', 'school-contact' ),
		'staff_error'       => __( 'Η ενέργεια δεν ήταν έγκυρη.', 'school-contact' ),
		'persistence_error' => __( 'Η αποθήκευση δεν ολοκληρώθηκε. Παρακαλούμε δοκιμάστε ξανά.', 'school-contact' ),
	);
	if ( isset( $messages[ $notice ] ) ) {
		$error = str_ends_with( $notice, 'error' ) || in_array( $notice, array( 'invalid_staff', 'invalid_email', 'missing_email', 'staff_validation', 'persistence_error' ), true );
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $error ? 'notice-error' : 'notice-success' ), esc_html( $messages[ $notice ] ) );
	}
}

function sc21_staff_normalize( string $value ): string {
	$value = trim( $value );
	$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( strtr( $value, array( 'Α'=>'α','Β'=>'β','Γ'=>'γ','Δ'=>'δ','Ε'=>'ε','Ζ'=>'ζ','Η'=>'η','Θ'=>'θ','Ι'=>'ι','Κ'=>'κ','Λ'=>'λ','Μ'=>'μ','Ν'=>'ν','Ξ'=>'ξ','Ο'=>'ο','Π'=>'π','Ρ'=>'ρ','Σ'=>'σ','Τ'=>'τ','Υ'=>'υ','Φ'=>'φ','Χ'=>'χ','Ψ'=>'ψ','Ω'=>'ω','Ά'=>'ά','Έ'=>'έ','Ή'=>'ή','Ί'=>'ί','Ό'=>'ό','Ύ'=>'ύ','Ώ'=>'ώ','Ϊ'=>'ϊ','Ϋ'=>'ϋ' ) ) );
	$value = strtr( $value, array( 'ά'=>'α','έ'=>'ε','ή'=>'η','ί'=>'ι','ό'=>'ο','ύ'=>'υ','ώ'=>'ω','ϊ'=>'ι','ΐ'=>'ι','ϋ'=>'υ','ΰ'=>'υ','ς'=>'σ' ) );
	return preg_replace( '/[\x{0300}-\x{036f}]/u', '', $value ) ?? $value;
}

function sc21_staff_detail_value( array $staff ): string { return trim( (string) ( $staff['classroom'] ?? '' ) . ' - ' . (string) ( $staff['specialty'] ?? '' ), ' -' ); }
function sc21_staff_email_value( array $staff ): string { $emails = sc21_staff_addresses( $staff ); return isset( $emails[0] ) ? (string) $emails[0] : ''; }

function sc21_staff_search_value( array $staff ): string {
	return implode( ' ', array( (string) ( $staff['full_name'] ?? '' ), (string) ( $staff['specialty'] ?? '' ), (string) ( $staff['classroom'] ?? '' ), (string) ( $staff['official_email'] ?? '' ), (string) ( $staff['alternative_email'] ?? '' ) ) );
}

function sc21_filtered_staff( array $staff, array $state ): array {
	$search = sc21_staff_normalize( $state['sc21_search'] );
	$items = array_filter( $staff, static function ( $member ) use ( $state, $search ): bool {
		if ( ! is_array( $member ) ) {
			return false;
		}
		$is_available = sc21_valid_staff( $member );
		if ( ( 'available' === $state['sc21_availability'] && ! $is_available ) || ( 'unavailable' === $state['sc21_availability'] && $is_available ) || ( 'all' !== $state['sc21_category'] && $state['sc21_category'] !== ( $member['category'] ?? '' ) ) ) {
			return false;
		}
		if ( '' === $search ) {
			return true;
		}
		$haystack = sc21_staff_search_value( $member );
		return false !== strpos( sc21_staff_normalize( $haystack ), $search );
	} );
	usort( $items, static function ( array $a, array $b ) use ( $state ): int {
		$key = $state['sc21_orderby'];
		$email_missing = false;
		if ( 'availability' === $key ) { $primary = ( sc21_valid_staff( $a ) ? 0 : 1 ) <=> ( sc21_valid_staff( $b ) ? 0 : 1 ); }
		elseif ( 'category' === $key ) { $primary = strnatcmp( sc21_staff_normalize( sc21_categories()[ $a['category'] ?? '' ] ?? '' ), sc21_staff_normalize( sc21_categories()[ $b['category'] ?? '' ] ?? '' ) ); }
		elseif ( 'details' === $key ) { $primary = strnatcmp( sc21_staff_normalize( sc21_staff_detail_value( $a ) ), sc21_staff_normalize( sc21_staff_detail_value( $b ) ) ); }
		elseif ( 'email' === $key ) { $ea = sc21_staff_email_value( $a ); $eb = sc21_staff_email_value( $b ); $email_missing = '' === $ea || '' === $eb; $primary = '' === $ea ? ( '' === $eb ? 0 : 1 ) : ( '' === $eb ? -1 : strnatcmp( sc21_staff_normalize( $ea ), sc21_staff_normalize( $eb ) ) ); }
		else { $primary = strnatcmp( sc21_staff_normalize( (string) ( $a['full_name'] ?? '' ) ), sc21_staff_normalize( (string) ( $b['full_name'] ?? '' ) ) ); }
		if ( 0 === $primary ) { $primary = strnatcmp( sc21_staff_normalize( (string) ( $a['full_name'] ?? '' ) ), sc21_staff_normalize( (string) ( $b['full_name'] ?? '' ) ) ); }
		if ( 0 === $primary ) { $primary = strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) ); }
		return 'DESC' === $state['sc21_order'] && ! $email_missing ? -$primary : $primary;
	} );
	return array_values( $items );
}

function sc21_admin_staff_form( array $staff, array $state, array $staff_records ): void {
	$is_edit = '' !== $staff['id'];
	$form_id = 'sc21-staff-save-' . ( $is_edit ? $staff['id'] : 'new' );
	$errors = sc21_staff_form_errors();
	$duplicate_records = array_map( static function ( array $record ): array {
		return array(
			'id'                => (string) ( $record['id'] ?? '' ),
			'full_name'         => (string) ( $record['full_name'] ?? '' ),
			'category'          => (string) ( $record['category'] ?? '' ),
			'specialty'         => (string) ( $record['specialty'] ?? '' ),
			'classroom'         => (string) ( $record['classroom'] ?? '' ),
			'official_email'    => (string) ( $record['official_email'] ?? '' ),
			'alternative_email' => (string) ( $record['alternative_email'] ?? '' ),
			'active'            => ! empty( $record['active'] ),
		);
	}, $staff_records );
	if ( isset( $errors['contact_email'] ) && ! isset( $errors['official_email'] ) ) {
		$errors['official_email'] = $errors['contact_email'];
	}
	?>
	<section class="sc21-card sc21-staff-editor" aria-labelledby="sc21-staff-editor-title">
		<header class="sc21-modal-header"><h2 id="sc21-staff-editor-title"><?php echo esc_html( $is_edit ? __( 'Επεξεργασία προσωπικού σχολείου', 'school-contact' ) : __( 'Προσθήκη προσωπικού σχολείου', 'school-contact' ) ); ?></h2><button type="button" class="sc21-dialog-close" aria-label="<?php esc_attr_e( 'Κλείσιμο', 'school-contact' ); ?>">×</button></header>
		<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-sc21-staff-records="<?php echo esc_attr( wp_json_encode( $duplicate_records ) ); ?>"><input type="hidden" name="action" value="sc21_save_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ); ?>"><?php sc21_staff_context_fields( $state ); wp_nonce_field( 'sc21_save_staff' ); ?>
			<div class="sc21-modal-body">
				<div id="sc21-editor-validation-summary" class="sc21-editor-validation-summary" role="alert" tabindex="-1"<?php echo $errors ? '' : ' hidden'; ?>><p><?php esc_html_e( 'Ελέγξτε τα επισημασμένα πεδία πριν αποθηκεύσετε την καταχώριση.', 'school-contact' ); ?></p></div>
				<section class="sc21-editor-section" aria-labelledby="sc21-staff-details-heading">
					<h3 id="sc21-staff-details-heading"><?php esc_html_e( 'Στοιχεία προσωπικού', 'school-contact' ); ?></h3>
					<div class="sc21-admin-grid">
						<p class="sc21-field-name"><label for="sc21-full-name"><?php esc_html_e( 'Ονοματεπώνυμο', 'school-contact' ); ?> <span class="sc21-field-required"><?php esc_html_e( '(υποχρεωτικό)', 'school-contact' ); ?></span></label><input id="sc21-full-name" name="full_name" type="text" maxlength="120" required value="<?php echo esc_attr( $staff['full_name'] ); ?>"<?php sc21_staff_field_attributes( $errors, 'full_name', 'sc21-full-name' ); ?>><?php sc21_staff_field_error( $errors, 'full_name', 'sc21-full-name' ); ?></p>
						<p><label for="sc21-category"><?php esc_html_e( 'Κατηγορία', 'school-contact' ); ?> <span class="sc21-field-required"><?php esc_html_e( '(υποχρεωτικό)', 'school-contact' ); ?></span></label><select id="sc21-category" name="category" required<?php sc21_staff_field_attributes( $errors, 'category', 'sc21-category' ); ?>><?php foreach ( sc21_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $staff['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php sc21_staff_field_error( $errors, 'category', 'sc21-category' ); ?></p>
						<p><label for="sc21-specialty"><?php esc_html_e( 'Ειδικότητα', 'school-contact' ); ?> <span class="sc21-field-required"><?php esc_html_e( '(υποχρεωτικό)', 'school-contact' ); ?></span></label><input id="sc21-specialty" name="specialty" type="text" maxlength="120" required value="<?php echo esc_attr( $staff['specialty'] ); ?>"<?php sc21_staff_field_attributes( $errors, 'specialty', 'sc21-specialty' ); ?>><?php sc21_staff_field_error( $errors, 'specialty', 'sc21-specialty' ); ?></p>
						<p><label for="sc21-classroom"><?php esc_html_e( 'Τμήμα', 'school-contact' ); ?> <span class="sc21-field-optional"><?php esc_html_e( '(προαιρετικό)', 'school-contact' ); ?></span></label><input id="sc21-classroom" name="classroom" type="text" maxlength="80" value="<?php echo esc_attr( $staff['classroom'] ); ?>"></p>
					</div>
				</section>
				<section class="sc21-editor-section" aria-labelledby="sc21-contact-details-heading">
					<h3 id="sc21-contact-details-heading"><?php esc_html_e( 'Στοιχεία επικοινωνίας', 'school-contact' ); ?></h3>
					<p id="sc21-contact-email-help" class="description sc21-editor-section-description"><?php esc_html_e( 'Για τα διαθέσιμα μέλη προσωπικού απαιτείται τουλάχιστον μία έγκυρη διεύθυνση email. Όταν έχουν οριστεί δύο διαφορετικές διευθύνσεις, το μήνυμα αποστέλλεται και στις δύο.', 'school-contact' ); ?></p>
					<div class="sc21-admin-grid">
						<p class="sc21-field-full"><label for="sc21-official-email"><?php esc_html_e( 'Υπηρεσιακό email', 'school-contact' ); ?> <span class="sc21-field-optional"><?php esc_html_e( '(προαιρετικό)', 'school-contact' ); ?></span></label><input id="sc21-official-email" name="official_email" type="email" maxlength="254" value="<?php echo esc_attr( $staff['official_email'] ); ?>"<?php sc21_staff_field_attributes( $errors, 'official_email', 'sc21-official-email' ); ?>><?php sc21_staff_field_error( $errors, 'official_email', 'sc21-official-email' ); ?></p>
						<p class="sc21-field-full"><label for="sc21-alternative-email"><?php esc_html_e( 'Εναλλακτικό email', 'school-contact' ); ?> <span class="sc21-field-optional"><?php esc_html_e( '(προαιρετικό)', 'school-contact' ); ?></span></label><input id="sc21-alternative-email" name="alternative_email" type="email" maxlength="254" value="<?php echo esc_attr( $staff['alternative_email'] ); ?>"<?php sc21_staff_field_attributes( $errors, 'alternative_email', 'sc21-alternative-email' ); ?>><?php sc21_staff_field_error( $errors, 'alternative_email', 'sc21-alternative-email' ); ?></p>
					</div>
				</section>
				<section class="sc21-editor-section" aria-labelledby="sc21-availability-heading">
					<h3 id="sc21-availability-heading"><?php esc_html_e( 'Διαθεσιμότητα', 'school-contact' ); ?></h3>
					<p class="sc21-availability-field"><label><input name="active" type="checkbox" value="1" <?php checked( ! empty( $staff['active'] ) ); ?>> <span><?php esc_html_e( 'Διαθέσιμος ως παραλήπτης', 'school-contact' ); ?></span></label><span class="description"><?php esc_html_e( 'Οι διαθέσιμοι παραλήπτες εμφανίζονται στη δημόσια φόρμα επικοινωνίας.', 'school-contact' ); ?></span></p>
				</section>
			</div>
		</form>
		<footer class="sc21-modal-footer"><div><?php if ( $is_edit ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc21-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Να διαγραφεί αυτό το μέλος του προσωπικού;', 'school-contact' ) ); ?>');"><input type="hidden" name="action" value="sc21_delete_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ); ?>"><?php sc21_staff_context_fields( $state ); wp_nonce_field( 'sc21_delete_staff_' . $staff['id'] ); ?><button type="submit" class="button-link-delete"><?php esc_html_e( 'Διαγραφή', 'school-contact' ); ?></button></form><?php endif; ?></div><div class="sc21-modal-footer-actions"><a class="button" href="<?php echo esc_url( sc21_admin_url( 'staff', sc21_staff_state_args( $state ) ) ); ?>"><?php esc_html_e( 'Ακύρωση', 'school-contact' ); ?></a><button form="<?php echo esc_attr( $form_id ); ?>" type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Αποθήκευση', 'school-contact' ) : __( 'Προσθήκη', 'school-contact' ) ); ?></button></div></footer>
	</section>
	<?php
}

function sc21_render_help_page(): void {
	?>
	<section class="sc21-card sc21-help-intro">
		<h2><?php esc_html_e( 'Σχετικά με το SchoolContact', 'school-contact' ); ?></h2>
		<p class="sc21-product-tagline">Connecting schools and families.</p>
		<p><?php esc_html_e( 'Ένα απλό εργαλείο για ασφαλή επικοινωνία μεταξύ σχολείου, οικογενειών και προσωπικού.', 'school-contact' ); ?></p>
		<p><strong><?php esc_html_e( 'Σχεδιασμός & ανάπτυξη:', 'school-contact' ); ?></strong> <?php esc_html_e( 'Σωτήρης Χατζηκώστας', 'school-contact' ); ?></p>
		<p class="sc21-product-meta"><span><?php printf( esc_html__( 'Έκδοση: %s', 'school-contact' ), esc_html( SC21_VERSION ) ); ?></span><span><?php esc_html_e( 'Άδεια: GPL-2.0-or-later', 'school-contact' ); ?></span><span>Free &amp; Open Source</span></p>
		<nav class="sc21-contact-links" aria-label="<?php esc_attr_e( 'Στοιχεία επικοινωνίας SchoolContact', 'school-contact' ); ?>">
			<a href="mailto:s.chatzikostas@gmail.com" aria-label="<?php esc_attr_e( 'Email Sotiris Chatzikostas', 'school-contact' ); ?>"><span class="dashicons dashicons-email" aria-hidden="true"></span><span>Email</span></a>
			<a href="https://www.linkedin.com/in/sotiris-chatzikostas/" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'LinkedIn Sotiris Chatzikostas', 'school-contact' ); ?>"><span class="sc21-link-icon" aria-hidden="true">in</span><span>LinkedIn</span></a>
			<a href="https://github.com/sChatzikostas/school-contact" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Προγραμματισμένο GitHub repository SchoolContact', 'school-contact' ); ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span>GitHub</span></a>
		</nav>
	</section>
	<section class="sc21-card sc21-help-guide">
		<h2><?php esc_html_e( 'Οδηγός χρήσης', 'school-contact' ); ?></h2>
		<details><summary><?php esc_html_e( 'Εγκατάσταση του πρόσθετου', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Χρειάζεστε πρόσβαση διαχειριστή και μια λειτουργική εγκατάσταση WordPress. Πριν ξεκινήσετε, προτείνεται αντίγραφο ασφαλείας του ιστοτόπου.', 'school-contact' ); ?></p><ol><li><?php esc_html_e( 'Κατεβάστε το αρχείο εγκατάστασης ZIP.', 'school-contact' ); ?></li><li><?php esc_html_e( 'Επιλέξτε Πρόσθετα - Προσθήκη νέου - Μεταφόρτωση προσθέτου.', 'school-contact' ); ?></li><li><?php esc_html_e( 'Επιλέξτε το ZIP, εγκαταστήστε το και ενεργοποιήστε το SchoolContact.', 'school-contact' ); ?></li></ol></div></details>
		<details><summary><?php esc_html_e( 'Αρχική ρύθμιση', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Από το μενού Επικοινωνία με το σχολείο, ανοίξτε τις Γενικές ρυθμίσεις. Συμπληρώστε το όνομα του σχολείου, το ονοματεπώνυμο διευθυντή ή διευθύντριας και την email διεύθυνση του σχολείου.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Διαχείριση προσωπικού', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Προσθέστε, επεξεργαστείτε ή διαγράψτε προσωπικό από την καρτέλα Προσωπικό. Μπορείτε να ενεργοποιείτε ή να απενεργοποιείτε κάθε παραλήπτη χωρίς να διαγράφετε τα στοιχεία του.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Επιλέξτε κατηγορία παραλήπτη, συμπληρώστε ειδικότητα και προαιρετικά τμήμα. Καταχωρίστε υπηρεσιακό email και, μόνο με έγκριση του σχολείου, εναλλακτικό email. Όταν υπάρχουν και οι δύο διευθύνσεις, τα μηνύματα αποστέλλονται και στις δύο.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Δημοσίευση της φόρμας', 'school-contact' ); ?></summary><div><ol><li><?php esc_html_e( 'Δημιουργήστε ή επεξεργαστείτε μια σελίδα WordPress.', 'school-contact' ); ?></li><li><?php esc_html_e( 'Στον επεξεργαστή επιλέξτε το κουμπί προσθήκης block, αναζητήστε το block Shortcode και επιλέξτε το.', 'school-contact' ); ?></li><li><?php esc_html_e( 'Επικολλήστε το ακόλουθο shortcode στο block:', 'school-contact' ); ?></li></ol><p><code id="sc21-shortcode">[school_communication]</code> <button type="button" class="button sc21-copy-shortcode" data-sc21-copy="[school_communication]" aria-describedby="sc21-copy-feedback"><?php esc_html_e( 'Αντιγραφή shortcode', 'school-contact' ); ?></button> <span id="sc21-copy-feedback" class="screen-reader-text" aria-live="polite"></span></p><p><?php esc_html_e( 'Δημοσιεύστε τη σελίδα και, αν θέλετε, προσθέστε την στο μενού πλοήγησης του ιστοτόπου.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Πώς αποστέλλονται τα μηνύματα', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Τα μηνύματα προς το προσωπικό στέλνονται στις καταχωρισμένες διευθύνσεις του. Η διεύθυνση του σχολείου λαμβάνει κοινοποίηση, εκτός αν είναι ήδη παραλήπτης. Τα μηνύματα που απευθύνονται απευθείας στο σχολείο δεν λαμβάνουν διπλή κοινοποίηση.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Ο παραλήπτης μπορεί να απαντήσει απευθείας στον γονέα ή κηδεμόνα. Η επιτυχής υποβολή της φόρμας δεν αποτελεί εγγύηση τελικής παράδοσης του email.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Δοκιμή λειτουργίας', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Υποβάλετε ένα δοκιμαστικό μήνυμα με ελεγχόμενη διεύθυνση email. Επιβεβαιώστε ότι έφτασε στον σωστό παραλήπτη και ότι το Reply-To αντιστοιχεί στη διεύθυνση του αποστολέα.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Ενημέρωση για το νέο σχολικό έτος', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Ενημερώστε το προσωπικό, τα τμήματα, τις ειδικότητες και τη διαθεσιμότητα από την καρτέλα Προσωπικό. Δεν χρειάζεται επανεγκατάσταση του πρόσθετου.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Συχνές ερωτήσεις και αντιμετώπιση προβλημάτων', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Αν τα μηνύματα δεν φτάνουν, ελέγξτε τις διευθύνσεις email, τους φακέλους ανεπιθύμητης αλληλογραφίας και τη ρύθμιση αποστολής email του WordPress. Βεβαιωθείτε ότι ο παραλήπτης είναι διαθέσιμος και διαθέτει έγκυρο email.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Η φόρμα μπορεί προσωρινά να περιορίσει πολλές προσπάθειες. Εξαιρέστε τη σελίδα επικοινωνίας από full-page cache, ώστε να μην εμφανίζονται παλιοί κωδικοί φόρμας. Για αλλαγές σε φιλοξενία ή ρύθμιση mail, επικοινωνήστε με τον διαχειριστή του ιστοτόπου.', 'school-contact' ); ?></p></div></details>
		<details><summary><?php esc_html_e( 'Ασφάλεια και προσωπικά δεδομένα', 'school-contact' ); ?></summary><div><p><?php esc_html_e( 'Το πρόσθετο δεν αποθηκεύει το περιεχόμενο μηνυμάτων ή στοιχεία επικοινωνίας γονέων. Οι διευθύνσεις email του προσωπικού δεν εμφανίζονται στη δημόσια φόρμα. Η ενσωματωμένη προστασία κατά ανεπιθύμητων ενεργειών μειώνει την κατάχρηση, χωρίς να αποτελεί απόλυτη εγγύηση.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Η χρήση εναλλακτικών διευθύνσεων απαιτεί έγκριση του σχολείου. Οι διαμορφωμένοι παραλήπτες μπορεί να βλέπουν ο ένας τον άλλον στις κεφαλίδες του email και τα μηνύματα παραμένουν στα γραμματοκιβώτια των παραληπτών. Το σχολείο οφείλει να παρέχει κατάλληλη ενημέρωση απορρήτου.', 'school-contact' ); ?></p></div></details>
	</section>
	<?php
}

function sc21_staff_sort_heading( string $label, string $key, array $state, string $class = '' ): void {
	$active = $key === $state['sc21_orderby'];
	$order = $active && 'ASC' === $state['sc21_order'] ? 'DESC' : 'ASC';
	$args = array_merge( sc21_staff_state_args( $state ), array( 'sc21_orderby' => $key, 'sc21_order' => strtolower( $order ) ) );
	$indicator = $active ? ( 'ASC' === $state['sc21_order'] ? '↑' : '↓' ) : '↕';
	printf( '<th%1$s%2$s><a class="sc21-sort-link" href="%3$s"><span>%4$s</span><span class="sc21-sort-indicator" aria-hidden="true">%5$s</span><span class="screen-reader-text">%6$s</span></a></th>', $class ? ' class="' . esc_attr( $class ) . '"' : '', $active ? ' aria-sort="' . esc_attr( 'ASC' === $state['sc21_order'] ? 'ascending' : 'descending' ) . '"' : '', esc_url( sc21_admin_url( 'staff', $args ) ), esc_html( $label ), esc_html( $indicator ), esc_html( $active ? ( 'ASC' === $state['sc21_order'] ? __( 'Αύξουσα ταξινόμηση', 'school-contact' ) : __( 'Φθίνουσα ταξινόμηση', 'school-contact' ) ) : __( 'Επιλέξτε για ταξινόμηση', 'school-contact' ) ) );
}

function sc21_admin_page(): void {
	sc21_require_admin();
	$options = sc21_get_options();
	$tab     = sc21_admin_tab();
	?>
	<div class="wrap sc21-admin" data-sc21-state-scope="<?php echo esc_attr( (string) get_current_user_id() ); ?>">
		<header class="sc21-admin-header">
			<h1 class="screen-reader-text">SchoolContact</h1>
			<div class="sc21-admin-brand">
				<img src="<?php echo esc_url( SC21_PLUGIN_URL . 'assets/logo-horizontal.svg' ); ?>" alt="">
				<p><?php esc_html_e( 'Διαχείριση επικοινωνίας σχολείου', 'school-contact' ); ?></p>
			</div>
		</header>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Ενότητες SchoolContact', 'school-contact' ); ?>"><a class="nav-tab <?php echo 'staff' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url() ); ?>"><?php esc_html_e( 'Προσωπικό', 'school-contact' ); ?></a><a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url( 'settings' ) ); ?>"><?php esc_html_e( 'Γενικές ρυθμίσεις', 'school-contact' ); ?></a><a class="nav-tab <?php echo 'about' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url( 'about' ) ); ?>"><?php esc_html_e( 'Βοήθεια & πληροφορίες', 'school-contact' ); ?></a></nav>
		<div class="sc21-admin-notices"><?php sc21_admin_notice(); ?></div>
	<?php if ( 'settings' === $tab ) : ?>
		<section class="sc21-card sc21-settings-content"><h2><?php esc_html_e( 'Γενικές ρυθμίσεις', 'school-contact' ); ?></h2><p><?php esc_html_e( 'Τα μηνύματα προς το προσωπικό σχολείου κοινοποιούνται αυτόματα στην email διεύθυνση σχολείου.', 'school-contact' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sc21_save_general"><?php wp_nonce_field( 'sc21_save_general' ); ?><p><label for="sc21-school-name"><?php esc_html_e( 'Όνομα σχολείου', 'school-contact' ); ?></label><input id="sc21-school-name" name="school_name" type="text" maxlength="150" required value="<?php echo esc_attr( $options['school_name'] ); ?>"></p><p><label for="sc21-principal-name"><?php esc_html_e( 'Ονοματεπώνυμο διευθυντή / διευθύντριας', 'school-contact' ); ?></label><input id="sc21-principal-name" name="principal_name" type="text" maxlength="120" value="<?php echo esc_attr( $options['principal_name'] ?? '' ); ?>"></p><p><label for="sc21-admin-email"><?php esc_html_e( 'Email διεύθυνσης σχολείου', 'school-contact' ); ?></label><input id="sc21-admin-email" name="admin_email" type="email" maxlength="254" required value="<?php echo esc_attr( $options['admin_email'] ); ?>"></p><?php submit_button( __( 'Αποθήκευση ρυθμίσεων', 'school-contact' ) ); ?></form></section><div class="notice notice-info inline"><p><?php esc_html_e( 'Οι εναλλακτικές διευθύνσεις email μπορεί να μην ανήκουν στο sch.gr. Χρησιμοποιήστε τες μόνο όταν έχουν εγκριθεί από το σχολείο. Όταν υπάρχουν και οι δύο διευθύνσεις, η επικοινωνία αποστέλλεται και στις δύο.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Το κείμενο απορρήτου της δημόσιας φόρμας είναι προσωρινό και χρειάζεται έγκριση από το σχολείο.', 'school-contact' ); ?></p></div><p><?php esc_html_e( 'Shortcode:', 'school-contact' ); ?> <code>[school_communication]</code></p>
	<?php elseif ( 'about' === $tab ) : ?>
		<?php sc21_render_help_page(); ?>
	<?php else :
		$state = sc21_staff_state( $_GET );
		$all_staff = array_values( array_filter( $options['staff'], 'is_array' ) );
		$available_count = count( array_filter( $all_staff, 'sc21_valid_staff' ) );
		$staff_items = sc21_filtered_staff( $all_staff, $state );
		$all_sorted_staff = sc21_filtered_staff( $all_staff, array_merge( $state, array( 'sc21_search' => '', 'sc21_category' => 'all', 'sc21_availability' => 'all' ) ) );
		$matching_ids = array_flip( array_filter( array_column( $staff_items, 'id' ), 'is_string' ) );
		$action = isset( $_GET['sc21_action'] ) && is_string( $_GET['sc21_action'] ) ? sanitize_key( wp_unslash( $_GET['sc21_action'] ) ) : '';
		$edit_id = isset( $_GET['staff_id'] ) && is_string( $_GET['staff_id'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_id'] ) ) : '';
		?>
		<section class="sc21-staff-page" aria-labelledby="sc21-staff-heading">
			<div class="sc21-section-heading">
				<div><h2 id="sc21-staff-heading"><?php esc_html_e( 'Προσωπικό σχολείου', 'school-contact' ); ?></h2><p><?php esc_html_e( 'Διαχειριστείτε τους παραλήπτες της φόρμας επικοινωνίας.', 'school-contact' ); ?></p></div>
				<a class="button button-primary" href="<?php echo esc_url( sc21_admin_url( 'staff', array_merge( sc21_staff_state_args( $state ), array( 'sc21_action' => 'add' ) ) ) ); ?>"><?php esc_html_e( 'Προσθήκη προσωπικού', 'school-contact' ); ?></a>
			</div>
			<div class="sc21-staff-toolbar">
				<div class="sc21-counts"><span><?php printf( esc_html__( 'Σύνολο: %d', 'school-contact' ), count( $all_staff ) ); ?></span><span class="sc21-available"><?php printf( esc_html__( 'Διαθέσιμοι: %d', 'school-contact' ), $available_count ); ?></span><span class="sc21-unavailable"><?php printf( esc_html__( 'Μη διαθέσιμοι: %d', 'school-contact' ), count( $all_staff ) - $available_count ); ?></span></div>
				<form class="sc21-staff-filters" method="get"><input type="hidden" name="page" value="sc21-school-communication"><input type="hidden" name="tab" value="staff"><input type="hidden" name="sc21_orderby" value="<?php echo esc_attr( $state['sc21_orderby'] ); ?>"><input type="hidden" name="sc21_order" value="<?php echo esc_attr( strtolower( $state['sc21_order'] ) ); ?>"><label for="sc21-search" class="screen-reader-text"><?php esc_html_e( 'Αναζήτηση προσωπικού', 'school-contact' ); ?></label><input id="sc21-search" name="sc21_search" type="search" value="<?php echo esc_attr( $state['sc21_search'] ); ?>" placeholder="<?php esc_attr_e( 'Αναζήτηση προσωπικού...', 'school-contact' ); ?>"><label for="sc21-category-filter" class="screen-reader-text"><?php esc_html_e( 'Κατηγορία', 'school-contact' ); ?></label><select id="sc21-category-filter" name="sc21_category"><option value="all"><?php esc_html_e( 'Όλες οι κατηγορίες', 'school-contact' ); ?></option><?php foreach ( sc21_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state['sc21_category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><label for="sc21-availability" class="screen-reader-text"><?php esc_html_e( 'Διαθεσιμότητα', 'school-contact' ); ?></label><select id="sc21-availability" name="sc21_availability"><option value="all" <?php selected( $state['sc21_availability'], 'all' ); ?>><?php esc_html_e( 'Όλο το προσωπικό', 'school-contact' ); ?></option><option value="available" <?php selected( $state['sc21_availability'], 'available' ); ?>><?php esc_html_e( 'Διαθέσιμοι', 'school-contact' ); ?></option><option value="unavailable" <?php selected( $state['sc21_availability'], 'unavailable' ); ?>><?php esc_html_e( 'Μη διαθέσιμοι', 'school-contact' ); ?></option></select><?php submit_button( __( 'Φιλτράρισμα', 'school-contact' ), 'secondary', '', false ); ?></form><div class="sc21-toolbar-summary"><p class="sc21-results-count" aria-live="polite"><?php printf( esc_html__( 'Εμφανίζονται %1$d από %2$d εγγραφές', 'school-contact' ), count( $staff_items ), count( $all_staff ) ); ?></p><a class="sc21-clear-filters" href="<?php echo esc_url( sc21_admin_url( 'staff', array( 'sc21_orderby' => $state['sc21_orderby'], 'sc21_order' => strtolower( $state['sc21_order'] ) ) ) ); ?>" <?php echo ( '' === $state['sc21_search'] && 'all' === $state['sc21_category'] && 'all' === $state['sc21_availability'] ) ? 'hidden' : ''; ?>><?php esc_html_e( 'Επαναφορά φίλτρων', 'school-contact' ); ?></a></div>
			</div>
			<div class="sc21-staff-table-region" role="region" aria-label="<?php esc_attr_e( 'Λίστα προσωπικού σχολείου', 'school-contact' ); ?>" tabindex="0">
				<table class="widefat striped sc21-staff-table"><caption class="screen-reader-text"><?php esc_html_e( 'Λίστα προσωπικού σχολείου', 'school-contact' ); ?></caption><colgroup><col class="sc21-col-name"><col class="sc21-col-category"><col class="sc21-col-details"><col class="sc21-col-emails"><col class="sc21-col-status"><col class="sc21-col-actions"></colgroup><thead><tr><?php sc21_staff_sort_heading( __( 'Ονοματεπώνυμο', 'school-contact' ), 'name', $state ); sc21_staff_sort_heading( __( 'Κατηγορία', 'school-contact' ), 'category', $state ); sc21_staff_sort_heading( __( 'Τμήμα / Ειδικότητα', 'school-contact' ), 'details', $state ); sc21_staff_sort_heading( __( 'Email παραληπτών', 'school-contact' ), 'email', $state ); sc21_staff_sort_heading( __( 'Διαθεσιμότητα', 'school-contact' ), 'availability', $state, 'sc21-status-column' ); ?><th><?php esc_html_e( 'Ενέργειες', 'school-contact' ); ?></th></tr></thead><tbody>
		<tr class="sc21-empty-state"<?php echo empty( $staff_items ) ? '' : ' hidden'; ?>><td colspan="6"><strong><?php echo empty( $all_staff ) ? esc_html__( 'Δεν έχει προστεθεί προσωπικό.', 'school-contact' ) : esc_html__( 'Δεν βρέθηκαν αποτελέσματα.', 'school-contact' ); ?></strong><p><?php echo empty( $all_staff ) ? esc_html__( 'Προσθέστε μέλη του προσωπικού που θα εμφανίζονται ως παραλήπτες στη φόρμα επικοινωνίας.', 'school-contact' ) : esc_html__( 'Δοκιμάστε διαφορετική αναζήτηση ή επαναφέρετε τα φίλτρα.', 'school-contact' ); ?></p></td></tr>
		<?php foreach ( $all_sorted_staff as $staff ) : $is_available = sc21_valid_staff( $staff ); $emails = sc21_staff_addresses( $staff ); $official = strtolower( sanitize_email( (string) ( $staff['official_email'] ?? '' ) ) ); $alternative = strtolower( sanitize_email( (string) ( $staff['alternative_email'] ?? '' ) ) ); ?>
			<tr data-sc21-category="<?php echo esc_attr( $staff['category'] ?? '' ); ?>" data-sc21-search="<?php echo esc_attr( sc21_staff_search_value( $staff ) ); ?>"<?php echo isset( $matching_ids[ $staff['id'] ?? '' ] ) ? '' : ' hidden'; ?>><td><strong class="sc21-staff-name"><?php echo esc_html( $staff['full_name'] ?? '' ); ?></strong></td><td><span class="sc21-staff-category"><?php echo esc_html( sc21_categories()[ $staff['category'] ?? '' ] ?? '' ); ?></span></td><td><span class="sc21-staff-details"><?php echo esc_html( trim( (string) ( $staff['classroom'] ?? '' ) . ' - ' . (string) ( $staff['specialty'] ?? '' ), ' -' ) ); ?></span></td><td><?php if ( empty( $emails ) ) : ?>-<?php else : ?><?php foreach ( $emails as $email ) : $key = strtolower( $email ); $type = $key === $official && $key === $alternative ? __( 'υπηρεσιακό και εναλλακτικό', 'school-contact' ) : ( $key === $official ? __( 'υπηρεσιακό', 'school-contact' ) : __( 'εναλλακτικό', 'school-contact' ) ); ?><div class="sc21-staff-email"><span><?php echo esc_html( $email ); ?></span> <span class="description">(<?php echo esc_html( $type ); ?>)</span></div><?php endforeach; ?><?php endif; ?></td><td><span class="sc21-status <?php echo $is_available ? 'is-available' : 'is-unavailable'; ?>"><?php echo $is_available ? esc_html__( 'Διαθέσιμος', 'school-contact' ) : esc_html__( 'Μη διαθέσιμος', 'school-contact' ); ?></span></td><td><div class="sc21-staff-actions"><a href="<?php echo esc_url( sc21_admin_url( 'staff', array_merge( sc21_staff_state_args( $state ), array( 'sc21_action' => 'edit', 'staff_id' => $staff['id'] ?? '' ) ) ) ); ?>"><?php esc_html_e( 'Επεξεργασία', 'school-contact' ); ?></a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc21-quick-action"><input type="hidden" name="action" value="sc21_toggle_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ?? '' ); ?>"><input type="hidden" name="active" value="<?php echo $is_available ? '0' : '1'; ?>"><?php sc21_staff_context_fields( $state ); wp_nonce_field( 'sc21_toggle_staff_' . ( $staff['id'] ?? '' ) ); ?><button type="submit"><?php echo $is_available ? esc_html__( 'Απενεργοποίηση', 'school-contact' ) : esc_html__( 'Ενεργοποίηση', 'school-contact' ); ?></button></form></div></td></tr>
		<?php endforeach; ?>
				</tbody></table>
			</div>
		<?php if ( 'add' === $action ) { sc21_admin_staff_form( array( 'id' => '', 'full_name' => '', 'specialty' => '', 'category' => 'classroom', 'classroom' => '', 'official_email' => '', 'alternative_email' => '', 'active' => false ), $state, $all_staff ); } elseif ( 'edit' === $action ) { foreach ( $all_staff as $staff ) { if ( $edit_id === ( $staff['id'] ?? '' ) ) { sc21_admin_staff_form( wp_parse_args( $staff, array( 'id' => '', 'full_name' => '', 'specialty' => '', 'category' => 'classroom', 'classroom' => '', 'official_email' => '', 'alternative_email' => '', 'active' => false ) ), $state, $all_staff ); break; } } } ?>
		</section>
	<?php endif; ?>
	</div>
	<?php
}
