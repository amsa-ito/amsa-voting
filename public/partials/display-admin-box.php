<?php
/**
 * This partial expects the following variables from the outer scope:
 *
 * @param string $poll_status The current status of the poll (e.g., 'open' or 'closed').
 * @param int $post_id The ID of the post associated with the poll.
 */

$button_text = ($poll_status === 'open') ? 'Close Voting' : 'Open Voting';
?>
    <p>This is visible to you because you have enough admin rights</p>
    <form id="admin-toggle-poll-status"data-post_id="<?php echo esc_attr($post_id); ?>">
        <button type="button" id="poll_status_change" name="poll_status_change" value=<?php echo $poll_status ?>><?php echo (esc_html($button_text))?></button>
    </form>