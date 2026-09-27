<?php

namespace Hostinger\Reach\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Base;
use Hostinger\Reach\Blocks\SubscriptionFormBlock;

class SubscriptionFormElementorWidget extends Widget_Base {

    public const FORM_ID_PREFIX = 'elementor-hostinger-reach-form-';
    public const WIDGET_NAME    = 'hostinger-reach';

    public function get_name(): string {
        return self::WIDGET_NAME;
    }


    public function get_title(): ?string {
        return __( 'Hostinger Reach', 'hostinger-reach' );
    }

    public function get_icon(): string {
        return 'eicon-envelope';
    }

    public function get_keywords(): array {
        return array(
            'form',
            'forms',
            'reach',
            'contact form',
            'hostinger',
            'email',
            'newsletter',
        );
    }

    public function get_categories(): array {
        return array(
            'basic',
        );
    }

    public function get_style_depends(): array {
        return array( 'hostinger-reach-subscription-block' );
    }

    public function get_script_depends(): array {
        return array( 'hostinger-reach-subscription-block-view' );
    }

    protected function register_controls(): void {

        $this->start_controls_section(
            'form',
            array(
                'label' => esc_html__( 'Form', 'hostinger-reach' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'formId',
            array(
                'label'      => esc_html__( 'Form ID', 'hostinger-reach' ),
                'type'       => Controls_Manager::HIDDEN,
                'input_type' => 'hidden',
                'default'    => '',
            )
        );

        $form_builder_description = sprintf(
            /* translators: %s: "Learn more" link. */
            esc_html__( 'Choose a Reach Form Builder template to embed instead. The Reach script loads it automatically. %s', 'hostinger-reach' ),
            '<a href="https://www.hostinger.com/support/hostinger-reach-form-builder-overview-setup-guide/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more', 'hostinger-reach' ) . '</a>'
        );

        $this->add_control(
            'formBuilderSelector',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw'  => '<div class="hostinger-reach-elementor-selector"></div>',
            )
        );

        $this->add_control(
            'formBuilderId',
            array(
                'label'   => esc_html__( 'Form Builder Template', 'hostinger-reach' ),
                'type'    => Controls_Manager::HIDDEN,
                'default' => '',
            )
        );

        $builder_mode_conditions = array(
            'relation' => 'or',
            'terms'    => array(
                array(
                    'name'     => 'formBuilderManual',
                    'operator' => '===',
                    'value'    => 'yes',
                ),
                array(
                    'name'     => 'formBuilderId',
                    'operator' => '!==',
                    'value'    => '',
                ),
            ),
        );

        $this->add_control(
            'formBuilderManual',
            array(
                'label'        => esc_html__( 'Enter template ID manually', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 'yes',
                'default'      => '',
                'conditions'   => $builder_mode_conditions,
            )
        );

        $this->add_control(
            'formBuilderIdManual',
            array(
                'label'       => esc_html__( 'Form Builder Template ID', 'hostinger-reach' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => $form_builder_description,
                'condition'   => array(
                    'formBuilderManual' => 'yes',
                ),
            )
        );

        $no_template_conditions = array(
            'relation' => 'and',
            'terms'    => array(
                array(
                    'name'     => 'formBuilderManual',
                    'operator' => '!==',
                    'value'    => 'yes',
                ),
                array(
                    'name'     => 'formBuilderId',
                    'operator' => '===',
                    'value'    => '',
                ),
            ),
        );

        $this->add_control(
            'showName',
            array(
                'label'        => esc_html__( 'Show Name', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 1,
                'default'      => 0,
                'conditions'   => $no_template_conditions,
            )
        );

        $this->add_control(
            'showSurname',
            array(
                'label'        => esc_html__( 'Show Surname', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 1,
                'default'      => 0,
                'conditions'   => $no_template_conditions,
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if ( empty( $settings['formId'] ) ) {
            $settings['formId'] = self::FORM_ID_PREFIX . $this->get_id();
        }

        if ( ( $settings['formBuilderManual'] ?? '' ) === 'yes' && ! empty( $settings['formBuilderIdManual'] ) ) {
            $settings['formBuilderId'] = $settings['formBuilderIdManual'];
        }

        $is_connected = (bool) apply_filters( 'hostinger_reach_is_connected', true );

        if ( ! $is_connected && $this->is_elementor_editor() ) {
            $this->print_connect_notice();
        }

        SubscriptionFormBlock::render_block_html( $settings, ElementorIntegration::INTEGRATION_NAME, $is_connected );
    }

    protected function content_template(): void {
        $is_connected = (bool) apply_filters( 'hostinger_reach_is_connected', true );

        if ( ! $is_connected ) {
            $this->print_connect_notice();
        }
        ?>
        <# var reachFormBuilderId = 'yes' === settings.formBuilderManual ? settings.formBuilderIdManual : settings.formBuilderId; #>
        <# reachFormBuilderId = reachFormBuilderId && /^[a-zA-Z0-9-]+$/.test( reachFormBuilderId ) ? reachFormBuilderId : ''; #>
        <# var reachFormId = settings.formId ? settings.formId : '<?php echo esc_js( self::FORM_ID_PREFIX ); ?>preview'; #>
        <# if ( reachFormBuilderId ) { #>
            <div data-reach-form="{{ reachFormBuilderId }}"></div>
        <# } else { #>
        <div class="hostinger-reach-block-subscription-form-wrapper">
            <form id="{{ reachFormId }}" class="hostinger-reach-block-subscription-form">
                <input type="hidden" name="id" value="{{ reachFormId }}">
                <input type="hidden" name="metadata.plugin" value="elementor">

                <div class="hostinger-reach-block-form-field">
                    <label
                        for="{{ reachFormId }}-email"><?php esc_html_e( 'Email', 'hostinger-reach' ); ?>
                        <span class="required">*</span></label>
                    <input type="email" id="{{ reachFormId }}-email" name="email" required>
                </div>

                <# if ( settings.showName ) { #>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="{{ reachFormId }}-name"><?php esc_html_e( 'Name', 'hostinger-reach' ); ?></label>
                        <input type="text" id="{{ reachFormId }}-name" name="name">
                    </div>
                <# } #>

                <# if ( settings.showSurname ) { #>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="{{ reachFormId }}-surname"><?php esc_html_e( 'Surname', 'hostinger-reach' ); ?></label>
                        <input type="text" id="{{ reachFormId }}-surname" name="surname">
                    </div>
                <# } #>

                <button
                    type="submit"
                    class="hostinger-reach-block-submit wp-block-button__link has-light-color has-color-3-background-color has-text-color has-background has-link-color has-medium-font-size wp-element-button">
                    <?php esc_html_e( 'Subscribe', 'hostinger-reach' ); ?>
                </button>

                <div class="reach-subscription-message" style="display: none;"></div>
            </form>
        </div>
        <# } #>
        <?php
    }

    private function is_elementor_editor(): bool {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return false;
        }

        $plugin = ElementorPlugin::instance();

        $is_edit_mode    = isset( $plugin->editor ) && $plugin->editor->is_edit_mode();
        $is_preview_mode = isset( $plugin->preview ) && $plugin->preview->is_preview_mode();

        return $is_edit_mode || $is_preview_mode;
    }

    private function print_connect_notice(): void {
        ?>
        <div class="hostinger-reach-block-connect">
            <div class="hostinger-reach-block-connect__title">
                <?php esc_html_e( 'You are not connected to Hostinger Reach', 'hostinger-reach' ); ?>
            </div>
            <div class="hostinger-reach-block-connect__subtitle">
                <?php esc_html_e( 'You are not connected to Hostinger Reach. To gain full access to this block, you need to connect your Hostinger Reach account.', 'hostinger-reach' ); ?>
            </div>
            <div class="hostinger-reach-block-connect__button-wrap">
                <a
                    target="_blank"
                    rel="noopener noreferrer"
                    href="<?php echo esc_url( admin_url( 'admin.php?page=hostinger-reach' ) ); ?>"
                    class="hostinger-block-button hostinger-block-button--is-normal hostinger-block-button--is-primary">
                    <?php esc_html_e( 'Connect Now', 'hostinger-reach' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
