<?php

class FD_Ajax {

	function __construct() {
		add_action( 'wp_ajax_fd_ajax_upload', array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_fd_ajax_deploy', array( $this, 'ajax_deploy' ) );
		add_action( 'wp_ajax_fd_ajax_github', array( $this, 'github' ) );
		// add_action( 'wp_ajax_fd_ajax_save', array( $this, 'save' ) );
	}

	function save() {

	}

	function github() {

		$nonce = isset( $_REQUEST['_nonce'] ) ? $_REQUEST['_nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'fd_deploy_nonce' ) ) {
			wp_die( 'security_check' );
		}

		$doing = isset( $_REQUEST['doing'] ) ? $_REQUEST['doing'] : false;
		$github = new FD_Github();

		switch ( $doing ) {
			case 'fetch':
				$post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : false;
				$repo = isset( $_REQUEST['repo'] ) ? $_REQUEST['repo'] : false;
				$version  = isset( $_REQUEST['version'] ) ? $_REQUEST['version'] : false;

				update_post_meta( $post_id, '_fd_github_version', $version );
				update_post_meta( $post_id, '_fd_github_repo', $repo );

				$path = $this->download_github( $repo, $version, $github );
				if ( $path ) {
					wp_send_json_success( $path );
				}

				wp_send_json_success();
				break;
			case 'get_tags':
				$repo = isset( $_REQUEST['repo'] ) ? $_REQUEST['repo'] : false;
				$options = '<option value="master">' . esc_html__( 'Master', 'textdomain' ) . '</option>';
				if ( $repo ) {
					$tags = $github->get_tags( $repo );
					foreach ( $tags as $tag ) {
						$options .= '<option value="' . esc_attr( $tag['name'] ) . '">' . esc_html( $tag['name'] ) . '</option>';
					}
				}
				wp_send_json_success( $options );

				break;
			default: // get repos.
				$options = '<option value="">' . esc_html__( 'Select a repository', 'textdomain' ) . '</option>';
				$repos = $github->get_repos();
				foreach ( $repos as $name => $full_name ) {
					$n = explode( '/', $full_name );
					$options .= '<option value="' . esc_attr( $full_name ) . '">' . esc_html( $n[1] . '/' . $n[0] ) . '</option>';
				}
				wp_send_json_success( $options );
		}

		die();
	}

	function download_github( $repo, $version, $github = null ) {
		return fd_download_github( $repo, $version, $github );
	}

	function ajax_deploy() {

		$nonce = isset( $_REQUEST['_nonce'] ) ? $_REQUEST['_nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'fd_deploy_nonce' ) ) {
			wp_die( 'security_check' );
		}

		$d = new FD_Deploy();

		$data = $_REQUEST['data'];

		$data = wp_parse_args(
			$data,
			array(
				'free_name' => '',
				'free_url' => '',
				'premium_name' => '',
				'premium_url' => '',
				'version' => '',
				'current' => '',
				'type' => '', // upload or github
				'attachment_id' => '', // Attachment Post ID
				'repo_name' => '', // Github repo full name
				'repo_version' => '', // Github version tag
			)
		);
		$type = $_REQUEST['type'];
		$nice_name = null;
		$file = null;
		$sub_folder = null;
		if ( $data['type'] != 'github' ) {
			// $attachment
			if ( $data['attachment_id'] ) {
				$file = get_attached_file( $data['attachment_id'] );
			}
		} else {
			// $base_name = basename( $data['repo_name'] );
			$nice_name = basename( $data['repo_name'] );
			$sub_folder = $data['repo_name'];
			$file = fd_get_upload_dir() . "/{$data['repo_name']}/{$nice_name}-github-{$data['repo_version']}.zip";
			if ( file_exists( $file ) && is_file( $file ) ) {
				unlink( $file );
			}
			if ( ! is_file( $file ) ) {
				$path = $this->download_github( $data['repo_name'], $data['repo_version'] );
				$file = fd_get_upload_dir() . $path;
			}
		}

		if ( 'premium' != $type ) {
			$r = $d->deploy_free( $file, $nice_name, $sub_folder );
		} else {
			$r = $d->deploy_premium( $file, $nice_name, $sub_folder );
		}

		if ( $r ) {
			wp_send_json_success( $r );
		} else {
			wp_send_json_error();
		}

		die();
	}

	function get_ext( $file ) {
		return strtolower( end( explode( '.', $file ) ) );
	}

	function nice_dir_name( $upload_file_name ) {
		return fd_nice_name( $upload_file_name );
	}

	function get_upload_dir() {
		return fd_get_upload_dir();
	}

	function upload_file() {
		$error = new WP_Error();
		if ( isset( $_FILES['deploy_zip_file'] ) ) {
			$file_name = $_FILES['deploy_zip_file']['name'];
			if ( ! $file_name ) {
				$error->add( 'no_file', 'No file selected' );
				return $error;
			}
			$file_size = $_FILES['deploy_zip_file']['size'];
			$file_tmp = $_FILES['deploy_zip_file']['tmp_name'];
			$file_type = $_FILES['deploy_zip_file']['type'];
			$file_ext = strtolower( end( explode( '.', $file_name ) ) );
			$expensions = array( 'zip' );

			if ( in_array( $file_ext, $expensions ) === false ) {
				$error->add( 'invalid_file_ext', 'Extension not allowed, please choose a zip file.' );
			}

			$max_upload_file_size = ini_get( 'upload_max_filesize' );
			$max_bytes = fd_convert_to_bytes( $max_upload_file_size . 'B' );

			if ( $file_size > $max_bytes ) {
				$error->add( 'file_size', sprintf( 'Upload file size must smaller or equal %s', $max_upload_file_size ) );
			}

			$new_dir = $this->nice_dir_name( $file_name );
			$save_path = $this->get_upload_dir() . '/' . $new_dir;
			if ( ! is_dir( $save_path ) ) {
				wp_mkdir_p( $save_path );
			}
			$file_save_path = $save_path . '/' . $file_name;

			if ( empty( $error->get_error_codes() ) ) {
				if ( @move_uploaded_file( $file_tmp, $file_save_path ) ) {
					return $file_save_path;
				} else {
					$error->add( 'could_not_move_file', 'Could not move file' );
				}

				return $error;
			} else {
				return $error;
			}
		} else {
			$error->add( 'no_file', 'No file selected' );
			return $error;
		}

	}

	function download( $url, $save_path ) {
		fd_download( $url, $save_path );
	}


}

new FD_Ajax();
