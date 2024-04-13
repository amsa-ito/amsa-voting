<?php
    $post_title = get_the_title($post_id);
    $post_content = get_post_field('post_content', $post_id);
?>
 
<h2><?php echo esc_html($post_title)?></h2>
<?php
if (!empty($post_content)){
    echo '<p>' . esc_html($post_content) . '</p>';
}
<?php
    $post_title = get_the_title($post_id);
    $post_content = get_post_field('post_content', $post_id);
?>
 
<h2><?php echo esc_html($post_title)?></h2>
<?php
if (!empty($post_content)){
    echo '<p>' . esc_html($post_content) . '</p>';
}