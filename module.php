<?php
/**
 * Plugin Name: JP Theme Tools Post Module
 * Plugin URI: https://github.com/jprieton/jp-theme-tools-polls-module/
 * Description: Post module for JP Theme Tools
 * Version: 0.1.0
 * Author: Javier Prieto
 * Text Domain: jptt
 * Domain Path: /languages
 * Author URI: https://github.com/jprieton/
 * License: GPL2
 */
defined( 'ABSPATH' ) or die( 'No direct script access allowed' );

define( 'JPTT_POST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'JPTT_POST_PLUGIN_URI', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, function() {
	global $wpdb;
	$wpdb instanceof \wpdb;
	$charset = !empty( $wpdb->charset ) ?
					"DEFAULT CHARACTER SET {$wpdb->charset}" :
					'';

	$collate = !empty( $wpdb->collate ) ?
					"COLLATE {$wpdb->collate}" :
					'';

	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}favorite` ("
					. "`favorite_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,"
					. "`post_id` bigint(20) unsigned NOT NULL DEFAULT '0',"
					. "`user_id` bigint(20) DEFAULT NULL,"
					. "PRIMARY KEY (`favorite_id`),"
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

	$is_favorite = (bool) $wpdb->get_var( "SELECT favorite_id FROM {$wpdb->prefix}favorite WHERE post_id = '{$post_id}' AND user_id = '{$user_id}' LIMIT 1" );

	if ( !$is_favorite ) {
		$wpdb->insert( "{$wpdb->prefix}favorite", compact( 'post_id', 'user_id' ) );
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
