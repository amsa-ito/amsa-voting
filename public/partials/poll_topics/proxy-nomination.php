<?php
/**
 * This partial expects the following variable from the outer scope:
 *
 * @param array $attendee_list array of user ids
 * @param int $post_id
 * 

 */
?>

<input type="text" id="amsa-voting-search-proxy"  placeholder="Search for names..">
<div class="amsa-voting-proxy-table-wrapper ">
<table id="amsa-voting-proxy-table">
<thead><tr><th>Display Name</th><th>User Role</th><th>University</th><th>Action</th></tr></thead>
<tbody>
<?php
foreach ($attendee_list as $user_id) {
    // Get user data
    $user_info = get_userdata($user_id);
    if ($user_info) {
        $display_name = $user_info->display_name;
        $user_role = prettify_role_names($user_info->roles);
        
        $university = get_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true)
        ?>
        <tr>
            <td><?php echo esc_html($display_name); ?></td>
            <td><?php echo esc_html($user_role); ?></td>
            <td><?php echo esc_html($university ? $university : "No University Registered"); ?></td>

            <td>
                <button class="proxy-nominate-button" data-post-id="<?php echo esc_attr($post_id); ?>" data-user-id="<?php echo esc_attr($user_id); ?>">Nominate as proxy</button>
            </td>
        </tr>
        <?php
    }
}
?>
</tbody>
</table>
</div>