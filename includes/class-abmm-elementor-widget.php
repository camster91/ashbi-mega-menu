<?php
/**
 * The "Ashbi Mega Menu" Elementor widget. Required by ABMM_Elementor only
 * after Elementor has loaded, so \Elementor\Widget_Base exists.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class ABMM_Elementor_Widget extends Widget_Base {

	public function get_name() {
		return 'ashbi-mega-menu';
	}

	public function get_title() {
		return __( 'Ashbi Mega Menu', 'ashbi-mega-menu' );
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'menu', 'mega menu', 'navigation', 'header', 'ashbi' );
	}

	/**
	 * Menus to choose from: ID => "Title (ID)".
	 *
	 * @return array<string,string>
	 */
	private function menu_options() {
		$options = array();
		foreach ( ABMM_Data::instance()->get_all() as $id => $menu ) {
			$title                    = (string) ( $menu['title'] ?? '' );
			$options[ (string) $id ] = '' !== $title ? $title . ' (' . $id . ')' : (string) $id;
		}
		return $options;
	}

	protected function register_controls() {
		$menus = $this->menu_options();

		$this->start_controls_section( 'abmm_content', array( 'label' => __( 'Menu', 'ashbi-mega-menu' ) ) );
		$this->add_control(
			'menu_id',
			array(
				'label'   => __( 'Menu', 'ashbi-mega-menu' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $menus,
				'default' => (string) array_key_first( $menus ),
			)
		);
		if ( function_exists( 'abmm_contextual_navigation_enabled' ) && abmm_contextual_navigation_enabled() ) {
			$this->add_control(
				'context',
				array(
					'label'        => __( 'Product navigation on product pages', 'ashbi-mega-menu' ),
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'page',
					'default'      => '',
				)
			);
		}
		$this->add_control(
			'abmm_where_to_edit',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: link to the menu editor. */
					esc_html__( 'Links, categories and icons are edited in %s. The Style tab here sets fonts, colours and the dropdown height for this placement.', 'ashbi-mega-menu' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ashbi Mega Menu', 'ashbi-mega-menu' ) . '</a>'
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$this->end_controls_section();

		$this->text_style_section( 'top', __( 'Menu bar', 'ashbi-mega-menu' ), array( 'nav__list > .{p}-nav__item > .{p}-nav__link' ), true );
		$this->text_style_section( 'cat', __( 'Category titles', 'ashbi-mega-menu' ), array( 'mega__cat-title', 'mega__section-title' ), false );
		$this->text_style_section( 'link', __( 'Links', 'ashbi-mega-menu' ), array( 'mega__col-label', 'mega__grid-label' ), true );
		$this->text_style_section( 'desc', __( 'Descriptions', 'ashbi-mega-menu' ), array( 'mega__cat-desc', 'mega__col-description' ), false );

		$this->start_controls_section( 'abmm_dropdown', array( 'label' => __( 'Dropdown', 'ashbi-mega-menu' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'panel_background',
			array(
				'label'     => __( 'Background', 'ashbi-mega-menu' ),
				'type'      => Controls_Manager::COLOR,
				// The menu sets its colours on the header element; a value set on
				// the dropdown is inherited by everything inside it.
				'selectors' => array( '{{WRAPPER}} .abmm-mega, {{WRAPPER}} .emm-mega' => '--abmm-panel-bg: {{VALUE}}; --emm-panel-bg: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'panel_height_mode',
			array(
				'label'   => __( 'Height (desktop)', 'ashbi-mega-menu' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''      => __( 'Menu setting', 'ashbi-mega-menu' ),
					'auto'  => __( 'Grow to fit all links', 'ashbi-mega-menu' ),
					'max'   => __( 'Up to a height, then scroll', 'ashbi-mega-menu' ),
					'fixed' => __( 'Always the same height', 'ashbi-mega-menu' ),
				),
				'default' => '',
			)
		);
		$this->add_control(
			'panel_height',
			array(
				'label'     => __( 'Height (px)', 'ashbi-mega-menu' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 200,
				'max'       => 1200,
				'default'   => 520,
				'condition' => array( 'panel_height_mode' => array( 'max', 'fixed' ) ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Typography and colours for one part of the menu, in both looks.
	 *
	 * @param string   $key   Control prefix.
	 * @param string   $label Section label.
	 * @param string[] $parts Class suffixes.
	 * @param bool     $hover Add a hover colour.
	 */
	private function text_style_section( $key, $label, $parts, $hover ) {
		$this->start_controls_section( 'abmm_' . $key . '_style', array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $key . '_typography',
				'selector' => ABMM_Elementor::selector( $parts ),
			)
		);
		$this->add_control(
			$key . '_color',
			array(
				'label'     => __( 'Colour', 'ashbi-mega-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( ABMM_Elementor::selector( $parts ) => 'color: {{VALUE}};' ),
			)
		);
		if ( $hover ) {
			$targets = 'top' === $key ? $parts : array( 'mega__col-link:hover .{p}-mega__col-label', 'mega__grid-link:hover .{p}-mega__grid-label' );
			$this->add_control(
				$key . '_hover_color',
				array(
					'label'     => __( 'Hover colour', 'ashbi-mega-menu' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( ABMM_Elementor::selector( $targets, 'top' === $key ? ':hover' : '' ) => 'color: {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$menu_id  = is_scalar( $settings['menu_id'] ?? null ) ? (string) $settings['menu_id'] : '';
		if ( '' === $menu_id || ! ABMM_Data::instance()->get( $menu_id ) ) {
			if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Choose a menu in the widget settings.', 'ashbi-mega-menu' ) . '</p>';
			}
			return;
		}
		$context = function_exists( 'abmm_contextual_navigation_enabled' ) && abmm_contextual_navigation_enabled() && 'page' === ( $settings['context'] ?? '' ) ? 'page' : '';

		// The dropdown height uses the same rules as the menu's Design tab,
		// scoped to this widget only.
		$style = ABMM_Style::sanitize(
			array(
				'panel_height_mode' => $settings['panel_height_mode'] ?? '',
				'panel_height'      => $settings['panel_height'] ?? '',
			)
		);
		if ( $style ) {
			$css = '';
			foreach ( array( 'abmm', 'emm' ) as $prefix ) {
				$css .= ABMM_Style::css( $style, '.elementor-element-' . sanitize_html_class( $this->get_id() ) . ' .' . $prefix . '-header.' . $prefix . '-header', $prefix );
			}
			echo '<style>' . wp_strip_all_tags( $css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS built from sanitized numbers and fixed selectors.
		}
		echo ABMM_Frontend::instance()->render( $menu_id, array( 'context' => $context ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
	}
}
