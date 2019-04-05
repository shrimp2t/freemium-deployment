<?php

class FD_Github {
	private $token = '';
	private $api_end_point = 'https://api.github.com';
	private $users = array();
	private $me = array();
	private $repos = array();
	private $tags = array();
	private $members = array();

	function __construct() {
		$this->token = get_option( 'fd_github_token' );
	}

	function get( $path, $params = array(), $args = array() ) {
		$path = str_replace( $this->api_end_point, '', $path );
		$sep = '/';
		if ( strpos( $path, '/' ) === 0 ) {
			$sep = '';
		}
		$url = $this->api_end_point . $sep . $path;
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( ! isset( $params['access_token'] ) || ! $params['access_token'] ) {
			$params['access_token'] = $this->token;
		}
		$url = add_query_arg( $params, $url );
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		$args = array_merge(
			array(
				'sslverify' => false,
				$args,
			)
		);
		return wp_remote_get( $url, $args );
	}

	function get_remote_body( $res ) {
		return json_decode( wp_remote_retrieve_body( $res ), true );
	}

	function get_members() {
		if ( ! empty( $this->members ) ) {
			return $this->members;
		}

		$this->get_me();

		if ( $this->me ) {
			$this->members[ $this->me['login'] ] = $this->me;
		}

		$this->get_orgs();
		$this->members = array_merge( $this->members, $this->users );

		return $this->members;
	}

	/**
	 * Get current user info
	 */
	function get_me() {
		$res = $this->get( 'user' );
		if ( wp_remote_retrieve_response_code( $res ) == 200 ) {
			$data = $this->get_remote_body( $res );
			$this->me = array(
				'login' => $data['login'],
				'repos_url' => $data['repos_url'],
			);
		}
	}

	/**
	 * Get Organizations member
	 */
	function get_orgs() {
		$res = $this->get( 'user/orgs' );
		if ( wp_remote_retrieve_response_code( $res ) == 200 ) {
			$data = $this->get_remote_body( $res );
			if ( is_array( $data ) && ! empty( $data ) ) {
				foreach ( $data as $member ) {
					$this->users[ $member['login'] ] = array(
						'login' => $member['login'],
						'repos_url' => $member['repos_url'],
					);
				}
			}
		}
	}

	/**
	 *
	 * @param $repos_url Github Api Repos URL
	 * @return array
	 */
	function fetch_repos( $path = null ) {
		// Example: 'https://api.github.com/orgs/FameThemes/repos'
		if ( ! $path ) {
			$path = 'user/repos';
		}
		$do = true;
		$page = 1;
		$repos = array();
		while ( $do ) {
			$res = $this->get(
				$path,
				array(
					'per_page' => 100,
					'page' => $page,
					'type' => 'all',
				)
			);
			if ( 200 == wp_remote_retrieve_response_code( $res ) ) {
				$data = $this->get_remote_body( $res );
				if ( is_array( $data ) && ! empty( $data ) ) {
					foreach ( $data as $repo ) {
						$repos[ $repo['name'] ] = $repo['full_name'];
					}

					$page ++;
				} else {
					$do = false;
				}
			} else {
				$do = false;
			}
		}

		$this->repos = $repos;
		return $this->repos;
	}

	function get_repos() {
		return $this->fetch_repos();
	}

	function get_tags( $repo_full_name ) {
		if ( isset( $this->tags[ $repo_full_name ] ) ) {
			return $this->tags[ $repo_full_name ];
		}
		$res = $this->get( 'repos/' . $repo_full_name . '/tags' );
		$tags = array();

		if ( 200 == wp_remote_retrieve_response_code( $res ) ) {
			$repos_tags = $this->get_remote_body( $res );
			if ( is_array( $repos_tags ) ) {
				foreach ( $repos_tags as $tag ) {
					$tags[ $tag['name'] ] = array(
						'name' => $tag['name'],
						'zipball_url' => $tag['zipball_url'],
					);
				}
			}
			$this->tags[ $repo_full_name ] = $tags;
		}
		$this->tags[ $repo_full_name ] = $tags;
		return $this->tags[ $repo_full_name ];
	}

	function get_tag( $repo_full_name, $tag ) {
		$this->get_tags( $repo_full_name );
		if ( isset( $this->tags[ $repo_full_name ] ) ) {
			if ( isset( $this->tags[ $repo_full_name ][ $tag ] ) ) {
				return $this->tags[ $repo_full_name ][ $tag ];
			}
		}
		return false;
	}

	function get_download_url( $repo_full_name, $tag ) {
		$download_url = false;
		if ( ! $tag || $tag == 'master' || $tag == '_master_' ) {
			$download_url = 'https://github.com/' . $repo_full_name . '/archive/master.zip';
		} else {
			$this->get_tags( $repo_full_name );
			if ( isset( $this->tags[ $repo_full_name ] ) ) {
				if ( isset( $this->tags[ $repo_full_name ][ $tag ] ) ) {
					$download_url = $this->tags[ $repo_full_name ][ $tag ] ['zipball_url'];
				}
			}
		}
		if ( $download_url ) {
			return $this->get_download_url_token( $download_url );
		}
		return false;
	}

	function get_download_url_token( $download_url ) {
		return add_query_arg( array( 'access_token' => $this->token ), $download_url );
	}

}



// $g = new FD_Github();
// $g->get_tags( 'shrimp2t/gallery-one' );
// $this->get_orgs();
/*
$url = 'https://api.github.com/user/repos?per_page=300&page=1&access_token='.$this->token;

// Get Organizations user
$url = 'https://api.github.com/user/orgs?access_token='.$this->token;
$url = 'https://api.github.com/user?access_token='.$this->token;
// get repo of a user
// $url = 'https://api.github.com/users/FameThemes/repos?per_page=200&page=1&access_token=f156639fac4884c6f7c7c785600b977834dded96';
// $url = 'https://api.github.com/users/shrimp2t/repos?per_page=200&page=1&access_token=f156639fac4884c6f7c7c785600b977834dded96';
$get_repos = wp_remote_get( $url, array( 'sslverify' => false ) );
$repos = json_decode( wp_remote_retrieve_body( $get_repos ), true );
$status_code = wp_remote_retrieve_response_code( $get_repos );

var_dump( $status_code );
var_dump( $repos );
die();
*/
