<?php
/**
 * This partial expects the following variables from the outer scope:
 *
 * @param bool $representatives_only Whether voting is open to AMSA representatives only.
 * @param bool $anonymous_voting Whether voting is anonymous or not.
 * @param string $voting_threshold The threshold required for passing the poll (e.g., simple majority).
 * @param bool $institution_weighted Whether votes are institutionally weighted or not.
 */
?>

<div class="amsa-voting-poll-settings">
    <h4>Poll Settings</h4>
    <ul>
        <li><?php echo ($representatives_only) ? 'AMSA Reps only' : 'Voting open to all'; ?></li>
        <li><?php echo ($anonymous_voting) ? 'Anonymous' : 'Non-anonymous'; ?></li>
        <li><?php echo ('Requires '.  ($voting_threshold === 'simple_majority') ? '½ simple majority' : 'super majority'); ?></li>
        <li><?php echo ($institution_weighted) ? 'Institutional-weighted votes' : 'Votes not weighted'; ?></li>
    </ul>
</div>