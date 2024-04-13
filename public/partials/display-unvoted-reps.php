<h4>AMSA Reps not voted:</h4>
<?php
if($unvoted_reps){
    echo("<ul>");
    foreach($unvoted_reps as $user_id){
        $university_meta = get_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
        $university_display = !empty($university_meta) ? "{$university_meta}" : 'No University logged with '.get_userdata($user_id)->username;
    
        echo("<li>".$university_display."</li>");
    }
    echo("</ul>");
}else{
    echo("All voted!!");
}

?>
