<?php

namespace Hostinger\AiTheme\Builder\Elementor;

defined( 'ABSPATH' ) || exit;

class PostBuildCustomizations {
	private const ANNOUNCEMENT_DISMISSED_META_KEY = 'elementor_admin_notices';
	private const INTRODUCTION_VIEWED_META_KEY = 'elementor_introduction';

	public function __construct() {
		if ( ! $this->should_apply_customizations() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'disable_announcements_for_admin' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'add_announcement_hiding_styles' ) );
	}

	private function should_apply_customizations(): bool {
		$builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );
		$is_elementor = $builder_type === 'elementor';
		$is_elementor_loaded = did_action( 'elementor/loaded' );

		return $is_elementor && $is_elementor_loaded;
	}

	public function disable_announcements_for_admin(): void {
		$current_user_id = get_current_user_id();

		if ( ! $current_user_id ) {
			return;
		}

		$this->mark_announcements_as_dismissed( $current_user_id );
		$this->mark_introduction_as_viewed( $current_user_id );
	}

	private function mark_announcements_as_dismissed( int $user_id ): void {
		$dismissed_notices = get_user_meta( $user_id, self::ANNOUNCEMENT_DISMISSED_META_KEY, true );

		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = array();
		}

		$notices_to_dismiss = array(
			'elementor_tracker',
			'elementor_dev_promote',
			'elementor_pro_promote',
			'elementor_announcement',
			'elementor_promotion',
		);

		$updated = false;
		foreach ( $notices_to_dismiss as $notice ) {
			if ( ! in_array( $notice, $dismissed_notices, true ) ) {
				$dismissed_notices[] = $notice;
				$updated = true;
			}
		}

		if ( $updated ) {
			update_user_meta( $user_id, self::ANNOUNCEMENT_DISMISSED_META_KEY, $dismissed_notices );
		}
	}

	private function mark_introduction_as_viewed( int $user_id ): void {
		$introduction_meta = get_user_meta( $user_id, self::INTRODUCTION_VIEWED_META_KEY, true );

		if ( empty( $introduction_meta ) || ! is_array( $introduction_meta ) ) {
			update_user_meta( $user_id, self::INTRODUCTION_VIEWED_META_KEY, array(
				'is_viewed' => true,
				'version'   => ELEMENTOR_VERSION ?? '1.0',
			) );
		}
	}

	public function add_announcement_hiding_styles(): void {
		?>
		<style type="text/css">
			/* Hide Elementor announcement popup */
			.e-announcements-root,
			#e-announcements-root,
			.elementor-templates-modal__header__logo-area-promotion,
			.elementor-promotion,
			.elementor-element-promotion {
				display: none !important;
				visibility: hidden !important;
				opacity: 0 !important;
				pointer-events: none !important;
			}
		</style>
		<?php
	}
}
