<?php

class FD_Deploy {
	public $free_dir = '';
	public $premium_dir = '';
	public $source_dir = '';
	public $finder;
	public $config = array();
	public $type = ''; // theme or plugin
	public $version = ''; // version of item
	public $item_name = ''; // version of item
	public $item_premium_name = ''; // version of item
	public $premium_suffix = 'premium';
	public $replace = '';
	public $replace_pro = '';
	public $replace_free = '';
	public $mod = '';


	public $skip_dirs = array(
		'/premium/',
	);


	public $skip_files = array();


	function __construct() {
		$this->finder = new FD_String_Finder();
	}

	function skip_dirs( $dirs ) {
		$this->skip_dirs = $dirs;
	}

	function skip_files( $files ) {
		$this->skip_files = $files;
	}

	function set_source_dir( $dir ) {
		$this->source_dir = trailingslashit( $dir );
	}

	function set_free_dir( $dir ) {
		$this->free_dir = trailingslashit( $dir );
	}

	function set_premium_dir( $dir ) {
		$this->premium_dir = trailingslashit( $dir );
	}

	function get_ext( $file ) {

		if ( $file && is_string( $file ) ) {
			$x = explode( '.', $file );
			if ( count( $x ) > 1 ) {
				return end( $x );
			}
		}
		return false;
	}

	function get_version_from_file_name( $file ) {
		if ( preg_match_all( '/\d+(?:\.\d+)+/', $file, $match ) ) {
			if ( ! empty( $match ) ) {
				if ( ! empty( $match[0] ) ) {

					return $match[0][0];
				}
			}
		}
		return false;
	}

	/**
	 *
	 * Kiểm tra file xem có thực hiện cắt nội dung hay không ?
	 *
	 * Chỉ chấp nhận files: .php, .js, .css, .sass, .scss, .less .txt, .readme
	 *
	 * @param $file
	 */
	function is_allow( $file ) {
		$ext = $this->get_ext( $file );
		if ( ! $ext ) {
			return false;
		}

		$allow_exts = array( 'php', 'js', 'css', 'sass', 'scss', 'less', 'txt', 'readme' );
		return in_array( $ext, $allow_exts );

	}
	function create_dir( $dir, $sub_dir ) {

		if ( is_dir( $dir ) ) {
			$dir = trailingslashit( $dir );
			$dir_perms = 0755;
			if ( @mkdir( $dir . $sub_dir, $dir_perms, true ) ) {
				return true;
			}
		}

		return false;

	}

	function is_php_file( $file ) {
		$ext = $this->get_ext( $file );
		return strtolower( $ext ) == 'php';
	}

	function replace_text( $content ) {
		if ( $this->replace ) {
			if ( $this->finder->version == 'free' ) {
				$content = str_replace( $this->replace, $this->replace_free, $content );
			} else {
				$content = str_replace( $this->replace, $this->replace_pro, $content );
			}
		}
		return $content;
	}

	function render_file( $full_file_path ) {
		if ( is_file( $full_file_path ) ) {

			if ( $this->is_allow( $full_file_path ) ) {
				$content = @file_get_contents( $full_file_path, 'w+' );
				if ( isset( $this->config['replace'] ) && $this->config['replace'] ) {
					if ( $this->finder->version == 'free' ) {
						$content = str_replace( $this->config['replace'], $this->config['replace_free'], $content );
					} else {
						$content = str_replace( $this->config['replace'], $this->config['replace_pro'], $content );
					}
				}
				if ( $this->is_php_file( $full_file_path ) ) {
					$content = $this->finder->deploy_php( $content );
					// var_dump( $content );
				} else {
					$content = $this->finder->deploy_none_php( $content );
				}

				// Fix WARNING: Both DOS and UNIX style line endings were found.
				$content = preg_replace( '~\R~u', "\r\n", $content );

				$fp = fopen( $full_file_path, 'w+' );
				fwrite( $fp, $content );
				fclose( $fp );
			}
		}
	}

