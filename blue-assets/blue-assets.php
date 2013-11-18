<?
/*
Plugin Name: Blue Assets
Description: Framework base files and optimization. SASS support.
Version: 1.0.0
License: GPLv2 or later
*/

add_action('wp_enqueue_scripts', function() {
	// Bootstrap Assets
	wp_enqueue_script(
		'blue-assets-bootstrap',
		plugins_url('includes/bootstrap/js/bootstrap.min.js' , __FILE__),
		array('jquery')
	);
	
	wp_register_style('blue-assets-bootstrap', plugins_url('includes/bootstrap/css/bootstrap.min.css', __FILE__));
	wp_enqueue_style('blue-assets-bootstrap');
	
	// Theme Assets
	wp_enqueue_script(
		'blue-assets-theme',
		'/js/',
		array('blue-assets-bootstrap')
	);
	wp_register_style(
		'blue-assets-theme',
		'/css/',
		array('blue-assets-bootstrap')
	);
	wp_enqueue_style('blue-assets-theme');
});

add_action('plugins_loaded', function() {
	$content_type = array(
		'css'	=>	'text/css',
		'js'	=>	'text/javascript',
	);
	
	$bundle = strtolower(trim(preg_replace('/\?[^\?]*/','',$_SERVER['REQUEST_URI']),'/'));
		
	if($content_type[$bundle] && is_dir($directory = get_stylesheet_directory() . '/'.$bundle)) {
		require_once __DIR__.'/includes/scss.php';
		
		$files = scandir($directory);
		
		foreach($files as $file) {
			if(preg_match('/^\w.*\.'.$bundle.'$/i', $file)) {
				switch ($bundle) {
					case 'css':
						if(!$scss) $scss = new scssc();
						$content .= $scss->compile(file_get_contents($directory . '/' . $file))."\n";
						break;
					default:
						$content .= file_get_contents($directory . '/' . $file)."\n";
						break;
				}
			}
		}
		
		header('content-type: '.$content_type[$bundle]);
		die($content);
	}
});