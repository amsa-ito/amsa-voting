<?php

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
        $candidates = $this->get_single_meta('_amsa_voting_candidates');
        $post_id = $this->post_id;
        $voted_users = $this->get_single_meta('_voted_users');
        if(array_key_exists($this->current_user_id, $voted_users)){
            $message = "Your vote is in!";
        }else{
            $message = "You have not voted yet!";
        }

        echo('<div class="amsa-voting-ballot-warning-messasges" id="amsa-voting-ballot-warning-messasges"><span id="amsa-voting-ballot-warning-messasge-text">'.$message.'</span><span class="closebtn" onclick="this.parentElement.style.display=\'none\';">&times;</span></div>');
        $this->render_partials('ballot_form.php', ['post_id'=>$post_id, 'candidates'=>$candidates]);

    }

    public function get_single_meta($key){
        return get_post_meta($this->post_id, $key, true);
    }

    public static function render_partials($partial_path, $args = array()) {
        // Construct the full path to the partial file
        $partial_file = plugin_dir_path(__FILE__) . 'partials/ballot/' . $partial_path;

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