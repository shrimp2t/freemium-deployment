<?php

class FD_Webhook {
	function __construct() {
		add_action( 'init', array( $this, 'trigger' ) );
	}

	function trigger() {
		if ( isset( $_GET['fd_action'] ) && $_GET['fd_action'] == 'deploy_webhook' ) {
			$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : false;
			$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : false;
			$this->deploy( $id, $nonce );
		}
	}

	/**
	 * @see https://developer.github.com/v3/activity/events/types/#releaseevent
	 *
	 * @param $id
	 * @param $nonce
	 */
	function deploy( $id, $nonce ) {
		$post = get_post( $id );
		if ( ! $post || get_post_type( $post ) != 'deployment' ) {
			wp_send_json_error( 'No item found.' );
		}

		$_nonce = get_post_meta( $post->ID, '_fd_nonce', true );
		if ( ! $nonce || $nonce != $_nonce ) {
			wp_send_json_error( 'Security check' );
		}
		$github_data = false;
		if ( isset( $_POST['payload'] ) ) {
			$github_data = json_decode( wp_unslash( $_POST['payload'] ), true );
		}

		// var_dump( $github_data );
		if ( ! $github_data || ! isset( $github_data['action'] ) || $github_data['action'] != 'published' ) {
			wp_send_json_error( 'No action' );
		}

		if ( ! isset( $github_data['release'] ) || ! is_array( $github_data['release'] ) ) {
			wp_send_json_error( 'No release found.' );
		}

		$release = wp_parse_args(
			$github_data['release'],
			array(
				'tag_name' => '',
				'zipball_url' => '',
			)
		);

		$repository = wp_parse_args(
			$github_data['repository'],
			array(
				'name' => '',
				'full_name' => '',
			)
		);

		$save_file = fd_download_github_from_url( $release['zipball_url'], $repository['full_name'], $release['tag_name'] );

		if ( ! $save_file ) {
			wp_send_json_error( 'Could not download file' );
		}

		$file = fd_get_upload_dir() . $save_file;
		$d = new FD_Deploy();
		$nice_name = basename( $repository['full_name'] );
		$sub_folder = $repository['full_name'];
		$r_free = $d->deploy_free( $file, $nice_name, $sub_folder, true );
		$r_pro = $d->deploy_premium( $file, $nice_name, $sub_folder, true );
		if ( ! $r_free || ! $r_pro ) {
			wp_send_json_error( 'Could not deploy' );
		}
		$args = array(
			'free_name' => $r_free['free_name'],
			'free_url' => $r_free['free_url'],
			'premium_name' => $r_pro['premium_name'],
			'premium_url' => $r_pro['premium_url'],
			'version' => $r_free['version'],
			'current' => '',
			'type' => 'github', // upload or github
			'attachment_id' => '', // Attachment Post ID
			'repo_name' => $repository['full_name'], // Github repo full name
			'repo_version' => $release['tag_name'], // Github version tag

			'premium_changelog' => isset( $r_pro['premium_changelog'] ) ? $r_pro['premium_changelog'] : '',
			'free_changelog' => isset( $r_free['free_changelog'] ) ? $r_free['free_changelog'] : '',
		);

		$versions = get_post_meta( $post->ID, '_fd_versions', true );
		if ( ! is_array( $versions ) ) {
			$versions = array();
		}

		$versions[ $args['version'] ] = $args;
		update_post_meta( $post->ID, '_fd_versions', $versions );
		do_action( 'fd_deployment_save_data', $post->ID, $versions );

		wp_send_json_success(
			array(
				'file' => $file,
				'args' => $args,
				'ID' => $post->ID,
				'versions' => $versions,
			)
		);

		die();
	}
}

new FD_Webhook();