	function get_files( $dir ) {

		$files = scandir( $dir, 1 );
		$list_file = array();

		foreach ( $files as $file ) {
			if ( $file != '.' && $file !== '..' ) {
				$list_file[] = $file;
			}
		}

		return $list_file;

	}

	function is_skip_dir( $dir ) {

		if ( ! $dir ) {
			return false;
		}

		// bỏ qua tất cả các thư mục có tên bắt đầu = dấu ".".
		if ( strpos( $dir, '.' ) === 0 ) {
			return true;
		}

		$skip = false;

		if ( $this->skip_dirs ) {
			if ( is_string( $this->skip_dirs ) && $this->skip_dirs != '' ) {
				return $this->skip_dirs == $dir;
			} elseif ( is_array( $this->skip_dirs ) && ! empty( $this->skip_dirs ) ) {
				return in_array( $dir, $this->skip_dirs );
			}
		}

		return $skip;
	}

	function is_skip_premium_file( $file ) {
		if ( $this->skip_files ) {
			if ( is_string( $this->skip_files ) && $this->skip_files != '' ) {
				return $file == $file;
			} elseif ( is_array( $this->skip_files ) && ! empty( $this->skip_files ) ) {
				return in_array( $file, $this->skip_files );
			}
		}
		return false;
	}

	function skip_config_file( $file ) {
		// Skip all dot file and config file.
		if ( strpos( $file, '.' ) === 0 ) {
			return true;
		}
		return $file == 'deploy.json';
	}

	function skip_hidden_folder( $path ) {
		$name = basename( $path );

		if ( strpos( $name, '.' ) === 0 ) {
			return true;
		}

		return false;
	}

	function render_free( $source_dir, $files ) {
		$skip_free = $this->is_skip_dir( $source_dir );

		if ( ! $skip_free ) {
			foreach ( $files as $file ) {
				$full_path = trailingslashit( $this->source_dir . $source_dir ) . $file;
				if ( is_file( $full_path ) ) {
					if ( ! $this->skip_config_file( $file ) ) {

						if ( ! $this->is_skip_premium_file( trailingslashit( $source_dir ) . $file ) ) {
							$free_file = trailingslashit( $this->free_dir . $source_dir ) . $file;
							// copy for free version
							if ( ! $skip_free ) {
								if ( ! copy( $full_path, $free_file ) ) {

								}
							}
							$this->render_file( $free_file );
						}
					}
				} elseif ( is_dir( $full_path ) ) {
					if ( ! $this->skip_hidden_folder( $full_path ) ) {
						$free_dir = trailingslashit( $this->free_dir . $source_dir );
						$test = trailingslashit( trailingslashit( $source_dir ) . $file );
						// Kiểm tra xem có bỏ qua dir này ko ?
						if ( ! $this->is_skip_dir( $test ) ) {
							$this->create_dir( $free_dir, $file );
							$sub_dir = trailingslashit( $source_dir . $file );
							$sub_files = $this->get_files( trailingslashit( $this->source_dir . $source_dir ) . $file );
							$this->render_free( $sub_dir, $sub_files );
						}
					}
				}
			}
		} // end is skip premium dir
	}

	function render_premium( $source_dir, $files ) {
		foreach ( $files as $file ) {
			$full_path = trailingslashit( $this->source_dir . $source_dir ) . $file;
			if ( is_file( $full_path ) ) {
				// do something width file here.
				if ( ! $this->skip_config_file( $file ) ) {
					$premium_file = trailingslashit( $this->premium_dir . $source_dir ) . $file;
					if ( ! copy( $full_path, $premium_file ) ) {

					}

					$this->render_file( $premium_file );
				}
			} elseif ( is_dir( $full_path ) ) {
				if ( ! $this->skip_hidden_folder( $full_path ) ) {
					$premium_dir = trailingslashit( $this->premium_dir . $source_dir );
					$this->create_dir( $premium_dir, $file );
					$sub_dir = trailingslashit( $source_dir . $file );
					$sub_files = $this->get_files( trailingslashit( $this->source_dir . $source_dir ) . $file );
					$this->render_premium( $sub_dir, $sub_files );
				}
			}
		}
	}


