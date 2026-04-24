<?php

namespace SiteLeads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SvgFilter {
	use Singleton;

	protected function __construct() {
		add_action( 'wp_kses_allowed_html', array( $this, 'getSvgKsesAllowedElements' ) );
	}


	function getSvgKsesAllowedElements( $allowed_html = array() ) {
			$svg_elements = array(
				'svg'     =>
					array(
						'fill',
						'stroke',
						'xmlns',
						'viewbox',
						'id',
						'data-name',
						'width',
						'height',
						'version',
						'xmlns:xlink',
						'x',
						'y',
						'enable-background',
						'xml:space',
						'style',
					),
				'path'    =>
					array(
						'fill',
						'stroke',
						'd',
						'id',
						'class',
						'data-name',
						'fill-rule',
						'clip-rule',
						'style',
					),
				'g'       =>
					array(
						'fill',
						'id',
						'stroke',
						'stroke-width',
						'fill',
						'fill-rule',
						'transform',
						'style',
					),
				'title'   =>
					array(),
				'polygon' =>
					array(
						'id',
						'points',
						'stroke',
						'style',
					),
				'rect'    =>
					array(
						'fill',
						'stroke',
						'x',
						'y',
						'width',
						'height',
						'transform',
						'rx',
						'style',
					),
				'circle'  =>
					array(
						'fill',
						'stroke',
						'cx',
						'cy',
						'r',
						'style',
					),
				'ellipse' =>
					array(
						'fill',
						'stroke',
						'cx',
						'cy',
						'rx',
						'ry',
						'style',
					),
			);

			$shared_attrs = array( 'data-*', 'id', 'class' );

			foreach ( $svg_elements as $element => $attrs ) {
				if ( ! isset( $allowed_html[ $element ] ) ) {
					$allowed_html[ $element ] = array();
				}

				$allowed_html[ $element ] = array_merge( $allowed_html[ $element ], array_fill_keys( array_merge( $attrs, $shared_attrs ), true ) );
			}

			return $allowed_html;

	}



}
