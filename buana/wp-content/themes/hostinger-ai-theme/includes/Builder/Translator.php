<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class Translator {
    public function translate_string( string $content ): string {
        if ( ! empty( $this->get_translations() ) ) {
            foreach ( $this->get_translations() as $key => $translation ) {
                $content = str_replace( 'trans-' . $key, $translation, $content );
            }
        }

        return $content;
    }

    public function translate_array( array $data ): array {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $data[ $key ] = $this->translate_array( $value );
            } elseif ( is_string( $value ) ) {
                $data[ $key ] = $this->translate_string( $value );
            }
        }

        return $data;
    }

    private function get_translations(): array {
        $contact_data    = get_option( 'hostinger_ai_contact', array() );
        $contact_email   = ! empty( $contact_data['email'] ) ? $contact_data['email'] : get_bloginfo( 'admin_email' );
        $contact_address = ! empty( $contact_data['address'] ) ? $contact_data['address'] : 'Example avenue 100, example country';
        $country_code    = ltrim( (string) ( $contact_data['phone_country_code'] ?? '' ), '+' );

        $contact_phone = ! empty( $contact_data['phone'] ) ? '+' . $country_code . $contact_data['phone'] : '(+1) 23456789';

        $contact_phone_display = ! empty( $contact_data['phone'] ) ? '(+' . $country_code . ') ' . $contact_data['phone'] : '(+1) 23456789';

        $social_data = get_option( 'hostinger_ai_social_links', array() );

        return array(
            // Navigation translations.
            'menu'                     => __( 'Menu', 'hostinger-ai-theme' ),
            'contacts'                 => __( 'Contacts', 'hostinger-ai-theme' ),
            'socials'                  => __( 'Socials', 'hostinger-ai-theme' ),
            'newsletter'               => __( 'Subscribe to our newsletter', 'hostinger-ai-theme' ),

            // Contact form widget translations.
            'contact_title'            => __( 'Get in Touch', 'hostinger-ai-theme' ),
            'contact_description'      => __( 'We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.', 'hostinger-ai-theme' ),
            'button_text'              => __( 'Send Message', 'hostinger-ai-theme' ),
            'name_label'               => __( 'Name', 'hostinger-ai-theme' ),
            'name_placeholder'         => __( 'What\'s your name?', 'hostinger-ai-theme' ),
            'email_label'              => __( 'Email', 'hostinger-ai-theme' ),
            'email_placeholder'        => __( 'What\'s your email?', 'hostinger-ai-theme' ),
            'message_label'            => __( 'Message', 'hostinger-ai-theme' ),
            'message_placeholder'      => __( 'Write your message...', 'hostinger-ai-theme' ),
            'date_label'               => __( 'Date', 'hostinger-ai-theme' ),
            'date_placeholder'         => __( 'Select a date', 'hostinger-ai-theme' ),
            'top_rated_by_100_clients' => __( 'Top rated by 100+ clients', 'hostinger-ai-theme' ),
            'current-year'             => wp_date( 'Y' ),
            'all-rights-reserved'      => __( 'All rights reserved.', 'hostinger-ai-theme' ),

            // Our location blocks.
            'address-heading'          => __( 'Address', 'hostinger-ai-theme' ),
            'hours-heading'            => __( 'Hours', 'hostinger-ai-theme' ),

            // Dynamic contact data (display, rendered as visible text).
            'contact_email'            => $contact_email,
            'contact_address'          => $contact_address,
            'contact_phone'            => $contact_phone_display,
            'encoded_email'            => esc_attr( $contact_email ),
            'encoded_address'          => rawurlencode( $contact_address ),
            'encoded_phone'            => esc_attr( $contact_phone ),
            'whatsapp-number'          => 'https://wa.me/' . $contact_phone,

            // Dynamic social links (full URLs built from username or link).
            'social_instagram_url'     => $this->build_social_url( (string) ( $social_data['instagram'] ?? '' ), 'instagram' ),
            'social_facebook_url'      => $this->build_social_url( (string) ( $social_data['facebook'] ?? '' ), 'facebook' ),
            'social_tiktok_url'        => $this->build_social_url( (string) ( $social_data['tiktok'] ?? '' ), 'tiktok' ),
        );
    }

    private function build_social_url( string $value, string $platform ): string {
        $homepages = array(
            'instagram' => 'https://instagram.com',
            'facebook'  => 'https://facebook.com',
            'tiktok'    => 'https://tiktok.com',
        );

        $value = trim( $value );

        if ( $value === '' ) {
            return esc_url( $homepages[ $platform ] );
        }

        // Already a URL (full or scheme-less domain).
        if ( preg_match( '#^(https?://)?(www\.)?(m\.)?(instagram\.com|facebook\.com|fb\.com|tiktok\.com)/#i', $value ) ) {
            if ( ! preg_match( '#^https?://#i', $value ) ) {
                $value = 'https://' . $value;
            }

            return esc_url( $value );
        }

        // Treat as username.
        $username = ltrim( $value, '@' );

        switch ( $platform ) {
            case 'tiktok':
                $url = 'https://tiktok.com/@' . $username;
                break;
            case 'facebook':
                $url = 'https://facebook.com/' . $username;
                break;
            case 'instagram':
            default:
                $url = 'https://instagram.com/' . $username;
                break;
        }

        return esc_url( $url );
    }
}
