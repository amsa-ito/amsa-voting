<?php
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';

class Amsa_Voting_Ballot_Public{
    private $post_id;
    public $current_user;
    public $current_user_id;

    public function __construct($post_id){
        $this->post_id = $post_id;
        $this->current_user = wp_get_current_user();
        $this->current_user_id = $this->current_user->ID;
    }

    public function render(){
        $voted_users = $this->get_single_meta('_voted_users');
        $poll_status =  $this->get_single_meta('_poll_status');


        if(array_key_exists($this->current_user_id, $voted_users)){
            $message= "Your vote is in!";
        }elseif($poll_status==='open'){
            $message= "You have not voted yet!";
        }

        echo('<div class="amsa-voting-poll-warning-messasges" id="amsa-voting-poll-warning-messasges"'.($message?:'style="display: none"').'><span id="amsa-voting-poll-warning-messasge-text">'.$message.'</span><span class="closebtn" onclick="this.parentElement.style.display=\'none\';">&times;</span></div>');

        echo('<div class="amsa-voting-ballot-wrapper" id="amsa-voting-ballot-wrapper">');
        $this->render_dynamic();
        echo('</div>');

    }

    public function render_dynamic(){
        $candidates = $this->get_single_meta('_amsa_voting_candidates');
        $post_id = $this->post_id;
        $poll_status =  $this->get_single_meta('_poll_status');


        $this->render_partials('display-poll-status.php', array('poll_status'=>$poll_status), 'poll_topics//');

        if(is_user_council_master()){
            $this->render_partials('display-admin-box.php', array('post_id'=>$post_id,'poll_status'=>$poll_status));
        }

        $this->render_partials('display-login-form.php', array('current_user'=>$this->current_user), 'poll_topics//');        
        if(!$this->current_user_id){
            return;
        }

        if($poll_status==='open'){
            $this->render_partials('ballot_form.php', ['post_id'=>$post_id, 'candidates'=>$candidates]);
            
        }
    }

    public function get_single_meta($key){
        return get_post_meta($this->post_id, $key, true);
    }

    public static function render_partials($partial_path, $args = array(), $partials_root='ballot/') {
        // Construct the full path to the partial file
        $partial_file = plugin_dir_path(__FILE__) . 'partials/' . $partials_root . $partial_path;

        // Ensure the partial file exists
        if (!file_exists($partial_file)) {
            // Handle error if the file doesn't exist
            error_log("file doesn't exist! ".$partial_file);
            return;
        }

        // Extract the variables from the $args array for use in the partial
        if (!empty($args)){
            extract($args);
        }

        // Include the partial file
        include $partial_file;
    }
}