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
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';


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
		add_action( 'init', array($this,'register_post_meta') );
		add_action( 'init', array($this,'create_amasa_rep_role') );
		add_action( 'add_meta_boxes', array($this,'voting_options_meta_box') );
		add_action( 'save_post_'.$this->post_name, array($this, 'save_voting_options_meta_box_data') );
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'settings_init'));
		add_action('wp_ajax_download_amsa_rep_example_csv', array($this, 'generate_amsa_rep_csv'));
		add_action('wp_ajax_generate_amsa_reps', array($this, 'generate_amsa_reps'));
		add_action('wp_ajax_download_example_csv_proxy', array($this, 'download_example_csv_proxy'));
		add_action('wp_ajax_map_proxies_from_csv', array($this, 'map_proxies_from_csv'));
		add_action('wp_ajax_reset_users_proxy_principal_meta', array($this, 'reset_users_proxy_principal_meta'));
		add_filter('manage_'.$this->post_name.'_posts_columns', array($this, 'add_admin_columns_to_poll_topics'));
		add_action('manage_'.$this->post_name.'_posts_custom_column', array($this, 'populate_poll_topics_columns_with_data'), 10, 2);


	
	}

	public function register_post_meta(){
		$this->helper_register_post_meta('_voted_users', 'array', array()); #array('user_id'=>array('vote_value'=>))
		$this->helper_register_post_meta('_voting_threshold', 'string', 'simple_majority');
		$this->helper_register_post_meta('_poll_status', 'string', 'unvoted');
		$this->helper_register_post_meta('_voting_outcome', 'integer', 0); # 0 for no result, 1 for pass, 2 for fail, 3 for chair's call
		$this->helper_register_post_meta('_anonymous_voting', 'integer', 0);
		$this->helper_register_post_meta('_representatives_only', 'integer', 0);
		$this->helper_register_post_meta('_institution_weighted', 'integer', 0);
		$this->helper_register_post_meta('_final_voted_numbers', 'array', array()); //['for'=sum_of_weights, 'away'=sum_of_weights, 'abstain'=sum_of_weights]
		$this->helper_register_post_meta('_final_voted_users', 'array', array()); //['for'=[user_ids...],'against'=[user_ids...],'abstain'=[user_ids...]]

		register_meta('user', 'amsa_voting_proxy', array(
			'type' => 'integer',
			'description' => 'AMSO voting proxy',
			'single' => true,
			'default' => -1,
			'sanitize_callback' => 'int' // Ensure the value is an integer
		));
		register_meta('user', 'amsa_voting_principals', array(
			'type' => 'array',
			'description' => 'AMSA voting principals',
			'single' => true,
			'default' => array(),
		));

	}

	function add_admin_columns_to_poll_topics($columns) {
		$columns['_voting_threshold'] = 'Voting Threshold';
		$columns['_poll_status'] = 'Poll Status';
		$columns['_voting_outcome'] = 'Voting Outcome';
		$columns['_anonymous_voting'] = 'Anonymous Voting';
		$columns['_representatives_only'] = 'Representatives Only';
		$columns['_institution_weighted'] = 'Institution Weighted';
		return $columns;
	}
	
	// Populate custom columns with data
	function populate_poll_topics_columns_with_data($column, $post_id) {
		switch ($column) {
			case '_voting_threshold':
				echo get_post_meta($post_id, '_voting_threshold', true);
				break;
			case '_poll_status':
				echo get_post_meta($post_id, '_poll_status', true);
				break;
			case '_voting_outcome':
				$voting_outcome = get_post_meta($post_id, '_voting_outcome', true);
				
				if ($voting_outcome == 0) {
					echo '-';
				} elseif ($voting_outcome == 1) {
					echo 'Carried';
				} elseif ($voting_outcome == 2) {
					echo 'Lost';
				} elseif ($voting_outcome == 3) {
					echo 'Tied';
				} else {
					echo 'There was a problem with the voting_outcome data';
					// Handle default case
				}
				break;
			case '_anonymous_voting':
				echo get_post_meta($post_id, '_anonymous_voting', true) ? '&#10004;' : '&#10008;';
				break;
			case '_representatives_only':
				echo get_post_meta($post_id, '_representatives_only', true)? '&#10004;' : '&#10008;';
				break;
			case '_institution_weighted':
				echo get_post_meta($post_id, '_institution_weighted', true)? '&#10004;' : '&#10008;';
				break;
			default:
				// Handle default case
				break;
		}
	}

	public function add_admin_menu() {
		add_menu_page(
			'AMSA Voting',          // Page title
			'AMSA Voting',          // Menu title
			'edit_posts',       // Capability
			'amsa-voting-menu',     // Menu slug
			'',                     // Function to display the page
			'dashicons-list-view',  // Icon URL
			6            
		);

		add_submenu_page(
			'amsa-voting-menu',
			'AMSA Voting Settings', // Page title
			'Settings', // Menu title
			'edit_posts', // Capability required to see this option
			'amsa_voting_settings', // Menu slug
			array($this, 'amsa_voting_settings_page'), // Function that outputs the page content

		);
	}

	public function amsa_voting_settings_page(){
		?>
		<div class="wrap">
        <h2>AMSA Voting Settings</h2>

        <!-- Settings Form -->
        <form action="options.php" method="POST">
            <?php
                settings_fields('amsa_voting_options_group'); // Option group name
                do_settings_sections('amsa_voting_settings'); // Page slug
                submit_button('Save Settings', 'primary', 'submit', false); // Improved submit button
            ?>
        </form>

        <!-- Import AMSA Reps Section -->
        <hr>
        <h3>Import AMSA Reps</h3>
        <p>Download the necessary format for importing AMSA Reps.</p>
        <p><a href="#" id="download_amsa_rep_example_csv" class="button button-primary">Download Example CSV</a></p>
        <p>Import AMSA Reps below with the above example format.</p>
        <form id="generate_amsa_rep_form" enctype="multipart/form-data">
            <label for="amsa_rep_csv_file">Upload CSV:
				<input type="file" id="amsa_rep_csv_file" name="amsa_rep_csv_file">
			</label>
            <button type="submit" name="submit_amsa_rep_csv" id="submit_amsa_rep_csv" class="button button-primary">Upload CSV</button>
			<label for="send_invite">
				<input type="checkbox" id="send_invite" name="send_invite"> Send Email Invitation
			</label>
        </form>

		<!-- Import Proxy Mapping Section -->
		<hr>
		<h3>Import Proxy Mapping</h3>
		<p>Download the example CSV format for importing proxy mappings.</p>
		<p><a href="#" id="download_example_csv_proxy" class="button button-primary">Download Example CSV</a></p>
		<p>Import proxy mapping below with the above example format. This maps attendees to proxies for voting events.</p>
		<form id="import_proxy_mapping_form" enctype="multipart/form-data">
			<label for="proxy_csv_file">Upload CSV:
				<input type="file" id="proxy_csv_file" name="proxy_csv_file">
			</label>
			<button type="submit" name="submit_proxy_csv" id="submit_proxy_csv" class="button button-primary">Upload CSV</button>
		</form>
		<div id="proxy_mapping_failed_emails" style="display: none">Failed Emails (either proxy or user email was not found):</div>
		
		<hr>
		<div id="reset-amsa-proxy-principal-wrapper">
			<h3>Reset existing proxy/principal relationships</h3>
			<button id="reset-amsa-proxy-principal-button" class="button button-primary">Reset User Meta</button>
		</div>
		</div>
		<?php
	}

	public function settings_init() {
		// Register a new setting for the "AMSA Voting" page
		register_setting('amsa_voting_options_group', 'amsa_voting_university_slug');
		register_setting('amsa_voting_options_group', 'amsa_voting_event_registration_post_id');

	
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

		add_settings_field(
			'amsa_voting_event_registration_post_id_field', // Field ID
			'Events Calendar Council Registration Event ID', // Title
			array($this, 'event_registration_id_cb'), // Callback function
			'amsa_voting_settings', // Page slug
			'amsa_voting_settings_section' // Section ID
		);
	}

	public function event_registration_id_cb(){
		$event_id = get_option('amsa_voting_event_registration_post_id');
		echo '<input type="text" name="amsa_voting_event_registration_post_id" value="' . esc_attr($event_id) . '">';

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
			'show_in_menu'        => 'amsa-voting-menu',
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-chart-pie',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'poll' ),
			'show_in_rest'		=>true,
		);
		
		register_post_type( $this->post_name, $args );
	}

	public function voting_options_meta_box() {
		add_meta_box(
			'amsa-voting-options',
			__( 'Voting Options', 'amsa-voting' ),
			array($this, 'voting_options_meta_box_callback'),
			$this->post_name,
			'side',
			'default'
		);
		add_meta_box(
			'amsa-voting-shortcode',
			__( 'Voting Shortcode', 'amsa-voting' ),
			array($this, 'shortcode_meta_box_callback'),
			$this->post_name,
			'side', // Display on the side
			'default'
		);
	}

	public function shortcode_meta_box_callback($post) {
		// Get the post ID
		$post_id = $post->ID;
		// Generate shortcode based on post ID or any other relevant information
		$shortcode = '['.str_replace('-','_',$this->plugin_name.'_'.$this->post_name).' poll_id="' . $post_id . '"]';
		// Display the shortcode
		echo '<p>Copy and paste this shortcode to embed the voting page: <br><code>' . $shortcode . '</code></p>';
	}

	public function voting_options_meta_box_callback( $post ) {
		$poll_status = get_post_meta( $post->ID, '_poll_status', true );

		// Check if the poll status is 'closed'
		$disabled = ($poll_status === 'closed') ? 'disabled' : '';

		$anonymous_voting = get_post_meta( $post->ID, '_anonymous_voting', true );
		// $roll_call = get_post_meta( $post->ID, '_roll_call', true );
		$representatives_only = get_post_meta( $post->ID, '_representatives_only', true );
		$institution_weighted = get_post_meta( $post->ID, '_institution_weighted', true );
	
		// Voting options checkboxes
		if($poll_status==='closed'){
			echo("Poll has been closed, settings can no longer be changed<br>");
		}
		?>
		<label for="anonymous_voting">
			<input type="checkbox" name="anonymous_voting" id="anonymous_voting" value="1" <?php checked( $anonymous_voting, 1 ); echo $disabled; ?>>
			<?php _e( 'Anonymous Voting', 'amsa-voting' ); ?>
		</label><br>
		<!--
		<label for="roll_call">
			<input type="checkbox" name="roll_call" id="roll_call" value="1" <?php //checked( $roll_call, 1 ); ?>>
			<?php //_e( 'Roll Call Required', 'amsa-voting' ); ?>
		</label><br>
	-->
		<label for="representative_voting_only">
			<input type="checkbox" name="representatives_only" id="representatives_only" value="1" <?php checked( $representatives_only, 1 ); echo $disabled;?>>
			<?php _e( 'Representatives Voting Only', 'amsa-voting' ); ?>
		</label><br>
		<label for="institution_weighted">
			<input type="checkbox" name="institution_weighted" id="institution_weighted" value="1" <?php checked( $institution_weighted, 1 ); echo $disabled;?>>
			<?php _e( 'Institution-Weighted Vote', 'amsa-voting' ); ?>
		</label><br>
	
		<hr>
	
		<p><?php _e( 'Voting Threshold:', 'amsa-voting' ); ?></p>
		<label>
			<input type="radio" name="voting_threshold" value="simple_majority" checked='checked' <?php checked( get_post_meta( $post->ID, '_voting_threshold', true ), 'simple_majority' ); echo $disabled;?>>
			<?php _e( 'Simple Majority (1/2 of votes)', 'amsa-voting' ); ?>
		</label><br>
		<label>
			<input type="radio" name="voting_threshold" value="supermajority" <?php checked( get_post_meta( $post->ID, '_voting_threshold', true ), 'supermajority' ); echo $disabled;?>>
			<?php _e( 'Supermajority (2/3 of votes)', 'amsa-voting' ); ?>
		</label>
		<?php
	}

	public function save_voting_options_meta_box_data( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if the poll is closed
		$poll_status = get_post_meta( $post_id, '_poll_status', true );
		if ($poll_status === 'closed') {
			// Do not save any changes if the poll is closed
			return;
		}
	
		$fields = array( 'anonymous_voting', 'representatives_only', 'institution_weighted', 'voting_threshold' );
	
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
			} else {
				delete_post_meta( $post_id, '_' . $field );
			}
		}
		// Enforce representatives only if institution weighted is checked
		if (isset($_POST['institution_weighted']) && $_POST['institution_weighted'] == '1') {
			update_post_meta( $post_id, '_representatives_only', '1' );
		}
	}

	public function generate_amsa_rep_csv() {
		$universities = $this->get_registered_universities();
		
		// Prepare CSV content
		$csv_content = "_wc_memberships_profile_field_".get_option('amsa_voting_university_slug').",email,display_name\n";
		foreach ($universities as $university) {
			$csv_content .= $university . ",,\n";
		}
	
		// Output CSV headers and content
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="amsa_rep_example.csv"');
		echo $csv_content;
		exit;
	}

	public function download_example_csv_proxy(){
		$csv_content="user_email,proxy_email";

		// Output CSV headers and content
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="proxy_example.csv"');
		echo $csv_content;
		exit;

	}


	public function map_proxies_from_csv(){
		if (isset($_POST['submit_csv']) ) {
			if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
				$file = $_FILES['csv_file']['tmp_name'];
				if (($handle = fopen($file, "r")) !== false) {
					$failed_emails = array();
					while (($data = fgetcsv($handle, 1000, ",")) !== false) {
						// Create user with data from CSV row
						$email = $data[0]; // Assuming email is in the second column
						$proxy_email = $data[1]; // Assuming display name is in the third column
						if($email==='user_email' && $proxy_email==='proxy_email'){
							continue;
						}
						if(!empty($email) && !empty($proxy_email) ){
							$user_id = email_exists($email);
							$proxy_user_id = email_exists($proxy_email); 
							error_log('user_id: '.$user_id);
							error_log('proxy_user_id: '.$proxy_user_id);

							if($user_id  && $proxy_user_id){
								// retract all principals of user
								$principals=get_user_meta($user_id, 'amsa_voting_principals', true);
								if($principals){
									foreach($principals as $principal_id){
										retract_proxy($principal_id);
									}
								}
								update_user_meta($user_id,'amsa_voting_principals',array());

								nominate_proxy($proxy_user_id, $user_id);
							}else{
								$failed_emails[] = [$email, $proxy_email];
							}
						}
					}
					fclose($handle);
					wp_send_json_success(array('alert_msg'=>'Proxies mapped successfully!','failed_emails'=>$failed_emails));
				} else {
					wp_send_json_error('<div class="error"><p>Error opening CSV file.</p></div>');
				}
			} else {
				wp_send_json_error('<div class="error"><p>Error uploading file.</p></div>');
			}
		}
		wp_die();
	}

	public function generate_amsa_reps(){
		if (isset($_POST['submit_csv']) && isset($_POST['send_invite'])) {
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
							if($user_id = email_exists($email)){
								$user = get_user_by('id', $user_id);
								$user->add_role('amsa_rep');
								update_user_meta($user_id, 'user_university', $university);

							}else{
								$user_id = $this->create_user_with_university($email, $display_name, $university);

							}
							if($user_id && $_POST['send_invite']){
								$this->send_invitation_to_amsa_rep(get_userdata($user_id), $university);
							}
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

	private function send_invitation_to_amsa_rep($user_object, $university){
		 // Get the user's email
		 $user_email = $user_object->user_email;

		if ( is_multisite() ){
			$blogname = $GLOBALS['current_site']->site_name;
		}
		else{
			$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		}

		 // Email subject
		 $subject = 'Welcome AMSA Representative of '.$university.' to AMSA Website';
 
		 // Email body
		 $message = 'Hello ' . $user_object->display_name . ',<br><br>';
		 $message .= 'You have been invited as an AMSA Represenatative of '.$university. '. To login to the AMSA website, please click the link below to login:<br>';
		 $message .= '<a href="' . wp_login_url() . '">' . wp_login_url() . '</a><br><br>';
		 $message .= 'if you don\'t remember your password you can reset it with your email ('.$user_email.')<br><br>';
		 $message .= 'Best regards,<br>';
		 $message .= $blogname;
 
		 // Email headers
		 $headers = array('Content-Type: text/html; charset=UTF-8');
 
		 // Send the email
		 $sent = wp_mail($user_email, $subject, $message, $headers);
 
		 // Check if the email was sent successfully
		 if ($sent) {
			 return true; // Email sent successfully
		 } else {
			 return false; // Failed to send email
		 }


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
			error_log($user_id->get_error_message());
			return false; // Return error message if user creation fails
		}
	}

	public function reset_users_proxy_principal_meta(){
		$args = array(
			'fields' => 'ID', 
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'     => 'amsa_voting_proxy',
					'value'   => -1,
					'compare' => '!='
				),
				array(
					'key'     => 'amsa_voting_principals',
					'value'   => serialize(array()), // Since the default is an empty array
					'compare' => '!='
				)
			)
		);
		$user_ids  = get_users($args);
		foreach ($user_ids as $user_id) {
			// Reset the meta values to default
			update_user_meta($user_id, 'amsa_voting_proxy', -1);
			update_user_meta($user_id, 'amsa_voting_principals', array());
		}
		wp_send_json_success(array('message' => 'User meta reset to default values for all affected users.'));
		// error_log(print_r(sizeof($users) ,true));
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