	function _get_config() {
		$file = $this->source_dir . 'deploy.json';
		if ( file_exists( $file ) ) {
			$content = @file_get_contents( $file );
			$this->config = json_decode( $content, true );
			if ( ! is_array( $this->config ) ) {
				$this->config = array();
			}

			$this->config = wp_parse_args(
				$this->config,
				array(
					'type' => '',
					'name' => '',
					'premium_name' => '',
					'function_premium' => '',
					'premium_suffix' => '',
					'replace' => '',
					'replace_pro' => '',
					'replace_free' => '',
					'premium_only' => '',
					'premium_files' => '',
				)
			);

			if ( is_array( $this->config ) ) {
				if ( isset( $this->config['function_premium'] ) && $this->config['function_premium'] ) {
					$this->finder->key = $this->config['function_premium'];
				}

				if ( isset( $this->config['name'] ) && $this->config['name'] ) {
					$this->item_name = $this->config['name'];
				}
				if ( isset( $this->config['premium_name'] ) && $this->config['premium_name'] ) {
					$this->item_premium_name = $this->config['premium_name'];
				}

				if ( isset( $this->config['premium_suffix'] ) && $this->config['premium_suffix'] ) {
					$this->premium_suffix = $this->config['premium_suffix'];
				}

				if ( isset( $this->config['replace'] ) && $this->config['replace'] ) {
					$this->replace = $this->config['replace'];
				}

				if ( isset( $this->config['replace_pro'] ) && $this->config['replace_pro'] ) {
					$this->replace_pro = $this->config['replace_pro'];
				}

				if ( isset( $this->config['replace_free'] ) && $this->config['replace_free'] ) {
					$this->replace_pro = $this->config['replace_free'];
				}
			}
		}

	}

	function config() {
		$this->_get_config();
		if ( ! empty( $this->config ) ) {

			if ( isset( $this->config['premium_only'] ) ) {
				$this->skip_dirs( $this->config['premium_only'] );
			}

			if ( isset( $this->config['premium_files'] ) ) {
				$this->skip_files( $this->config['premium_files'] );
			}

			if ( isset( $this->config['type'] ) ) {
				$this->type = $this->config['type'];
			}
		}
	}

	function get_version( $files ) {

		// if is theme.
		if ( 'plugin' != $this->type ) {
			// get version from style.
			$style_file = $this->source_dir . 'style.css';

			$default_headers = array(
				'Name' => '',
				'Version' => 'Version',
			);

			if ( is_file( $style_file ) ) {
				$data = get_file_data( $style_file, $default_headers, 'theme' );
				if ( is_array( $data ) && isset( $data['Version'] ) ) {
					$this->version = $data['Version'];
				}
			}
		} else {

			$default_headers = array(
				'Name' => '',
				'Version' => 'Version',
			);

			foreach ( $files as $file ) {
				if ( ! $this->version ) {
					if ( $this->is_php_file( $file ) ) {
						$plugin_file = $this->source_dir . $file;
						$data = get_file_data( $plugin_file, $default_headers, 'plugin' );
						if ( is_array( $data ) && isset( $data['Version'] ) ) {
							$this->version = $data['Version'];
						}
					}
				}
			}
		}

	}

	function delete_dir( $dir_path ) {
		if ( is_dir( $dir_path ) ) {
			$objects = scandir( $dir_path );
			foreach ( $objects as $object ) {
				if ( $object != '.' && $object != '..' ) {
					if ( filetype( $dir_path . '/' . $object ) == 'dir' ) {
						$this->delete_dir( $dir_path . '/' . $object );
					} else {
						@unlink( $dir_path . '/' . $object );
					}
				}
			}
			@reset( $objects );
			@rmdir( $dir_path );
		}
	}


