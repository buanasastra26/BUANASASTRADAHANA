<?php

namespace Hostinger\Reach\Admin;

use Hostinger\WpMenuManager\Menus as WpMenu;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Menus {

    public function init(): void {
        add_action( 'admin_menu', array( $this, 'add_main_menu_page' ), 2 );
        add_filter( 'hostinger_menu_subpages', array( $this, 'add_sub_menu_page' ), 20 );
        add_filter( 'hostinger_admin_menu_bar_items', array( $this, 'add_admin_bar_items' ), 110 );
        add_action( 'admin_menu', array( $this, 'maybe_remove_hostinger_menu' ), 999 );
    }

    public function maybe_remove_hostinger_menu(): void {
        $submenus = apply_filters( 'hostinger_menu_subpages', array() );

        $other_submenus = array_filter(
            $submenus,
            function ( $submenu ) {
                return ( $submenu['menu_slug'] ?? '' ) !== 'hostinger-reach';
            }
        );

        if ( empty( $other_submenus ) ) {
            remove_menu_page( 'hostinger' );
        } else {
            // Hostinger parent already lists Reach — drop the standalone duplicate.
            remove_menu_page( 'hostinger-reach' );
        }
    }

    public function add_main_menu_page(): void {
        add_menu_page(
            $this->get_title(),
            $this->get_title(),
            'manage_options',
            'hostinger-reach',
            array( $this, 'render_plugin_content' ),
            $this->get_icon(),
            1.5
        );
    }

    public function add_sub_menu_page( array $submenus ): array {
        $submenus[] = array(
            'page_title' => $this->get_title(),
            'menu_title' => $this->get_title(),
            'capability' => 'manage_options',
            'menu_slug'  => 'hostinger-reach',
            'callback'   => array( $this, 'render_plugin_content' ),
            'menu_order' => 10,
        );

        return $submenus;
    }

    public function add_admin_bar_items( array $menu_items ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $menu_items;
        }

        $menu_items[] = array(
            'id'    => 'hostinger-reach',
            'title' => esc_html( $this->get_title() ),
            'href'  => self::get_reach_admin_url(),
        );

        return $menu_items;
    }

    public function render_plugin_content(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'hostinger-reach' ), '', array( 'response' => 403 ) );
        }

        $allowed_tags = array_merge(
            wp_kses_allowed_html( 'post' ),
            array(
                'svg'  => array(
                    'xmlns'   => true,
                    'width'   => true,
                    'height'  => true,
                    'viewbox' => true,
                    'fill'    => true,
                    'class'   => true,
                    'style'   => true,
                ),
                'path' => array(
                    'fill-rule' => true,
                    'clip-rule' => true,
                    'd'         => true,
                    'fill'      => true,
                ),
            )
        );
        echo wp_kses( WpMenu::renderMenuNavigation(), $allowed_tags );
        ?>
        <div id="hostinger-reach-app" class="hostinger-reach-app"></div>
        <?php
    }

    public static function get_reach_admin_url(): string {
        return admin_url( 'admin.php?page=hostinger-reach' );
    }

    private function get_title(): string {
        return __( 'Reach', 'hostinger-reach' );
    }

    private function get_icon(): string {
        return plugin_dir_url( dirname( __DIR__ ) ) . 'frontend/vue/assets/images/icons/reach-menu-logo.svg';
    }
}
