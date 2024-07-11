<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://amsa.org.au
 * @since      1.0.2
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/includes
 */

/**
 * poll for council
 *
 *
 * @package    Amsa_Voting
 * @subpackage Amsa_Voting/includes
 * @author     Steven Zhang <stevenzhangshao@gmail.com>
 */
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-amsa-voting-poll-topics-public.php';

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';


class Amsa_Voting_Poll_Topic{
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

    public function __construct( $plugin_name, $version, $post_name ){
        $this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->post_name = $post_name;

        add_action( 'init', array($this,'register_poll_topic_post_type') );
        add_action( 'init', array($this,'register_post_meta') );
        add_filter('manage_'.$this->post_name.'_posts_columns', array($this, 'add_admin_columns_to_poll_topics'));
		add_action('manage_'.$this->post_name.'_posts_custom_column', array($this, 'populate_poll_topics_columns_with_data'), 10, 2);
        add_filter('post_row_actions', [$this,'duplicate_post_link'], 10, 2);
		add_action('admin_action_duplicate_'.$this->post_name, [$this, 'duplicate_post_handler']);
        add_action( 'add_meta_boxes', array($this,'voting_options_meta_box') );
		add_action( 'save_post_'.$this->post_name, array($this, 'save_voting_options_meta_box_data') );
		add_action('manage_posts_extra_tablenav', [$this, 'add_export_button']);
		add_action('admin_init', [$this, 'export_poll_topics_to_csv']);

		add_shortcode(str_replace('-','_',$plugin_name.'_'.$post_name), array($this, 'voting_page_shortcode'));
        add_shortcode('poll_topics_overview_table', array($this,'poll_topics_overview_shortcode'));
		add_shortcode('active_poll_topics_overview', array($this,'display_active_poll_topics'));
        add_filter( 'the_content', array($this, 'voting_page_display' ));
		add_action('amsa_voting_poll_closed', array($this, 'talley_results'));
		add_action('amsa_voting_poll_open', array($this, 'unfreeze_poll'));
		add_action('wp_ajax_process_and_store_votes', array($this, 'process_and_store_votes'));
		add_action( 'wp_ajax_nominate_proxy', array($this, 'nominate_proxy_ajax_handler' ));
		add_action( 'wp_ajax_retract_proxy', array($this, 'retract_proxy_ajax_handler' ));
		add_action( 'wp_ajax_diplay_proxy_table', array($this, 'diplay_proxy_table_ajax_handler' ));

		add_action('wp_ajax_handle_poll_status_change', array($this, 'handle_poll_status_change'));

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

	public function add_export_button($which){
		if ($which === 'top' && get_post_type() === 'poll_topic') {
			echo '<input type="submit" name="export_poll_topics" class="button button-primary" value="Export to CSV" style="margin-left:10px;">';
		}
	}

	function export_poll_topics_to_csv() {
		if (!is_user_council_master() || !isset($_GET['export_poll_topics']) || !isset($_GET['post']) ) {
			return;
		}
	
		$filename = 'poll_topics_' . date('Y-m-d') . '.csv';
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Content-Type: text/csv; charset=' . get_option('blog_charset'), true);
	
		$output = fopen('php://output', 'w');

	
		// Get all Poll Topics
		$args = array(
			'post_type' => $this->post_name,
			'posts_per_page' => -1,
			'include' => $_GET['post']
		);
		$posts = get_posts($args);
	
		// Output CSV column headings
		$headers = array('ID', 'Title', 'Content');
		$first_post = reset($posts);
		$meta_keys = array_keys(get_post_meta($first_post->ID));
		$headers = array_merge($headers, $meta_keys);
		fputcsv($output, $headers);
	
		// Output CSV rows
		foreach ($posts as $post) {
			$data = array(
				$post->ID,
				$post->post_title,
				$post->post_content,
			);
			$meta_data = get_post_meta($post->ID);
			foreach ($meta_keys as $key) {
				$data[] = isset($meta_data[$key]) ? maybe_serialize($meta_data[$key][0]) : '';
			}
			fputcsv($output, $data);
		}
	
		fclose($output);
		exit;
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

    public function duplicate_post_link($actions, $post) {
		if ($post->post_type == $this->post_name) {
			$actions['duplicate'] = '<a href="' . admin_url('admin.php?action=duplicate_'.$this->post_name.'&post=' . $post->ID) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
		}
		return $actions;
	}

	public function duplicate_post_handler() {
		if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] != 'duplicate_'.$this->post_name) {
			return;
		}
	
		$post_id = absint($_GET['post']);
		$post = get_post($post_id);
	
		if (empty($post) || !current_user_can('edit_post', $post_id)) {
			wp_die('Error occurred while duplicating the post.');
		}
	
		$new_post_args = array(
			'post_title' => $post->post_title . ' (Copy)',
			'post_content' => $post->post_content,
			'post_status' => 'draft', // Or any other status you prefer
			'post_type' => $post->post_type,
		);
	
		$new_post_id = wp_insert_post($new_post_args);

		$exclusion_meta = ['_voted_users', '_poll_status','_voting_outcome','_final_voted_numbers','_final_voted_users'];
	
		if ($new_post_id) {
			// Duplicate post meta
			$post_meta = get_post_meta($post_id);
			foreach ($post_meta as $meta_key => $meta_values) {
				if(in_array($meta_key, $exclusion_meta)){
					continue;
				}
				foreach ($meta_values as $meta_value) {
					add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
				}
			}
			// Redirect to the new duplicated post
			wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
			exit();
		} else {
			wp_die('Error occurred while duplicating the post.');
		}
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
	public function populate_poll_topics_columns_with_data($column, $post_id) {
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

    public function voting_page_shortcode($atts) {
		$poll_id = $atts['poll_id'];
		$voting_page = new Amsa_Voting_Poll_Topics_Public($poll_id);
		ob_start();
		$voting_page->render();
		return ob_get_clean();

	}

    public function display_active_poll_topics() {
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
			$voting_page = new Amsa_Voting_Poll_Topics_Public($poll_id);
			ob_start();
			$voting_page->render();
			$rendered_page = ob_get_clean();
			$content .= $rendered_page;

		}

		return $content;
	}




	public function process_and_store_votes() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		$current_user_id = get_current_user_id();
		if (isset($_POST['vote']) && $current_user_id>0) {
			$vote = sanitize_text_field($_POST['vote']);
			$post_id = intval($_POST['post_id']); // Use intval to sanitize the ID

			$voting_page = new Amsa_Voting_Poll_Topics_Public($post_id);

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
			$voting_page = new Amsa_Voting_Poll_Topics_Public($post_id);

			if (is_user_council_master()) {
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

				$voting_page = new Amsa_Voting_Poll_Topics_Public($post_id);

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
			$voting_page = new Amsa_Voting_Poll_Topics_Public($post_id);

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
			$voting_page = new Amsa_Voting_Poll_Topics_Public($post_id);
			ob_start();
			$voting_page->render_proxy_nomination_list();
			wp_send_json_success(array('rendered_content'=>ob_get_clean()));

		}else{
			wp_send_json_error('Please login to manage your proxies.');
		}
		wp_die();
	}

}