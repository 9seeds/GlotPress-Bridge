<?php
/*
Plugin Name: GlotPress Bridge
Description: Syncs admin users between WordPress & GlotPress
Author: 9seeds
Version: 1.0
Author URI: http://9seeds.com
*/

class GlotPress_Bridge {

	public function hook() {
		//add_filter( 'all', array( $this, 'hook_debug' ) );
		//add_action( 'all', array( $this, 'hook_debug' ) );
		
		add_action( 'admin_init', array( $this, 'register_bridge_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_bridge_menu' ) );
		add_action( 'set_user_role', array( $this, 'maybe_add_admin' ), 10, 2 );
	}

	public function hook_debug( $name ) {
		file_put_contents( '/tmp/hook.txt', "{$name}\n", FILE_APPEND );
	}
	
	public function add_bridge_menu() {
		add_options_page( __( 'GlotPress Settings', 'gp-bridge' ),
						  __( 'GlotPress', 'gp-bridge' ),
						  'edit_users',
						  'gp-bridge-options',
						  array( $this, 'print_bridge_options' ) );
	}
	
	public function register_bridge_settings() {
		register_setting('gp-bridge-settings-group','glotpress_dir',    array( $this, 'sanitize_dir' ) );
		add_settings_section( 'gp_dir', __( 'GlotPress Directory', 'gp-bridge' ), array( $this, 'print_dir_instructions' ), 'gp-bridge' ); //NULL / NULL no section label needed
		add_settings_field( 'glotpress_dir',    __( 'GlotPress Directory', 'gp-bridge' ),    array( $this, 'print_dir_input' ), 'gp-bridge', 'gp_dir' );
	}
	
	public function print_bridge_options() {
		?>
		<div class="wrap">
   			<div id="icon-options-general" class="icon32"><br/></div>
			<h2><?php _e( 'GlotPress Settings', 'gp-bridge' ); ?></h2>
					
			<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">
				<?php settings_fields( 'gp-bridge-settings-group' ); ?>
				<?php do_settings_sections( 'gp-bridge' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	public function print_dir_instructions() {
		?><p><?php _e( 'Please specify the directory where can be found so GlotPress functionality may be included.', 'gp-bridge' );?> </p><?php
	}

	public function print_dir_input() {
		$dir = get_option( 'glotpress_dir' );
		if ( ! $dir )
			$dir = ABSPATH;
		?><input type="text" id="glotpress_dir" name="glotpress_dir" value="<?php echo $dir; ?>" class="regular-text" /><?php
	}
	
	public function sanitize_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			add_settings_error( 'glotpress_dir', 'glotpress_dir', 'Directory not found' );
			return $dir;
		}
		
		if ( file_exists( trailingslashit( $dir ) . 'scripts/add-admin.php' ) ) {
			$output = '';
			if ( ! $this->gp_load_cli( $output ) )
				add_settings_error( 'glotpress_dir', 'glotpress_dir', 'Error running PHP CLI (not installed or not in path) ' . join( "\n", $output ) );				
			return $dir;
		}
		add_settings_error( 'glotpress_dir', 'glotpress_dir', 'GlotPress not found in specified directory' );
		return $dir;
	}

	/**
	 * This won't work - there are function name conflicts between GlotPress & WordPress
	private function gp_load_native() {
		$gp_load = trailingslashit( get_option( 'glotpress_dir' ) ) . 'gp-load.php';
		if ( file_exists( $gp_load ) ) {
			require_once $gp_load;
			return true;
		}
		return false;
	}

	private function add_admin_native( $user_login ) {
		$user_to_make_admin = GP::$user->by_login( $user_login );
		if ( !$user_to_make_admin ) {
			wp_die( "User '{$user_login}' doesn't exist in GlotPress." );
		}
		if ( !GP::$permission->create( array( 'user_id' => $user_to_make_admin->id, 'action' => 'admin' ) ) ) {
			wp_die( "Error making '{$user_login}' an admin." );
		}
	}
	 */

	public function maybe_add_admin( $id, $role ) {
		//not sure if this works for super-admin as well
		if ( $role == 'administrator' && $this->gp_load_cli() ) {
			$user = get_user_by( 'id', $id );
			$this->add_admin_cli( $user->user_login );
		}
	}

	/**
	 * A hack, but should work in at least some instances
	 */
	private function gp_load_cli( &$output = NULL ) {
		exec( 'php -v', $output, $return );

		//anything but zero (0) here is an error
		if ( $return )
			return false;
		return true;
	}
	
	private function add_admin_cli( $user_login ) {
		$add_admin = trailingslashit( get_option( 'glotpress_dir' ) ) . 'scripts/add-admin.php';
		if ( file_exists( $add_admin ) ) {
			exec( "php {$add_admin} {$user_login} 2>&1", $output, $return );
			if ( $return )
				wp_die( 'Error adding GlotPress admin via CLI: ' . join( "\n", $output ) );
		}	
	}
}

$gp_bridge = new GlotPress_Bridge();
$gp_bridge->hook();