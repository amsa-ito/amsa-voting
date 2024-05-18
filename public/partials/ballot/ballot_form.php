<?php
/**
 * 
 * param post_id
 * param candidates
 */

if (empty($candidates) || !is_array($candidates)) {
    ?>
            <p>No candidates found.</p>
    <?php
}else{
    shuffle($candidates);
    $candidate_count = count($candidates);
    ?>
    <form method="post" action="" id="ballot_form">
    <h2>Select Your Preferences</h2>

    <?php
        foreach ($candidates as $candidate) {
            ?>
            <label for="candidate_<?php echo esc_attr($candidate)?>"><?php echo esc_html($candidate) ?></label>
            <select name="candidate_preference[]" id="candidate_<?php echo esc_attr($candidate) ?>" class="candidate-select">
            <option value="">Select preference</option>
            <?php
            for ($i = 1; $i <= $candidate_count; $i++) {
                echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
            }
            echo '</select>';
            echo '<br>';
        }
    ?>
    <input type="hidden" name="post_id" value="<?php echo $post_id ?>">
    <p><input type="submit" name="submit_ballot" value="Submit Ballot"></p>
    </form>
    <?php
}
