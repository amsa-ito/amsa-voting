<?php
/**
 * This partial expects $current_user variable from the outer scope.
 *
 * @param WP_User $current_user The current user object.
 */
if ($current_user->ID) {
    $display_name = $current_user->display_name ? $current_user->display_name : $current_user->username;
    $university = get_user_meta($current_user->ID, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
    $user_role = prettify_role_names($current_user->roles);

    ?>
    <div class="already-logged-in-message">
        <p>You are currently logged in as <?php echo $display_name ?> (<?php echo($user_role.", ".($university ? $university : "No University Registered"))?>)</p>
    </div>
    <?php
} else {
    ?>
    <div class="amsa-voting-login-message">
        You need to be logged in to be eligible for voting
    </div>
    <div class="woocommerce">
        <?php wc_get_template('myaccount/form-login.php'); ?>
    </div>
    <?php
}
?>