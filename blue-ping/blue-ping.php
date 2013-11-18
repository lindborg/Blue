<?
/*
Plugin Name: Blue Ping
Description: Uptime and server performance monitoring.
Version: 1.0.0
License: GPLv2 or later
*/

add_action('plugins_loaded', function() {
	$hook = strtolower(trim(preg_replace('/\?[^\?]*/','',$_SERVER['REQUEST_URI']),'/'));
		
	if($hook == 'ping') {
		$starttime = (microtime(true)*1000);
		$content = file_get_contents('http://'.$_SERVER['HTTP_HOST'].'/');
		$responsetime = (microtime(true)*1000)-$starttime;
		
		$status = 'OK';
		
		header('content-type: text/xml; charset=UTF-8');
		
		die('<?xml version="1.0" encoding="UTF-8"?><pingdom_http_custom_check><status>'.$status.'</status><response_time>'.number_format($responsetime,3,'.','').'</response_time></pingdom_http_custom_check>');
	}
});