<?php
/*
Plugin Name: MegaMenu WP
Plugin URI: https://daisythemes.com/
Description: Add magazine mega menu for WordPress
Author: daisythemes, shrimp2t
Author URI: https://daisythemes.com/
Version: 1.0.0
Text Domain: megamenu-wp
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/




class MegaMenu_WP {
    function __construct(){
        define( 'MAGAZINE_MEGA_MENU_URL',  trailingslashit( plugins_url('', __FILE__) ));
        define( 'MAGAZINE_MEGA_MENU_PATH', trailingslashit( dirname( __FILE__) ) );

        include MAGAZINE_MEGA_MENU_PATH.'inc/admin.php';
        include MAGAZINE_MEGA_MENU_PATH.'inc/class-mega-item.php';
        include MAGAZINE_MEGA_MENU_PATH.'inc/menu.php';

        include MAGAZINE_MEGA_MENU_PATH.'inc/settings.php';

        if ( is_admin() ){

        }
        if ( ! is_admin() ) {
            add_action('wp_enqueue_scripts', array($this, 'scripts'), 3 );
        }

        add_action( 'wp_ajax_megamneu_wp_load_posts', array( __CLASS__, 'ajax_load_posts' ) );
        add_action( 'wp_ajax_nopriv_megamneu_wp_load_posts', array( __CLASS__, 'ajax_load_posts' ) );
        add_filter('widget_text', 'do_shortcode');
    }

    static function get_theme_support( $feature = null, $default = null ){

        $options = array(
            'mobile_mod'            => 0, // Break point when toggle mobile mod
            'disable_auto_css'      => 0, // Do not apply auto css
        );

        $support = get_theme_support( 'megamenu-wp' );
        if ( is_array( $support ) && ! empty( $support ) ) {
            $sp = current( $support );
            if ( ! $feature ) {
                return wp_parse_args( $sp, $options );
            }
            if ( is_array( $sp ) ) {
                if ( isset( $sp[ $feature ] ) ) {
                    return $sp[ $feature ];
                }
            }
        }
        return wp_parse_args( $default, $options );
    }

    function scripts(){
        wp_enqueue_style( 'megamenu-wp', MAGAZINE_MEGA_MENU_URL.'style.css' );
        wp_enqueue_script( 'megamenu-wp', MAGAZINE_MEGA_MENU_URL.'assets/js/megamenu-wp.js', array(  'jquery' ), '1.0.1', true  );

        $args = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'loading_icon' => apply_filters( 'megamenu_wp_loading_icon', '<div class="spinner"><div class="uil-squares-css" style="transform:scale(0.4);"><div><div></div></div><div><div></div></div><div><div></div></div><div><div></div></div><div><div></div></div><div><div></div></div><div><div></div></div><div><div></div></div></div></div>' ),
            'theme_support' => $this->get_theme_support()
        );

        if ( ! $args['theme_support']['mobile_mod'] ) {
            $args['theme_support']['mobile_mod'] = absint( get_theme_mod( 'mega_mobile_break_points' , 720 ) );
        }

        if ( ! $args['theme_support']['disable_auto_css'] ) {
            $args['theme_support']['disable_auto_css'] = absint( get_theme_mod( 'mega_disable_css' , false ) );
        }

        $args['mega_content_left'] = floatval( get_theme_mod( 'mega_content_left' ) );
        $args['mega_content_right'] = floatval( get_theme_mod( 'mega_content_right' ) );
        $args['mega_parent_level'] = absint( get_theme_mod( 'mega_parent_level' ) );

        $args['animation'] = get_theme_mod( 'mega_animation' );
        // shift-up,  shift-down, shift-left, shift-right, fade, flip, animation-none
        if ( ! $args['animation'] ) {
            $args['animation'] = 'shift-up';
        }

        wp_localize_script( 'megamenu-wp', 'MegamenuWp', $args );
        $margin_top = get_theme_mod( 'mega_content_margin_top' );
        $margin_top = floatval( $margin_top );
        $css = '.megamenu-wp-desktop #megamenu-wp-page .megamenu-wp .mega-item .mega-content li.mega-content-li { margin-top: '.$margin_top.'px; }';

        wp_add_inline_style( 'megamenu-wp', $css );

    }

    static function get_template( $template ){

        $template_folders = array(
            get_stylesheet_directory().'/', // Child theme
            get_stylesheet_directory().'/templates/', // child theme
            get_template_directory().'/', // Parent theme
            get_template_directory().'/templates/', // Parent theme
            MAGAZINE_MEGA_MENU_PATH.'templates/' // Plugin
        );

       

        foreach ( $template_folders as $folder ) {
            $file = $folder.$template;
            if ( file_exists( $file ) ) {
                return $file;
            }
        }

        return false;
    }

    static function get_previewing_data( $key, $default = null ){
        if ( ! isset( $GLOBALS['_customized_decode'] ) || ! is_array( $GLOBALS['_customized_decode'] ) ) {
            return $default;
        }
        if ( is_array( $key ) ) {
            if ( isset( $GLOBALS['_customized_decode'][ $key[0] ] ) && is_array( $GLOBALS['_customized_decode'][ $key[0] ] ) ) {
                if ( $GLOBALS['_customized_decode'][ $key[0] ][ $key[ 1 ] ] ) {
                    return $GLOBALS['_customized_decode'][ $key[0] ][ $key[ 1 ] ];
                }
            }
        } else {
            if ( isset( $GLOBALS['_customized_decode'][ $key ] ) ) {
                return $GLOBALS['_customized_decode'][ $key ];
            }
        }

        return $default;
    }

    static function is_mega_nav_active( $nav_id ){

        if ( isset( $GLOBALS[ '_mega_menu_enable_'.$nav_id ] ) ) {
            return $GLOBALS[ '_mega_menu_enable_'.$nav_id ];
        }

        $key = 'nav_menu['. $nav_id .']';
        if ( MegaMenu_WP::is_preview( array( $key, 'mega_enable' ) ) ) {
            $mega_enable = MegaMenu_WP::get_previewing_data( array( $key, 'mega_enable' ) );
        } else {
            $mega_enable = get_term_meta( $nav_id, '_mega_enable', true );
        }

        $GLOBALS[ '_mega_menu_enable_'.$nav_id ] = $mega_enable;
        return $GLOBALS[ '_mega_menu_enable_'.$nav_id ];
    }

    static function is_preview( $key_check = false ){
        if ( is_customize_preview() ) {
            if ( isset( $_POST['wp_customize'] ) && $_POST['wp_customize'] == 'on') {
                if ( ! isset( $GLOBALS['_customized_decode']) ) {
                    if ( isset( $_POST['customized'] ) ) {
                        $GLOBALS['_customized_decode'] = json_decode( wp_unslash($_POST['customized'] ), true);
                    } else {
                        $GLOBALS['_customized_decode'] = array();
                    }
                }

                if ( $key_check ) {
                    if ( is_array( $key_check ) ) {
                        if ( isset( $GLOBALS['_customized_decode'] [ $key_check[ 0 ] ] ) ) {
                            return isset( $GLOBALS['_customized_decode'][ $key_check[ 0 ] ] [ $key_check[ 1 ] ] );
                        }
                    } else {
                        return isset( $GLOBALS['_customized_decode'][ $key_check ] );
                    }
                }
            }
        }
        return false;
    }

    static function ajax_load_posts(){
        $args = MegaMenu_WP_Menu_Item::get_post_query_args( $_REQUEST );
        $content = MegaMenu_WP_Menu_Item::posts_content( $args );
        wp_send_json_success( $content );
        die();
    }
}

new MegaMenu_WP();

