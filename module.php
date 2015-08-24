<?php
/**
 * Plugin Name: JP Theme Tools Post Module
 * Plugin URI: https://github.com/jprieton/jp-theme-tools-polls-module/
 * Description: Post module for JP Theme Tools
 * Version: 0.2.0
 * Author: Javier Prieto
 * Text Domain: jptt
 * Domain Path: /languages
 * Author URI: https://github.com/jprieton/
 * License: GPL2
 */
defined( 'ABSPATH' ) or die( 'No direct script access allowed' );

define( 'JPTT_POST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'JPTT_POST_PLUGIN_URI', plugin_dir_url( __FILE__ ) );

/**
 * Indica si el usuario ha votado en un post
 * @global \wpdb $wpdb
 * @param int $post_id
 * @param int $user_id
 * @return boolean
 */
function has_voted( $post_id = NULL, $user_id = NULL ) {
	global $wpdb;
	$wpdb instanceof \wpdb;

	$user_id = ((int) $user_id)? : get_current_user_id();
	$post_id = ((int) $post_id) ? : get_the_ID();

	if ( !$user_id && !$post_id ) {
		return FALSE;
	}

	$is_voted = (bool) $wpdb->get_var( "SELECT vote_id FROM {$wpdb->prefix}favorite WHERE post_id = '{$post_id}' AND user_id = '{$user_id}' LIMIT 1" );

	return $is_voted;
}

/**
 * Indica si el post esta marcado como favorito por el usuario
 * @global \wpdb $wpdb
 * @param int $post_id
 * @param int $user_id
 * @return boolean
 */
function is_favorite( $post_id = NULL, $user_id = NULL ) {
	global $wpdb;
	$wpdb instanceof \wpdb;

	$user_id = ((int) $user_id)? : get_current_user_id();
	$post_id = ((int) $post_id) ? : get_the_ID();

	if ( !$user_id && !$post_id ) {
		return FALSE;
	}

	$is_favorite = (bool) $wpdb->get_var( "SELECT vote_id FROM {$wpdb->prefix}favorite WHERE post_id = '{$post_id}' AND user_id = '{$user_id}' LIMIT 1" );

	return $is_favorite;
}

register_activation_hook( __FILE__, function() {
	global $wpdb;
	$wpdb instanceof \wpdb;
	$charset = !empty( $wpdb->charset ) ?
					"DEFAULT CHARACTER SET {$wpdb->charset}" :
					'';

	$collate = !empty( $wpdb->collate ) ?
					"COLLATE {$wpdb->collate}" :
					'';

	$favorite_query = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}favorite` ("
					. "`favorite_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,"
					. "`post_id` bigint(20) unsigned NOT NULL DEFAULT '0',"
					. "`user_id` bigint(20) DEFAULT NULL,"
					. "PRIMARY KEY (`favorite_id`),"
					. "KEY `post_id` (`post_id`)"
					. ") ENGINE=InnoDB {$charset} {$collate} AUTO_INCREMENT=1";
	$wpdb->query( $favorite_query );

	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}votes` ("
					. "`vote_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,"
					. "`post_id` bigint(20) unsigned NOT NULL DEFAULT '0',"
					. "`user_id` bigint(20) DEFAULT NULL,"
					. "`vote_value` bigint(20),"
					. "PRIMARY KEY (`vote_id`),"
					. "KEY `post_id` (`post_id`)"
					. ") ENGINE=InnoDB {$charset} {$collate} AUTO_INCREMENT=1";
	$wpdb->query( $query );
} );

add_action( 'wp_ajax_user_post_toggle_favorite', function () {

	global $wpdb;
	$wpdb instanceof \wpdb;

	$Input = new \jptt\core\Input();
	$userdata = get_userdata( get_current_user_id() );

	$post_id = (int) $Input->get( 'post_id' );
	$user_id = (int) $userdata->ID;

	if ( !is_favorite( $post_id, $user_id ) ) {
		$wpdb->insert( "{$wpdb->prefix}favorite", compact( 'post_id', 'user_id' ) );
		wp_send_json( TRUE );
	} else {
		wp_send_json( FALSE );
	}
}, 10 );

add_action( 'wp_ajax_user_post_vote', function () {

	global $wpdb;
	$wpdb instanceof \wpdb;

	$Input = new \jptt\core\Input();
	$userdata = get_userdata( get_current_user_id() );

	$post_id = (int) $Input->get( 'post_id' );
	$user_id = (int) $userdata->ID;
	$vote_value = (int) $Input->get( 'post_id' );

	if ( !has_voted( $post_id, $user_id ) ) {
		$wpdb->insert( "{$wpdb->prefix}voted", compact( 'post_id', 'user_id', 'vote_value' ) );
		wp_send_json( TRUE );
	} else {
		$wpdb->delete( "{$wpdb->prefix}favorite", compact( 'post_id', 'user_id' ) );
		wp_send_json( FALSE );
	}
}, 10 );

add_action( 'wp_ajax_user_post_visited', function() {
	$Input = new \jptt\core\Input();
	$post_id = (int) $Input->get( 'post_id' );

	$visit_count = (int) get_post_meta( $post_id, '_visit_count', TRUE );
	$visit_last_ip = get_post_meta( $post_id, '_visit_last_ip', TRUE );

	$visit_ip = ip2long( $Input->ip_address() );

	if ( in_array( $visit_ip, (array) $visit_last_ip ) ) {
		wp_send_json( FALSE );
	}

	if ( is_array( $visit_last_ip ) ) {
		$visit_last_ip = array( $visit_ip );
	} else {
		$visit_last_ip[] = $visit_ip;
		$visit_count++;
	}

	update_post_meta( $post_id, '_visit_count', $visit_count );
	update_post_meta( $post_id, '_visit_last_ip', $visit_last_ip );
}, 10 );

add_action( 'wp_ajax_nopriv_user_post_visited', function() {
	do_action( 'wp_ajax_user_post_visited' );
}, 10 );

add_action( 'wp_footer', function() {
	if ( !is_singular() ) return FALSE;
	?>
	<script>
		$(function () {
				jQuery.get('<?php echo admin_url( 'admin-ajax.php' ) ?>', {post_id: 1000, action: 'user_post_visited'});
		});
	</script>
	<?php
} );
