<?php

namespace Hostinger\Reach\Integrations\Elementor;

use Elementor\Core\Documents_Manager;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Utils;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Submissions\Database\Entities\Form_Snapshot;
use ElementorPro\Modules\Forms\Submissions\Database\Query;
use ElementorPro\Modules\Forms\Submissions\Database\Repositories\Form_Snapshot_Repository;
use Exception;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Api\ResourceIdManager;
use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\IntegrationInterface;
use Hostinger\Reach\Integrations\IntegrationWithForms;
use Hostinger\Reach\Repositories\FormRepository;
use WP_Post;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class ElementorIntegration extends IntegrationWithForms implements IntegrationInterface {

    public const INTEGRATION_NAME            = 'elementor';
    public const AUTOLOAD_META_KEY           = 'hostinger_reach_add_elementor_widget';
    public const AUTOLOAD_FORM_BUILDER_ID    = 'hostinger_reach_add_elementor_widget_form_builder_id';
    public const ADD_BLOCK_QUERY_ARG         = 'hostinger_reach_add_block';
    public const ADD_BLOCK_NONCE             = 'hostinger_reach_add_block';
    public const EDITOR_SCROLL_SCRIPT_HANDLE = 'hostinger-reach-elementor-editor-scroll';
    public const EDITOR_SCROLL_SCRIPT_FILE   = 'elementor-editor-scroll.js';
    public const EDITOR_FORM_SCRIPT_HANDLE   = 'hostinger-reach-elementor-form-selector';
    public const EDITOR_FORM_SCRIPT_FILE     = 'elementor-reach-form.js';
    public const EDITOR_FORM_STYLE_FILE      = 'elementor-reach-form.css';

    protected SubscriptionFormElementorWidget $widget;
    private ReachApiHandler $reach_api_handler;

    public function __construct( FormRepository $form_repository, ReachApiHandler $reach_api_handler ) {
        parent::__construct( $form_repository );
        $this->reach_api_handler = $reach_api_handler;
    }

    public function init(): void {
        parent::init();
        add_action( 'hostinger_reach_integration_activated', array( $this, 'on_integration_activated' ) );
        $this->register_widget_hooks();
    }

    public function register_widget_hooks(): void {
        add_action( 'elementor/widgets/register', array( $this, 'register_new_widgets' ) );
        add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'maybe_insert_reach_widget' ) );
        add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_scroll_script' ) );
        add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_form_selector' ) );
        add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_styles' ) );
    }

    public function enqueue_editor_scroll_script(): void {
        $script_path = HOSTINGER_REACH_PLUGIN_DIR . 'frontend/dist/' . self::EDITOR_SCROLL_SCRIPT_FILE;
        if ( ! file_exists( $script_path ) ) {
            return;
        }

        wp_enqueue_script(
            self::EDITOR_SCROLL_SCRIPT_HANDLE,
            Functions::get_frontend_url() . self::EDITOR_SCROLL_SCRIPT_FILE,
            array(),
            filemtime( $script_path ),
            true
        );
    }

    public function enqueue_editor_form_selector(): void {
        $script_path = HOSTINGER_REACH_PLUGIN_DIR . 'frontend/dist/' . self::EDITOR_FORM_SCRIPT_FILE;
        if ( ! file_exists( $script_path ) ) {
            return;
        }

        wp_enqueue_script(
            self::EDITOR_FORM_SCRIPT_HANDLE,
            Functions::get_frontend_url() . self::EDITOR_FORM_SCRIPT_FILE,
            array(),
            filemtime( $script_path ),
            true
        );

        $style_path = HOSTINGER_REACH_PLUGIN_DIR . 'frontend/dist/' . self::EDITOR_FORM_STYLE_FILE;
        if ( file_exists( $style_path ) ) {
            wp_enqueue_style(
                self::EDITOR_FORM_SCRIPT_HANDLE,
                Functions::get_frontend_url() . self::EDITOR_FORM_STYLE_FILE,
                array(),
                filemtime( $style_path )
            );
        }

        $resource_id = $this->reach_api_handler->get_resource_id();
        if ( $resource_id === ResourceIdManager::NON_EXISTENT_RESOURCE_ID ) {
            $resource_id = '';
        }

        wp_localize_script(
            self::EDITOR_FORM_SCRIPT_HANDLE,
            'hostinger_reach_elementor_data',
            array(
                'restUrl'     => esc_url_raw( rest_url() ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'resourceId'  => $resource_id,
                'reachDomain' => $this->reach_api_handler->get_reach_domain(),
                'domain'      => $this->reach_api_handler->get_functions()->get_host_info(),
                'widgetName'  => SubscriptionFormElementorWidget::WIDGET_NAME,
                'embedScript' => HOSTINGER_REACH_EMBED_SCRIPT_URL,
                'isConnected' => $this->reach_api_handler->is_connected() ? 'yes' : 'no',
                'connectUrl'  => admin_url( 'admin.php?page=hostinger-reach' ),
                'i18n'        => array(
                    'connectTitle'      => __( 'You are not connected to Hostinger Reach', 'hostinger-reach' ),
                    'connectSubtitle'   => __( 'You are not connected to Hostinger Reach. To gain full access to this block, you need to connect your Hostinger Reach account.', 'hostinger-reach' ),
                    'connectToReach'    => __( 'Connect to Reach', 'hostinger-reach' ),
                    'cardNotConnected'  => __( 'Reach is not Connected', 'hostinger-reach' ),
                    'useTemplate'       => __( 'Use Form builder template', 'hostinger-reach' ),
                    'useClassic'        => __( 'Use classic Reach block', 'hostinger-reach' ),
                    'changeSelection'   => __( 'Change the selection', 'hostinger-reach' ),
                    'editInReach'       => __( 'Edit in Reach', 'hostinger-reach' ),
                    'selectForm'        => __( 'Select a form', 'hostinger-reach' ),
                    'createForm'        => __( 'Create a new form', 'hostinger-reach' ),
                    'refresh'           => __( 'Refresh forms', 'hostinger-reach' ),
                    'cancel'            => __( 'Cancel', 'hostinger-reach' ),
                    'continue'          => __( 'Continue', 'hostinger-reach' ),
                    'noForms'           => __( 'No forms yet. Create your first form in Hostinger Reach.', 'hostinger-reach' ),
                    'loading'           => __( 'Loading…', 'hostinger-reach' ),
                    'searchPlaceholder' => __( 'Search a template', 'hostinger-reach' ),
                    'noResults'         => __( 'No forms match your search.', 'hostinger-reach' ),
                    'emptyTitle'        => __( 'No forms yet', 'hostinger-reach' ),
                    'emptySubtitle'     => __( 'Build your first form in Hostinger Reach and add it to any page on your site.', 'hostinger-reach' ),
                    'emptyCreate'       => __( 'Create a form in Reach', 'hostinger-reach' ),
                ),
            )
        );
    }

    public function enqueue_preview_styles(): void {
        $handle = self::EDITOR_FORM_SCRIPT_HANDLE . '-preview';

        wp_register_style( $handle, false, array(), HOSTINGER_REACH_PLUGIN_VERSION );
        wp_enqueue_style( $handle );

        $widget_selector = '.elementor-widget-' . SubscriptionFormElementorWidget::WIDGET_NAME;
        $css             = sprintf(
            '%1$s .hostinger-reach-block-subscription-form-wrapper,%1$s [data-reach-form]{pointer-events:none;}',
            $widget_selector
        );

        wp_add_inline_style( $handle, $css );
    }

    public function active_integration_hooks(): void {
        add_action( 'transition_post_status', array( $this, 'handle_transition_post_status' ), 10, 3 );
        add_filter( 'hostinger_reach_get_group', array( $this, 'filter_hostinger_reach_get_group' ), 10, 2 );
        add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_elementor_pro_new_record' ) );
        add_action( 'wp_insert_post', array( $this, 'flag_new_elementor_post' ), 10, 3 );
        add_action( 'admin_init', array( $this, 'flag_existing_elementor_post' ) );
    }

    public function on_integration_activated( string $integration_name ): void {
        if ( $integration_name === self::INTEGRATION_NAME ) {
            $this->set_elementor_onboarding();
            $this->init_existing_forms();
        }
    }

    public function handle_transition_post_status( string $new_status, string $old_status, WP_Post $post ): void {
        if ( $new_status === 'publish' ) {
            $this->set_forms( $post );
            $this->maybe_unset_forms( $post );
        } elseif ( $old_status === 'publish' ) {
            $this->unset_all_forms( $post );
        }
    }

    public function register_new_widgets(): void {
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return;
        }
        ElementorPlugin::instance()->widgets_manager->register( $this->get_widget() );
    }

    public function filter_hostinger_reach_get_group( string $group, string $form_id ): string {
        if ( ! empty( $group ) || ! $this->is_elementor_form_id( $form_id ) ) {
            return $group;
        }

        try {
            $form = $this->form_repository->get( $form_id );
            $post = $form['post'] ?? null;
            if ( $post ) {
                return $post['post_title'] ?? self::INTEGRATION_NAME;
            }
        } catch ( Exception $e ) {
            return self::INTEGRATION_NAME;
        }

        return self::INTEGRATION_NAME;
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public static function is_active(): bool {
        return class_exists( 'Elementor\Plugin' );
    }

    public static function get_new_page_url(): string {
        if ( ! class_exists( 'Elementor\Core\Documents_Manager' ) ) {
            return '';
        }

        return Documents_Manager::get_create_new_post_url( 'page' );
    }

    public function get_form_ids( WP_Post $post ): array {
        return array_values(
            array_unique(
                array_merge(
                    $this->get_reach_form_ids( $post ),
                    $this->get_elementor_form_ids_from_actions()
                )
            )
        );
    }

    public function get_plugin_data(): PluginData {
        if ( class_exists( 'Elementor\Core\Documents_Manager' ) ) {
            $add_form_url = add_query_arg(
                array(
                    self::ADD_BLOCK_QUERY_ARG => '1',
                ),
                Documents_Manager::get_create_new_post_url()
            );
        } else {
            $add_form_url = '';
        }

        return PluginData::from_array(
            array(
                'id'                  => self::INTEGRATION_NAME,
                'folder'              => 'elementor',
                'file'                => 'elementor.php',
                'admin_url'           => 'admin.php?page=elementor',
                'add_form_url'        => $add_form_url,
                'edit_url'            => 'post.php?post={post_id}&action=elementor#elementor-element-{form_id}',
                'url'                 => 'https://wordpress.org/plugins/elementor/',
                'download_url'        => 'https://downloads.wordpress.org/plugin/elementor.zip',
                'title'               => __( 'Elementor', 'hostinger-reach' ),
                'icon'                => null,
                'is_view_form_hidden' => false,
                'is_edit_form_hidden' => false,
                'can_toggle_forms'    => true,
                'import_enabled'      => $this->is_import_supported(),
            )
        );
    }

    public function handle_elementor_pro_new_record( Form_Record $record ): void {
        $email = $this->find_email( $record->get_formatted_data() );
        if ( $email ) {
            $form                 = $this->get_form_by_request();
            $form_repository_item = $this->form_repository->get( $form->id ?? '' );
            if ( empty( $form_repository_item ) || $form_repository_item['is_active'] === false ) {
                return;
            }

            do_action(
                'hostinger_reach_submit',
                array(
                    'group'    => ! empty( $form ) ? $form->name : self::INTEGRATION_NAME,
                    'email'    => $email,
                    'metadata' => array(
                        'plugin'  => self::INTEGRATION_NAME . '-pro',
                        'form_id' => ! empty( $form ) ? $form->id : null,
                    ),
                )
            );
        }
    }

    public function flag_new_elementor_post( int $post_id, WP_Post $post, bool $update ): void {
        if ( $update ) {
            return;
        }

        if ( empty( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) && empty( $_GET[ self::AUTOLOAD_META_KEY ] ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'elementor_action_new_post' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $form_builder_id = isset( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) ) : '';

        // '1' is the legacy marker for "add the default form" and does not reference a Form Builder template.
        if ( $form_builder_id === '1' ) {
            $form_builder_id = '';
        }

        update_post_meta( $post_id, self::AUTOLOAD_META_KEY, '1' );
        update_post_meta( $post_id, self::AUTOLOAD_FORM_BUILDER_ID, $form_builder_id );
    }

    public function flag_existing_elementor_post(): void {
        if ( empty( $_GET['action'] ) || $_GET['action'] !== 'elementor' ) {
            return;
        }

        if ( empty( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        if ( ! $post_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::ADD_BLOCK_NONCE ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( ! $this->is_elementor_post( $post_id ) ) {
            return;
        }

        $form_builder_id = sanitize_text_field( wp_unslash( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) );

        // '1' is the legacy marker for "add the default form" and does not reference a Form Builder template.
        if ( $form_builder_id === '1' ) {
            $form_builder_id = '';
        }

        update_post_meta( $post_id, self::AUTOLOAD_META_KEY, '1' );
        update_post_meta( $post_id, self::AUTOLOAD_FORM_BUILDER_ID, $form_builder_id );
    }

    public function is_elementor_post( int $post_id ): bool {
        $edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );

        return $edit_mode === 'builder';
    }

    public function maybe_insert_reach_widget(): void {
        if ( ! $this->is_elementor_installed() ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        $should_add_widget = get_post_meta( $post_id, self::AUTOLOAD_META_KEY, true ) === '1';
        if ( ! $should_add_widget ) {
            return;
        }

        $document = ElementorPlugin::instance()->documents->get( $post_id );
        if ( ! $document ) {
            return;
        }

        $form_builder_id = (string) get_post_meta( $post_id, self::AUTOLOAD_FORM_BUILDER_ID, true );

        $elements = $document->get_elements_data();

        $widget_data = array(
            array(
                'id'       => Utils::generate_random_string(),
                'elType'   => 'section',
                'settings' => array(),
                'elements' => array(
                    array(
                        'id'       => Utils::generate_random_string(),
                        'elType'   => 'column',
                        'settings' => array(
                            '_column_size' => 100,
                        ),
                        'elements' => array(
                            array(
                                'id'         => Utils::generate_random_string(),
                                'elType'     => 'widget',
                                'widgetType' => SubscriptionFormElementorWidget::WIDGET_NAME,
                                'settings'   => array(
                                    'formId'        => SubscriptionFormElementorWidget::FORM_ID_PREFIX . random_int( 1, PHP_INT_MAX ),
                                    'formBuilderId' => $form_builder_id,
                                    'showName'      => 0,
                                    'showSurname'   => 0,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $document->save( array( 'elements' => array_merge( $elements, $widget_data ) ) );
        delete_post_meta( $post_id, self::AUTOLOAD_META_KEY );
        delete_post_meta( $post_id, self::AUTOLOAD_FORM_BUILDER_ID );
    }

    public function is_import_supported(): bool {
        return class_exists( 'ElementorPro\Modules\Forms\Submissions\Database\Repositories\Form_Snapshot_Repository' )
                && class_exists( 'ElementorPro\Modules\Forms\Submissions\Database\Query' );
    }

    public function get_import_summary(): array {
        $summary = array();

        if ( ! $this->is_import_supported() ) {
            return $summary;
        }

        $forms = Form_Snapshot_Repository::instance()->all();
        $query = Query::get_instance();

        foreach ( $forms as $form ) {
            $form_id = $form->id ?? null;
            $post_id = $form->post_id ?? null;

            if ( ! $form_id || ! $post_id ) {
                continue;
            }

            $counts = $query->count_submissions_by_status(
                array(
                    'form' => array(
                        'value' => $post_id . '_' . $form_id,
                    ),
                )
            );

            $summary[ $form_id ] = array(
                'title'    => $form->name ?? $form_id,
                'contacts' => (int) $counts->get( 'read', 0 ) + (int) $counts->get( 'unread', 0 ),
            );
        }

        return $summary;
    }

    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        if ( ! $this->is_import_supported() ) {
            return array();
        }

        if ( empty( $form_id ) ) {
            return array();
        }

        try {
            $form = $this->form_repository->get( $form_id );
        } catch ( Exception $e ) {
            return array();
        }

        $post_id = $form['post']['ID'] ?? null;
        $page    = (int) floor( $offset / $limit ) + 1;
        $result  = Query::get_instance()->get_submissions(
            array(
                'page'             => $page,
                'per_page'         => $limit,
                'filters'          => array(
                    'form'   => array(
                        'value' => $post_id . '_' . $form_id,
                    ),
                    'status' => array(
                        'value' => 'all',
                    ),
                ),
                'with_meta'        => true,
                'with_form_fields' => false,
            )
        );

        $submissions = $result['data'] ?? array();
        $entries     = array();

        foreach ( $submissions as $submission ) {
            $email = $this->find_email( array( $submission['main']['value'] ?? '' ) );

            if ( ! $email ) {
                $email = $this->find_email( array_column( $submission['values'], 'value' ) );
            }

            if ( ! $email ) {
                continue;
            }

            $contact = new ReachContact(
                $email,
                '',
                '',
                array(
                    'plugin'  => self::INTEGRATION_NAME,
                    'form_id' => $form_id,
                    'group'   => $form->name ?? self::INTEGRATION_NAME,
                )
            );

            $entries[] = $contact->to_array();
        }

        return $entries;
    }

    public function init_existing_forms(): void {
        $post_ids = $this->get_elementor_posts();
        foreach ( $post_ids as $post_id ) {
            $this->update_forms_from_post( $post_id );
        }
    }

    public function set_elementor_onboarding(): void {
        update_option( 'elementor_onboarded', 1 );
    }

    private function get_widget(): ?Widget_Base {
        if ( ! $this->is_elementor_installed() ) {
            return null;
        }
        return new SubscriptionFormElementorWidget();
    }

    private function update_forms_from_post( int $post_id ): void {
        $form_ids = $this->get_forms_from_post_id( $post_id );
        $this->update_form_repository( $form_ids, $post_id );
    }

    private function set_forms( WP_Post $post ): void {
        $elementor_reach_forms = $this->get_reach_form_ids( $post );
        $elementor_pro_forms   = $this->get_elementor_form_ids_from_actions();
        $form_ids              = array_values( array_unique( array_merge( $elementor_reach_forms, $elementor_pro_forms ) ) );

        $this->update_form_repository( $form_ids, $post->ID );
    }

    private function get_reach_form_ids( WP_Post $post ): array {
        return array_values(
            array_unique(
                array_merge(
                    $this->get_elementor_form_ids_from_content( $post->post_content ),
                    $this->get_reach_form_ids_from_post_id( $post->ID ),
                    $this->get_reach_form_ids_from_actions()
                )
            )
        );
    }

    private function get_reach_form_ids_from_post_id( int $post_id ): array {
        $form_ids = array();

        $elementor_metadata = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! is_string( $elementor_metadata ) || $elementor_metadata === '' ) {
            return $form_ids;
        }

        $elementor_metadata = json_decode( $elementor_metadata, true );
        if ( ! is_array( $elementor_metadata ) ) {
            return $form_ids;
        }

        foreach ( $elementor_metadata as $element ) {
            $form_ids = array_merge( $form_ids, $this->find_reach_form_widget( $element ) );
        }

        return $form_ids;
    }

    private function get_reach_form_ids_from_actions(): array {
        $form_ids = array();
        $actions  = json_decode( wp_unslash( $_POST['actions'] ?? '' ), true );
        $status   = $actions['save_builder']['data']['status'] ?? 'draft';
        $elements = $actions['save_builder']['data']['elements'] ?? array();

        if ( ! empty( $elements ) && $status === 'publish' ) {
            foreach ( $elements as $element ) {
                $form_ids = array_merge( $form_ids, $this->find_reach_form_widget( $element ) );
            }
        }

        return $form_ids;
    }

    private function find_reach_form_widget( array $element ): array {
        $form_ids = array();

        $is_reach_widget = ( $element['widgetType'] ?? '' ) === SubscriptionFormElementorWidget::WIDGET_NAME;
        $form_id         = $element['settings']['formId'] ?? '';

        if ( $is_reach_widget && empty( $form_id ) && ! empty( $element['id'] ) ) {
            $form_id = SubscriptionFormElementorWidget::FORM_ID_PREFIX . $element['id'];
        }

        if ( $is_reach_widget && ! empty( $form_id ) ) {
            $form_ids[] = $form_id;
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $nested_element ) {
                $form_ids = array_merge( $form_ids, $this->find_reach_form_widget( $nested_element ) );
            }
        }

        return $form_ids;
    }

    private function update_form_repository( array $form_ids, int $post_id ): void {
        if ( empty( $form_ids ) ) {
            update_option( Functions::HOSTINGER_REACH_HAS_FORMS, true );
        }
        foreach ( $form_ids as $form_id ) {
            $form = array(
                'form_id' => $form_id,
                'type'    => self::INTEGRATION_NAME,
            );

            if ( $this->form_repository->exists( $form_id ) ) {
                $this->form_repository->update( $form );
            } else {
                $this->form_repository->insert( array_merge( $form, array( 'post_id' => $post_id ) ) );
            }
        }
    }

    private function get_elementor_form_ids_from_content( string $content ): array {
        if ( ! $this->is_elementor_installed() ) {
            return array();
        }
        $form_ids = array();
        $pattern  = '/<form id="' . SubscriptionFormElementorWidget::FORM_ID_PREFIX . '([A-Za-z0-9]+)"/';
        preg_match_all( $pattern, $content, $matches );
        foreach ( $matches[1] as $form_id ) {
            $form_ids[] = SubscriptionFormElementorWidget::FORM_ID_PREFIX . $form_id;
        }

        return $form_ids;
    }

    private function get_elementor_form_ids_from_actions(): array {
        $form_ids = array();
        $actions  = json_decode( wp_unslash( $_POST['actions'] ?? '' ), true );
        $status   = $actions['save_builder']['data']['status'] ?? 'draft';
        $elements = $actions['save_builder']['data']['elements'] ?? array();

        if ( ! empty( $elements ) && $status === 'publish' ) {
            foreach ( $elements as $element ) {
                $form_ids = array_merge( $form_ids, $this->find_form_widget( $element ) );
            }
        }

        return $form_ids;
    }

    private function find_form_widget( array $element ): array {
        $form_ids = array();

        if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'form' && isset( $element['id'] ) ) {
            $form_ids[] = $element['id'];
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $nested_element ) {
                $form_ids = array_merge( $form_ids, $this->find_form_widget( $nested_element ) );
            }
        }

        return $form_ids;
    }

    private function is_elementor_form_id( string $form_id ): bool {
        if ( ! $this->is_elementor_installed() ) {
            return false;
        }
        return str_starts_with( $form_id, SubscriptionFormElementorWidget::FORM_ID_PREFIX );
    }

    private function find_email( array $data ): string {
        foreach ( $data as $value ) {
            $sanitized_value = sanitize_email( $value );
            if ( filter_var( $sanitized_value, FILTER_VALIDATE_EMAIL ) ) {
                return $value;
            }
        }

        return '';
    }

    private function get_form_by_request(): ?Form_Snapshot {
        $post_id = sanitize_text_field( $_POST['post_id'] ?? '' );
        $form_id = sanitize_text_field( $_POST['form_id'] ?? '' );

        return Form_Snapshot_Repository::instance()->find( $post_id, $form_id );
    }

    private function get_forms_from_post_id( int $post_id ): array {
        $form_ids           = $this->get_reach_form_ids_from_post_id( $post_id );
        $elementor_metadata = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! is_string( $elementor_metadata ) || $elementor_metadata === '' ) {
            return $form_ids;
        }

        $elementor_metadata = json_decode( $elementor_metadata, true );
        if ( ! is_array( $elementor_metadata ) ) {
            return $form_ids;
        }

        foreach ( $elementor_metadata as $element ) {
            $form_ids = array_merge( $form_ids, $this->find_form_widget( $element ) );
        }

        return array_values( array_unique( $form_ids ) );
    }

    private function get_elementor_posts(): array {
        $args = array(
            'post_type'      => array( 'page', 'post', 'product', 'elementor_library' ),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_elementor_data',
                    'value'   => '"widgetType":"form"',
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_elementor_data',
                    'value'   => '"widgetType":"' . SubscriptionFormElementorWidget::WIDGET_NAME . '"',
                    'compare' => 'LIKE',
                ),
            ),
        );

        $post_ids = get_posts( $args );

        return $post_ids;
    }

    private function is_elementor_installed(): bool {
        return class_exists( 'Elementor\Plugin' ) && class_exists( 'Elementor\Widget_Base' ) && class_exists( 'Elementor\Utils' );
    }
}
