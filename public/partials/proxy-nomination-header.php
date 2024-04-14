<?php
/**
 * This partial expects the following variable from the outer scope:
 *
 * @param int  $current_proxy_id int user id of proxy
 * @param int $current_principal_ids array of user id's corresponding to their principals
 * @param int $post_id
 * 

 */

if($current_proxy_id>0){
    $proxy_university = get_user_meta($current_proxy_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
    $proxy_user_object = get_userdata($current_proxy_id);
    $university_display=esc_html($proxy_university ? $proxy_university : "No University Registered");
    $current_proxy_display= ($proxy_user_object->display_name ? $proxy_user_object->display_name : $proxy_user_object->username). " (".$university_display.") currently holds your proxy vote";
}else{
    $current_proxy_display='You haven\'t nominated a proxy';
}
if(!$current_principal_ids){
    ?>
    <p>Current Proxy: <?php echo esc_html($current_proxy_display) ?></p>
    <?php if($current_proxy_id>0){
        ?>
        <button data-post-id="<?php echo esc_attr($post_id); ?>" id="retract-proxy-button">Retract your proxy</button>
        <?
    }else{
        ?>
        <button data-post-id="<?php echo esc_attr($post_id); ?>" id="display-proxy-table-button">Look for proxy</button>
        <?php
    }
}

if($current_principal_ids){
    ?>
    <h4>Current Proxies you hold:</h4>
    <ul>
    <?php
    foreach($current_principal_ids as $user_id){
        $user = get_userdata($user_id);
        if ($user) {
            // Determine what to display: Display Name or User Login
            $display_name = $user->display_name ? $user->display_name : $user->username;
            $user_role = prettify_role_names($user->roles);
            
            // Get user meta for _wc_memberships_profile_field_university
            $university_meta = get_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
            
            // Check if the university meta is not empty
            $university_display = !empty($university_meta) ? $university_meta : 'No University Registered';
            
            // Display the information
            echo('<li>'.htmlspecialchars($display_name) ." (".$user_role.", ".$university_display . ")</li>");
        }
    }
    echo '</ul>';
}
?>