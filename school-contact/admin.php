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

function sc21_admin_redirect( string $notice, string $tab = 'staff' ): void {
	wp_safe_redirect( add_query_arg( 'sc21_notice', $notice, sc21_admin_url( 'settings' === $tab ? 'settings' : 'staff' ) ) );
	exit;
}

function sc21_admin_post_string( string $key ): ?string {
	if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return null;
	}

	return wp_unslash( $_POST[ $key ] );
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
	if ( null === $name || null === $specialty || null === $category || null === $classroom || null === $official || null === $alternative || ! is_string( $active_raw ) ) {
		return new WP_Error( 'invalid_staff' );
	}
	$name        = sanitize_text_field( $name );
	$specialty   = sanitize_text_field( $specialty );
	$classroom   = sanitize_text_field( $classroom );
	$official    = trim( $official );
	$alternative = trim( $alternative );
	$active      = '1' === $active_raw;
	if ( '' === $name || '' === $specialty || sc21_text_length( $name ) > 120 || sc21_text_length( $specialty ) > 120 || sc21_text_length( $classroom ) > 80 || ! array_key_exists( $category, sc21_categories() ) ) {
		return new WP_Error( 'invalid_staff' );
	}
	if ( ( '' !== $official && ! is_email( $official ) ) || ( '' !== $alternative && ! is_email( $alternative ) ) ) {
		return new WP_Error( 'invalid_email' );
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
		return new WP_Error( 'missing_email' );
	}
	return $staff;
}

function sc21_save_staff(): void {
	sc21_require_admin();
	check_admin_referer( 'sc21_save_staff' );
	$id = sc21_admin_post_string( 'staff_id' );
	if ( null === $id || ( '' !== $id && ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) ) ) {
		sc21_admin_redirect( 'staff_error' );
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
		sc21_admin_redirect( 'staff_error' );
	}
	$staff = sc21_clean_staff( $id );
	if ( is_wp_error( $staff ) ) {
		sc21_admin_redirect( $staff->get_error_code() );
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
	if ( ! sc21_update_options( $options ) ) { sc21_admin_redirect( 'persistence_error' ); }
	sc21_admin_redirect( $updated ? 'staff_updated' : 'staff_added' );
}

function sc21_toggle_staff(): void {
	sc21_require_admin();
	$id     = sc21_admin_post_string( 'staff_id' );
	$active = sc21_admin_post_string( 'active' );
	if ( null === $id || null === $active || ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) || ! in_array( $active, array( '0', '1' ), true ) ) {
		sc21_admin_redirect( 'staff_error' );
	}
	check_admin_referer( 'sc21_toggle_staff_' . $id );
	$options = sc21_get_options();
	foreach ( $options['staff'] as $index => $staff ) {
		if ( ! is_array( $staff ) || $id !== ( $staff['id'] ?? '' ) ) {
			continue;
		}
		if ( '1' === $active && empty( sc21_staff_addresses( $staff ) ) ) {
			sc21_admin_redirect( 'missing_email' );
		}
		$options['staff'][ $index ]['active'] = '1' === $active;
		if ( ! sc21_update_options( $options ) ) { sc21_admin_redirect( 'persistence_error' ); }
		sc21_admin_redirect( '1' === $active ? 'staff_available' : 'staff_unavailable' );
	}
	sc21_admin_redirect( 'staff_error' );
}

function sc21_delete_staff(): void {
	sc21_require_admin();
	$id = sc21_admin_post_string( 'staff_id' );
	if ( null === $id || ! preg_match( '/^[a-f0-9-]{36}$/i', $id ) ) {
		sc21_admin_redirect( 'staff_error' );
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
		sc21_admin_redirect( 'staff_error' );
	}
	if ( ! sc21_update_options( $options ) ) { sc21_admin_redirect( 'persistence_error' ); }
	sc21_admin_redirect( 'staff_deleted' );
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
		'staff_error'       => __( 'Η ενέργεια δεν ήταν έγκυρη.', 'school-contact' ),
		'persistence_error' => __( 'Η αποθήκευση δεν ολοκληρώθηκε. Παρακαλούμε δοκιμάστε ξανά.', 'school-contact' ),
	);
	if ( isset( $messages[ $notice ] ) ) {
		$error = str_ends_with( $notice, 'error' ) || in_array( $notice, array( 'invalid_staff', 'invalid_email', 'missing_email', 'persistence_error' ), true );
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $error ? 'notice-error' : 'notice-success' ), esc_html( $messages[ $notice ] ) );
	}
}

