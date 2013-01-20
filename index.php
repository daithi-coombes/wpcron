<?php

/*
  Plugin Name: Wordpress Cron
  Plugin URI: http://david-coombes.com
  Description: Automatically run cron jobs
  Author: Daithi Coombes
  Version: 0.1
  Author URI: http://david-coombes.com
 */

/**
 * The CCC (Control Command Center) hits admin-ajax.php This 
 * page then requests the homepage using phpCurl which thus triggers the 
 * wordpress crons and scheduled posts. To ensure that the scheduled posts are 
 * definitely sent the hit.php page should be called once every 10 mins after 
 * its due for one hour.
 * 
 * The reason the CCC doesn't directly hit the homepage is to save bandwidth.
 * Hitting the homepage will mean download the page to the CCC, where as hitting
 * the admin-ajax.php allows wpcron to run checks to make sure post went out.
 * 
 * Scheduled Post's
 * 		plugin hooks into wordpress posts. When a post is set for a future date
 * 		the timestamp, key, blog_url are sent to the CCC. This is a default
 * 		feature of the plugin and requires no installation.
 * 
 * Custom crons
 * 		For developers custom crons can be set up. A script url, key, blog_url
 * 		and timestamp are sent to the CCC
 */
error_reporting(E_ALL);
ini_set('display_errors', 'on');

//globals
$wp_cron_ccc_server = "http://wp_cron.loc/ccc/setAlarm.php";
$wp_cron_key = "0akjdfha659374jsdfl732ol87fkLJH87LLSfjhLH";

//constants
define('WPCRON_DIR', dirname(__FILE__));
define('WPCRON_URL', 'http://wp_cron.loc/ccc');


//includes
require_once( WPCRON_DIR .'/application/includes/debug.func.php');
require_once( WPCRON_DIR . '/application/includes/apache-log4php-2.3.0/Logger.php');

/**
 * Set up logger?
 */
Logger::configure( WPCRON_DIR . '/application/includes/Log4php.config.xml');
$logger = Logger::getLogger("main");
$logger->warn("this is a warning");


/**
 * actions
 */
add_action('save_post', 'wpcron_check_post', 10, 2);

/**
 * Hook for checking all posts, if post is set for the future then
 * wpcron_ccc_send() is called to send the curl request.
 *
 * @param integer $postID The post id
 * @param stdClass $post The post object
 * @return void 
 */
function wpcron_check_post($postID, $post) {

	wpcron_log("Checking post {$postID}..");
	
	/**
	 * make sure post is for future and not revision etc.
	 */
	if(
		(@$post->post_status!='future') ||
		(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
	) return;
	
	/**
	 * logging
	 * @deprecated
	 */
	$str = "";
	foreach($post as $key => $val)
		$str .= "\t{$key}\t\t\t=>\t{$val}\n";
	wpcron_log($str);
		
	/**
	 * if all is ok then send request.
	 */
	wpcron_ccc_send( $post->post_date_gmt );
}

/**
 * Log a string. Log file is at WP_CRON_DIR/log.txt
 *
 * @param string $str 
 */
function wpcron_log($str) {

	$log = WPCRON_DIR . "/log.txt";
	$fp = fopen($log, "a+");
	fwrite($fp, time() . " - {$str}\n");
	fclose($fp);
}

/**
 * Send an alarm request to the ccc server.
 *
 * @global string $key
 * @param string $date The post_gmt date from the post object
 */
function wpcron_ccc_send( $date ) {

	if(empty($date)) return;
	
	/**
	 * Logging
	 * @deprecated
	 */
	wpcron_log("sending post for {$date}");
	
	/**
	 * vars
	 */
	global $wp_cron_key;
	global $wp_cron_ccc_server;
	$blog = get_bloginfo('wpurl');
	$now = time();
	$ch = curl_init($wp_cron_ccc_server);
	
	/**
	 * send request
	 */
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	$data = array(
		'blog' => $blog,
		'now' => $now,
		'alarm' => $date,
		'key' => $key
	);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	wpcron_log("response => \n" . $output);
}