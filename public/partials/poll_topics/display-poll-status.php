<?php
// @param $poll_status
$status_display="";
switch($poll_status) {
    case 'open':
        $status_display = 'The poll is currently open.';
        break;
    case 'closed':
        $status_display = 'The poll is now closed.';
        break;
    case 'unvoted':
        $status_display = 'The poll is not open yet';
        break;
    default:
    $status_display = 'Poll status is unknown.';
}
?>
<div class="poll-status-display poll-status-<?php echo $poll_status ?>"><?php echo $status_display ?></div>