<?php
/**
 * This partial expects the following variable from the outer scope:
 *
 * @param array $votes An associative array containing the counts of votes categorized by type (e.g., 'for', 'against', 'abstain').
 * @param array $users_per_vote associative array containing which user id voted for, NULL if it is anonymous voting
 * @param int $is_institutional_weighted
 * @param int $is_anonymous
 * @param int $is_council_master
 */

 function amsa_voting_display_result_card($vote_type_label, $key, $votes, $users_per_vote, $is_anonymous){
    ?>
    <div class="amsa-voting-result-col">
    <div class="amsa-voting-card amsa-voting-card-<?php echo str_replace(' ', '-', $vote_type_label)?>">
        <div class="amsa-voting-card-header">
        <h5 class="amsa-voting-card-title"><?php echo($vote_type_label.': '. $votes[$key]) ?></h5>
        </div>
        <div class="amsa-voting-voted-users">	
            <?php 
                if($is_anonymous && !$is_council_master){
                    echo'<p>Names hidden</p>';
                }else{
                    if (isset($users_per_vote[$key]) && is_array($users_per_vote[$key])) {
                    foreach($users_per_vote[$key] as $user_id){
                        $user = get_userdata($user_id);
                        if ($user) {
                            // Determine what to display: Display Name or User Login
                            $display_name = $user->display_name ? $user->display_name : $user->username;
                            
                            // Get user meta for _wc_memberships_profile_field_university
                            $university_meta = get_user_meta($user_id, '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug'), true);
                            
                            // Check if the university meta is not empty
                            $university_display = !empty($university_meta) ? " ({$university_meta})" : ' (No University logged with account)';
                            
                            // Display the information
                            echo('<p>'.htmlspecialchars($display_name) . $university_display . "</p>");
                        }
                    }
                }
            }

            ?>		
        </div>
    </div>
    </div>
    <?php
 }
?>

<div class="amsa-voting-result-row">
    
    <h4>Total ballots cast: <?php echo array_sum(array_map('count', $users_per_vote));?></h4>
    <?php
    if($is_institutional_weighted){
        echo("<h4>Total weighted votes: ".($is_council_master ? array_sum($votes):"Hidden")."</h4>");
    }

    
    amsa_voting_display_result_card('Votes for', 'for', $votes, $users_per_vote, $is_anonymous);
    amsa_voting_display_result_card('Votes against', 'against', $votes, $users_per_vote, $is_anonymous); 
    amsa_voting_display_result_card('Abstentions', 'abstain', $votes, $users_per_vote, $is_anonymous); 
    ?>
    
</div>