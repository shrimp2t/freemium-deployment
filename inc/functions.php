<?php

function fd_convert_to_bytes( $from ) {
	$number = substr( $from, 0, -2 );
	switch ( strtoupper( substr( $from, -2 ) ) ) {
		case 'KB':
			return $number * 1024;
		case 'MB':
			return $number * pow( 1024, 2 );
		case 'GB':
			return $number * pow( 1024, 3 );
		case 'TB':
			return $number * pow( 1024, 4 );
		case 'PB':
			return $number * pow( 1024, 5 );
		default:
			return $from;
	}
}


/**
 * Tạo một folder mới từ file name
 * File name được phân cách các từ với nhau với dầu gạch ngang "-"
 * - Từ "master" ở cuối file name sẽ bị xóa ví dụ: "onepress-master.zip" sẽ thành "onepress"
 * - Version number ở cuối file sẽ bị xóa ví dụ: "onepress-v1.2.3.zip" hoặc "onepress-1.2.3.zip" sẽ thành "onepress"
 * - Nếu có cả "master" và version number thì sẽ chỉ xóa cái cuối cùng.
 *
 * @param $upload_file_name
 * @return string
 */
function fd_nice_name( $upload_file_name ) {
	$words = explode( '-', $upload_file_name );
	$n = count( $words );
	if ( $n > 1 ) {
		$last_word = end( $words );
		if ( preg_match( '/\d+(?:\.\d+)+/', $last_word, $matches ) ) {
			unset( $words[ $n - 1 ] );
			$name = join( '-', $words );
		} elseif ( strpos( $last_word, 'master' ) !== false ) {
			unset( $words[ $n - 1 ] );
			$name = join( '-', $words );
		} else {
			$name = substr( $upload_file_name, 0, strrpos( $upload_file_name, '.' ) );
		}
		return $name;
	}
	return substr( $upload_file_name, 0, strrpos( $upload_file_name, '.' ) );
}



function fd_get_upload_dir() {
	$fd_upload_dir_name = 'freemium-deployment';
	$dir = WP_CONTENT_DIR . '/uploads/' . $fd_upload_dir_name;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	return $dir;
}

function fd_get_upload_url() {
	$fd_upload_dir_name = 'freemium-deployment';
	$url = WP_CONTENT_URL . '/uploads/' . $fd_upload_dir_name;
	return $url;
}

function fd_download( $url, $save_path ) {
	$path = dirname( $save_path );
	if ( ! is_dir( $path ) ) {
		wp_mkdir_p( $path );
	}

	if ( is_dir( $path ) ) {

		$response = wp_remote_get( $url, array( 'timeout' => 15000 ) );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( 'application/zip' != $content_type ) { // Allow zip file only
			// Add error
			// $this->instance->errors[ $this->instance->file_key ] = array( 'error' => $error, 'msg' => __( 'Cannot access repo.', 'edd-git' ) );
			// Bail
			return false;
		}

		$fp = fopen( $save_path, 'w' );
		fwrite( $fp, $response['body'] );
		fclose( $fp );
	}
}


function fd_download_github( $repo, $version, $github = null ) {
	if ( ! $github || ! $github instanceof FD_Github ) {
		$github = new FD_Github();
	}

	$download_url = $github->get_download_url( $repo, $version );
	if ( $download_url ) {
		$dir = fd_get_upload_dir();
		$name = basename( $repo );
		$save_path = "{$dir}/{$repo}/{$name}-github-{$version}.zip";
		fd_download( $download_url, $save_path );
		return str_replace( $dir, '', $save_path );
	}

	return false;
}

function fd_download_github_from_url( $url, $repo, $version, $github = null ) {
	if ( ! $github || ! $github instanceof FD_Github ) {
		$github = new FD_Github();
	}

	$download_url = $github->get_download_url_token( $url );
	if ( $download_url ) {
		$dir = fd_get_upload_dir();
		$name = basename( $repo );
		$save_path = "{$dir}/{$repo}/{$name}-github-{$version}.zip";
		fd_download( $download_url, $save_path );
		return str_replace( $dir, '', $save_path );
	}

	return false;
}

function fd_versions_sort_cb( $version1, $version2 ) {
	if ( $version1['version'] === $version2['version'] ) {
		return 0;
	}
	return version_compare( $version1['version'], $version2['version'], '<' ) ? -1 : 1;
}
