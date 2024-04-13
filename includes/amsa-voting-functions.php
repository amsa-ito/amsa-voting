<?php

function get_event_attendees($event_id){
    $attendees_orm = tribe_attendees();
    $attendees_orm->where( 'event', $event_id )->where( 'rsvp_status__or_none', 'yes' );
    $user_ids=[];
    foreach($attendees_orm->all() as $attendee){
        $user_ids[]=$attendee->post_author;
    }
    return $user_ids;
}

function get_users_per_vote($post_id){
    $final_voted_users = get_post_meta($post_id, '_final_voted_users',true);
    if($final_voted_users){
        return $final_voted_users;
    }

    $retrieved_votes = get_post_meta($post_id, '_voted_users',true);
    $default_votes = array('for', 'against', 'abstain');
    $result=  array();
    foreach($default_votes as $vote_type){
        $vote_type_keys = array_keys(array_column($retrieved_votes, 'vote_value'), $vote_type);
        if(empty($vote_type_keys)){
            continue;
        }
        $flipped_vote_type_keys = array_flip($vote_type_keys);
        $keyed_users = array_keys($retrieved_votes);
        $result[$vote_type]=array_intersect_key($keyed_users, $flipped_vote_type_keys);
    }
   
    return $result;
}

function sanitise_votes($post_id){
    // Check if the post requires representative-only voting
    $representatives_only = get_post_meta($post_id, '_representatives_only', true);
    $retrieved_votes = get_post_meta($post_id, '_voted_users',true);

    // Only proceed if the voting is restricted to representatives
    if ($representatives_only) {
        // Iterate through each vote
        if($retrieved_votes){
            foreach ($retrieved_votes as $user_id => $vote_details) {
                if (!is_user_representing_amsa_rep($user_id)) {
                    // Remove vote if user is not eligible
                    unset($retrieved_votes[$user_id]);
                }
            }
            update_post_meta($post_id, '_voted_users', $retrieved_votes);
        }

    }
    return $retrieved_votes;

}


// process the _voted_users meta so that it is ['for'=sum_of_weights, 'away'=sum_of_weights, 'abstain'=sum_of_weights
function calculate_votes($post_id){
    $final_voted_numbers = get_post_meta($post_id, '_final_voted_numbers',true);
    if($final_voted_numbers ){
        return $final_voted_numbers;
    }
    if (!function_exists('helper_calculate_vote_type')) {
    function helper_calculate_vote_type($vote_type, $votes_array, $post_id){
        $vote_type_keys = array_keys(array_column($votes_array, 'vote_value'), $vote_type);;
        if(count($vote_type_keys)===0){
            return 0;
        }
        $flipped_vote_type_keys = array_flip($vote_type_keys);

        $all_weights=get_all_weights(array_intersect_key(array_keys($votes_array), $flipped_vote_type_keys),$post_id);

        return $all_weights;
        }
    }
    $retrieved_votes = sanitise_votes($post_id);
    // it is in the form of array(user_id=>[vote_value=>])
    
    $calculated_votes = array();
    foreach(array('for','against','abstain') as $vote_type){
        $calculated_votes[$vote_type]=helper_calculate_vote_type($vote_type,$retrieved_votes, $post_id);
    }
    return $calculated_votes;
}

function get_all_weights($user_ids, $post_id){
    $combined_weights=[];
    if($user_ids){
        foreach($user_ids as $user_id){
            $weight = get_user_voting_weight($user_id, $post_id);
            $weight+=get_principal_weight($user_id, $post_id);
            
            $combined_weights[]=$weight;
        }
    }
    return array_sum($combined_weights);
}

// array of user_id's, for which the sum of the weights of the principals of each user is returned
function get_principal_weights($user_ids, $post_id){
    $principal_weights=array();
    if($user_ids){
        foreach($user_ids as $user_id){
            $principal_weights[]=get_principal_weight($user_id, $post_id);
        }
    }

    return array_sum($principal_weights);
}

function get_principal_weight($user_id, $post_id){
    $principals = get_user_meta($user_id, 'amsa_voting_principals', true);
    $weights=array();
    if($principals){
        foreach($principals as $principal_id){
            if($principal_id==$user_id){
                // prevent infinite recursion
                continue;
            }
            $weights[] = get_user_voting_weight($principal_id, $post_id);
        }
    }
    return array_sum($weights);
}

