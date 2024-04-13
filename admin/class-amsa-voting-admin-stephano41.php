<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://amsa.org.au
 * @since      1.0.0
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/admin
 * @author     Steven Zhang <stevenzhangshao@gmail.com>
 */
class Amsa_Voting_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	private $post_name;
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $post_name ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->post_name = $post_name;

		add_action( 'init', array($this,'register_poll_topic_post_type') );
		add_action( 'add_meta_boxes', array($this,'voting_options_meta_box') );
		add_action( 'save_post_'.$this->post_name, array($this, 'save_voting_options_meta_box_data') );
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'settings_init'));
		add_action('wp_ajax_download_example_csv', array($this, 'generate_csv'));
		add_action('wp_ajax_generate_amsa_reps', array($this, 'generate_amsa_reps'));


		// $this->helper_register_post_meta('_for_count', 'integer', 0);
		// $this->helper_register_post_meta('_against_count', 'integer', 0);
		// $this->helper_register_post_meta('_abstain_count', 'integer', 0);
		$this->helper_register_post_meta('_voted_users', 'array', array());
		$this->helper_register_post_meta('_voting_threshold', 'string', 'simple_majority');
		$this->helper_register_post_meta('_poll_status', 'string', 'unvoted');
		$this->helper_register_post_meta('_voting_outcome', 'integer', 0); # 0 for no result, 1 for pass, 2 for fail, 3 for chair's call

		
		$this->create_amasa_rep_role();
		
	}
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type='.$this->post_name,
			'AMSA Voting Settings', // Page title
			'Settings', // Menu title
			'manage_options', // Capability required to see this option
			'amsa_voting_settings', // Menu slug
			array($this, 'amsa_voting_settings_page'), // Function that outputs the page content

		);
	}

	public function amsa_voting_settings_page(){
		?>
		<div class="wrap">
			<h2>AMSA Voting Settings</h2>
			<form action="options.php" method="POST">
				<?php
					settings_fields('amsa_voting_options_group'); // Option group name
					do_settings_sections('amsa_voting_settings'); // Page slug
					submit_button();

					// Add input field for uploading CSV file
				?>
			</form>
			
			<br><br>
			<h4>Import AMSA Reps</h4>
			Download the necessary format for importing AMSA Reps
			<button id="download_example_csv">Download Example CSV</button>		
			Import AMSA Reps below with above example format. You'll need to send them a password reset email from the user section.
			<form id="generate_amsa_rep_form" enctype="form/multipart" >
				<input type="file" id="csv_file" name="csv_file">
				<button type="submit" name="submit_csv" id="submit_csv">Upload CSV</button>
			</form>
		</div>
		<?php
	}

	public function settings_init() {
		// Register a new setting for the "AMSA Voting" page
		register_setting('amsa_voting_options_group', 'amsa_voting_university_slug');
	
		// Register a new section in the "AMSA Voting" page
		add_settings_section(
			'amsa_voting_settings_section', // Section ID
			'General Settings', // Title
			array($this, 'settings_section_cb'), // Callback function
			'amsa_voting_settings' // Page slug
		);
	
		// Register a new field in the "section" section, inside the "AMSA Voting" page
		add_settings_field(
			'amsa_voting_university_slug_field', // Field ID
			'Woocommerce Profile Fields University Slug', // Title
			array($this, 'university_field_cb'), // Callback function
			'amsa_voting_settings', // Page slug
			'amsa_voting_settings_section' // Section ID
		);
	}

	public function settings_section_cb() {
		echo '<p>Slug corresponding to a profile field in Woocommerce Memberships plugin to retrieve university information.</p>';
	}

	public function university_field_cb() {
		// Get the value of the setting we've registered
		$university = get_option('amsa_voting_university_slug');
		// Output the field
		echo '<input type="text" name="amsa_voting_university_slug" value="' . esc_attr($university) . '">';
		
	}

	public function create_amasa_rep_role(){
		if ( ! get_role( 'amsa_rep' ) ) {
			// Get the subscriber role
			$subscriber_role = get_role( 'subscriber' );
	
			// Create AMSA Rep role with the same capabilities as subscriber
			if ( $subscriber_role ) {
				add_role( 'amsa_rep', __( 'AMSA Representative', 'amsa-voting' ), $subscriber_role->capabilities );
			}
		}
	}

	public function get_registered_universities(){
		$university_profile_field = SkyVerge\WooCommerce\Memberships\Profile_Fields::get_profile_field_definition(get_option('amsa_voting_university_slug'));
		return $university_profile_field->get_options();
	}

	private function helper_register_post_meta($meta_key, $type, $default=NULL){
		register_post_meta(
			$this->post_name,
			$meta_key,
			array(
				'single'=>true,
				'type'=>$type,
				'default'=>$default
			)
			);
	}

	public function register_poll_topic_post_type() {
		$labels = array(
			'name'               => __( 'Poll Topics', 'amsa-voting' ),
			'singular_name'      => __( 'Poll Topic', 'amsa-voting' ),
			'add_new'            => __( 'Add New', 'amsa-voting' ),
			'add_new_item'       => __( 'Add New Poll Topic', 'amsa-voting' ),
			'edit_item'          => __( 'Edit Poll Topic', 'amsa-voting' ),
			'new_item'           => __( 'New Poll Topic', 'amsa-voting' ),
			'view_item'          => __( 'View Poll Topic', 'amsa-voting' ),
			'search_items'       => __( 'Search Poll Topics', 'amsa-voting' ),
			'not_found'          => __( 'No Poll Topics found', 'amsa-voting' ),
			'not_found_in_trash' => __( 'No Poll Topics found in Trash', 'amsa-voting' ),
			'parent_item_colon'  => __( 'Parent Poll Topic:', 'amsa-voting' ),
			'menu_name'          => __( 'Poll Topics', 'amsa-voting' ),
		);
	
		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-chart-pie',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'poll' ),
		);
		
		register_post_type( $this->post_name, $args );
	}

	public function voting_options_meta_box() {
		add_meta_box(
			'amsa-voting-options',
			__( 'Voting Options', 'amsa-voting' ),
			array($this, 'voting_options_meta_box_callback'),
			$this->post_name,
			'normal',
			'default'
		);
	}

	public function voting_options_meta_box_callback( $post ) {
		$anonymous_voting = get_post_meta( $post->ID, '_anonymous_voting', true );
		$roll_call = get_post_meta( $post->ID, '_roll_call', true );
		$representatives_only = get_post_meta( $post->ID, '_representatives_only', true );
		$institution_weighted = get_post_meta( $post->ID, '_institution_weighted', true );
	
		// Voting options checkboxes
		?>
		<label for="anonymous_voting">
			<input type="checkbox" name="anonymous_voting" id="anonymous_voting" value="1" <?php checked( $anonymous_voting, 1 ); ?>>
			<?php _e( 'Anonymous Voting', 'amsa-voting' ); ?>
		</label><br>
		<label for="roll_call">
			<input type="checkbox" name="roll_call" id="roll_call" value="1" <?php checked( $roll_call, 1 ); ?>>
			<?php _e( 'Roll Call Required', 'amsa-voting' ); ?>
		</label><br>
		<label for="representative_voting_only">
			<input type="checkbox" name="representatives_only" id="representatives_only" value="1" <?php checked( $representatives_only, 1 ); ?>>
			<?php _e( 'Vote for Representatives Only', 'amsa-voting' ); ?>
		</label><br>
		<label for="institution_weighted">
			<input type="checkbox" name="institution_weighted" id="institution_weighted" value="1" <?php checked( $institution_weighted, 1 ); ?>>
			<?php _e( 'Institution-Weighted Vote', 'amsa-voting' ); ?>
		</label><br>
	
		<hr>
	
		<p><?php _e( 'Voting Threshold:', 'amsa-voting' ); ?></p>
		<label>
			<input type="radio" name="voting_threshold" value="simple_majority" checked='checked' <?php checked( get_post_meta( $post->ID, '_voting_threshold', true ), 'simple_majority' ); ?>>
			<?php _e( 'Simple Majority (1/2 of votes)', 'amsa-voting' ); ?>
		</label><br>
		<label>
			<input type="radio" name="voting_threshold" value="supermajority" <?php checked( get_post_meta( $post->ID, '_voting_threshold', true ), 'supermajority' ); ?>>
			<?php _e( 'Supermajority (2/3 of votes)', 'amsa-voting' ); ?>
		</label>
		<?php
	}

	public function save_voting_options_meta_box_data( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
	
		$fields = array( 'anonymous_voting', 'roll_call', 'representatives_only', 'institution_weighted', 'voting_threshold' );
	
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
			} else {
				delete_post_meta( $post_id, '_' . $field );
			}
		}
	}

	public function generate_csv() {
		$universities = $this->get_registered_universities();
		
		// Prepare CSV content
		$csv_content = "_wc_memberships_profile_field_".get_option('amsa_voting_university_slug').",email,display_name\n";
		foreach ($universities as $university) {
			$csv_content .= $university . ",,\n";
		}
	
		// Output CSV headers and content
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="example.csv"');
		echo $csv_content;
		exit;
	}

	public function generate_amsa_reps(){
		error_log('i was called!');
		error_log(print_r($_POST,true));
		error_log(print_r($_FILES,true));
		if (isset($_POST['submit_csv'])) {
			if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
				$file = $_FILES['csv_file']['tmp_name'];
				if (($handle = fopen($file, "r")) !== false) {
					while (($data = fgetcsv($handle, 1000, ",")) !== false) {
						// Create user with data from CSV row
						$email = $data[1]; // Assuming email is in the second column
						$display_name = $data[2]; // Assuming display name is in the third column
						$university = $data[0]; // Assuming university is in the first column
						if($display_name==='display_name' && $email==='email'){
							continue;
						}
						if(!empty($email) && !empty($display_name) && !empty($university)){
							$user_creation = $this->create_user_with_university($email, $display_name, $university);
						}
					}
					fclose($handle);
					wp_send_json_success('Users created successfully!');
				} else {
					wp_send_json_error('<div class="error"><p>Error opening CSV file.</p></div>');
				}
			} else {
				wp_send_json_error('<div class="error"><p>Error uploading file.</p></div>');
			}
		}
		wp_die();
	}

	private function generate_unique_username( $username ) {

		static $i;
		if ( null === $i ) {
			$i = 1;
		} else {
			$i++;
		}
	
		if ( ! username_exists( $username ) ) {
			return $username;
		}
	
		$new_username = sprintf( '%s-%s', $username, $i );
	
		if ( ! username_exists( $new_username ) ) {
			return $new_username;
		} else {
			return call_user_func( __FUNCTION__, $username );
		}
	}

	public function create_user_with_university($email, $display_name, $university) {
		// Generate username from display name
		$username = $this->generate_unique_username(sanitize_user($display_name));
	
		// Generate random password
		$password = wp_generate_password();
	
		// Create user data array
		$userdata = array(
			'user_login'    => $username,
			'user_pass'     => $password,
			'user_email'    => $email,
			'display_name'  => $display_name,
			'role'          => 'amsa_rep', // Adjust role as needed
		);
	
		// Insert the user into the database
		$user_id = wp_insert_user($userdata);
	
		if (!is_wp_error($user_id)) {
			// User created successfully, now save university as user meta
			update_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), $university);
			
			// Optionally, send user notification email
			wp_new_user_notification($user_id, $password);
			
			// Optionally, redirect user to a specific page
			// wp_redirect('URL_OF_YOUR_CHOICE');
			// exit;
			
			return $user_id; // Return user ID if needed
		} else {
			return $user_id->get_error_message(); // Return error message if user creation fails
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Amsa_Voting_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Amsa_Voting_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/amsa-voting-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Amsa_Voting_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Amsa_Voting_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/amsa-voting-admin.js', array( 'jquery' ), time(), false );

	}

}