function sc21_staff_sort_value( array $staff ): string {
	$value = (string) ( $staff['full_name'] ?? '' ) . '|' . (string) ( $staff['specialty'] ?? '' );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}

function sc21_filtered_staff( array $staff, string $search, string $availability ): array {
	$items = array_filter( $staff, static function ( $member ) use ( $search, $availability ): bool {
		if ( ! is_array( $member ) ) {
			return false;
		}
		$is_available = sc21_valid_staff( $member );
		if ( ( 'available' === $availability && ! $is_available ) || ( 'unavailable' === $availability && $is_available ) ) {
			return false;
		}
		if ( '' === $search ) {
			return true;
		}
		$haystack = (string) ( $member['full_name'] ?? '' ) . ' ' . (string) ( $member['specialty'] ?? '' );
		return false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $haystack, $search, 0, 'UTF-8' ) : stripos( $haystack, $search ) );
	} );
	usort( $items, static fn( array $a, array $b ): int => strcmp( sc21_staff_sort_value( $a ), sc21_staff_sort_value( $b ) ) );
	return array_values( $items );
}

function sc21_admin_staff_form( array $staff ): void {
	$is_edit = '' !== $staff['id'];
	$form_id = 'sc21-staff-save-' . ( $is_edit ? $staff['id'] : 'new' );
	?>
	<section class="sc21-card sc21-staff-editor" aria-labelledby="sc21-staff-editor-title">
		<header class="sc21-modal-header"><h2 id="sc21-staff-editor-title"><?php echo esc_html( $is_edit ? __( 'Επεξεργασία προσωπικού σχολείου', 'school-contact' ) : __( 'Προσθήκη προσωπικού σχολείου', 'school-contact' ) ); ?></h2><button type="button" class="sc21-dialog-close" aria-label="<?php esc_attr_e( 'Κλείσιμο', 'school-contact' ); ?>">×</button></header>
		<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sc21_save_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ); ?>"><?php wp_nonce_field( 'sc21_save_staff' ); ?>
			<div class="sc21-modal-body"><h3><?php esc_html_e( 'Στοιχεία προσωπικού', 'school-contact' ); ?></h3><div class="sc21-admin-grid"><p class="sc21-field-name"><label for="sc21-full-name"><?php esc_html_e( 'Ονοματεπώνυμο', 'school-contact' ); ?></label><input id="sc21-full-name" name="full_name" type="text" maxlength="120" required value="<?php echo esc_attr( $staff['full_name'] ); ?>"></p><p><label for="sc21-specialty"><?php esc_html_e( 'Ειδικότητα', 'school-contact' ); ?></label><input id="sc21-specialty" name="specialty" type="text" maxlength="120" required value="<?php echo esc_attr( $staff['specialty'] ); ?>"></p><p><label for="sc21-category"><?php esc_html_e( 'Κατηγορία', 'school-contact' ); ?></label><select id="sc21-category" name="category"><?php foreach ( sc21_categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $staff['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p><p><label for="sc21-classroom"><?php esc_html_e( 'Τμήμα', 'school-contact' ); ?></label><input id="sc21-classroom" name="classroom" type="text" maxlength="80" value="<?php echo esc_attr( $staff['classroom'] ); ?>"></p></div><h3><?php esc_html_e( 'Στοιχεία επικοινωνίας', 'school-contact' ); ?></h3><div class="sc21-admin-grid"><p class="sc21-field-full"><label for="sc21-official-email"><?php esc_html_e( 'Υπηρεσιακό email (προαιρετικό)', 'school-contact' ); ?></label><input id="sc21-official-email" name="official_email" type="email" maxlength="254" value="<?php echo esc_attr( $staff['official_email'] ); ?>"></p><p class="sc21-field-full"><label for="sc21-alternative-email"><?php esc_html_e( 'Εναλλακτικό email (προαιρετικό)', 'school-contact' ); ?></label><input id="sc21-alternative-email" name="alternative_email" type="email" maxlength="254" value="<?php echo esc_attr( $staff['alternative_email'] ); ?>"><span class="description"><?php esc_html_e( 'Όταν έχουν καταχωριστεί και οι δύο διευθύνσεις, τα μηνύματα αποστέλλονται και στις δύο.', 'school-contact' ); ?></span></p></div><p class="sc21-availability-field"><label><input name="active" type="checkbox" value="1" <?php checked( ! empty( $staff['active'] ) ); ?>> <span><?php esc_html_e( 'Διαθέσιμος ως παραλήπτης', 'school-contact' ); ?></span></label><span class="description"><?php esc_html_e( 'Οι διαθέσιμοι παραλήπτες εμφανίζονται στη δημόσια φόρμα επικοινωνίας.', 'school-contact' ); ?></span></p></div>
		</form>
		<footer class="sc21-modal-footer"><div><?php if ( $is_edit ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc21-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Να διαγραφεί αυτό το μέλος του προσωπικού;', 'school-contact' ) ); ?>');"><input type="hidden" name="action" value="sc21_delete_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ); ?>"><?php wp_nonce_field( 'sc21_delete_staff_' . $staff['id'] ); ?><button type="submit" class="button-link-delete"><?php esc_html_e( 'Διαγραφή', 'school-contact' ); ?></button></form><?php endif; ?></div><div class="sc21-modal-footer-actions"><a class="button" href="<?php echo esc_url( sc21_admin_url() ); ?>"><?php esc_html_e( 'Ακύρωση', 'school-contact' ); ?></a><button form="<?php echo esc_attr( $form_id ); ?>" type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Αποθήκευση', 'school-contact' ) : __( 'Προσθήκη', 'school-contact' ) ); ?></button></div></footer>
	</section>
	<?php
}

