<?php
/**
 * Plugin Name:     WooCommerce Memeberships Elementor Visibility
 * Plugin URI:      https://mburnette.com
 * Description:     Visibility toggles for Elementor based on WooCommerce Memberships
 * Author:          Marcus Burnette
 * Author URI:      https://mburnette.com
 * Text Domain:     elemwcm
 * Version:         0.1.0
 *
 * Heavily borrowed from:
 * https://github.com/seventhqueen/visibility-logic-elementor/blob/master/Elementor_Visibility_Control.php
 */


namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCM_Elementor_Visibility_Control
 */
class WCM_Elementor_Visibility_Control {


	private static $instance = null;

	/**
	 * @return WCM_Elementor_Visibility_Control|null
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function init() {
		if ( ! defined( 'ELEMENTOR_PATH' ) ) {
			return;
		}

		// Add section for settings
		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'register_section' ] );
		add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'register_section' ] );

		add_action( 'elementor/element/common/ecl_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );
		add_action( 'elementor/element/section/ecl_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/section/render_content', [ $this, 'content_change' ], 999, 2 );

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'section_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'section_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'section_should_render' ], 10, 2 );

	}

	public function register_section( $element ) {
		$element->start_controls_section(
			'ecl_section',
			[
				'tab'   => Controls_Manager::TAB_ADVANCED,
				'label' => __( 'Visibility for Memberships', 'wcm-visibility-elementor' ),
			]
		);
		$element->end_controls_section();
	}

	/**
	 * @param $element \Elementor\Widget_Base
	 * @param $section_id
	 * @param $args
	 */
	public function register_controls( $element, $args ) {

		$element->add_control(
			'wcm_ecl_enabled', [
				'label'        => __( 'Enable Conditions', 'wcm-visibility-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Yes', 'wcm-visibility-elementor' ),
				'label_off'    => __( 'No', 'wcm-visibility-elementor' ),
				'return_value' => 'yes',
			]
		);

		$element->add_control(
			'wcm_ecl_membership_visible',
			[
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Visible for:', 'wcm-visibility-elementor' ),
				'options'     => $this->get_membership_plans(),
				'default'     => [],
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [
					'wcm_ecl_enabled'     => 'yes',
					'wcm_ecl_membership_hidden' => [],
				],
			]
		);

		$element->add_control(
			'wcm_ecl_membership_hidden',
			[
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Hidden for:', 'wcm-visibility-elementor' ),
				'options'     => $this->get_membership_plans(),
				'default'     => [],
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [
					'wcm_ecl_enabled'      => 'yes',
					'wcm_ecl_membership_visible' => [],
				],
			]
		);

	}

	private function get_membership_plans() {

        $all_membership_plans = wc_memberships_get_membership_plans();
        $editable_membership_plans = apply_filters( 'editable_membership_plans', $all_membership_plans );

        $data = [ 'wcm-nonmember' => 'Non Members', 'wcm-allmembers' => 'All Members' ];

        foreach($all_membership_plans as $plan){
            $data[ $plan->slug ] = $plan->name;
        }

        return $data;
	}


	/**
	 * @param string $content
	 * @param $widget \Elementor\Widget_Base
	 *
	 * @return string
	 */
	public function content_change( $content, $widget ) {

		if ( Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}

		// Get the settings
		$settings = $widget->get_settings();

		if ( ! $this->should_render( $settings ) ) {
			return '';
		}

		return $content;

	}

	public function section_should_render( $should_render, $section ) {
		// Get the settings
		$settings = $section->get_settings();

		if ( ! $this->should_render( $settings ) ) {
			return false;
		}

		return $should_render;

	}

	private function should_render( $settings ) {
        $user_active_memberships = wc_memberships_get_user_active_memberships( get_current_user_id() );
        $all_user_active_memberships = [];

        foreach($user_active_memberships as $membership){
            $all_user_active_memberships[] = $membership->plan->slug;
		}

		//echo '<pre>'.print_r($settings, true).'</pre>';

		if ( $settings['wcm_ecl_enabled'] == 'yes' ) {

			//visible for
			if ( ! empty( $settings['wcm_ecl_membership_visible'] ) ) {

                // if all members chosen
                if( in_array( 'wcm-allmembers', $settings['wcm_ecl_membership_visible'] ) && !empty($all_user_active_memberships) ){
                    return true;
                }
                // if non-members chosen
                else if( in_array( 'wcm-nonmember', $settings['wcm_ecl_membership_visible'] ) && empty($all_user_active_memberships) ){
                    return true;
                }
                // otherwise...
                else {

                    // contains membership
                    $has_membership = false;
                    foreach ( $settings['wcm_ecl_membership_visible'] as $setting ) {
                        if ( in_array( $setting, (array) $all_user_active_memberships ) ) {
                            $has_membership = true;
                        }
                    }
                    if ( $has_membership === false ) {
                        return false;
                    }

				}

			} //hidden for
			elseif ( ! empty( $settings['wcm_ecl_membership_hidden'] ) ) {

                // if all members chosen
                if( in_array( 'wcm-allmembers', $settings['wcm_ecl_membership_hidden'] ) && !empty($all_user_active_memberships) ){
                    return false;
                }
                // if non-members chosen
                else if( in_array( 'wcm-nonmembers', $settings['wcm_ecl_membership_hidden'] ) && empty($all_user_active_memberships) ){
                    return false;
                }
                // otherwise...
                else {

                    // contains membership
                    $has_membership = true;
                    foreach ( $settings['wcm_ecl_membership_hidden'] as $setting ) {
                        if ( in_array( $setting, (array) $all_user_active_memberships ) ) {
                            $has_membership = false;
                        }
                    }
                    if ( $has_membership === false ) {
                        return false;
                    }

                }
			}
		}

		return true;
	}


}

WCM_Elementor_Visibility_Control::get_instance()->init();