<?php

class Amsa_Voting_Speaker_List {
    private $plugin_name;
	private $post_name;
    private $version;


public function __construct($plugin_name, $version, $post_name ) {
    $this->plugin_name = $plugin_name;
    $this->version = $version;
    $this->post_name = $post_name;
    add_action('init', [$this, 'register_post_type']);
    add_action('init',[$this, 'register_post_meta']);
    add_shortcode('amsa_voting_speaker_list', [$this, 'display_speaker_list']);
    add_action('wp_ajax_nominate_speaker', [$this, 'nominate_speaker']);
    add_action('wp_ajax_retract_nomination', [$this, 'handle_retract_nomination']);
    
}

public function register_post_meta(){
    register_post_meta(
        $this->post_name,
        '_speaker_list',
        array(
            'single'=>true,
            'type'=>'array',
            'default'=>array()
        )
        );
}

public function register_post_type() {
    $args = [
        'labels' => [
            'name' => 'Speaker Lists',
            'singular_name' => 'Speaker List',
        ],
        'show_in_menu' => 'amsa-voting-menu',
        'public' => true,
        'has_archive' => true,
        'supports' => ['title'], // only supports title
        'exclude_from_search' => true, // not searchable
        'publicly_queryable' => false, // not directly queryable via URL
        // 'show_in_rest' => true, // enable Gutenberg support
    ];

    register_post_type($this->post_name, $args);
}


public function display_speaker_list($atts) {
    if(!array_key_exists('post_id', $atts)){
        return;
    }

    $post_id = intval($atts['post_id']);

    if($post_id<=0){
        return;
    }

    ob_start();
    echo "<div id='amsa-voting-speaker-list-wrapper'>";
    echo $this->_display_speaker_list($post_id);
    echo "</div>";
    return ob_get_clean();
   
}

public function _display_speaker_list($post_id){
    ob_start();

    $user_id = get_current_user_id();

    echo('<div id="amsa-voting-speaker-table">');
    echo '<table>';
    echo '<tr><th>#</th><th>Name</th><th></th></tr>';

    // Query the speaker lists
    $speaker_user_ids = get_post_meta($post_id, '_speaker_list', true);
    if($speaker_user_ids){
        foreach($speaker_user_ids as $index => $speaker_user_id){
            $user_info = get_userdata($speaker_user_id);
            ?>
            <tr>
            <td><?php echo $index+1 ?></td>
            <td><?php echo esc_html($user_info->display_name) ?></td>
            <td>
            <?php 
             if (current_user_can('edit_posts') || $user_id == $speaker_user_id){
                ?><td><button class="speaker-removal-button" data-post-id="<?php echo $post_id ?>" data-speaker-user-id="<?php echo $speaker_user_id ?>">&#x2716;</button></td><?php
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
        <form id="nominate-speaker-form">
        <?php 
        error_log($user_id);
        error_log(print_r($speaker_user_ids,true));
        error_log(print_r(in_array($user_id, $speaker_user_ids),true));


        if(in_array($user_id, $speaker_user_ids)){
            ?>
            <input type="hidden" name="action" value="retract_nomination">
            <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
            <input type="hidden" name="speaker_user_id" value="<?php echo get_current_user_id()?>">
            <input type="submit" value="Remove yourself from the list">
            <?php
        }else{
            ?>
            <input type="hidden" name="action" value="nominate_speaker">
            <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
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
    if(isset($_POST['post_id'])){
        $post_id = intval($_POST['post_id']);
        $user_id = get_current_user_id();

        $speaker_list = get_post_meta($post_id, '_speaker_list', true);
        $speaker_list[]=$user_id;

        update_post_meta($post_id, '_speaker_list', $speaker_list);

        wp_send_json_success(['message'=>'Nomination added.','rendered_content'=>$this->_display_speaker_list($post_id)]);
    }else{
        wp_send_json_error('Error adding nomination.');

    }
    wp_die();
    
}

public function handle_retract_nomination() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to retract your nomination.');
    }
    check_ajax_referer($this->plugin_name.'-nonce', 'nonce');
    if(isset($_POST['post_id']) && isset($_POST['speaker_user_id'])){

        $post_id = intval($_POST['post_id']);
        $speaker_user_id = intval($_POST['speaker_user_id']);

        if (get_current_user_id() != $speaker_user_id && !current_user_can('edit_posts')) {
            wp_send_json_error('You do not have permission to retract this nomination.');
        }

        $speaker_list = get_post_meta($post_id, '_speaker_list', true);

        if(($key = array_search($speaker_user_id, $speaker_list))!==false){
            unset($speaker_list[$key]);
            update_post_meta($post_id, '_speaker_list', $speaker_list);

            wp_send_json_success(['message'=>'Nomination retracted','rendered_content'=>$this->_display_speaker_list($post_id)]);


        }else{
            wp_send_json_error('No such speaker found');
        }
    }else{
        wp_send_json_error('Error removing nomination.');

    }
    wp_die();
}

}
