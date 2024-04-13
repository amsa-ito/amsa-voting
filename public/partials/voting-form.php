<?php
/**
 * This partial expects the following variable from the outer scope:
 *
 * @param int $post_id The ID of the post associated with the poll.
 * @param string $existing_vote One of (for, against, abstain)
 */
?>

<form id="amsa_voting_form" data-post_id="<?php echo esc_attr($post_id); ?>">
    <!-- <form method="post" action=""> -->
        <p><input type="radio" name="vote" value="for" <?php checked($existing_vote, 'for' )?>> For</p>
        <p><input type="radio" name="vote" value="against" <?php checked($existing_vote, 'against' )?>> Against</p>
        <p><input type="radio" name="vote" value="abstain" <?php checked($existing_vote, 'abstain' )?>> Abstain</p>
        <p><input type="button" name="submit_vote" value="Vote" id="submit_vote"></p>
    </form>