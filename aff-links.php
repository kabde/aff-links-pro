<?php
/**
 * Plugin Name: Aff Links Pro
 * Description: Gestion professionnelle de liens d'affiliation avec CPT, redirections /go/, QR codes et shortcode.
 * Version:     1.2.1
 * Author:      Abderrahim Khalid
 * License:     GPL-2.0+
 * Text Domain: aff-links
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AFF_LINKS_PRO_VERSION', '1.2.1' );

/*--------------------------------------------------------------
# 1. CPT « aff » (permalien /go/slug)
--------------------------------------------------------------*/
add_action( 'init', function () {

	$labels = array(
		'name'          => __( 'Affiliations',         'aff-links' ),
		'singular_name' => __( 'Affiliation',          'aff-links' ),
		'menu_name'     => __( 'Affiliations',         'aff-links' ),
		'add_new_item'  => __( 'Nouvelle affiliation', 'aff-links' ),
		'edit_item'     => __( 'Modifier affiliation', 'aff-links' ),
		'new_item'      => __( 'Nouvelle affiliation', 'aff-links' ),
		'view_item'     => __( 'Voir affiliation',     'aff-links' ),
		'search_items'  => __( 'Rechercher',           'aff-links' ),
		'not_found'     => __( 'Aucune trouvée',       'aff-links' ),
	);

	register_post_type( 'aff', array(
		'labels'             => $labels,
		'public'             => true,
		'exclude_from_search'=> true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_icon'          => 'dashicons-admin-links',
		'supports'           => array( 'title' ),
		'show_in_nav_menus'  => true,
		'rewrite'            => array(
			'slug'       => 'go',
			'with_front' => false,
			'feeds'      => false,
			'pages'      => false,
		),
	) );
} );

/*--------------------------------------------------------------
# 2. Metabox : URL d’affiliation
--------------------------------------------------------------*/
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'aff_links_url_box',
		__( 'Affiliate URL', 'aff-links' ),
		function ( $post ) {
			$value = esc_url( get_post_meta( $post->ID, '_aff_url', true ) );
			wp_nonce_field( 'aff_links_save_url', 'aff_links_url_nonce' );
			echo '<input type="url" name="aff_url" style="width:100%" placeholder="https://..." value="' . $value . '" required>';
		},
		'aff',
		'normal',
		'high'
	);
} );

add_action( 'save_post_aff', function ( $post_id ) {
	$nonce = isset( $_POST['aff_links_url_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aff_links_url_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'aff_links_save_url' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['aff_url'] ) ) {
		$url = esc_url_raw( trim( wp_unslash( $_POST['aff_url'] ) ) );
		if ( $url ) {
			update_post_meta( $post_id, '_aff_url', $url );
		} else {
			delete_post_meta( $post_id, '_aff_url' );
		}
	}
} );

/*--------------------------------------------------------------
# 3. Colonnes admin : Destination | Lien /go/ | QR Code
--------------------------------------------------------------*/
add_filter( 'manage_aff_posts_columns', function ( $cols ) {
	$cols['aff_dest'] = __( 'Destination', 'aff-links' );
	$cols['aff_link'] = __( 'Lien /go/',  'aff-links' );
	$cols['aff_qr']   = __( 'QR Code',    'aff-links' );
	return $cols;
} );

add_action( 'manage_aff_posts_custom_column', function ( $col, $post_id ) {

	$permalink = get_permalink( $post_id );

	if ( 'aff_dest' === $col ) {
		echo esc_url( get_post_meta( $post_id, '_aff_url', true ) );

	} elseif ( 'aff_link' === $col ) {
		echo '<code>' . esc_url( $permalink ) . '</code>';

	} elseif ( 'aff_qr' === $col ) {
		$qr = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . rawurlencode( $permalink );
		echo '<img src="' . esc_url( $qr ) . '" alt="QR" width="60" height="60">';
	}
}, 10, 2 );

/*--------------------------------------------------------------
# 4. Redirection : /go/slug → URL d’affiliation
--------------------------------------------------------------*/
add_action( 'template_redirect', function () {
	if ( is_singular( 'aff' ) ) {
		$url = get_post_meta( get_queried_object_id(), '_aff_url', true );
		if ( $url && wp_http_validate_url( $url ) ) {
			wp_redirect( esc_url_raw( $url ), 302 ); // mettre 301 si permanent
			exit;
		}
	}
} );

/*--------------------------------------------------------------
# 5. Short-code : [aff_link id="123"] ou [aff_link slug="amazon"]
--------------------------------------------------------------*/
add_shortcode( 'aff_link', function ( $atts ) {

	$atts = shortcode_atts(
		array( 'id' => 0, 'slug' => '' ),
		$atts,
		'aff_link'
	);

	$post = $atts['id']
		? get_post( intval( $atts['id'] ) )
		: get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, 'aff' );

	if ( ! $post || 'aff' !== $post->post_type ) {
		return '';
	}

	return '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a>';
} );

register_activation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
