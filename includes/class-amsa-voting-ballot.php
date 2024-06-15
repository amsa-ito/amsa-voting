<?php
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-amsa-voting-ballot-public.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';


class Amsa_Voting_Ballot{
    private $plugin_name;
    private $version;
    private $post_name;

    public function __construct($plugin_name, $version, $post_name){
        $this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->post_name = $post_name;

        add_action( 'init', array($this,'register_post_type') );
        add_action( 'init', array($this,'register_post_meta') );
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_filter( 'the_content', array($this, 'ballot_page_display' ));
		add_action('wp_ajax_cast_ballot', array($this, 'handle_ajax_cast_ballot'));
		add_action('wp_ajax_handle_ballot_status_change', array($this, 'handle_ballot_status_change'));


        add_filter('post_row_actions', [$this,'duplicate_post_link'], 10, 2);
		add_action('admin_action_duplicate_'.$this->post_name, [$this, 'duplicate_post_handler']);

        add_filter('post_row_actions', [$this, 'export_voted_users_link'], 10, 2);
        add_action('admin_action_export_voted_users_'.$this->post_name, [$this, 'export_voted_users_handler']);

    }

    public function register_post_type(){
        $labels = array(
			'name'               => __( 'Ballot', 'amsa-voting' ),
			'singular_name'      => __( 'ballot', 'amsa-voting' ),
			'add_new'            => __( 'Add New', 'amsa-voting' ),
			'add_new_item'       => __( 'Add New Ballot', 'amsa-voting' ),
			'edit_item'          => __( 'Edit Ballot', 'amsa-voting' ),
			'new_item'           => __( 'New Ballot', 'amsa-voting' ),
			'view_item'          => __( 'View Ballot', 'amsa-voting' ),
			'search_items'       => __( 'Search Ballots', 'amsa-voting' ),
			'not_found'          => __( 'No Ballots found', 'amsa-voting' ),
			'not_found_in_trash' => __( 'No Ballots found in Trash', 'amsa-voting' ),
			'parent_item_colon'  => __( 'Parent Ballot:', 'amsa-voting' ),
			'menu_name'          => __( 'Ballots', 'amsa-voting' ),
		);
	
		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'amsa-voting-menu',
			'menu_position'       => 20,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'ballot' ),
			'show_in_rest'		=>true,
		);
		
		register_post_type( $this->post_name, $args );

    }

    public function register_post_meta(){
        $this->helper_register_post_meta('_voted_users', 'array', array()); #array('user_id'=>array('candidate_name'=>'preference number'))
		$this->helper_register_post_meta('_poll_status', 'string', 'unvoted');
		$this->helper_register_post_meta('_final_voted_numbers', 'array', array()); //['for'=sum_of_weights, 'away'=sum_of_weights, 'abstain'=sum_of_weights]
		$this->helper_register_post_meta('_final_voted_users', 'array', array()); //['for'=[user_ids...],'against'=[user_ids...],'abstain'=[user_ids...]]

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

    public function add_meta_boxes(){
        add_meta_box(
            'amsa_voting_ballot_candidates',
            __('Candidate Names', 'amsa-voting'),
            array($this, 'render_meta_box_content'),
            $this->post_name,
            'normal',
            'default'
        );
    }

    public function render_meta_box_content($post){
        wp_nonce_field('amsa_voting_ballot_candidates_nonce', 'amsa_voting_ballot_candidates_nonce');

        $candidates = get_post_meta($post->ID, '_amsa_voting_candidates', true);
        $candidates = is_array($candidates) ? $candidates : array('');

        echo '<div id="amsa-voting-candidates">';
        foreach ($candidates as $candidate) {
            echo '<p><input type="text" name="amsa_voting_candidates[]" value="' . esc_attr($candidate) . '" /></p>';
        }
        echo '</div>';
        echo '<p><button type="button" id="add_candidate_button" class="button">' . __('Add Candidate', 'amsa-voting') . '</button></p>';

    }

    public function save_meta_box_data($post_id){
        if (!isset($_POST['amsa_voting_ballot_candidates_nonce']) || !wp_verify_nonce($_POST['amsa_voting_ballot_candidates_nonce'], 'amsa_voting_ballot_candidates_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['amsa_voting_candidates'])) {
            $candidates = array_map('sanitize_text_field', $_POST['amsa_voting_candidates']);
            $candidates = array_filter($candidates); // Remove empty values
            if (!empty($candidates)) {
                update_post_meta($post_id, '_amsa_voting_candidates', $candidates);
            } else {
                delete_post_meta($post_id, '_amsa_voting_candidates');
            }
        } else {
            delete_post_meta($post_id, '_amsa_voting_candidates');
        }
    }

    public function ballot_page_display($content){
        if ( is_single() && get_post_type() === $this->post_name  ){
			global $post;
			$poll_id = $post->ID;
			$voting_page = new Amsa_Voting_Ballot_Public($poll_id);
			ob_start();
			$voting_page->render();
			$content .= ob_get_clean();

		}

		return $content;
    }

    public function handle_ajax_cast_ballot(){
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
		$current_user_id = get_current_user_id();
        if ($current_user_id>0){
            if(isset($_POST['candidate_preference']) && isset($_POST['post_id'])){
               
                    $preferences = $_POST['candidate_preference'];

                    // Check for duplicate preference numbers
                    $preference_values = array_values($preferences);
                    if (count($preference_values) !== count(array_unique($preference_values))) {
                        wp_send_json_error('Duplicate preference numbers are not allowed.');
                    }

                    $post_id=$_POST['post_id'];

                    $has_voted = get_post_meta($post_id, '_voted_users', True);

                    $has_voted[$current_user_id]=$preferences;
                    update_post_meta($post_id,'_voted_users',$has_voted);
        
                    wp_send_json_success(array('rendered_content'=>NULL, 'message'=>"Thank you for submitting your preferences!"));
        
            }
            
            wp_send_json_error(print_r($_POST,true));
        }else{
            wp_send_json_error('Please login to cast your ballot');

        }

		wp_die();

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

		$exclusion_meta = ['_voted_users', '_poll_status','_final_voted_numbers','_final_voted_users', '_amsa_voting_candidates'];
	
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

    public function export_voted_users_link($actions, $post) {
        if ($post->post_type == $this->post_name) {
            $actions['export_voted_users'] = '<a href="' . wp_nonce_url(admin_url('admin.php?action=export_voted_users_' . $this->post_name . '&post=' . $post->ID), 'export_voted_users_' . $post->ID) . '">Export Voted Users</a>';
        }
        return $actions;
    }

    public function export_voted_users_handler() {
        if (!isset($_GET['post']) || !current_user_can('edit_post', $_GET['post']) || $_GET['action'] != 'export_voted_users_'.$this->post_name) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $post_id = intval($_GET['post']);
        check_admin_referer('export_voted_users_' . $post_id);

        $voted_users = get_post_meta($post_id, '_voted_users', true);

        if (empty($voted_users)) {
            wp_die(__('No voted users found.'));
        }

        $csv_data = [];

        // Extract candidate names from the first user's votes (assuming all users have the same candidates)
        $candidate_names = get_post_meta($post_id, '_amsa_voting_candidates', True);
        $header = array_merge(['user_id','user_role','university','user_name'], $candidate_names);
        $csv_data[] = $header;

        foreach ($voted_users as $user_id => $votes) {
            $user = get_userdata($user_id);
            $display_name = $user->display_name ? $user->display_name : $user->username;
            $user_role = prettify_role_names($user->roles);
            $university_meta = get_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);

            $row = [$user_id, $user_role, $university_meta, $display_name];

            foreach ($candidate_names as $candidate_name) {
                $row[] = $votes[$candidate_name] ?? '';
            }
            $csv_data[] = $row;
        }

        // Output CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=voted_users.csv');

        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);

        exit;
    }

    public function handle_ballot_status_change() {
		check_ajax_referer($this->plugin_name.'-nonce', 'nonce');

		if (isset($_POST['poll_status_change'])) {

			$post_id = intval($_POST['post_id']);
			$voting_page = new Amsa_Voting_Ballot_Public($post_id);

			if (is_user_council_master()) {
				$current_status = get_post_meta($post_id, '_poll_status', true);

				$new_status = ($current_status === 'open') ? 'closed' : 'open';
				update_post_meta($post_id, '_poll_status', $new_status);
				update_post_meta($post_id, '_poll_' . $new_status . '_timestamp', current_time('timestamp'));


				ob_start();
				$voting_page->render_dynamic();
				$success_json = array('poll_status'=>$new_status, 'rendered_content'=> ob_get_clean());
				wp_send_json_success($success_json);

			}
		}
		wp_die();

	}




}