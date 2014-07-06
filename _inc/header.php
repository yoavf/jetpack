<div class="jp-content">
	<div class="jp-frame">
		<div class="header">
			<nav role="navigation" class="header-nav drawer-nav nav-horizontal">

				<ul class="main-nav">

					<?php foreach( $GLOBALS['submenu']['jetpack'] as $submenu_page ) :
						if ( ! current_user_can( $submenu_page[1] ) ) {
							continue;
						}
						$current = ( $submenu_page[2] === $_GET['page'] ) ? 'current' : '';
						?>

						<li class="jp-menu-<?php echo esc_attr( $submenu_page[2] ); ?>">
							<a href="<?php echo esc_url( Jetpack::admin_url( 'page=' . $submenu_page[2] ) ); ?>" class="<?php echo esc_attr( $current ); ?>">
								<?php echo esc_html( $submenu_page[0] ); ?>
							</a>
						</li>
					<?php endforeach; ?>

				</ul>

			</nav>
		</div><!-- .header -->
		<div class="wrapper">