<?php

class Amsa_Voting_Page {
    private $post_id;
    public $current_user;
    public $current_user_id;

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->current_user = wp_get_current_user();
        $this->current_user_id = $this->current_user->ID;


    }

    public static function render_partials($partial_path, $args = array()) {
        // Construct the full path to the partial file
        $partial_file = plugin_dir_path(__FILE__) . 'partials/' . $partial_path;

        // Ensure the partial file exists
        if (!file_exists($partial_file)) {
            // Handle error if the file doesn't exist
            return;
        }

        // Extract the variables from the $args array for use in the partial
        if (!empty($args)){
            extract($args);
        }

        // Include the partial file
        include $partial_file;
    }

    private function get_single_meta($key){
        return get_post_meta($this->post_id, $key, true);
    }

    public function set_user_default_proxy(){
        if(!$this->current_user){
            return;
        }
        $current_proxy_id = get_user_meta($this->current_user_id, 'amsa_voting_proxy', true);
        if (empty($current_proxy_id)) {
            // Find a user with role 'amsa_rep' and matching university meta key
            $args = array(
                'role' => 'amsa_rep',
                'meta_query' => array(
                    array(
                        'key' => '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), 
                        'value' => get_user_meta($this->current_user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true), // Get current user's university meta key
                        'compare' => '='
                    )
                )
            );
    
            // Retrieve users based on the arguments
            $users = get_users($args);
    
            // If users are found, set the proxy to the first user found
            if (!empty($users)) {
                $proxy_user = $users[0];
                update_user_meta($this->current_user_id, 'amsa_voting_proxy', $proxy_user->ID);
            }
        }

    }



    private function get_event_attendees($event_id){
        $attendees_orm = tribe_attendees();
        $attendees_orm->where( 'event', $event_id )->where( 'rsvp_status__or_none', 'yes' );
        $user_ids=[];
        foreach($attendees_orm->all() as $attendee){
            $user_ids[]=$attendee->post_author;
        }
        return $user_ids;
    }

    public static function calculate_votes($post_id){
        if (!function_exists('helper_calculate_vote_type')) {
        function helper_calculate_vote_type($vote_type, $votes_array){
            $vote_type_keys = array_keys(array_column($votes_array, 'vote_value'), $vote_type);;
            if(count($vote_type_keys)===0){
                return 0;
            }
            $flipped_vote_type_keys = array_flip($vote_type_keys);

            $vote_weights = array_column($votes_array, 'vote_weight');

            return array_sum($vote_weights);
        }
        }
        $retrieved_votes = get_post_meta($post_id, '_voted_users',true);
        // it is in the form of array(user_id=>[vote_weight=>,vote_value=>])
        
        $default_votes = array();
        $default_votes['for']=helper_calculate_vote_type('for', $retrieved_votes);
        $default_votes['against']=helper_calculate_vote_type('against', $retrieved_votes);
        $default_votes['abstain']=helper_calculate_vote_type('abstain', $retrieved_votes);
		
		return $default_votes;
    }

    private function get_users_per_vote(){
        $retrieved_votes = $this->get_single_meta('_voted_users');
        $default_votes = array('for', 'against', 'abstain');
        $result=  array();
        foreach($default_votes as $vote_type){
            $vote_type_keys = array_keys(array_column($retrieved_votes, 'vote_value'), $vote_type);
            if(empty($vote_type_keys)){
                continue;
            }
            $flipped_vote_type_keys = array_flip($vote_type_keys);
            $keyed_users = array_keys($retrieved_votes);
            $result[$vote_type]=array_intersect_key($keyed_users, $flipped_vote_type_keys);
        }
       
        return $result;
    }

    public function render_proxy_nomination_list(){
        $this->render_partials('proxy-nomination.php', array('attendee_list'=>$this->get_event_attendees(get_option('amsa_voting_event_registration_post_id')),
    'post_id'=>$this->post_id));
    }

    public function render_proxy_nomination_header(){
        $current_proxy_id = get_user_meta($this->current_user_id, 'amsa_voting_proxy', true);
        $this->render_partials('proxy-nomination-header.php', array('current_proxy_id'=>$current_proxy_id, 'post_id'=>$this->post_id));
    }

    public function render() {
        echo('<div class="amsa-voting-poll-topic-wrapper" id="poll-topic-'.$this->post_id.'">');
        // $this->render_partials('poll-headers.php', array('post_id'=>$this->post_id));

        $current_user = wp_get_current_user();
        echo('<div id="amsa-voting-proxy-nomination-header">');
        $this->render_proxy_nomination_header($current_user->ID);
        echo('</div>');
        
        echo('<div id="amsa-voting-proxy-table-wrapper">');
        // if(!$current_proxy_id){
        //     $this->render_proxy_nomination_list($current_user->ID);
        // }
        echo('</div>');


        $this->render_partials('display-login-form.php', array('current_user'=>$current_user));
        $this->render_partials('display-proxy-form.php');
        if(!$current_user){
            return;
        }

        $this->render_partials('display-poll-settings.php', array('anonymous_voting'=>$this->get_single_meta('_anonymous_voting'),
                                                                    'representatives_only'=>$this->get_single_meta('_representatives_only'),
                                                                    'institution_weighted'=>$this->get_single_meta('_institution_weighted'),
                                                                    'voting_threshold'=>$this->get_single_meta('_voting_threshold')
                                                                    ));

        $this->render_dynamic($current_user);	
        echo("</div>");

    
    }

    public function render_dynamic($current_user){
        echo("<div id='amsa-voting-dynamic-content-wrapper'>");
        $poll_status =  $this->get_single_meta('_poll_status');
        $is_anonymous = $this->get_single_meta('_anonymous_voting');

        if ( $this->is_user_council_master() && $poll_status!=='closed'){
            $this->render_partials('display-admin-box.php', array('post_id'=>$this->post_id,'poll_status'=>$poll_status));
        }

		echo("<div id='amsa-voting-result-wrapper'>");
		if ($poll_status==='closed'){
            $this->render_partials('voting-result.php',array('post_id'=>$this->post_id));
		}
		if ($poll_status==='closed' || $this->is_user_council_master()){
            $votes=$this->calculate_votes($this->post_id);
            $users_per_vote=$this->get_users_per_vote();
            $is_institutional_weighted = $this->get_single_meta('_institution_weighted');
            $is_council_master = $this->is_user_council_master();

            $this->render_partials('voting-counts.php',array('votes'=>$votes, 
            'users_per_vote'=>$users_per_vote,
             'is_anonymous'=>$is_anonymous,
              'is_institutional_weighted'=>$is_institutional_weighted,
            'is_council_master'=>$is_council_master));
		}
		echo("</div>");

		if($this->has_user_voted($current_user->ID) && $poll_status!=='closed'){
            $this->render_partials('already-voted-message.php');
		}
        $not_require_rep =  $this->is_user_rep_eligible($current_user);
        $user_has_proxy = get_user_meta($current_user->ID, 'amsa_voting_proxy', true);
        echo("<div id='amsa-voting-form-wrapper'>");
        if($user_has_proxy){
            echo("You've assigned a proxy, edit your proxy if you want to vote");
        }

		if ( $poll_status==='open' && $not_require_rep && !$user_has_proxy){
    		$existing_vote = $this->get_user_vote($current_user->ID);

            $this->render_partials('voting-form.php', array('existing_vote'=>$existing_vote,'post_id'=>$this->post_id));

		}
		echo("</div>");
        echo("<div id='amsa-voting-unvoted-reps-wrapper'>");
        if(!$is_anonymous &&  $this->get_single_meta('_representatives_only' )){
            $this->render_partials('display-unvoted-reps.php', array('unvoted_reps'=>$this->get_unvoted_amsa_reps()));
        }
		echo("</div>");
        echo("</div>");
    }

    private function get_unvoted_amsa_reps(){
        $args = [
            'role'    => 'amsa_rep',
            'fields'  => 'ID', // Retrieve only the user IDs for efficiency
        ];
        
        $user_query = new WP_User_Query($args);
        $amsa_rep_user_ids = $user_query->get_results();
        $has_voted = array_keys($this->get_single_meta('_voted_users'));     

        return array_diff($amsa_rep_user_ids, $has_voted);

    }

    public static function is_user_council_master(){
		return current_user_can('edit_others_posts');
	}

    private function get_user_vote($user_id){
        $voted_users=$this->get_single_meta('_voted_users');

        if(array_key_exists($user_id, $voted_users)){
            return $voted_users[$user_id]["vote_value"];
        }
		return NULL;
	}

    private function has_user_voted($user_id){

		$has_voted = $this->get_single_meta('_voted_users');
		if(array_key_exists($user_id, $has_voted)){
			return true;
		}
		return false;
	}

    private function is_user_rep_eligible($current_user){
		$representatives_only = $this->get_single_meta('_representatives_only' );
		if(!$representatives_only){
			return true;
		}
		if($representatives_only && in_array('amsa_rep', $current_user->roles)){
			return true;
		}
		return false;
	}


    // Add more methods for handling voting if necessary
}