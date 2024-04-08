<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://amsa.org.au
 * @since      1.0.0
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/public
 * @author     Steven Zhang <stevenzhangshao@gmail.com>
 */
require_once plugin_dir_path(__FILE__).'amsa-voting-page.php';


class Amsa_Voting_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;
	private $post_name;


	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $post_name ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->post_name = $post_name;


		// add_action( 'the_content', array($this, 'display_voting_page' ));
		add_shortcode(str_replace('-','_',$plugin_name.'_'.$post_name), array($this, 'voting_page_shortcode'));		
		add_filter( 'the_content', array($this, 'voting_page_display' ));
		add_action('amsa_voting_poll_closed', array($this, 'talley_results'));
		add_action('wp_ajax_process_and_store_votes', array($this, 'process_and_store_votes'));
		add_action('wp_ajax_nopriv_process_and_store_votes', function() {
															wp_send_json_error('You must be logged in to vote.');
															});
		add_action( 'wp_ajax_nominate_proxy', array($this, 'nominate_proxy_ajax_handler' ));
		add_action( 'wp_ajax_retract_proxy', array($this, 'retract_proxy_ajax_handler' ));
		add_action( 'wp_ajax_diplay_proxy_table', array($this, 'diplay_proxy_table_ajax_handler' ));



		add_action('wp_ajax_handle_poll_status_change', array($this, 'handle_poll_status_change'));
		add_action('wp_ajax_nopriv_handle_poll_status_change', array($this, 'handle_poll_status_change'));

		// add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

	}

	public function talley_results($post_id){
		$votes = Amsa_Voting_Page::calculate_votes($post_id);
		$for_count = $votes['for'];
		$against_count = $votes['against'];
		$abstain_count = $votes['abstain'];
		$voting_threshold = get_post_meta( $post_id, '_voting_threshold', true );

		if($voting_threshold==='simple_majority'){
			if($for_count>$against_count){
				update_post_meta( $post_id, '_voting_outcome', 1 );
			} elseif($for_count < $against_count){
				update_post_meta($post_id, '_voting_outcome', 2);
			} else {
				// Assuming 3 indicates a tie/chair to decide in system
				update_post_meta($post_id, '_voting_outcome', 3);
			}
		}else{
			// Assuming any other threshold value requires a supermajority
			if($for_count >= 2 * $against_count){
				update_post_meta($post_id, '_voting_outcome', 1);
			} else {
				update_post_meta($post_id, '_voting_outcome', 2);
			}

		}

	}

	public function voting_page_display($content){
		if ( is_single() && get_post_type() === $this->post_name  ){
			global $post;
			$poll_id = $post->ID;
			$voting_page = new Amsa_Voting_Page($poll_id);
			ob_start();
			$voting_page->render();
			$content .= ob_get_clean();

		}

		return $content;
	}

	public function voting_page_shortcode($atts) {
		$poll_id = $atts['poll_id'];
		$voting_page = new Amsa_Voting_Page($poll_id);
		ob_start();
		$voting_page->render();
		return ob_get_clean();
	
	}


	public function process_and_store_votes() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');


		if (isset($_POST['vote']) && is_user_logged_in()) {
			$vote = sanitize_text_field($_POST['vote']);
			$post_id = intval($_POST['post_id']); // Use intval to sanitize the ID

			$current_status = get_post_meta($post_id, '_poll_status', true);
			if($current_status==='closed'){
				wp_send_json_error('This poll is closed!');
			}

			$current_user = wp_get_current_user();
			if(get_user_meta($current_user->ID, 'amsa_voting_proxy', true)){
				wp_send_json_error("You've assigned a proxy and hence are ineligible to vote");
			}


			$has_voted = get_post_meta( $post_id, '_voted_users', true );

			$weight = $this->get_user_voting_weight($current_user, $post_id);
			
			$has_voted[$current_user->ID]=array('vote_weight'=>$weight,'vote_value'=>$vote);
			update_post_meta($post_id,'_voted_users',$has_voted);
			
			$voting_page = new Amsa_Voting_Page($post_id);
			ob_start();
			$voting_page->render_dynamic($current_user);
			// $this->display_already_voted_message();
			wp_send_json_success(array('rendered_content'=>ob_get_clean()));
		}
		wp_die();
	}

	public function get_default_members_behind_amsa_rep($university_slug, $user_id){
		$args = array(
			'meta_query' => 
				array(
					'relation' => 'AND',
					array(
						'key' => $university_slug,
						'value' => get_user_meta($user_id, $university_slug, true),
						'compare' => '=',
					),
					array(
						'relation' => 'OR',
						array(
							'key' => 'amsa_voting_proxy',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key' => 'amsa_voting_proxy',
							'value' => '',
							'compare' => '=',
						),
						array(
							'key' => 'amsa_voting_proxy',
							'value' => 0,
							'compare' => '=',
						),

					),
				),
				'count_total' => true,
		);
		// Create a new user query
		$user_query = new WP_User_Query($args);

		// Get the total number of users matching the query
		$user_count = $user_query->get_total();
		if($user_count){
			return $user_count;
		}
		return 0;
	}

	public function handle_poll_status_change() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		if (isset($_POST['poll_status_change'])) {
			
			$post_id = intval($_POST['post_id']);
			$voting_page = new Amsa_Voting_Page($post_id);

			if ($voting_page->is_user_council_master()) {
				$current_status = get_post_meta($post_id, '_poll_status', true);
				if($current_status==='closed'){
					wp_send_json_error('This poll is closed!');
				}
				$new_status = ($current_status === 'open') ? 'closed' : 'open';
				update_post_meta($post_id, '_poll_status', $new_status);
				update_post_meta($post_id, '_poll_' . $new_status . '_timestamp', current_time('timestamp'));
				do_action('amsa_voting_poll_'.$new_status, $post_id);
				

				ob_start();
				$voting_page->render_dynamic(wp_get_current_user());
				$success_json = array('poll_status'=>$new_status, 'rendered_content'=> ob_get_clean());
				wp_send_json_success($success_json);
			
			}
		}
		wp_die();

	}

	public function get_user_voting_weight($user_object, $post_id){
		// default everyone is one
		$weight = 1; 

		if (in_array('amsa_rep', $user_object->roles) && get_post_meta( $post_id, '_institution_weighted', true )){
			if(get_post_meta( $post_id, '_institution_weighted', true )){
				$weight = 250 + $this->get_default_members_behind_amsa_rep('_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), $user_object->ID);
				error_log('instutional weight: '.$weight);
			}
			// $proxies = get_user_meta($current_user->ID, 'amsa_voting_proxy', true);
			
		}
		$weight += $this->get_weights_of_principals($user_object, $post_id);

		return $weight;

	}

	public function get_weights_of_principals($user_object, $post_id){
		$principals = $this->get_principals_of_user($user_object->ID);
		error_log(print_r($principals,true));
		if(!$principals){
			return 0;
		}
		$weights=array();
		foreach($principals as $principal_user){
			if($principal_user->ID==$user_object->ID){
				// prevent infinite recursion
				continue;
			}
			$weights[] = $this->get_user_voting_weight($principal_user, $post_id);
		}
		return array_sum($weights);

	}

	public function get_principals_of_user($user_id){
		$args = array(
			'meta_query' => array(
				array(
					'key' => 'amsa_voting_proxy',
					'value' => $user_id,
					'compare' => '=',
				),
			),
		);
		
		// Create a new user query
		$user_query = new WP_User_Query($args);
	
		// Get the results
		$principals = $user_query->get_results();
	
		return $principals;

	}

	public function nominate_proxy_ajax_handler() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		if (is_user_logged_in() && isset( $_POST['proxy_user_id'] ) && isset( $_POST['post_id'] )){
			$current_user = wp_get_current_user();
			$proxy_user_id = isset($_POST['proxy_user_id']) ? intval($_POST['proxy_user_id']) : 0;
			if($proxy_user_id==$current_user->ID){
				wp_send_json_error("You can't nominate yourself as proxy!");
			}

			if ($proxy_user_id > 0) {
				$post_id=$_POST['post_id'];

				$current_user_roles = $current_user->roles;
				$proxy_user = get_user_by('ID', $proxy_user_id);

				// this stops them from seeing the voting form and nomination form
				update_user_meta($current_user->ID, 'amsa_voting_proxy', $proxy_user_id);
				
				// stop any existing votes from counting
				$has_voted = get_post_meta( $post_id, '_voted_users', true );
				if(array_key_exists($current_user->ID, $has_voted)){
					unset($has_voted[$current_user->ID]);
					update_post_meta($post_id,'_voted_users',$has_voted);
				}
				
				if(in_array('amsa_rep', $current_user_roles)){
					if(in_array('subscriber', $proxy_user->roles)){
						// in the event amsa_rep proxies to a normal member
						$proxy_user->add_role('amsa_rep');
						$current_user->remove_role('amsa_rep');
						$current_user->add_role('subscriber');
					}
				}
				$voting_page = new Amsa_Voting_Page($post_id);
				ob_start();
				$voting_page->render_proxy_nomination_header();
				wp_send_json_success(array('rendered_content'=>ob_get_clean()));
			}else{
				wp_send_json_error('Please select a proxy to nominate');
			}
		}else{
			wp_send_json_error('Please login to nominate proxy');
		}
		wp_die();
	}

	public function retract_proxy_ajax_handler(){
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
		if (is_user_logged_in() && isset( $_POST['post_id'])){
			$current_user = wp_get_current_user();
			update_user_meta($current_user->ID, 'amsa_voting_proxy', 0);
			$post_id=$_POST['post_id'];
			$voting_page = new Amsa_Voting_Page($post_id);
			ob_start();
			$voting_page->render_proxy_nomination_header();
			wp_send_json_success(array('rendered_content'=>ob_get_clean()));

		}else{
			wp_send_json_error('Please login to nominate proxy');
		}
		wp_die();
	}

	public function diplay_proxy_table_ajax_handler(){
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
		if (is_user_logged_in() && isset( $_POST['post_id'])){
			$post_id=$_POST['post_id'];
			$voting_page = new Amsa_Voting_Page($post_id);
			ob_start();
			$voting_page->render_proxy_nomination_list();
			wp_send_json_success(array('rendered_content'=>ob_get_clean()));

		}else{
			wp_send_json_error('Please login to manage your proxies');
		}
		wp_die();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/amsa-voting-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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
		// wp_register_script($this->plugin_name, false, array("jquery"), false, array( 'in_footer' => true ));
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/amsa-voting-public.js', array( 'jquery' ), time(), true );
		$variable_to_js = [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce($this->plugin_name.'-nonce')
		];
		wp_localize_script($this->plugin_name, 'Theme_Variables', $variable_to_js);

	}

}
