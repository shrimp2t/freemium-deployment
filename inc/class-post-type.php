<?php

class FD_Post_Type {
	static $type = 'deployment';
	function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'save_post', array( $this, 'save' ) );

		add_filter( 'manage_deployment_posts_columns', array( $this, 'custom_edit_deployment_columns' ) );
		add_action( 'manage_deployment_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );

		add_action( 'wp_ajax_fd_ajax_deployment', array( $this, 'ajax' ) );

		add_action( 'fd_deployment_save_data', array( $this, 'set_current_version' ) );
	}

	function ajax() {

		$nonce = isset( $_REQUEST['_nonce'] ) ? $_REQUEST['_nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'fd_deploy_nonce' ) ) {
			wp_die( 'security_check' );
		}

		$versions = isset( $_REQUEST['versions'] ) ? $_REQUEST['versions'] : array();
		if ( ! is_array( $versions ) ) {
			$versions = array();
		}
		$post_id = $_REQUEST['post_id'];

		$new_versions = array();
		$default = array(
			'version' => '',
			'free_name' => '',
			'premium_name' => '',
		);
		foreach ( $versions as $key => $version ) {
			$version = wp_parse_args( $version, $default );
			// Remove empty version
			if ( ! $version['free_name'] && ! $version['premium_name'] ) {
				unset( $versions[ $key ] );
			} else {
				$new_versions[ $version['version'] ] = $version;
				$version_numbers[ $version['version'] ] = $version['version'];
			}
		}

		$versions = $new_versions;
		// usort($versions, 'fd_versions_sort_cb' );
		array_multisort( $version_numbers, SORT_ASC, $versions );

		// Update the meta field.
		update_post_meta( $post_id, '_fd_versions', $versions );
		do_action( 'fd_deployment_save_data', $post_id, $versions );
		wp_send_json_success( $versions );

		die( 'deployment_saved' );
	}

	function set_current_version( $post_id, $versions = array() ) {
		$current_version = null;

		$versions = get_post_meta( $post_id, '_fd_versions', true );
		if ( ! is_array( $versions ) ) {
			$versions = array();
		}

		$default = array(
			'version' => '',
		);

		foreach ( $versions as $key => $version ) {
			$version = wp_parse_args( $version, $default );
			if ( $version['current'] ) {
				$current_version = $version;
			}
		}

		// If still empty
		// Auto get latest version number.
		if ( ! $current_version ) {
			$current_version_number = '';
			foreach ( $versions as $key => $version ) {
				$version = wp_parse_args( $version, $default );
				// Remove empty version
				if ( version_compare( $current_version_number, $version['version'], '<' ) ) {
					$current_version = $version;
					$current_version_number = $version['version'];
				}
			}
		}

		// Update current version
		update_post_meta( $post_id, '_fd_current_version_number', $current_version['version'] );
		update_post_meta( $post_id, '_fd_current_version_data', $current_version );
		do_action( 'fd_deployment_current_version_update', $post_id, $current_version );

		if ( empty( $current_version ) ) {
			return;
		}

		$auto           = get_post_meta( $post_id, '_edd_auto_update', true );
		if ( ! $auto ) {
			return false;
		}
		$download_id        = absint( get_post_meta( $post_id, '_edd_download_id', true ) );
		$download_free_id   = absint( get_post_meta( $post_id, '_edd_download_free_id', true ) );
		$file_id            = absint( get_post_meta( $post_id, '_edd_download_file_id', true ) );
		$file_free_id       = absint( get_post_meta( $post_id, '_edd_download_free_file_id', true ) );

		if ( $download_id ) { // Pro version
			$files  = get_post_meta( $download_id, 'edd_download_files', true );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			$pro_file_url = fd_get_upload_url() . $current_version['premium_url'];

			$condition = 'all';
			if ( isset( $files[ $file_id ] ) && is_array( $files[ $file_id ] ) ) {
				if ( isset( $files[ $file_id ] ['condition'] ) ) {
					$condition = $files[ $file_id ] ['condition'];
				}
			}

			$args = array(
				'attachment_id' => '',
				'file' => $pro_file_url,
				'git_url' => 'https://github.com/' . $current_version['repo_name'],
				'git_version' => $current_version['repo_version'],
				'name' => $current_version['premium_name'],
				'git_folder_name' => '',
				'condition' => $condition,
			);

			$files[ $file_id ] = $args;
			update_post_meta( $download_id, 'edd_download_files', $files );
			update_post_meta( $download_id, '_edd_sl_version', $current_version['version'] );

			if ( ! isset( $current_version['premium_changelog'] ) ) {
				$current_version['premium_changelog'] = '';
			}
			update_post_meta( $download_id, '_edd_sl_changelog', $current_version['premium_changelog'] );

		}

		if ( $download_free_id ) { // Free
			$files  = get_post_meta( $download_free_id, 'edd_download_files', true );
			if ( ! is_array( $files ) ) {
				$files = array();
			}

			$free_file_url = fd_get_upload_url() . $current_version['free_url'];

			$condition = 'all';
			if ( isset( $files[ $file_free_id ] ) && is_array( $files[ $file_free_id ] ) ) {
				if ( isset( $files[ $file_free_id ] ['condition'] ) ) {
					$condition = $files[ $file_free_id ] ['condition'];
				}
			}

			$args = array(
				'attachment_id' => '',
				'file' => $free_file_url,
				'git_url' => 'https://github.com/' . $current_version['repo_name'],
				'git_version' => $current_version['repo_version'],
				'name' => $current_version['free_name'],
				'git_folder_name' => '',
				'condition' => $condition,
			);

			$files[ $file_free_id ] = $args;
			update_post_meta( $download_free_id, 'edd_download_files', $files );
			update_post_meta( $download_free_id, '_edd_sl_version', $current_version['version'] );

			if ( ! isset( $current_version['free_changelog'] ) ) {
				$current_version['free_changelog'] = '';
			}
			update_post_meta( $download_free_id, '_edd_sl_changelog', $current_version['free_changelog'] );

		}

	}

	function remove_version( $version ) {

		$dir = fd_get_upload_dir();
		$have_files = false;
		$free_file = $dir . $version['free_path'];
		if ( $version['free_path'] && file_exists( $free_file ) ) {
			if ( is_file( $free_file ) ) {
				$have_files = true;
				@unlink( $free_file );
			}
		}

		$premium_file = $dir . '/' . $version['premium_path'];
		if ( $version['premium_path'] && file_exists( $premium_file ) ) {
			if ( is_file( $premium_file ) ) {
				$have_files = true;
				@unlink( $premium_file );
			}
		}

		// check if dir is empty
		if ( $have_files ) { // Ensure delete correct folder
			$delete_dir = dirname( $premium_file );
			$files = scandir( $delete_dir, 1 );
			$count = 0;
			foreach ( $files as $file ) {
				echo $file . "\r\n";
				if ( $file != '.' && $file !== '..' ) {
					$count ++;
				} else {
					@unlink( $delete_dir . '/' . $file );
				}
			}

			// Is empty this dir
			if ( ! $count ) {
				@rmdir( $delete_dir );
			}
		}

	}


	/**
	 * Add more deployment columns
	 *
	 * @since 1.0.0
	 * @param $columns
	 * @return mixed
	 */
	function custom_edit_deployment_columns( $columns ) {

		$columns['free_version'] = esc_html__( 'Free Version', 'wp-coupon' );
		$columns['premium_version']     = esc_html__( 'Premium Version', 'wp-coupon' );
		$columns['version_number']      = esc_html__( 'Current Version', 'wp-coupon' );

		unset( $columns['date'] );
		return $columns;
	}


	/**
	 * Display deployment column data
	 *
	 * @since 1.0.0
	 * @param $column
	 * @param $post_id
	 */
	function custom_columns( $column, $post_id ) {
		$version = get_post_meta( $post_id, '_fd_current_version_data', true );
		if ( ! is_array( $version ) ) {
			$version = array();
		}

		$default = array(
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

		$version = wp_parse_args( $version, $default );
		$url = fd_get_upload_url();

		switch ( $column ) {
			case 'free_version':
				if ( $version['free_name'] && $version['free_path'] ) {
					?>
						<a href="<?php echo $url . $version['free_path']; ?>" target="_blank"><?php echo esc_html( $version['free_name'] ); ?></a>
						<?php
				} else {
					echo $version['free_name'];
				}
				break;
			case 'premium_version':
				if ( $version['premium_name'] && $version['premium_path'] ) {
					?>
					<a href="<?php echo $url . $version['premium_path']; ?>" target="_blank"><?php echo esc_html( $version['premium_name'] ); ?></a>
					<?php
				} else {
					echo $version['free_name'];
				}

				break;
			case 'version_number':
					 echo $version['version'];
				break;

		}

	}

	function register_post_type() {
		$labels = array(
			'name'               => _x( 'Deployments', 'post type general name', 'textdomain' ),
			'singular_name'      => _x( 'Deployment', 'post type singular name', 'textdomain' ),
			'menu_name'          => _x( 'Deployments', 'admin menu', 'textdomain' ),
			'name_admin_bar'     => _x( 'Deployment', 'add new on admin bar', 'textdomain' ),
			'add_new'            => _x( 'Add New', 'book', 'textdomain' ),
			'add_new_item'       => __( 'Add New Deployment', 'textdomain' ),
			'new_item'           => __( 'New Deployment', 'textdomain' ),
			'edit_item'          => __( 'Edit Deployment', 'textdomain' ),
			'view_item'          => __( 'View Deployment', 'textdomain' ),
			'all_items'          => __( 'All Deployments', 'textdomain' ),
			'search_items'       => __( 'Search Deployments', 'textdomain' ),
			'parent_item_colon'  => __( 'Parent Deployments:', 'textdomain' ),
			'not_found'          => __( 'No deployments found.', 'textdomain' ),
			'not_found_in_trash' => __( 'No deployments found in Trash.', 'textdomain' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Description.', 'textdomain' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'deployment' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title' ), // editor
			'register_meta_box_cb'    => array( $this, 'register_meta_box' ),
		);

		register_post_type( $this::$type, $args );
	}

	function register_meta_box() {
		add_meta_box( $this::$type . '-meta', esc_html__( 'Deployments', 'textdomain' ), array( $this, 'meta_box' ), $this::$type, 'normal' );
		add_meta_box( $this::$type . '-config', esc_html__( 'Config', 'textdomain' ), array( $this, 'meta_box_config' ), $this::$type, 'side' );
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save( $post_id ) {

		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['fd_metabox_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['fd_metabox_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'fd_metabox' ) ) {
			return $post_id;
		}

		/*
		 * If this is an autosave, our form has not been submitted,
		 * so we don't want to do anything.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$keys = array(
			'_edd_download_id' => '',
			'_edd_download_file_id' => '',
			'_edd_download_free_id' => '',
			'_edd_download_free_file_id' => '',
			'_edd_auto_update' => '',
		);

		foreach ( $keys as $k => $v ) {
			$v = isset( $_REQUEST[ $k ] ) ? sanitize_text_field( $_REQUEST[ $k ] ) : '';
			update_post_meta( $post_id, $k, $v );
		}

	}

	function meta_box_config( $post ) {

		$nonce = get_post_meta( $post->ID, '_fd_nonce', true );
		if ( ! $nonce ) {
			$nonce = wp_create_nonce( 'webhook' );
			update_post_meta( $post->ID, '_fd_nonce', $nonce );
		}
		$webhook_url = home_url( '/' );
		$webhook_url = add_query_arg(
			array(
				'fd_action' => 'deploy_webhook',
				'id' => $post->ID,
				'nonce' => $nonce,
			),
			$webhook_url
		);
		?>
		<?php if ( get_option( 'fd_github_token' ) ) { ?>
		<div class="field">
			<p>
				<label><strong>GitHub Webhook URL</strong></label>
				<span class="howto">Select GitHub repository to make it works.</span>
			</p>
			<code style="display: block;"><?php echo esc_url( $webhook_url ); ?></code>
		</div>
		<?php } ?>

		<?php
			$download_id = get_post_meta( $post->ID, '_edd_download_id', true );
			$file_id = get_post_meta( $post->ID, '_edd_download_file_id', true );

			$download_free_id = get_post_meta( $post->ID, '_edd_download_free_id', true );
			$file_free_id = get_post_meta( $post->ID, '_edd_download_free_file_id', true );

			$autoupdate = get_post_meta( $post->ID, '_edd_auto_update', true );

			$downloads = get_posts(
				array(
					'post_status' => array( 'pending', 'draft', 'future', 'publish' ),
					'post_type' => 'download',
					'orderby' => 'title',
					'order' => 'asc',
					'posts_per_page' => -1,
				)
			);

		?>
			<div class="github-webhook">

				<p>
					<label> <input type="checkbox" name="_edd_auto_update" <?php checked( $autoupdate, 1 ); ?> value="1"> <strong>Enable auto update download file for EDD download item.</strong></label>
				</p>

				<p>
					<label><strong>PRO: EDD Download Item</strong></label>
					<select name="_edd_download_id" class="block-input">
						<option value=""><?php esc_html_e( '---Select download---' ); ?></option>
						<?php foreach ( $downloads as $d ) { ?>
							<option <?php selected( $download_id, $d->ID ); ?> value="<?php echo esc_attr( $d->ID ); ?>"><?php echo esc_html( $d->post_title ); ?></option>
						<?php } ?>
					</select>
				</p>
				<p>
					<label><strong>PRO: Download file ID</strong></label>
					<span class="howto">If you fetch download from a git repo use 0 else chose other number you want.</span>
					<input type="text" class="block-input" name="_edd_download_file_id" value="<?php echo esc_attr( $file_id ); ?>"/>
				</p>

				<p>
					<label><strong>FREE: EDD Download Item</strong></label>
					<select name="_edd_download_free_id" class="block-input">
						<option value=""><?php esc_html_e( '---Select download---' ); ?></option>
						<?php foreach ( $downloads as $d ) { ?>
							<option <?php selected( $download_free_id, $d->ID ); ?> value="<?php echo esc_attr( $d->ID ); ?>"><?php echo esc_html( $d->post_title ); ?></option>
						<?php } ?>
					</select>
				</p>
				<p>
					<label><strong>FREE: Download file ID</strong></label>
					<span class="howto">If you fetch download from a git repo use 0 else chose other number you want.</span>
					<input type="text" class="block-input" name="_edd_download_free_file_id" value="<?php echo esc_attr( $file_free_id ); ?>"/>
				</p>
			</div>
		<?php
	}


	function meta_box( $post ) {

		wp_nonce_field( 'fd_metabox', 'fd_metabox_nonce' );
		$version_data = get_post_meta( $post->ID, '_fd_versions', true );
		if ( ! $version_data || empty( $version_data ) ) {
			$version_data = new stdClass();
		}

		?>
		<div class="fd-meta-box">
			<input type="hidden" class="fd_versions" name="fd_versions" autocomplete="off" value="<?php echo esc_attr( json_encode( $version_data ) ); ?>">

			<?php if ( get_option( 'fd_github_token' ) ) { ?>
			<div class="github-repos">
				<p>
					<label for="edd_readme_location"><strong>GitHub Repository</strong></label>
					<span class="howto">Select GitHub repository to fetch item.</span>
				</p>
				<p>
					<select class="github_repo" name="github_repo" data-value="<?php echo esc_attr( get_post_meta( $post->ID, '_fd_github_repo', true ) ); ?>">
						<option disabled selected="selected" value=""><?php esc_html_e( 'Loading repos...', 'textdomain' ); ?></option>
					</select>
					<select name="github_version" class="github_version" data-value="<?php echo esc_attr( get_post_meta( $post->ID, '_fd_github_version', true ) ); ?>">
						<option value=""> --- </option>
					</select>
					<button type="button" class="fetch-repo button button-primary"><?php esc_html_e( 'Fetch', 'textdomain' ); ?></button>
				</p>
			</div>


			<?php } ?>

			<p class="new-button">
				<a href="#" class="fd-new-upload-item"><?php esc_html_e( 'New Version', 'textdomain' ); ?></a>
			</p>

			<table class="fd-versions-table wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<th class="remove" style="width: 30px;"></th>
						<th class="free-version">Free Version</th>
						<th class="premium-version">Premium Version</th>
						<th class="version-number">Version</th>
						<?php  /*
						<th class="current-version" title="Current version"><span class="dashicons dashicons-yes"></span></th>
						*/ ?>
						<th class="actions"></th>
					</tr>
				</thead>
				<tbody>
					<tr class="tpl">
						<td class="remove"><a class="" href="#"><span class="dashicons dashicons-no-alt"></span</a></td>
						<td class="free-version"><?php esc_html_e( '[No resource]', 'textdomain' ); ?></td>
						<td class="premium-version"><?php esc_html_e( '[No resource]', 'textdomain' ); ?></td>
						<td class="version-number">...</td>
						<?php  /*
						<td class="current-version"><a href="#" class="set-current-version"><span class="dashicons dashicons-yes"></span></a></td>
						*/ ?>
						<td class="actions">
							<span class="fd-github-icon"></span>
							<button class="button button-secondary file-select-button" type="button"><span class="dashicons dashicons-upload"></span></button>
							<a class="button button-secondary fd-update-button" type="button" href="#"><span class="dashicons dashicons-update"></span></a>
						</td>
					</tr>
				</tbody>
			</table>



		</div>
		<?php
	}
}


new FD_Post_Type();
