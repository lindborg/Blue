<?php

function blue_api_pjax() {
	wp_enqueue_script(
		'blue-api-pjax',
		plugins_url( 'pjax.js' , __FILE__),
		array('jquery')
	);
	
	wp_register_style('blue-api-pjax', plugins_url('pjax.css', __FILE__));
	wp_enqueue_style('blue-api-pjax');
}

#add_action('wp_enqueue_scripts', 'blue_api_pjax');