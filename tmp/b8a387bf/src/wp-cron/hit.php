<?php
/**
 * Get the blog url from $_GET
 * curl_init to the blog url
 */

$blog = $_REQUEST['blog'];

$ch = curl_init($blog);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
$info = curl_getinfo($ch);
