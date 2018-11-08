<?php

/**
 * @package           Polylang_Comments_Merger
 *
 * @wordpress-plugin
 * Plugin Name:       Polylang Comments Merger
 * Plugin URI:        http://rbnet.it
 * Description:       Merges comments from all translations of the posts in Polylang. <strong>Absolutely no support!</strong>
 * Version:           0.5.8
 * Author:            Roberto Bolli
 * Author URI:        http://rbnet.it
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rbnet-pcmerger
 * Domain Path:       /languages/
 */

// Built using code from no longer maintained WPML Comment Merging plugin: http://wordpress.org/extend/plugins/wpml-comment-merging/
// Thanks to Rhialto (http://rhialto.com) for the hints and Jonathan (https://wordpress.org/support/users/jonathanmoorebcsorg/) for original code.

// Safety check
add_action( 'admin_init', 'rbnet_test_polylang_installation' );
function rbnet_test_polylang_installation() {
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'polylang/polylang.php' ) ) {
        add_action( 'admin_notices', 'rbnet_plugin_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

function rbnet_plugin_notice() {
	$class = 'notice notice-error';
	$message = __( '<strong>Polylang Comments Merger</strong> requires <strong>Polylang</strong> plugin to be installed and active.', 'rbnet-pcmerger' );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
}

// Define constants
if ( !defined( 'RBNETPM_BASE' ) ) {
    define( 'RBNETPM_BASE', plugin_basename( __FILE__ ) );
}


// Load plugin textdomain
if ( !function_exists( 'rbnet_load_textdomain' ) ) {

    function rbnet_load_textdomain() {
        $path = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
        load_plugin_textdomain( 'rbnet-pcmerger', false, $path );
    }
    add_action( 'init', 'rbnet_load_textdomain' );
}

// Sort merged comments
function sort_merged_comments($a, $b) {
	return $a->comment_ID - $b->comment_ID;
}

// Merge comments on frontend
function merge_comments($comments, $post_ID) {

	global $polylang;

	$translationIds = PLL()->model->post->get_translations($post_ID);

	foreach ( $translationIds as $key=>$translationID ){
		if( $translationID != $post_ID ) {
			$translatedPostComments = get_comments( array('post_id' => $translationID, 'status' => 'approve', 'order' => 'ASC') );
			if ( $translatedPostComments ) {
				$comments = array_merge($comments, $translatedPostComments);
			}
		}
	}

	// re-sort merged comment array
	if ( count($translationIds) >1 ) {
		usort($comments, 'sort_merged_comments');
	}

	return $comments;
}
add_filter('comments_array', 'merge_comments', 100, 2);


// Count merged comments - In AdminCP leaves comments per translations
function merge_comment_count($count, $post_ID) {

	if ( !is_admin() ){
		global $polylang;
  
		$translationIds = PLL()->model->post->get_translations($post_ID);
    
		foreach ( $translationIds as $key=>$translationID ){
			if( $translationID != $post_ID ) {
				$translatedPost = get_post($translationID);
				if ( $translatedPost ) {
					$count = $count + $translatedPost->comment_count;
				}
			}
		}
	}
    return $count;
}
add_filter('get_comments_number', 'merge_comment_count', 100, 2);

// Stop Polylang filtering comments
function polylang_remove_comments_filter() {
    global $polylang;
    remove_filter('comments_clauses', array(&$polylang->filters, 'comments_clauses'));
}
add_action('wp','polylang_remove_comments_filter');

// Add custom meta link on plugin list page
if ( !function_exists( 'rbnet_meta_links' ) ) {

    function rbnet_meta_links( $links, $file ) {
        if ( $file == RBNETPM_BASE ) {
            $links[] = '<a href="https://www.paypal.me/RobertoBolli/2" target="_blank" title="' . __( 'Donate', 'rbnet-pcmerger' ) . '"><strong>' . __( 'Donate', 'rbnet-pcmerger' ) . '</strong></a>';
        }
        return $links;
    }
    add_filter( 'plugin_row_meta', 'rbnet_meta_links', 10, 2 );
}
