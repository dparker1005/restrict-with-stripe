<?php
/**
 * Add restriction options to Elementor Widgets.
 * @since 1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Controls_Manager;

class RWStripe_Elementor {
    private static $_instance = null;

    public $locations = array(
        array(
            'element' => 'common',
            'action'  => '_section_style',
        ),
        array(
            'element' => 'section',
            'action'  => 'section_advanced',
        )
    );
    public $section_name = 'rwstripe_elementor_section';

	/**
	 * Register new section for RWStripe restricted products.
     *
     * @since 1.0
	 */
	private function __construct() {
        // Register new section to display restriction controls
        $this->register_sections();
        $this->content_restriction();
	}

    /**
     *
     * Ensures only one instance of the class is loaded or can be loaded.
     *
     * @since 1.0
     *
     * @return RWStripe_Elementor An instance of the class.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) )
            self::$_instance = new self();

        return self::$_instance;
    }

    /**
     * Initizlize registering settings sections to display restriction controls.
     *
     * @since 1.0
     */
    private function register_sections() {
        foreach( $this->locations as $where ) {
            add_action( 'elementor/element/'.$where['element'].'/'.$where['action'].'/after_section_end', array( $this, 'add_section' ) );
        }
    }

    /**
     * Callback for registering sections.
     *
     * @since 1.0
     *
     * @param Object $element The element to add the section to.
     */
    public function add_section( $element ) {
        $exists = \Elementor\Plugin::instance()->controls_manager->get_control_from_stack( $element->get_unique_name(), $this->section_name );

        if( !is_wp_error( $exists ) )
            return false;

        $element->start_controls_section(
            $this->section_name, array(
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
                'label' => __( 'Restrict With Stripe', 'restrict-with-stripe' ),
            )
        );

        $element->end_controls_section();
    }

    /**
     * Initialize content restriction.
     *
     * @since 1.0
     */
	protected function content_restriction() {
		// Setup controls
		$this->register_controls();

		// Filter elementor render_content hook
		add_action( 'elementor/widget/render_content', array( $this, 'rwstripe_elementor_render_content' ), 10, 2 );
        add_action( 'elementor/frontend/section/should_render', array( $this, 'rwstripe_elementor_should_render' ), 10, 2 );
	}

	/**
     * Add restriction controls to settings section.
     *
     * @since 1.0
     */
	protected function register_controls() {
		foreach( $this->locations as $where ) {
				add_action('elementor/element/'.$where['element'].'/'.$this->section_name.'/before_section_end', array( $this, 'add_controls' ) );
		}
	}

	/**
     * Callback for building restriction controls.
     *
     * @since 1.0
     *
     * @param Object $element The element to add the section to.
     */
	public function add_controls( $element ) {
        // Get all prdoucts from Stripe.
        $RWStripe_Stripe = RWStripe_Stripe::get_instance();
		$products = $RWStripe_Stripe->get_all_products();
        if ( is_string( $products ) ) {
            $element->add_control(
                'rwstripe_error_message', array(
                    'label' => __( 'Error: Could not communicate with Stripe.', 'restrict-with-stripe' ),
                    'type' => Controls_Manager::RAW_HTML,
                )
            );
        } else {
            // Show restriction checkboxes.
            $formatted_products = array();
            foreach ( $products as $product ) {
                $formatted_products[$product->id] = $product->name;
            }
            $element->add_control(
                'rwstripe_stripe_product_ids', array(
                    'label' => __( 'Restrict by products:', 'restrict-with-stripe' ),
                    'type' => Controls_Manager::SELECT2,
                    'multiple' => 'true',
                    'options' => $formatted_products,
                    'label_block' => 'true',
                    'description' => __( 'Select products to restrict content to', 'restrict-with-stripe' ),
                )
            );
            // If this is not a section, add toggle to enable/disable showing checkout form to users without access.
            if ( $element->get_name() !== 'section' ) {
                $element->add_control(
                    'rwstripe_show_checkout_form', array(
                        'label' => __( 'Show purchase link:', 'restrict-with-stripe' ),
                        'description' => __( 'Allow users without access to purhcase this content', 'restrict-with-stripe' ),
                        'type' => Controls_Manager::SWITCHER,
                        'label_on' => __( 'Yes', 'restrict-with-stripe' ),
                        'label_off' => __( 'No', 'restrict-with-stripe' ),
                        'return_value' => 'true',
                        'default' => false,
                    )
                );
            }
        }
	}

    /**
     * Check if user access to content. Also used to hide restricted sections.
     *
     * @since 1.0
     *
     * @param bool $should_render Whether the section should be rendered.
     * @param Object $element The element to add the section to.
     * @return bool Whether the section should be rendered.
     */
    public function rwstripe_elementor_should_render( $should_render, $element ) {
        // Don't hide content in editor mode.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $should_render;
		}

		// Bypass if it's already hidden.
		if ( $should_render === false ) {
			return $should_render;
		}
		
		// Get the element settings.
        $settings = $element->get_active_settings();

        // Check if this element is restricted.
		if ( empty( $settings['rwstripe_stripe_product_ids'] ) || ! is_array( $settings['rwstripe_stripe_product_ids'] ) ) {
            return true;
        }
		
		// Check if the current user has access to this restricted page/post.
		$RWStripe_Stripe = RWStripe_Stripe::get_instance();
		return ( is_user_logged_in() && $RWStripe_Stripe->customer_has_product( rwstripe_get_customer_id_for_user(), $settings['rwstripe_stripe_product_ids'] ) );

    }

	/**
	 * Filter content of Elementor widgets.
     *
     * @since 1.0
     *
     * @param string $content The content to filter.
     * @param Object $widget The widget being displayed.
     * @return string The filtered content.
	 */
	public function rwstripe_elementor_render_content( $content, $widget ){
        // Check if this element should be rendered as-is.
        if ( $this->rwstripe_elementor_should_render( true, $widget ) ) {
            return $content;
        }

        // User does not have access. Check if checkout form should be shown.
        $settings = $widget->get_active_settings();
        if ( empty( $settings['rwstripe_show_checkout_form'] ) ) {
            return '';
        }

        // Render checkout form.
        ob_start();
        rwstripe_restricted_content_message( $settings['rwstripe_stripe_product_ids'] );
        return ob_get_clean();
	}
}
