<?php

if ( ! defined( 'ABSPATH' ) ) exit;

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php
		add_action('wp_print_styles', function() {
			// dequeue all styles and scripts not related to siteleads
		global $wp_styles, $wp_scripts;
		foreach ( $wp_styles->queue as $handle ) {
			if ( strpos( $handle, 'siteleads-' ) === false && strpos( $handle, 'wp-' )!== 0 ) {
				wp_dequeue_style( $handle );
			}
		}

		foreach ( $wp_scripts->queue as $handle ) {
			if ( strpos( $handle, 'siteleads-' ) === false ) {
				wp_dequeue_script( $handle );
			}
		}
		}, 0);
?>
	<?php wp_head(); ?>
	<style>
		/* hide all non siteleads elements in the preview */
		body > *:not([class*="siteleads"]) {
			display: none !important;
		}
		:root{
			--wp-admin--admin-bar--height: 24px;
		}
	</style>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="siteleads-preview-overlay"></div>
<?php wp_footer(); ?>
</body>
</html>


