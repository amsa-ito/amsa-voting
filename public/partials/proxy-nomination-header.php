<?php
/**
 * This partial expects the following variable from the outer scope:
 *
 * @param int  $current_proxy_id int user id of proxy
 * @param int $post_id
 * 

 */
$current_proxy_name = ($current_proxy_id) ? get_userdata($current_proxy_id)->display_name. " (".get_user_meta($current_proxy_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true).") currently holds your proxy vote": 'You haven\'t nominated a proxy';
?>
<p>Current Proxy: <?php echo esc_html($current_proxy_name) ?></p>
<?php if($current_proxy_id){
    ?>
    <button data-post-id="<?php echo esc_attr($post_id); ?>" id="retract-proxy-button">Retract your proxy</button>
    <?
}else{
    ?>
    <button data-post-id="<?php echo esc_attr($post_id); ?>" id="display-proxy-table-button">Look for proxy</button>
    <?php
}
?>