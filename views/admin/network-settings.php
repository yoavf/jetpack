<?php
	extract( $data );
?>

<div class="wrap">
	<h2><?php _e( 'Network Settings', 'jetpack' ); ?></h2>
	<form action="edit.php?action=jetpack-network-settings" method="POST">
		<h3><?php _e( 'Global', 'jetpack' ); ?></h3>
		<p><?php _e( 'These settings affect all sites on the network.', 'jetpack' ); ?></p>
		<table class="form-table">
<?php /*
			<tr valign="top">
				<th scope="row"><label for="auto-connect">Auto-Connect New Sites</label></th>
				<td>
					<input type="checkbox" name="auto-connect" id="auto-connect" value="1" <?php checked($options['auto-connect']); ?> />
					<label for="auto-connect">Automagically connect all new sites in the network.</label>
				</td>
			</tr>
/**/ ?>
			<tr valign="top">
				<th scope="row"><label for="sub-site-override"><?php _e( 'Sub-site override', 'jetpack' ); ?></label></th>
				<td>
					<input type="checkbox" name="sub-site-connection-override" id="sub-site-override" value="1" <?php checked($options['sub-site-connection-override']); ?> />
					<label for="sub-site-override"><?php _e( 'Allow individual site administrators to manage their own connections (connect and disconnect) to <a href="//wordpress.com">WordPress.com</a>', 'jetpack' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="manage_auto_activated_modules">Manage modules</label></th>
				<td>
					<input type="checkbox" name="manage_auto_activated_modules" id="manage_auto_activated_modules" onclick="jQuery('#jpms_settings_modules').toggle();" value="1" <?php checked( $options['manage_auto_activated_modules'] ); ?>/>
					<label for="manage_auto_activated_modules">Control which modules are auto-activated</label>
				</td>
			</tr>
		</table>
		
		<?php
			$display_modules = ( 1 == $options['manage_auto_activated_modules'] )? 'block': 'none';
		?>
		<div id="jpms_settings_modules" style="display: <?php echo $display_modules; ?>">
		<h3><?php _e( 'Modules', 'jetpack' ); ?></h3>
		<p><?php _e( 'Modules to be automatically activated when new sites are created.', 'jetpack' ); ?></p>



<?php

		require_once( JETPACK__PLUGIN_DIR . 'class.jetpack-network-modules-list-table.php' );
		$myListTable = new Jetpack_Network_Modules_List_Table();
		echo '<div class="wrap"><h3>' . __('Modules', 'jetpack') . '</h3>';
		$myListTable->prepare_items();
		$myListTable->display();
		echo '</div>';
?>	

		</div>

		<?php submit_button(); ?>

	</form>
</div>
