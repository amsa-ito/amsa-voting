<?php
/**
 * This partial expects the following variables from the outer scope:
 *
 * @param int $post_id The ID of the post associated with the poll.
 */
?>



<div class='amsa-voting-result'>
<h4>Result: <?php 
    $poll_result = get_post_meta( $post_id, '_voting_outcome', true );
    if($poll_result==0){
        echo('-');
    }elseif($poll_result==1){
        echo('<strong style="color:green">Carried</strong>');
    }elseif($poll_result==2){
        echo('<strong style="color:red">Lost</strong>');
    }elseif($poll_result==3){
        echo('<strong style="color:blue">Tied</strong>');
    }else{
        echo('<strong>There is something wrong with the code (_voting_outcome should not be anything other than 0,1,2,3)</strong>');
    }
?></h4>
<h4>Poll closed at: <?php echo(date('Y-m-d H:i:s', get_post_meta($post_id, '_poll_closed_timestamp', true))) ?></h4> 
</div>