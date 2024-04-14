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
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';



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
		add_shortcode('poll_topics_overview_table', array($this,'poll_topics_overview_shortcode'));
		add_shortcode('active_poll_topics_overview', array($this,'display_active_poll_topics'));
		add_filter( 'the_content', array($this, 'voting_page_display' ));
		add_action('amsa_voting_poll_closed', array($this, 'talley_results'));
		add_action('amsa_voting_poll_oepn', array($this, 'unfreeze_poll'));
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

	function display_active_poll_topics() {
		// Query to retrieve active poll topics
		$args = array(
			'post_type' => 'poll_topic',
			'posts_per_page' => -1, // Retrieve all posts
			'meta_query' => array(
				array(
					'key' => '_poll_status',
					'value' => 'open',
					'compare' => '=' // Get posts where _poll_status is not 'closed'
				)
			)
		);
		$query = new WP_Query($args);
		$current_user_id = get_current_user_id();
		$is_user_representing_amsa_rep=is_user_representing_amsa_rep($current_user_id);

        $user_has_proxy = get_user_meta($current_user_id, 'amsa_voting_proxy', true) > 0;
		if($user_has_proxy){
			return '<div class="amsa-voting-active-poll-topics-has-proxy">You\'ve assigned a proxy, edit your proxy if you want to vote.</div>';
		}

		// Output for card format
		$output = '<div class="amsa-voting-active-poll-topics-overview">';

		while ($query->have_posts()) {
			$query->the_post();
			$post_id = get_the_ID();
			$post_title = get_the_title();
			$anonymous_voting = get_post_meta($post_id, '_anonymous_voting', true);
			$representatives_only = get_post_meta($post_id, '_representatives_only', true);

			if(!$representatives_only || ($representatives_only && $is_user_representing_amsa_rep)){

				$institution_weighted = get_post_meta($post_id, '_institution_weighted', true);
				$voting_threshold = get_post_meta($post_id, '_voting_threshold', true);
				// Card output for each poll topic
				ob_start();
				?>
				<div class="amsa-voting-active-poll-topics-col">
					<div class="amsa-voting-active-poll-topics-card">
						<div class="amsa-voting-active-poll-topics-card-header">
							<h5 class="amsa-voting-active-poll-topics-card-title"><strong>Motion: </strong><?php echo $post_title ?></h5>
						</div>
						<div class="amsa-voting-active-poll-topics-card-body">
							<p class="amsa-voting-active-poll-topics-card-text">
							<?php echo ($representatives_only) ? 'AMSA Reps only' : 'Voting open to all'; ?>,
							<?php echo ($anonymous_voting) ? 'Anonymous voting' : 'Non-anonymous'; ?>,
							<?php echo ('Requires '.  ($voting_threshold === 'simple_majority') ? '½ simple majority' : 'super majority'); ?>,
							<?php echo ($institution_weighted) ? 'Institutional-weighted votes' : 'Votes not weighted'; ?>,
						</div>
						<a href="<?php echo get_permalink()?>" class="amsa-voting-active-poll-topics-btn btn button">Vote</a>
					</div>
				</div>
				<?php
				$output .=ob_get_clean();
			}

		}

		// Restore original post data
		wp_reset_postdata();

		$output .= '</div>'; // Close row

		return $output;


	}

	public function poll_topics_overview_shortcode(){
		$args = array(
			'post_type' => 'poll_topic',
			'posts_per_page' => -1, // Retrieve all posts
		);
		$query = new WP_Query($args);

		ob_start();
		?>
		<div class="amsa-voting-poll-topics-overview-table">
		<table><thead><tr>
			<th>Time</th><th>Title</th><th>Voting Outcome</th><th>Votes (For/Against)</th>
		</tr></thead><tbody>
		<?php
		$output = ob_get_clean();
		while ($query->have_posts()) {
			$query->the_post();
			$post_id = get_the_ID();
			$post_date_time = get_the_date() . ' ' . get_the_time(); // Post creation date and time
			$post_title = get_the_title();
			$post_permalink = get_permalink();
			$voting_outcome = get_post_meta($post_id, '_voting_outcome', true); // Custom meta key _voting_outcome
			$poll_status = get_post_meta($post_id, '_poll_status', true);
			if($voting_outcome==0){
				$voting_outcome_display='-';
			}elseif($voting_outcome==1){
				$voting_outcome_display='<strong style="color:green">Carried</strong>';
			}elseif($voting_outcome==2){
				$voting_outcome_display='<strong style="color:red">Lost</strong>';
			}elseif($voting_outcome==3){
				$voting_outcome_display='<strong style="color:blue">Tied</strong>';
			}else{
				$voting_outcome_display='<strong>There is something wrong with the code (_voting_outcome should not be anything other than 0,1,2,3)</strong>';
			}

			if($poll_status==='closed' && !get_post_meta($post_id, '_institution_weighted', true)){
				$vote_numbers = calculate_votes($post_id); // Custom meta key _voted_users
				$vote_number_display=$vote_numbers['for']."/".$vote_numbers['against'];
			}else{
				$vote_number_display="hidden";
			}

			// Add a row for each poll topic
			$output .= '<tr class="amsa-voting-poll-topics-overview-row amsa-voting-poll-topics-overview-row-'.$poll_status.'">';
			$output .= '<td>' . $post_date_time . '</td>';
			$output .= '<td><a href="' . $post_permalink . '">' . $post_title . '</a></td>';
			$output .= '<td>' . $voting_outcome_display . '</td>';
			$output .= '<td>' . $vote_number_display . '</td>';
			$output .= '</tr>';
		}
		// Restore original post data
		wp_reset_postdata();

		// Close table
		$output .= '</tbody></table></div>';

		return $output;
	}

	public function unfreeze_poll($post_id){
		update_post_meta($post_id, '_final_voted_numbers', array());
		update_post_meta($post_id, '_final_voted_users', array());
	}

	public function talley_results($post_id){
		$votes = calculate_votes($post_id);
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
		update_post_meta($post_id, '_final_voted_numbers', $votes);
		update_post_meta($post_id, '_final_voted_users', get_users_per_vote($post_id));

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

		$current_user_id = get_current_user_id();
		if (isset($_POST['vote']) && $current_user_id>0) {
			$vote = sanitize_text_field($_POST['vote']);
			$post_id = intval($_POST['post_id']); // Use intval to sanitize the ID

			$voting_page = new Amsa_Voting_Page($post_id);

			$current_status = $voting_page->get_single_meta('_poll_status');
			if($current_status==='closed'){
				wp_send_json_error('This poll is closed!');
			}

			if(get_user_meta($current_user_id, 'amsa_voting_proxy', true)>0){
				wp_send_json_error("You've assigned a proxy and hence are ineligible to vote.");
			}

			if(!$voting_page->is_user_rep_eligible()){
				wp_send_json_error("You need to be an AMSA Representative or be proxied by an AMSA Representative to participate in this vote!");
			}

			$has_voted = $voting_page->get_single_meta('_voted_users');

			$has_voted[$current_user_id]=array('vote_value'=>$vote);
			update_post_meta($post_id,'_voted_users',$has_voted);

			ob_start();
			$voting_page->render_dynamic();
			// $this->display_already_voted_message();
			wp_send_json_success(array('rendered_content'=>ob_get_clean()));
		}
		wp_die();
	}


	public function handle_poll_status_change() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		if (isset($_POST['poll_status_change'])) {

			$post_id = intval($_POST['post_id']);
			$voting_page = new Amsa_Voting_Page($post_id);

			if ($voting_page->is_user_council_master()) {
				$current_status = get_post_meta($post_id, '_poll_status', true);

				$new_status = ($current_status === 'open') ? 'closed' : 'open';
				update_post_meta($post_id, '_poll_status', $new_status);
				update_post_meta($post_id, '_poll_' . $new_status . '_timestamp', current_time('timestamp'));
				do_action('amsa_voting_poll_'.$new_status, $post_id);


				ob_start();
				$voting_page->render_dynamic();
				$success_json = array('poll_status'=>$new_status, 'rendered_content'=> ob_get_clean());
				wp_send_json_success($success_json);

			}
		}
		wp_die();

	}


	public function nominate_proxy_ajax_handler() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		$current_user_id = get_current_user_id();
		if ($current_user_id>0 && isset( $_POST['proxy_user_id'] ) && isset( $_POST['post_id'] )){

			$proxy_user_id = intval($_POST['proxy_user_id']);

			// prevent user nominating themself as proxy
			if($proxy_user_id==$current_user_id){
				wp_send_json_error("You can't nominate yourself as proxy!");
			}
			// current user has principals, they can't nominate another proxy (someone has already nominated them as proxy)
			if(get_user_meta($current_user_id, 'amsa_voting_principals', true)){
				wp_send_json_error("Someone has nominated you as their proxy, you cannot nominate another proxy.");
			}

			if ($proxy_user_id > 0) {
				$post_id=$_POST['post_id'];

				// $proxy_user = get_userdata($proxy_user_id);

				// update the respective meta to stop them from seeing the voting form and nomination form
				nominate_proxy($proxy_user_id, $current_user_id);

				$voting_page = new Amsa_Voting_Page($post_id);

				// stop any existing votes from counting
				$has_voted = $voting_page->get_single_meta( '_voted_users');
				if(array_key_exists($current_user_id, $has_voted)){
					unset($has_voted[$current_user_id]);
					update_post_meta($post_id,'_voted_users',$has_voted);
				}

				ob_start();
				$voting_page->render_proxy_nomination_header();
				$rendered_content = ob_get_clean();

				ob_start();
				$voting_page->render_dynamic();
				$voting_form = ob_get_clean();

				wp_send_json_success(array('rendered_content'=>$rendered_content, 'voting_form'=>$voting_form));
			}else{
				wp_send_json_error('Please select a proxy to nominate.');
			}
		}else{
			wp_send_json_error('Please login to nominate proxy.');
		}
		wp_die();
	}

	public function retract_proxy_ajax_handler(){
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
		$current_user_id = get_current_user_id();

		if ($current_user_id>0  && isset( $_POST['post_id'])){
			retract_proxy($current_user_id);

			$post_id=$_POST['post_id'];
			$voting_page = new Amsa_Voting_Page($post_id);

			ob_start();
			$voting_page->render_proxy_nomination_header();
			$rendered_content = ob_get_clean();

			ob_start();
			$voting_page->render_dynamic();
			$voting_form = ob_get_clean();

			wp_send_json_success(array('rendered_content'=>$rendered_content, 'voting_form'=>$voting_form));

		}else{
			wp_send_json_error('Please login to nominate proxy.');
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
			wp_send_json_error('Please login to manage your proxies.');
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
