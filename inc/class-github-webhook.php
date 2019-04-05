<?php
class FD_Github_Webhook {
	public $id;

	function __construct( $development_id ) {
		$this->id = $development_id;
	}

	function get_nonce() {
		$token = get_post_meta( $this->id, '_github_webhook_nonce', true );
		if ( ! $token ) {
			$token = wp_create_nonce( 'github_nonce_' . $this->id );
			update_post_meta( $this->id, '_github_webhook_nonce', $token );
		}
		return $token;
	}

	function webhooks_url() {
		$url = home_url( '/' );
		return add_query_arg(
			array(
				'webhook_deployment' => $this->id,
				'nonce' => $this->get_nonce(),
			),
			$url
		);
	}



}

class FD_Github_Webhook_Init {

	private $errors = array();

	function __construct() {
		add_action( 'wp', array( $this, 'init' ) );
	}


	function init() {

		if ( ! isset( $_REQUEST['webhook_deployment'] ) || ! $_REQUEST['webhook_deployment'] ) {
			// do nothing because not web hook respond.
			return;
		}
		if ( ! isset( $_REQUEST['nonce'] ) ) {
			// do nothing because not web hook respond.
			die( 'Invalid nonce' );
		}

		$deploy_id = absint( $_REQUEST['webhook_deployment'] );
		$post = get_post( $deploy_id );
		if ( ! $post ) {
			die( 'Deployment not found.' );
		}
		$nonce = $_REQUEST['nonce'];
		$_nonce = get_post_meta( $post->ID, '_github_webhook_nonce', true );
		if ( $_nonce !== $nonce ) {
			die( 'Security check' );
		}

		if ( ! isset( $_POST['payload'] ) ) {
			die( 'Invalid data' );
		}

		$payload = wp_unslash( $_POST['payload'] );
		// var_dump( $payload );
		$payload = json_decode( $payload, true );

		if ( ! is_array( $payload ) ) {
			die( 'Invalid data structure' );
		}

		if ( $payload['action'] !== 'published' ) {
			die( 'Draft version, do nothing.' );
		}

		if ( ! isset( $payload['release'] ) || ! is_array( $payload['release'] ) ) {
			die( 'Invalid release version.' );
		}

		$repo_version = $payload['release']['name'];
		$repository_full_name = $payload['repository']['full_name'];

		// $repo_version = 'v1.0.1';
		// $repository_full_name = 'FameThemes/easymega';
		$nice_name = basename( $repository_full_name );
		$download_path = $this->download( $repository_full_name, $repo_version );

		var_dump( $repository_full_name );
		var_dump( $repo_version );
		var_dump( $download_path );
		var_dump( file_exists( $download_path ) );

		if ( $download_path ) {
			$d = new FD_Deploy();
			$r_free = $d->deploy_free( $download_path, $nice_name, $repository_full_name, true );
			$r_pro = $d->deploy_premium( $download_path, $nice_name, $repository_full_name, true );

			$version = array(
				'free_name' => '',
				'free_url' => '',
				'free_path' => '',
				'premium_name' => '',
				'premium_url' => '',
				'premium_path' => '',
				'version' => '',
				'type' => '', // upload or github
				'attachment_id' => '', // Attachment Post ID
				'repo_name' => '', // Github repo full name
				'repo_version' => '', // Github version tag
			);

			if ( $r_free ) {
				$version = array_merge( $version, $r_free );
			}

			if ( $r_pro ) {
				$version = array_merge( $version, $r_pro );
			}

			var_dump( $r_free );

			if ( $version['version'] ) {
				// Update the meta field.
				$version['type'] = 'github';
				$version['repo_name'] = $repository_full_name;
				$version['repo_version'] = $repo_version;
				$version['current'] = 1;
				$versions = get_post_meta( $post->ID, '_fd_versions', true );
				if ( ! is_array( $versions ) ) {
					$versions = array();
				}
				foreach ( $versions as $k => $v ) {
					$v['current'] = '';
					$versions[ $k ] = $v;
				}

				$versions[ $version['version'] ] = $version;
				update_post_meta( $post->ID, '_fd_versions', $versions );
				$this->auto_update_download( $post->ID, $version );

				die( 'done' );

			} else {
				die( 'no_version_found' );
			}
		}

		die( 'do_nothing' );
	}

	function auto_update_download( $deploy_id, $version ) {
		$auto           = get_post_meta( $deploy_id, '_edd_auto_update', true );
		if ( ! $auto ) {
			return false;
		}
		$download_id        = absint( get_post_meta( $deploy_id, '_edd_download_id', true ) );
		$download_free_id   = absint( get_post_meta( $deploy_id, '_edd_download_free_id', true ) );
		$file_id            = absint( get_post_meta( $deploy_id, '_edd_download_file_id', true ) );
		$file_free_id       = absint( get_post_meta( $deploy_id, '_edd_download_free_file_id', true ) );

		if ( $download_id ) { // Pro version
			$files  = get_post_meta( $download_id, 'edd_download_files', true );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			$pro_file_url = fd_get_upload_url() . $version['premium_url'];

			$args = array(
				'attachment_id' => '',
				'file' => $pro_file_url,
				'git_url' => 'https://github.com/' . $version['repo_name'],
				'git_version' => $version['repo_version'],
				'name' => $version['premium_name'],
				'git_folder_name' => '',
				'condition' => 'all',
			);

			$files[ $file_id ] = $args;
			update_post_meta( $download_id, 'edd_download_files', $files );
			update_post_meta( $download_id, '_edd_sl_version', $version['version'] );

			if ( ! isset( $version['premium_changelog'] ) ) {
				$version['premium_changelog'] = '';
			}
			update_post_meta( $download_id, '_edd_sl_changelog', $version['premium_changelog'] );

		}

		if ( $download_free_id ) { // Free
			$files  = get_post_meta( $download_free_id, 'edd_download_files', true );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			$free_file_url = fd_get_upload_url() . $version['free_url'];

			$args = array(
				'attachment_id' => '',
				'file' => $free_file_url,
				'git_url' => 'https://github.com/' . $version['repo_name'],
				'git_version' => $version['repo_version'],
				'name' => $version['free_name'],
				'git_folder_name' => '',
				'condition' => 'all',
			);

			$files[ $file_free_id ] = $args;
			update_post_meta( $download_free_id, 'edd_download_files', $files );
			update_post_meta( $download_free_id, '_edd_sl_version', $version['version'] );

			if ( ! isset( $version['free_changelog'] ) ) {
				$version['free_changelog'] = '';
			}
			update_post_meta( $download_free_id, '_edd_sl_changelog', $version['free_changelog'] );

		}

	}

	function download( $repo, $version ) {
		$github = new FD_Github();
		$download_url = $github->get_download_url( $repo, $version );
		if ( $download_url ) {
			$dir = fd_get_upload_dir();
			$name = basename( $repo );
			$save_path = "{$dir}/{$repo}/{$name}-github-{$version}.zip";
			fd_download( $download_url, $save_path );
			return $save_path;
		}

		return false;
	}


}

new FD_Github_Webhook_Init();
