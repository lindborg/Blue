<?
/*
Plugin Name: Blue API
Description: Optimized loadtime and external hooks. JSON performance. Mustache and QueryPath server support. PJAX client support.
Version: 1.0.0
License: GPLv2 or later
*/
	require_once __DIR__.'/includes/Mustache/Autoloader.php';
	require_once __DIR__.'/includes/QueryPath/qp.php';
	require_once __DIR__.'/includes/Pjax/pjax.php';
	
	define('BLUE_API_BINDINGS_FOLDER_NAME','bindings');
		
	// HOOKS
	
	add_action('wp_loaded', function() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	});
	add_filter('template_include', function($template) {
		global $wp_query;

		if($wp_query->query_vars['output'] && ($api = blue_api($output = $wp_query->query_vars['output']))) {
			switch($output) {
				case 'json':
					header('content-type: application/json');
					nocache_headers();
					break;
				case 'jsonp':
					header('content-type: application/javascript');
					nocache_headers();
					break;
				case 'html':
					header('content-type: text/html');
					header('pragma: cache');
					break;
			}
			
			die($api);
		}
		return $template;
	});
	add_filter('rewrite_rules_array', function($rules) {
		$newrules = array();
		
		foreach($rules as $rule=>$rewrite) {
			$rule = rtrim($rule,'?$');
			foreach(array('json','jsonp','html') as $extension) {
				$newrules[$rule.$extension.'/?$'] = $rewrite.'&output='.$extension;
				$newrules[$extension.'?$'] = $rewrite.'&output='.$extension;
			}
		}
										
		return $newrules+$rules;
	});
	add_filter('query_vars', function($public_query_vars) {
		$public_query_vars[] = 'output';
		return $public_query_vars;
	});
	add_action('wp_enqueue_scripts', function() {
		wp_enqueue_script(
			'hogan',
			plugins_url('includes/Hogan/hogan-2.0.0.js', __FILE__)
		);
	});
	
	// LOAD
	
	function blue_api($output) {
		if($view = blue_api_view($output)) {
			return $view;
		}
		if($model = blue_api_model($output)) {
			return $model;
		}
	}
	function blue_api_view($output='html') {
		global $wp_query;
		if(is_string($template = blue_api_template($output))) {
			switch($output) {
				case 'html':
					$view_content = @qp(blue_api_view_format(file_get_contents($template)));
					$opendir = opendir(blue_api_template_directory().'/queries');
								
					while($readdir = readdir($opendir)) {
						if(strpos($readdir,'._') !== 0 && preg_match('/\.'.$output.'$/iu', $readdir)) {
							$query_selector = rawurldecode(preg_replace('/\.[a-z]+$/iu','',$readdir));
							$query_content = blue_api_view_format(file_get_contents(blue_api_template_directory().'/queries/'.$readdir));
							
							if($view_content->find($query_selector)->size() > 0) {
								if(is_file($query_data_file = blue_api_template_directory().'/queries/'.preg_replace('/\.[a-z]+$/','.php',$readdir))) {
									if($query_data = require $query_data_file) {
										$query_content = blue_api_render_view_model($query_content,$query_data);
									}
								}
								$view_content->find($query_selector)->append($query_content);
							}
							
						}
					}
					return $view_content->find('body')->innerHTML();
					break;
				default:
					return file_get_contents($template);
					break;
			}
		}
		
		return false;
	}
	function blue_api_view_format($view) {
		return str_replace('><', '><!--#--><',$view);
	}
	function blue_api_model($output='json') {
		global $wp_query;
		
		$default_data = blue_api_model_default_data();
		
		foreach(array('process','method','query') as $method) {
			if(function_exists('blue_api_model_'.$method)) {
				if(!is_blue_api_error($data = call_user_func('blue_api_model_'.$method,$default_data))) {
					switch($output) {
						case 'json':
							$json .= json_encode($data, JSON_PRETTY_PRINT);
							return apply_filters('blue_api_model_'.$output,$json);
						case 'jsonp':
							if($_GET['callback']) $json .= $_GET['callback'].'(';
							$json .= json_encode($data, JSON_PRETTY_PRINT);
							if($_GET['callback']) $json .= ');';
							return apply_filters('blue_api_model_'.$output,$json);
						default:
							return $data;
					}
				}
			}
		}
	}
	function blue_api_model_default_data() {
		$data = array(
			'meta' => array(
				'title'			=>	wp_title('|', false, 'right') . get_bloginfo('name'),
				'permalink'		=>	get_permalink(),
				'body_class'	=>	implode(' ', get_body_class()),
			)
		);
		
		if(is_category()) {
			$data['category'] = get_category(get_query_var('cat'));
		}
		
		return apply_filters('blue_api_model_default_data',$data);
	}
	function blue_api_render($output='html') {
		$view = blue_api_view($output);
		$model = blue_api_model(false);
		
		echo blue_api_render_view_model($view,$model);
	}
	function blue_api_render_view_model($view,$model) {
		Mustache_Autoloader::register();
		$mustache = new Mustache_Engine;
		return $mustache->render($view, $model);
	}
	
	// TEMPLATE
	
	function blue_api_template($output) {
		global $wp_query;
		
		$templates = array(
			'is_search'			=>	'search-{s}',
			'is_search'			=>	'search',
			'is_tax'			=>	'taxonomy-{tax_name}',
			'is_tax'			=>	'taxonomy',
			'is_front_page'		=>	'front-page',
			'is_home'			=>	'home',
			'is_attachment'		=>	'attachment',
			'is_single'			=>	'single-{post_name}',
			'is_single'			=>	'single',
			'is_page'			=>	'page-{page_name}',
			'is_page'			=>	'page',
			'is_category'		=>	'category-{category_name}',
			'is_category'		=>	'category',
			'is_tag'			=>	'tag-{tag_name}',
			'is_tag'			=>	'tag',
			'is_author'			=>	'author',
			'is_date'			=>	'date',
			'is_archive'		=>	'archive',
			'is_comments_popup'	=>	'comment',
			'is_paged'			=>	'paged',
		);
		
		foreach($templates as $validation_function=>$template) {
			if(call_user_func($validation_function)) {
				foreach($wp_query->query as $name=>$value) {
					$template = str_replace('{'.$name.'}',sanitize_title($value),$template);
				}
				if($template = blue_api_template_file_path($template.'.'.$output)) {
					return apply_filters('blue_api_template',$template);
				}
			}
		}

		if($template = blue_api_template_file_path(blue_api_template_uri().'.'.$output)) {
			return apply_filters('blue_api_template',$template);
		}
		
		if(is_404() && ($template = blue_api_template_file_path('404.'.$output))) {
			return apply_filters('blue_api_template',$template);
		}
		
		if($template = blue_api_template_file_path('index.'.$output)) {
			return apply_filters('blue_api_template',$template);
		}
	}
	function blue_api_template_file_path($file) {
		if(is_file($template = blue_api_template_directory().'/'.$file)) {
			return $template;
		}
		else if(is_file($template = __DIR__.'/'.BLUE_API_BINDINGS_FOLDER_NAME.'/.'.$file)) {
			return $template;
		}
	}
	function blue_api_template_uri() {
		$uri = preg_replace('/\?[^\?]*$/iu','',$_SERVER['REQUEST_URI']);
		$uri = trim($uri,'/');
		$uri = preg_replace('/\/(json|jsonp|html)$/iu','',$uri);
		return str_replace('/','-',$uri);
	}
	function blue_api_template_directory() {
		return get_template_directory().'/'.BLUE_API_BINDINGS_FOLDER_NAME;
	}
	
	// MODEL HANDLERS
	
	function blue_api_model_process($data) {
		if(is_string($template = blue_api_template('php'))) {
			return require $template;
		}
		return new Blue_API_Model_Error();
	}
	function blue_api_model_query($data) {
		global $wp_query;
		
		$data['posts'] = array();
		
		if($wp_query->have_posts()) {
			foreach($wp_query->posts as $post) {
				if($post = blue_api_model_query_process_post($post,$data)) {
					$data['posts'][] = $post;
				}
			}
			
		}
		
		return apply_filters('blue_api_model_query',$data);
	}
	function blue_api_model_query_process_post($post,$data) {
		unset($post->post_date_gmt);
		unset($post->post_status);
		unset($post->comment_status);
		unset($post->ping_status);
		unset($post->post_password);
		unset($post->to_ping);
		unset($post->pinged);
		unset($post->post_modified);
		unset($post->post_modified_gmt);
		unset($post->post_content_filtered);
		unset($post->guid);
		unset($post->post_type);
		unset($post->post_mime_type);
		unset($post->filter);
		unset($post->category_id);
		unset($post->category_slug);
		unset($post->category_name);
		unset($post->post_enddate);
		unset($post->comment_count);
		unset($post->format_content);
		
		$author = get_userdata($post->post_author)->data;
		$post->post_author = array(
			'ID'			=>	$author->ID,
			'display_name'	=>	$author->display_name,
			'user_email'	=>	$author->user_email,
		);
		
		foreach(get_taxonomies('','names') as $taxonomy) {
			if($terms = get_the_terms($post->ID,$taxonomy)) {
				$post->{$taxonomy} = $terms;
			}
		}
		
		/*
		foreach(get_post_meta($post->ID) as $name=>$meta) {
			$post->{$name} = current($meta);
		}
		*/
		
		$post->comments = get_comments('post_id='.$post->ID);
		
		if($post_thumbnail_id = get_post_thumbnail_id($post->ID)) {
			$post->image = array();
			foreach(array('thumbnail'=>false,'medium'=>false,'large'=>false,'full'=>false) as $size=>$values) {
				$attachment = wp_get_attachment_image_src($post_thumbnail_id,$size);
				$post->image[$size] = array(
					'src'		=>	$attachment[0],
					'width'		=>	$attachment[1],
					'height'	=>	$attachment[2],
				);
			}
		}
		
		$post->link = get_permalink($post->ID);
		
		return apply_filters('blue_api_model_query_process_post',$post,$data);
	}
	
	function blue_api_model_method($data) {
		$methodName = current(blue_api_arguments());
		
		if($methodName && function_exists($methodName)) {
			$arguments = $_GET?$_GET:array_slice(blue_api_arguments(),1);
			return call_user_func_array($methodName,$arguments);
		}
		return new Blue_API_Model_Error();
	}
	
	// UTILS
	
	function is_blue_api_error($data) {
		return is_a($data,'Blue_API_Model_Error');
	}
	function blue_api_request() {
		return '/'.preg_replace('/'.BLUE_API_CONTROLLER_REGEXP.'/iu','',$_SERVER['REQUEST_URI']);
	}
	function blue_api_arguments() {
		return explode('/',trim(blue_api_request(),'/'));
	}
	
	class Blue_API_Model_Error {}
?>