function sc21_render_help_page(): void {
	?>
	<section class="sc21-card sc21-help-intro">
		<img class="sc21-product-logo" src="<?php echo esc_url( SC21_PLUGIN_URL . 'assets/logo-horizontal.svg' ); ?>" alt="SchoolContact">
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

function sc21_admin_page(): void {
	sc21_require_admin();
	$options = sc21_get_options();
	$tab     = sc21_admin_tab();
	?>
	<div class="wrap sc21-admin"><h1>SchoolContact</h1><nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Ενότητες SchoolContact', 'school-contact' ); ?>"><a class="nav-tab <?php echo 'staff' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url() ); ?>"><?php esc_html_e( 'Προσωπικό', 'school-contact' ); ?></a><a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url( 'settings' ) ); ?>"><?php esc_html_e( 'Γενικές ρυθμίσεις', 'school-contact' ); ?></a><a class="nav-tab <?php echo 'about' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( sc21_admin_url( 'about' ) ); ?>"><?php esc_html_e( 'Βοήθεια & πληροφορίες', 'school-contact' ); ?></a></nav><?php sc21_admin_notice(); ?>
	<?php if ( 'settings' === $tab ) : ?>
		<section class="sc21-card"><h2><?php esc_html_e( 'Γενικές ρυθμίσεις', 'school-contact' ); ?></h2><p><?php esc_html_e( 'Τα μηνύματα προς το προσωπικό σχολείου κοινοποιούνται αυτόματα στην email διεύθυνση σχολείου.', 'school-contact' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sc21_save_general"><?php wp_nonce_field( 'sc21_save_general' ); ?><p><label for="sc21-school-name"><?php esc_html_e( 'Όνομα σχολείου', 'school-contact' ); ?></label><input id="sc21-school-name" name="school_name" type="text" maxlength="150" required value="<?php echo esc_attr( $options['school_name'] ); ?>"></p><p><label for="sc21-principal-name"><?php esc_html_e( 'Ονοματεπώνυμο διευθυντή / διευθύντριας', 'school-contact' ); ?></label><input id="sc21-principal-name" name="principal_name" type="text" maxlength="120" value="<?php echo esc_attr( $options['principal_name'] ?? '' ); ?>"></p><p><label for="sc21-admin-email"><?php esc_html_e( 'Email διεύθυνσης σχολείου', 'school-contact' ); ?></label><input id="sc21-admin-email" name="admin_email" type="email" maxlength="254" required value="<?php echo esc_attr( $options['admin_email'] ); ?>"></p><?php submit_button( __( 'Αποθήκευση ρυθμίσεων', 'school-contact' ) ); ?></form></section><div class="notice notice-info inline"><p><?php esc_html_e( 'Οι εναλλακτικές διευθύνσεις email μπορεί να μην ανήκουν στο sch.gr. Χρησιμοποιήστε τες μόνο όταν έχουν εγκριθεί από το σχολείο. Όταν υπάρχουν και οι δύο διευθύνσεις, η επικοινωνία αποστέλλεται και στις δύο.', 'school-contact' ); ?></p><p><?php esc_html_e( 'Το κείμενο απορρήτου της δημόσιας φόρμας είναι προσωρινό και χρειάζεται έγκριση από το σχολείο.', 'school-contact' ); ?></p></div><p><?php esc_html_e( 'Shortcode:', 'school-contact' ); ?> <code>[school_communication]</code></p>
	<?php elseif ( 'about' === $tab ) : ?>
		<?php sc21_render_help_page(); ?>
	<?php else :
		$search = isset( $_GET['sc21_search'] ) && is_string( $_GET['sc21_search'] ) ? sanitize_text_field( wp_unslash( $_GET['sc21_search'] ) ) : '';
		$availability = isset( $_GET['sc21_availability'] ) && is_string( $_GET['sc21_availability'] ) ? sanitize_key( wp_unslash( $_GET['sc21_availability'] ) ) : 'all';
		$availability = in_array( $availability, array( 'all', 'available', 'unavailable' ), true ) ? $availability : 'all';
		$all_staff = array_values( array_filter( $options['staff'], 'is_array' ) );
		$available_count = count( array_filter( $all_staff, 'sc21_valid_staff' ) );
		$staff_items = sc21_filtered_staff( $all_staff, $search, $availability );
		$action = isset( $_GET['sc21_action'] ) && is_string( $_GET['sc21_action'] ) ? sanitize_key( wp_unslash( $_GET['sc21_action'] ) ) : '';
		$edit_id = isset( $_GET['staff_id'] ) && is_string( $_GET['staff_id'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_id'] ) ) : '';
		?>
		<div class="sc21-staff-toolbar">
			<div class="sc21-counts"><span><?php printf( esc_html__( 'Σύνολο: %d', 'school-contact' ), count( $all_staff ) ); ?></span><span class="sc21-available"><?php printf( esc_html__( 'Διαθέσιμοι: %d', 'school-contact' ), $available_count ); ?></span><span class="sc21-unavailable"><?php printf( esc_html__( 'Μη διαθέσιμοι: %d', 'school-contact' ), count( $all_staff ) - $available_count ); ?></span></div>
			<a class="button button-primary" href="<?php echo esc_url( sc21_admin_url( 'staff', array( 'sc21_action' => 'add' ) ) ); ?>"><?php esc_html_e( 'Προσθήκη προσωπικού', 'school-contact' ); ?></a>
		</div>
		<form class="sc21-staff-filters" method="get"><input type="hidden" name="page" value="sc21-school-communication"><input type="hidden" name="tab" value="staff"><label for="sc21-search" class="screen-reader-text"><?php esc_html_e( 'Αναζήτηση προσωπικού', 'school-contact' ); ?></label><input id="sc21-search" name="sc21_search" type="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Αναζήτηση ονόματος ή ειδικότητας', 'school-contact' ); ?>"><label for="sc21-availability" class="screen-reader-text"><?php esc_html_e( 'Διαθεσιμότητα', 'school-contact' ); ?></label><select id="sc21-availability" name="sc21_availability"><option value="all" <?php selected( $availability, 'all' ); ?>><?php esc_html_e( 'Όλο το προσωπικό', 'school-contact' ); ?></option><option value="available" <?php selected( $availability, 'available' ); ?>><?php esc_html_e( 'Διαθέσιμοι', 'school-contact' ); ?></option><option value="unavailable" <?php selected( $availability, 'unavailable' ); ?>><?php esc_html_e( 'Μη διαθέσιμοι', 'school-contact' ); ?></option></select><?php submit_button( __( 'Φιλτράρισμα', 'school-contact' ), 'secondary', '', false ); ?></form>
		<table class="widefat striped sc21-staff-table"><thead><tr><th><?php esc_html_e( 'Ονοματεπώνυμο', 'school-contact' ); ?></th><th><?php esc_html_e( 'Κατηγορία', 'school-contact' ); ?></th><th><?php esc_html_e( 'Τμήμα / Ειδικότητα', 'school-contact' ); ?></th><th><?php esc_html_e( 'Email παραληπτών', 'school-contact' ); ?></th><th><?php esc_html_e( 'Διαθεσιμότητα', 'school-contact' ); ?></th><th><?php esc_html_e( 'Ενέργειες', 'school-contact' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $staff_items ) ) : ?><tr><td colspan="6"><?php esc_html_e( 'Δεν βρέθηκε προσωπικό με αυτά τα κριτήρια.', 'school-contact' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $staff_items as $staff ) : $is_available = sc21_valid_staff( $staff ); $emails = sc21_staff_addresses( $staff ); $official = strtolower( sanitize_email( (string) ( $staff['official_email'] ?? '' ) ) ); $alternative = strtolower( sanitize_email( (string) ( $staff['alternative_email'] ?? '' ) ) ); ?>
			<tr><td><?php echo esc_html( $staff['full_name'] ?? '' ); ?></td><td><?php echo esc_html( sc21_categories()[ $staff['category'] ?? '' ] ?? '' ); ?></td><td><?php echo esc_html( trim( ( $staff['classroom'] ?? '' ) . ' · ' . ( $staff['specialty'] ?? '' ), ' ·' ) ); ?></td><td><?php if ( empty( $emails ) ) : ?>-<?php else : ?><?php foreach ( $emails as $email ) : $key = strtolower( $email ); $type = $key === $official && $key === $alternative ? __( 'υπηρεσιακό και εναλλακτικό', 'school-contact' ) : ( $key === $official ? __( 'υπηρεσιακό', 'school-contact' ) : __( 'εναλλακτικό', 'school-contact' ) ); ?><div><?php echo esc_html( $email ); ?> <span class="description">(<?php echo esc_html( $type ); ?>)</span></div><?php endforeach; ?><?php endif; ?></td><td><span class="sc21-status <?php echo $is_available ? 'is-available' : 'is-unavailable'; ?>"><?php echo $is_available ? esc_html__( 'Διαθέσιμος', 'school-contact' ) : esc_html__( 'Μη διαθέσιμος', 'school-contact' ); ?></span></td><td><a href="<?php echo esc_url( sc21_admin_url( 'staff', array( 'sc21_action' => 'edit', 'staff_id' => $staff['id'] ?? '' ) ) ); ?>"><?php esc_html_e( 'Επεξεργασία', 'school-contact' ); ?></a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc21-quick-action"><input type="hidden" name="action" value="sc21_toggle_staff"><input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ?? '' ); ?>"><input type="hidden" name="active" value="<?php echo $is_available ? '0' : '1'; ?>"><?php wp_nonce_field( 'sc21_toggle_staff_' . ( $staff['id'] ?? '' ) ); ?><button type="submit"><?php echo $is_available ? esc_html__( 'Απενεργοποίηση', 'school-contact' ) : esc_html__( 'Ενεργοποίηση', 'school-contact' ); ?></button></form></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( 'add' === $action ) { sc21_admin_staff_form( array( 'id' => '', 'full_name' => '', 'specialty' => '', 'category' => 'classroom', 'classroom' => '', 'official_email' => '', 'alternative_email' => '', 'active' => false ) ); } elseif ( 'edit' === $action ) { foreach ( $all_staff as $staff ) { if ( $edit_id === ( $staff['id'] ?? '' ) ) { sc21_admin_staff_form( wp_parse_args( $staff, array( 'id' => '', 'full_name' => '', 'specialty' => '', 'category' => 'classroom', 'classroom' => '', 'official_email' => '', 'alternative_email' => '', 'active' => false ) ) ); break; } } } ?>
	<?php endif; ?>
	</div>
	<?php
}
