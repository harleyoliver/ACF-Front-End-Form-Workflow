<?php
/**
 * Plugin Name: ACF Front-End Form Workflow
 * Description: An approvals proccess for submitted ACF Front-End Forms
 * Version: 1.0
 * Author: Harley Oliver
 * Author URI: https://au.linkedin.com/in/harleyoliver
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Basic security, prevents file from being loaded directly.
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );


/**
 * Register the Approved and Rejected Post Status'
 */
function custom_post_status() {
	register_post_status( 'rejected', array(
		'label'                     => _x( 'Rejected', 'post' ),
		'public'                    => false,
		'show_in_admin_all_list'    => false,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>' ),
		)
	);
	register_post_status( 'approved', array(
		'label'                     => _x( 'Approved', 'post' ),
		'public'                    => true,
		'show_in_admin_all_list'    => false,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>' ),
		)
	);
}
add_action( 'init', 'custom_post_status' );

// Use jQuery to hide the Minor Publishing Tools
function wpb_append_post_status_list(){
	global $post;
	if( $post->post_type == 'gift_benefit' ){
		echo '
		<script>
			jQuery(document).ready(function($){
				$("#minor-publishing").hide();
			});
		</script>
		';
	}
}
add_action('admin_footer-post.php', 'wpb_append_post_status_list');


/**
 * Change the Publish Button value to Save
 */
add_filter( 'gettext', 'change_publish_button', 10, 2 );
function change_publish_button( $translation, $text ) {
	if ( 'gift_benefit' == get_post_type())
	if ( $text == 'Publish' )
	return 'Save'; return $translation;
}


/**
 * Adds The Recipient Name as the Post Title, and send a notification email when the front end form is submitted.
 */
function my_pre_save_post( $post_id ) {

    $date_received = new DateTime($_POST['acf']['field_5b14b3a8d8164']);
    $date_received = $date_received->format('d-m-Y');
    $recipient_name = $_POST['acf']['field_5b14b3ec0a786'];
    $recipient_email = $_POST['acf']['field_5b15d2014bd45'];
    $recipient_position = $_POST['acf']['field_5b14b3f80a787'];
    $recipient_department = $_POST['acf']['field_5b14b40e0a788'];
    $sender_name = $_POST['acf']['field_5b14b4260a789'];
    $sender_position = $_POST['acf']['field_5b14b4360a78a'];
    $sender_organisation = $_POST['acf']['field_5b14b4470a78b'];
    $gift_description = $_POST['acf']['field_5b14b4620a78c'];
    $gift_reason = $_POST['acf']['field_5b14b47c0a78d'];
    $gift_value = $_POST['acf']['field_5b14b4910a78e'];
    $first_gift = $_POST['acf']['field_5b14b59707bb3'];
    $cumulative_value = $_POST['acf']['field_5b14b5eb07bb4'];
    $executive_directors_email = $_POST['acf']['field_5b1615b78bdaa'];

	$post = array(
		'ID' => $post_id,
		'post_title' => $recipient_name,
	); 

    wp_update_post($post); 

	$to = $executive_directors_email;
	$subject = 'New Benefit/Gift Notification from ' . $recipient_name;
	$body = 'Date Gift or Benefit Received: ' . $date_received . '<br>';
	$body .= 'Recipient’s Name: ' . $recipient_name . '<br>';
	$body .= 'Recipient’s Name: ' . $recipient_email . '<br>';
	$body .= 'Recipient’s Position: ' . $recipient_position . '<br>';
	$body .= 'Recipient’s Department: ' . $recipient_department . '<br>';
	$body .= 'Received From: ' . $sender_name . '<br>';
	$body .= 'Sender’s Position: ' . $sender_position . '<br>';
	$body .= 'Sender’s Organisation: ' . $sender_organisation . '<br>';
	$body .= 'Description of Gift/Benefit: ' . $gift_description . '<br>';
	$body .= 'Reason for Gift/Benefit: ' . $gift_reason . '<br>';
	$body .= 'Estimated Value of Gift/Benefit: ' . $gift_value . '<br>';
	if( '' ==  $cumulative_value ) {
		$body .= 'First time offer: Yes<br>';
	} else {
		$body .= 'First time offer: No<br>';
		$body .= 'Cumulative value of gifts offered by this individual within the last 12 months: ' . $cumulative_value . '<br>';
	}
	$body .= '<br><a href="' . home_url() . '/wp-admin/post.php?post=' . $post_id . '&action=edit">Review Gift/Benefit Notification</a>';
	$headers = array('Content-Type: text/html; charset=UTF-8');
	
	wp_mail( $to, $subject, $body, $headers );

	return $post_id;
}
add_filter('acf/pre_save_post' , 'my_pre_save_post' );


/**
 * Changes the Post status to Approved or Rejected depending on the ACF Radio Button's value
 */
function my_acf_save_post( $post_id ) {

    $value = get_field('gift_decision');

  	$my_post = array(
		'ID'           => $post_id,
		'post_status'	=> $value,
	);

	wp_update_post( $my_post );

	return $post_id;
}
add_action('acf/save_post', 'my_acf_save_post', 20);


/**
 * Sends a notification upon review
 */
function post_status_update( $new_status, $old_status, $post ) {

    $recipient_email = $_POST['acf']['field_5b15d2014bd45'];
    $gift_decision = $_POST['acf']['field_5b14b65e04d0b'];
    $rejection_reason = $_POST['acf']['field_5b14b7f9f2616'];
    $admin_name = $_POST['acf']['field_5b14b6a704d0c'];
    $admin_position = $_POST['acf']['field_5b14b6de04d0d'];
    $reviewed_date = new DateTime($_POST['acf']['field_5b14b6f104d0e']);
    $reviewed_date = $reviewed_date->format('d-m-Y');
    
    if ( $new_status == 'rejected' ) {
        $to = $recipient_email;
        $subject = 'Your Benefit/Gift Notification has been Reviewed';
        $body = 'Gift/Benefit Status: Rejected<br>';
        $body .= 'Rejection Reason: ' . $rejection_reason . '<br>';
        $body .= 'Reviewer: ' . $admin_name . '<br>';
        $body .= 'Position: ' . $admin_position . '<br>';
        $body .= 'Reviewed Date: ' . $reviewed_date . '<br>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
            
        wp_mail( $to, $subject, $body, $headers );
    }

    if ( $new_status == 'approved' ) {
        $to = $recipient_email;
        $subject = 'Your Benefit/Gift Notification has been Reviewed';
        $body = 'Gift/Benefit Status: Approved<br>';
        $body .= 'Reviewer: ' . $admin_name . '<br>';
        $body .= 'Position: ' . $admin_position . '<br>';
        $body .= 'Reviewed Date: ' . $reviewed_date . '<br>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
            
        wp_mail( $to, $subject, $body, $headers );
    }
}
add_action( 'transition_post_status', 'post_status_update', 10, 3 );