function get_user_voting_weight($user_id, $post_id){
    $user_roles = get_user_meta($user_id, 'wp_capabilities', true);
    if(array_key_exists('amsa_rep', $user_roles) && get_post_meta( $post_id, '_institution_weighted', true )){
        $weight=250 + get_default_members_behind_amsa_rep($user_id);        
    }else{
        $weight=1;
    }
    return $weight;
}

function get_default_members_behind_amsa_rep($user_id){
    $university_slug = '_wc_memberships_profile_field_'.get_option('amsa_voting_university_slug');
    $university = get_user_meta($user_id, $university_slug, true);
    $args= array(
                'meta_query' => 
            array(
                'relation' => 'AND',
                array(
                    'key' => $university_slug,
                    'value' => $university,
                    'compare' => '=',
                ),
                // don't get users even if same university that have actively nominated their rep - we'll count them later
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'amsa_voting_proxy',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'amsa_voting_proxy',
                        'value' => '',
                        'compare' => '=',
                    ),
                    // -1 is default setting, 0 means they've actively retracted their vote at some point
                    array(
                        'key' => 'amsa_voting_proxy',
                        'value' => 0,
                        'compare' => '<',
                    ),
                    array(
                        'key' => 'amsa_voting_proxy',
                        'value' => $user_id,
                        'compare' => '=',
                    ),

                ),
            ),
            'count_total' => true,
            'exclude'=>array($user_id)
    );
    // Create a new user query
    $user_query = new WP_User_Query($args);

    // Get the total number of users matching the query
    $user_count = $user_query->get_total();
    if($user_count){
        return $user_count;
    }
    return 0;
}

function nominate_proxy($proxy_id, $current_user_id){
    $principals=get_user_meta($current_user_id, 'amsa_voting_principals', true);
    if($principals){
        // they are representing someone else already, don't allow further proxying
        return;
    }

    update_user_meta($current_user_id, 'amsa_voting_proxy', $proxy_id);
        
    // update the principals behind the proxy, this should prevent the proxy nominating a proxy
    $principals=get_user_meta($proxy_id, 'amsa_voting_principals', true);
    error_log('164 principals:'.print_r($principals,true));
    
    if(!in_array($current_user_id, $principals, true)){
        $principals[]=$current_user_id;
        update_user_meta($proxy_id, 'amsa_voting_principals', $principals);
        error_log('updated principals');
    }
    error_log('post_nominate, user_id: '.$current_user_id.', principals: '.print_r(get_user_meta($proxy_id, 'amsa_voting_principals', true),true)." proxy_id: ".print_r(get_user_meta($proxy_id, 'amsa_voting_proxy', true),true));
}

function retract_proxy($current_user_id){
    $proxy_user_id = get_user_meta($current_user_id, 'amsa_voting_proxy', true);
    if($proxy_user_id==0){
        return;
    }
    $principals=get_user_meta($proxy_user_id, 'amsa_voting_principals', true);
    if(($key = array_search($current_user_id, $principals))!==false){
        unset($principals[$key]);
        update_user_meta($proxy_user_id, 'amsa_voting_principals', $principals);
    }
    update_user_meta($current_user_id, 'amsa_voting_proxy', 0);

}

function get_amsa_reps(){
    $args = [
        'role'    => 'amsa_rep',
        'fields'  => 'ID', // Retrieve only the user IDs for efficiency
    ];
    $user_query = new WP_User_Query($args);
    return $user_query->get_results();


}

function is_user_representing_amsa_rep($user_id){
    $current_user_roles = get_user_meta($user_id, 'wp_capabilities', true);
    if(array_key_exists('amsa_rep', $current_user_roles)){
        return true;
    }

    $principals = get_user_meta($user_id, 'amsa_voting_principals', true);
    $is_user_proxying_for_rep = false;
    if($principals){
        foreach($principals as $principal){
            $capabilities = get_user_meta($principal, 'wp_capabilities', true);
            if(!$capabilities){
                continue;
            }
            if(array_key_exists('amsa_rep', get_user_meta($principal, 'wp_capabilities', true))){
                $is_user_proxying_for_rep=true;
                break;
            }
        }
    }
    return $is_user_proxying_for_rep;
}

function prettify_role_names($roles){
    $display_role = implode(', ', $roles);
    $display_role = str_replace('subscriber', 'AMSA Member', $display_role);
    $display_role = str_replace('amsa_rep', 'AMSA Representative', $display_role);
    $display_role = str_replace('customer, ', '', $display_role);
    $display_role = str_replace(', customer', '', $display_role);
    $display_role = str_replace('customer', '', $display_role);
    return $display_role;
}