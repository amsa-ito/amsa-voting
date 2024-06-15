<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';


class Amsa_Voting_Poll_Topics_Public {
    private $post_id;
    public $current_user;
    public $current_user_id;
    private $warning_messages;

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->current_user = wp_get_current_user();
        $this->current_user_id = $this->current_user->ID;

        $this->warning_messages = $this->set_user_default_proxy();

    }

    public static function render_partials($partial_path, $args = array()) {
        // Construct the full path to the partial file
        $partial_file = plugin_dir_path(__FILE__) . 'poll_topics/partials/' . $partial_path;

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

    public function get_single_meta($key){
        return get_post_meta($this->post_id, $key, true);
    }

    public function set_user_default_proxy(){
        if(!$this->current_user_id){
            return "";
        }
        $current_user_roles = get_user_meta($this->current_user_id, 'wp_capabilities', true);
        if(array_key_exists('amsa_rep', $current_user_roles)){
            return "";
        }
        $current_principals=get_user_meta($this->current_user_id, 'amsa_voting_principals', true);
       
        if($current_principals){
            // they can't actually be proxying anyone
            update_user_meta($this->current_user_id ,'amsa_voting_proxy', 0);
            return "";
        }
        $current_proxy_id = get_user_meta($this->current_user_id, 'amsa_voting_proxy', true);
        $current_user_university = get_user_meta($this->current_user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
        if(!$current_user_university){
            return "Please complete your profile fields in your <a href='". get_permalink( get_option('woocommerce_myaccount_page_id') )."' title=My Account>Membership Profile</a>";

        }
        // default proxy_id is -1
        if ($current_proxy_id<0) {
            // Find a user with role 'amsa_rep' and matching university meta key
            $args = array(
                'role' => 'amsa_rep',
                'meta_query' => array(
                    array(
                        'key' => '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'),
                        'value' => $current_user_university, // Get current user's university meta key
                        'compare' => '='
                    )
                )
            );

            // Retrieve users based on the arguments
            $users = get_users($args);

            // If users are found, set the proxy to the first user found
            if (!empty($users)) {
                $proxy_user = $users[0];
                nominate_proxy($proxy_user->ID, $this->current_user_id);
                return "By default AMSA Member's vote goes to your AMSA Rep, but you can retract your proxy";
            }else{
                return "By default AMSA Member's vote goes to your AMSA Rep, but your AMSA Rep couldn't be found for your university";
            }
        }
        return "";

    }


    // process the _voted_users meta so that it is ['for'=[user_ids...],'against'=[user_ids...],'abstain'=[user_ids...]]
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
        $event_attendees = get_event_attendees(get_option('amsa_voting_event_registration_post_id'));
        $amsa_reps = get_amsa_reps();
        $nomination_list = array_unique(array_merge($amsa_reps, $event_attendees));
        $this->render_partials('proxy-nomination.php', array('attendee_list'=>$nomination_list,
    'post_id'=>$this->post_id));
    }

    public function render_proxy_nomination_header(){
        $current_proxy_id = get_user_meta($this->current_user_id, 'amsa_voting_proxy', true);
        $this->render_partials('proxy-nomination-header.php', array('current_proxy_id'=>$current_proxy_id, 'post_id'=>$this->post_id,
        'current_principal_ids'=>get_user_meta($this->current_user_id, 'amsa_voting_principals', true)));
    }

    public function render() {
        echo('<div class="amsa-voting-poll-topic-wrapper" id="poll-topic-'.$this->post_id.'">');
        echo('<div class="amsa-voting-poll-warning-messasges" id="amsa-voting-poll-warning-messasges" style="display: none"><span id="amsa-voting-poll-warning-messasge-text">'.$this->warning_messages.'</span><span class="closebtn" onclick="this.parentElement.style.display=\'none\';">&times;</span></div>');
        // $this->render_partials('poll-headers.php', array('post_id'=>$this->post_id));

        // $current_user = wp_get_current_user();
        $this->render_partials('display-login-form.php', array('current_user'=>$this->current_user));
        if(!$this->current_user_id){
            return;
        }
        echo('<div id="amsa-voting-proxy-nomination-header">');
        $this->render_proxy_nomination_header();
        echo('</div>');

        echo('<div id="amsa-voting-proxy-nomination-list-wrapper">');
        // place holder for putting in the proxy nomination list
        echo('</div>');

        $this->render_partials('display-poll-settings.php', array('anonymous_voting'=>$this->get_single_meta('_anonymous_voting'),
                                                                    'representatives_only'=>$this->get_single_meta('_representatives_only'),
                                                                    'institution_weighted'=>$this->get_single_meta('_institution_weighted'),
                                                                    'voting_threshold'=>$this->get_single_meta('_voting_threshold')
                                                                    ));

        echo("<div id='amsa-voting-dynamic-content-wrapper'>");
        $this->render_dynamic();
        echo("</div>");

        echo("</div>");


    }

    public function render_dynamic(){
        $poll_status =  $this->get_single_meta('_poll_status');
        $is_anonymous = $this->get_single_meta('_anonymous_voting');

        $this->render_partials('display-poll-status.php', array('poll_status'=>$poll_status));

        if ( is_user_council_master()){
            $this->render_partials('display-admin-box.php', array('post_id'=>$this->post_id,'poll_status'=>$poll_status));
        }

		echo("<div id='amsa-voting-result-wrapper'>");
		if ($poll_status==='closed'){
            $this->render_partials('voting-result.php',array('post_id'=>$this->post_id));
		}
		if ($poll_status==='closed' || is_user_council_master()){
            $votes=calculate_votes($this->post_id);
            $users_per_vote=get_users_per_vote($this->post_id);
            $is_institutional_weighted = $this->get_single_meta('_institution_weighted');
            $is_council_master = is_user_council_master();

            $this->render_partials('voting-counts.php',array('votes'=>$votes,
            'users_per_vote'=>$users_per_vote,
             'is_anonymous'=>$is_anonymous,
              'is_institutional_weighted'=>$is_institutional_weighted,
            'is_council_master'=>$is_council_master));
		}
		echo("</div>");

		if($this->has_user_voted() && $poll_status!=='closed'){
            $this->render_partials('already-voted-message.php');
		}
        $not_require_rep =  $this->is_user_rep_eligible();
        $user_has_proxy = get_user_meta($this->current_user_id, 'amsa_voting_proxy', true) > 0;
        echo("<div id='amsa-voting-form-wrapper'>");
        if($poll_status!=='closed'){
            if($user_has_proxy){
                echo("You've assigned a proxy, edit your proxy if you want to vote");
            }else if(!$not_require_rep){
                echo("This poll requires you to be an AMSA rep or representing an AMSA rep");
            }
        }

        // render the form
		if ( $poll_status==='open' && $not_require_rep && !$user_has_proxy){
    		$existing_vote = $this->get_user_vote();

            $this->render_partials('voting-form.php', array('existing_vote'=>$existing_vote,'post_id'=>$this->post_id));

		}
		echo("</div>");
        echo("<div id='amsa-voting-unvoted-reps-wrapper'>");
        if(!$is_anonymous &&  $this->get_single_meta('_representatives_only' )){
            $this->render_partials('display-unvoted-reps.php', array('unvoted_reps'=>$this->get_unvoted_amsa_reps()));
        }
		echo("</div>");
    }

    private function get_unvoted_amsa_reps(){
        $amsa_rep_user_ids = get_amsa_reps();
        $has_voted = array_keys($this->get_single_meta('_voted_users'));

        return array_diff($amsa_rep_user_ids, $has_voted);

    }

    private function get_user_vote(){
        $voted_users=$this->get_single_meta('_voted_users');

        if(array_key_exists($this->current_user_id, $voted_users)){
            return $voted_users[$this->current_user_id]["vote_value"];
        }
		return NULL;
	}

    private function has_user_voted(){

		$has_voted = $this->get_single_meta('_voted_users');
		if(array_key_exists($this->current_user_id, $has_voted)){
			return true;
		}
		return false;
	}

    public function is_user_rep_eligible(){
		$representatives_only = $this->get_single_meta('_representatives_only' );
		if(!$representatives_only){
			return true;
		}

        $user_presentating_amsa_rep = is_user_representing_amsa_rep($this->current_user_id);

		if($representatives_only && $user_presentating_amsa_rep){

			return true;
		}
		return false;
	}


    // Add more methods for handling voting if necessary
}