	function get_changelog( $dir ) {
		$readme_file = $dir . '/readme.txt';
		$changelog = false;
		if ( $this->type != 'theme' ) {
			if ( defined( 'EDD_GIT_PLUGIN_DIR' ) ) {
				if ( file_exists( $readme_file ) ) {
					if ( ! class_exists( 'Automattic_Readme' ) ) {
						include_once EDD_GIT_PLUGIN_DIR . 'includes/parse-readme.php';
					}

					$Parser = new Automattic_Readme();
					$content = $Parser->parse_readme( $readme_file );
					if ( is_array( $content ) && isset( $content['sections'] ) ) {
						if ( isset( $content['sections']['changelog'] ) ) {
							$changelog = wp_kses_post( $content['sections']['changelog'] );
						}
					}
				}
			}
		}

		if ( ! $changelog ) {
			$readme_file = $dir . '/changelog.txt';
			if ( file_exists( $readme_file ) ) {
				$changelog = @file_get_contents( $readme_file );
			}
		}

		return $changelog;
	}


	function deploy_free( $zip_file, $nice_name = '', $sub_folder = null, $get_changelog = false ) {

		$return = false;
		if ( file_exists( $zip_file ) ) {

			$upload_dir = fd_get_upload_dir();
			if ( ! $nice_name ) {
				$file_name = basename( $zip_file );
				$nice_name = fd_nice_name( $file_name );
			}
			if ( ! $sub_folder ) {
				$base_dir = $upload_dir . '/' . $nice_name;
			} else {
				$base_dir = $upload_dir . '/' . $sub_folder;
			}

			$id = uniqid();

			$source_dir = false;
			$_source_dir = $base_dir . '/_source_' . $id;
			if ( ! is_dir( $_source_dir ) ) {
				wp_mkdir_p( $_source_dir );
			}

			$r = fd_unzip_file( $zip_file, $_source_dir );

			if ( $r ) {
				if ( is_dir( $_source_dir ) ) {
					$files = $this->get_files( $_source_dir );
					if ( ! empty( $files ) ) {
						foreach ( $files as $f ) {
							if ( ! $source_dir ) {
								$source_dir = $_source_dir . '/' . $f;
							}
						}
					}
				}
			}

			if ( $source_dir ) {
				$this->set_source_dir( $source_dir );
				$this->config();

				$item_name = $this->item_name ? $this->item_name : $nice_name;

				$free_dir = $base_dir . '/_free_' . $id . '/' . $item_name;
				wp_mkdir_p( $free_dir );
				$this->set_free_dir( $free_dir );

				$files = $this->get_files( $this->source_dir );
				$this->get_version( $files );
				if ( ! $this->version ) {
					$this->version = $this->get_version_from_file_name( basename( $zip_file ) );
				}

				$this->finder->set_mod( 'free' );
				$this->render_free( '/', $files );

				// Get changelog.
				$changelog = null;
				if ( $get_changelog ) {
					$changelog = $this->get_changelog( $free_dir );
				}

				// Zip free.
				$old_free_zip = $base_dir . '/' . $item_name . '.zip';
				$r = fd_zip_folder( $free_dir, $old_free_zip );
				if ( $this->version ) {
					$free_zip = $base_dir . '/' . $item_name . '-free-v' . trim( $this->version ) . '.zip';
				} else {
					$free_zip = $base_dir . '/' . $item_name . '-free.zip';
				}
				if ( $r ) {
					@rename( $old_free_zip, $free_zip );
				}

				// Remove temp folders.
				$this->delete_dir( dirname( $free_dir ) );
				$this->delete_dir( $_source_dir );
				$relative_path = str_replace( $upload_dir, '', $free_zip );
				$return = array(
					'free_name' => basename( $free_zip ),
					'free_path' => $relative_path,
					'free_url'  => $relative_path,
					'version'  => $this->version,
				);

				if ( $get_changelog ) {
					$return['free_changelog'] = $changelog;
				}
			}
		}

		return $return;

	}

