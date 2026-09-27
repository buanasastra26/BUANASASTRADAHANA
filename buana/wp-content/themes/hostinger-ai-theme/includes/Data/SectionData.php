<?php

namespace Hostinger\AiTheme\Data;

defined( 'ABSPATH' ) || exit;

class SectionData {
    private const HOMEPAGE_ALLOWED_BY_TYPE = array(
        'business' => array(
            'hero',
            'hero-video',
            'hero-services',
            'hero-india',
            'about-us',
            'services',
            'contact',
            'location',
            'customer-reviews',
            'call-to-action',
            'gallery',
            'faq',
        ),
        'other' => array(
            'hero',
            'hero-video',
            'hero-services',
            'hero-india',
            'about-us',
            'services',
            'contact',
            'location',
            'customer-reviews',
            'call-to-action',
            'gallery',
            'faq',
        ),
        'online store' => array(
            'hero',
            'hero-video',
            'hero-for-online-store',
            'hero-india',
            'about-us',
            'product-categories',
            'product-list',
            'customer-reviews',
            'call-to-action',
            'gallery',
            'faq',
            'contact',
        ),
        'blog' => array(
            'hero',
            'hero-video',
            'hero-india',
            'about-us',
            'blog-posts',
            'customer-reviews',
            'call-to-action',
            'contact',
        ),
        'landing page' => array(
            'hero',
            'hero-video',
            'hero-india',
            'about-us',
            'services',
            'customer-reviews',
            'call-to-action',
            'contact',
        ),
        'booking' => array(
            'hero',
            'hero-services',
            'hero-video',
            'hero-india',
            'about-us',
            'services',
            'booking',
            'customer-reviews',
            'contact',
            'location',
            'call-to-action',
        ),
        'portfolio' => array(
            'hero',
            'hero-video',
            'hero-portfolio',
            'hero-india',
            'about-us',
            'projects',
            'my-background',
            'customer-reviews',
            'call-to-action',
            'gallery',
            'contact',
        ),
        'affiliate-marketing' => array(
            'hero',
            'hero-video',
            'hero-india',
            'about-us',
            'blog-posts',
            'customer-reviews',
            'call-to-action',
            'contact',
        ),
    );

    private const HOMEPAGE_SINGLETONS = array(
        'hero' => array(
            'hero',
            'hero-video',
            'hero-portfolio',
            'hero-for-online-store',
            'hero-services',
            'hero-india',
        ),
        'cta'  => array(
            'call-to-action'
        ),
        'form' => array(
            'contact',
            'booking'
        ),
    );

    public static function get_homepage_sections_pool( array $website_type, bool $is_one_page = false ): array {
        if ( empty( $website_type ) ) {
            return array();
        }

        if ( $is_one_page ) {
            $pool = array();
            foreach ( $website_type as $type ) {
                if ( isset( self::HOMEPAGE_ALLOWED_BY_TYPE[ $type ] ) ) {
                    $pool = array_merge( $pool, self::HOMEPAGE_ALLOWED_BY_TYPE[ $type ] );
                }
            }

            return array_values( array_unique( $pool ) );
        }

        $primary = $website_type[0];
        return self::HOMEPAGE_ALLOWED_BY_TYPE[ $primary ] ?? array();
    }

    public static function get_homepage_singletons(): array {
        return self::HOMEPAGE_SINGLETONS;
    }

    public static function get_sections_for_website_type( array $website_type = array(), bool $should_render_india = false ): array {
        $sections = array(
            'hero-video'              => 'Title, subtitle, cta button with a fullscreen background video. Preferred hero section for modern and dynamic websites.',
            'hero'                    => 'Title, subtitle, cta buttons, optional video background.',
            'about-us'                => 'Title, subtitle, image.',
            'services'                => 'Title, subtitle, cards about services.',
            'contact'                 => 'Title, subtitle, contact information, form.',
            'location'                => 'Title, subtitle, address, map.',
            'projects'                => 'Title, subtitle, project cards.',
            'agenda'                  => 'Title, subtitle, agenda/schedule cards for an event (time, session, speaker). Use only for an agenda or schedule page.',
            'customer-reviews'        => 'Title, subtitle, single customer review.',
            'call-to-action'          => 'Title, description, cta and illustration.',
            'my-background'           => 'My Background section used for personal or portfolio sites, showing details about education, work, skills, and achievements.',
            'gallery'                 => 'Gallery section displays images.',
            'blog-posts'              => 'Contains the content of the blog post.',
            'faq'                     => 'Title, FAQ questions and answers.',
            'real-estate-list'        => 'Real Estate Title, description, cta and real estate image.',
            'ticket-list'             => 'Ticket Title, description, cta and image.',
            'hotel-room-list'         => 'Hotel Room Title, description, cta and image.',
            'travel-destination-list' => 'Title, subtitle, description, cta and image.',
            'food-menu'               => 'Title, subtitle, food menu, description, cta and image.',
        );

        if ( in_array( 'booking', $website_type, true ) ) {
            $sections['booking'] = 'Title, description, image.';
        }

        if ( in_array( 'portfolio', $website_type, true ) ) {
            $sections['hero-portfolio'] = 'Title, subtitle, cta, social icons, portfolio images or video background.';
        }

        if ( WebsiteTypeHelper::contains( $website_type, 'online store' ) ) {
            $sections['hero-for-online-store'] = 'Title, subtitle and cta buttons.';
            $sections['product-categories']    = 'Contains the product category CTAs.';
            $sections['product-list']          = 'Contains product list CTAs.';
        }

        if ( in_array( 'business', $website_type, true ) ) {
            $sections['hero-services'] = 'Title, subtitle, cta buttons, optional video background. Preferred hero section for services websites.';
        }

        if ( $should_render_india ) {
            $sections['hero-india'] = 'Title, subtitle, cta buttons, optional video background. Preferred hero section for India websites and India locales.';
            unset( $sections['hero-video'] );
            unset( $sections['hero'] );

            if ( isset( $sections['hero-portfolio'] ) ) {
                unset( $sections['hero-portfolio'] );
            }

            if ( isset( $sections['hero-services'] ) ) {
                unset( $sections['hero-services'] );
            }
        }

        return $sections;
    }
}
