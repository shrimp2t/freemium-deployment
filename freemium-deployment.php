<?php
/*
Plugin Name: Freemium Deployment
Plugin URL: https://www.famethemes.com/
Description: Deploy source code to Free and Pro version and auto sync with EDD download files.
Version: 1.1.1
Author: Shrimp2t
Author URI: https://www.famethemes.com/
*/

// if ( is_admin() ) {
require_once dirname( __FILE__ ) . '/inc/class-finder.php';
require_once dirname( __FILE__ ) . '/inc/class-post-type.php';
require_once dirname( __FILE__ ) . '/inc/class-github.php';
require_once dirname( __FILE__ ) . '/inc/class-deploy.php';
require_once dirname( __FILE__ ) . '/inc/class-zip.php';
require_once dirname( __FILE__ ) . '/inc/functions.php';
require_once dirname( __FILE__ ) . '/inc/class-ajax.php';
require_once dirname( __FILE__ ) . '/inc/class-webhook.php';

add_action( 'admin_menu', 'fd_register_ref_page', 20 );

	/**
	 * Adds a submenu page under a custom post type parent.
	 */
function fd_register_ref_page() {
	add_submenu_page(
		'options-general.php',
		esc_html__( 'Freemium Deployment', 'textdomain' ),
		esc_html__( 'Freemium Deployment', 'textdomain' ),
		'manage_options',
		'freemium-deployment',
		'fd_page_callback'
	);
}


	/**
	 * Display callback for the submenu page.
	 */
function fd_page_callback() {
	if ( isset( $_POST['github_token'] ) ) {
		update_option( 'fd_github_token', sanitize_text_field( $_POST['github_token'] ) );
	}

	?>
		<div class="wrap">
			<h1><?php _e( 'Freemium Deployment Settings', 'textdomain' ); ?></h1>
			<div id="fd_form_msg"></div>
			<form id="deploy-form" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'fd_settings', 'fd_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="github_token"><?php esc_html_e( 'Github Token', 'textdomain' ); ?></label></th>
						<td>
							<input name="github_token" id="github_token" value="<?php echo esc_attr( get_option( 'fd_github_token' ) ); ?>" class="regular-text" type="text">
							<p class="description">Get your token <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a> </p>
						</td>
					</tr>
				</table>
				<p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p>
			</form>
		</div>
		<?php
}


function fd_load_custom_wp_admin_style( $hook ) {
	wp_enqueue_media();
	$url = trailingslashit( plugins_url( '/', __FILE__ ) );
	wp_enqueue_style( 'fd_admin_css', $url . 'assets/css/admin.css' );
	$add = true;
	if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
		if ( get_post_type() != 'deployment' ) {
			$add = false;
		}
	} elseif ( $hook != 'tools_page_freemium-deployment' ) {
		$add = false;
	}

	if ( $add ) {
		$url = trailingslashit( plugins_url( '/', __FILE__ ) );
		wp_enqueue_script( 'fd_admin_js', $url . 'assets/js/admin.js' );
		wp_localize_script(
			'fd_admin_js',
			'FD_Config',
			array(
				'nonce' => wp_create_nonce( 'fd_deploy_nonce' ),
				'deploying' => 'Deploying...',
				'deploy_url' => fd_get_upload_url(),
			)
		);
	}

}

	add_action( 'admin_enqueue_scripts', 'fd_load_custom_wp_admin_style' );
// }
/*
if ( isset( $_GET['debug'] ) ) {

	$f = new FD_String_Finder();
	$f->key = 'megamenuwp_is__premium';
	$dir = dirname(__FILE__);
	$content = file_get_contents($dir . '/upload/test-file.php');

	$content = $f->remove_premium_function($content);
	$content = $f->deploy_code($content);
	$save_file = fopen($dir . '/upload/deploy-test.php', 'w');
	fwrite($save_file, $content);
	fclose($save_file);

// echo '<pre>';
// echo $content;
// echo '</pre>';

	var_dump(strlen('if ( megamenuwp_is__premium() == true ) {
			$template_folders[] = MAGAZINE_MEGA_MENU_PATH.\'premium/templates/\';
		}'));

	die();
}
*/