	function deploy_premium( $zip_file, $nice_name = null, $sub_folder = null, $get_changelog = false ) {

		$return = false;

		if ( file_exists( $zip_file ) ) {

			$upload_dir = fd_get_upload_dir();
			if ( ! $nice_name ) {
				$file_name = basename( $zip_file );
				$nice_name = fd_nice_name( $file_name );
			}
			if ( ! $sub_folder ) {
				$base_dir = $upload_dir . '/' . $nice_name;
			} else {
				$base_dir = $upload_dir . '/' . $sub_folder;
			}

			$id = uniqid();

			$source_dir = false;
			$_source_dir = $base_dir . '/_source_' . $id;
			if ( ! is_dir( $_source_dir ) ) {
				wp_mkdir_p( $_source_dir );
			}

			$r = fd_unzip_file( $zip_file, $_source_dir );

			if ( $r ) {

				if ( is_dir( $_source_dir ) ) {
					$files = $this->get_files( $_source_dir );
					if ( ! empty( $files ) ) {
						foreach ( $files as $f ) {
							if ( ! $source_dir ) {
								$source_dir = $_source_dir . '/' . $f;
							}
						}
					}
				}
			}

			if ( $source_dir ) {
				$this->set_source_dir( $source_dir );
				$this->config();
				$item_name = $this->item_name ? $this->item_name : $nice_name;
				if ( $this->item_premium_name ) {
					$premium_dir = $base_dir . '/_premium_' . $id . '/' . $this->item_premium_name;
				} else {
					$premium_dir = $base_dir . '/_premium_' . $id . '/' . $item_name . '-' . $this->premium_suffix;
				}

				wp_mkdir_p( $premium_dir );

				$this->set_premium_dir( $premium_dir );

				$files = $this->get_files( $this->source_dir );
				$this->get_version( $files );

				if ( ! $this->version ) {
					$this->version = $this->get_version_from_file_name( basename( $zip_file ) );
				}

				$this->finder->set_mod( 'premium' );
				$this->render_premium( '/', $files );

				// Get changelog.
				$changelog = null;
				if ( $get_changelog ) {
					$changelog = $this->get_changelog( $premium_dir );
				}

				// add zip file.
				// Zip premium folder.
				if ( $this->item_premium_name ) {
					$old_premium_zip  = $premium_zip = $base_dir . '/' . $this->item_premium_name . '.zip';
				} else {
					$old_premium_zip  = $premium_zip = $base_dir . '/' . $item_name . '-' . $this->premium_suffix . '.zip';
				}

				$r = fd_zip_folder( $premium_dir, $old_premium_zip );
				if ( $r ) {
					if ( $this->version ) {
						if ( $this->item_premium_name ) {
							$premium_zip = $base_dir . '/' . $this->item_premium_name . '-v' . trim( $this->version ) . '.zip';

						} else {
							$premium_zip = $base_dir . '/' . $item_name . '-' . $this->premium_suffix . '-v' . trim( $this->version ) . '.zip';
						}

						@rename( $old_premium_zip, $premium_zip );
					}
				}

				// Remove temp folders.
				$this->delete_dir( dirname( $premium_dir ) );
				$this->delete_dir( $_source_dir );

				$relative_path = str_replace( $upload_dir, '', $premium_zip );
				$return = array(
					'premium_name' => basename( $premium_zip ),
					'premium_path' => $relative_path,
					'premium_url'  => $relative_path,
					'version'  => $this->version,
				);

				if ( $get_changelog ) {
					$return['premium_changelog'] = $changelog;
				}
			}
		}

		return $return;

	}



}
