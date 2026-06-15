<?php
/**
 * Plugin Name: Web Majstor — Kontakt forma
 * Description: AJAX endpoint za kontakt formu sa naslovne stranice. Poruke se čuvaju u admin panelu (Poruke sa sajta) i šalju na admin email.
 * Author: Web Majstor
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type( 'wm_poruka', [
		'labels' => [
			'name'          => 'Poruke sa sajta',
			'singular_name' => 'Poruka',
			'menu_name'     => 'Poruke sa sajta',
		],
		'public'       => false,
		'show_ui'      => true,
		'menu_icon'    => 'dashicons-email-alt',
		'supports'     => [ 'title', 'editor', 'custom-fields' ],
		'map_meta_cap' => true,
		'capabilities' => [ 'create_posts' => 'do_not_allow' ],
	] );
} );

function wm_handle_contact() {
	// Honeypot — botovi popune skriveno polje, ljudima je nevidljivo.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success( [ 'message' => 'OK' ] );
	}

	$ime     = sanitize_text_field( wp_unslash( $_POST['ime'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$telefon = sanitize_text_field( wp_unslash( $_POST['telefon'] ?? '' ) );
	$usluga  = sanitize_text_field( wp_unslash( $_POST['usluga'] ?? '' ) );

	$dozvoljene_usluge = [ 'Web dizajn', 'Automatizacija procesa', 'QA testiranje', 'Drugo' ];

	if ( '' === $ime || ! is_email( $email ) || ! in_array( $usluga, $dozvoljene_usluge, true ) ) {
		wp_send_json_error( [ 'message' => 'Molimo popunite ispravno sva obavezna polja.' ], 400 );
	}

	$sadrzaj = sprintf(
		"Ime i prezime: %s\nEmail: %s\nTelefon: %s\nUsluga: %s\nDatum: %s",
		$ime,
		$email,
		$telefon ?: '—',
		$usluga,
		wp_date( 'd.m.Y H:i' )
	);

	$post_id = wp_insert_post( [
		'post_type'    => 'wm_poruka',
		'post_status'  => 'publish',
		'post_title'   => sprintf( '%s — %s', $ime, $usluga ),
		'post_content' => $sadrzaj,
	] );

	if ( $post_id ) {
		update_post_meta( $post_id, 'email', $email );
		update_post_meta( $post_id, 'telefon', $telefon );
		update_post_meta( $post_id, 'usluga', $usluga );
	}

	// Na lokalnom serveru mail najčešće ne radi — ne sme da obori zahtev.
	if ( function_exists( 'wp_mail' ) ) {
		@wp_mail(
			get_option( 'admin_email' ),
			'Nova poruka sa sajta — ' . $ime,
			$sadrzaj . "\n\nOdgovorite na: " . $email
		);
	}

	wp_send_json_success( [ 'message' => 'Hvala, Vaša poruka je poslata. Uskoro ćemo Vam se javiti.' ] );
}
add_action( 'wp_ajax_wm_contact', 'wm_handle_contact' );
add_action( 'wp_ajax_nopriv_wm_contact', 'wm_handle_contact' );

// Fix: sekcija sa kontakt formom mora biti iznad 100vh sekcija koje je blokiraju.
add_action( 'wp_head', function () {
	echo '<style>
._100vh {
	pointer-events: none;
}
._100vh * {
	pointer-events: auto;
}
</style>';
} );
