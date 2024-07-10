<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/amsa-voting-functions.php';


class Amsa_Voting_Speaker_List {
    private $plugin_name;
    private $version;


public function __construct($plugin_name, $version, $post_name ) {
    $this->plugin_name = $plugin_name;
    $this->version = $version;
    add_action('init', [$this, 'register_block']);
    add_action('wp_ajax_amsa_nominate_speaker', [$this, 'nominate_speaker']);
    add_action('wp_ajax_amsa_retract_nomination', [$this, 'handle_retract_nomination']);
    add_action('wp_ajax_real_time_speaker_list', [$this, 'real_time_speaker_list_callback']);

}

public function register_block() {

    $success = register_block_type(plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/speaker-list/block.json', 
    array(
        'render_callback' => [$this, 'render_speaker_list_block'],
    )
    );
    
}

public function render_speaker_list_block($attributes) {
    if (!isset($attributes['blockID']) || empty($attributes['blockID'])) {
        return 'Error: Block ID is missing.';
    }

    $block_id = sanitize_text_field($attributes['blockID']);
    return $this->display_speaker_list($block_id);
}



public function display_speaker_list($block_id) {

    ob_start();
    ?>
     <?php if (is_user_council_master()) : ?>
        <div class="amsa-voting-real-time-switch">
            <label for="real-time-update">Real-Time Update</label>
            <input type="checkbox" id="real-time-update" data-block-id="<?php echo $block_id ?>">
     </div>
        <?php endif; ?>
    <div class="amsa-voting-speaker-short-code-wrapper">
    <div class="amsa-voting-speaker-list-warning-messasges" id="amsa-voting-speaker-list-warning-messasges" style="display: none"><span id="amsa-voting-speaker-list-warning-messasge-text"></span><span class="closebtn" onclick="this.parentElement.style.display='none';">&times;</span></div>
    <div id='amsa-voting-speaker-list-wrapper-<?php echo $block_id; ?>'>
    <?php
    echo $this->_display_speaker_list($block_id);
    echo "</div></div>";
    return ob_get_clean();
   
}

public function _display_speaker_list($block_id){
    ob_start();

    $user_id = get_current_user_id();
    echo('Your Speaker ID is: '.$user_id);
    echo('<div id="amsa-voting-speaker-table">');
    echo '<table>';
    echo '<tr><th>#</th><th>Name</th><th>Speaker Number</th><th></th></tr>';

    // Query the speaker lists
    $speaker_user_ids = get_option('amsa_speaker_list_' . $block_id, array());
    error_log(print_r($block_id ,true));
    error_log(print_r($speaker_user_ids ,true));
    if($speaker_user_ids){
        foreach($speaker_user_ids as $index => $speaker_user_id){
            $user_info = get_userdata($speaker_user_id);
            ?>
            <tr>
            <td><?php echo $index+1 ?></td>
            <td><?php echo esc_html($user_info->display_name) ?></td>
            <td><?php echo $speaker_user_id ?></td>
            <td>
            <?php 
             if (is_user_council_master() || $user_id == $speaker_user_id){
                ?><td><button class="speaker-removal-button" data-block-id="<?php echo $block_id ?>" data-speaker-user-id="<?php echo $speaker_user_id ?>">&#x2716;</button></td><?php
             }
            ?>
            </td>
            </tr>
            <?php
        }
    }else{
        echo '<div class="amsa-voting-speaker-list-no-speaker">No delegates are currently on the speaker list.</div>';
    }

    echo '</table>';
    echo('</div>');

    // Display form if user is logged in
    if (is_user_logged_in()) {
        ?>
        <form id="nominate-speaker-form-<?php echo $block_id; ?>" class="nominate-speaker-form">
        <?php 
        if(in_array($user_id, $speaker_user_ids)){
            ?>
            <input type="hidden" name="action" value="amsa_retract_nomination">
            <input type="hidden" id="amsa-voting-speaker-list-block-id" name="block_id" value="<?php echo $block_id ?>">
            <input type="hidden" name="speaker_user_id" value="<?php echo $user_id?>">
            <input type="submit" value="Remove yourself from the list">
            <?php
        }else{
            ?>
            <input type="hidden" name="action" value="amsa_nominate_speaker">
            <input type="hidden" id="amsa-voting-speaker-list-block-id" name="block_id" value="<?php echo $block_id ?>">
            <input type="hidden" name="speaker_user_id" value="<?php echo $user_id?>">
            <input type="submit" value="Nominate Yourself">
            <?php
        }
        echo '</form>';
    } else {
        echo 'Please log in to nominate yourself.';
    }

    ?>
    <?php

    return ob_get_clean();
}

public function nominate_speaker() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to nominate yourself.');
        return;
    }
    check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
    if (isset($_POST['block_id']) && isset($_POST['speaker_user_id'])) {
        $block_id = sanitize_text_field($_POST['block_id']);
        $user_id = intval($_POST['speaker_user_id']);
        $speaker_list = get_option('amsa_speaker_list_' . $block_id, array());
        
        $speaker_list[]=$user_id;
        update_option('amsa_speaker_list_' . $block_id, $speaker_list);
        $rendered_content = $this->_display_speaker_list($block_id);

        wp_send_json_success(array(
            'message' => 'Nomination added successfully.',
            'rendered_content' => $rendered_content,
            'block_id' => $block_id,
        ));
    } else {
        wp_send_json_error('Invalid request.');
    }
    wp_die();
    
}

public function handle_retract_nomination() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to retract your nomination.');
    }
    check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
    if(isset($_POST['block_id']) && isset($_POST['speaker_user_id'])){

        $block_id = sanitize_text_field($_POST['block_id']);
        $user_id = intval($_POST['speaker_user_id']);

        if (get_current_user_id() != $user_id && !is_user_council_master()) {
            wp_send_json_error('You do not have permission to retract this nomination.');
        }

        $speaker_list = get_option('amsa_speaker_list_' . $block_id, array());

        if(($key = array_search($user_id, $speaker_list))!==false){
            unset($speaker_list[$key]);
            update_option('amsa_speaker_list_' . $block_id, $speaker_list);
            
            $rendered_content = $this->_display_speaker_list($block_id);

            wp_send_json_success(['message'=>'Nomination retracted',
            'rendered_content'=>$rendered_content,
             'block_id' => $block_id,]);


        }else{
            wp_send_json_error('No such speaker found');
        }
    }else{
        wp_send_json_error('Error removing nomination');

    }
    wp_die();
}

public function real_time_speaker_list_callback(){
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to use this functionality');
    }
    if(!is_user_council_master()){
        wp_send_json_error('You must be an admin to use this functionality');
    }
    check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
    if(isset($_POST['block_id'])){
        $block_id = sanitize_text_field($_POST['block_id']);
        wp_send_json_success(['rendered_content'=>$this->_display_speaker_list($block_id)]);

    }
    wp_die();
}

}
