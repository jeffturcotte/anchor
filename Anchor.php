<?php
/**
 * Anchor is an *alpha* routing library for PHP 5
 *
 * @copyright  Copyright (c) 2011 Jeff Turcotte, others
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    MIT (see LICENSE or bottom of this file)
 * @package    Anchor
 * @link       http://github.com/jeffturcotte/anchor
 * @version    1.0.0a1
 */
class AnchorNotFoundException extends Exception {}
class AnchorContinueException extends Exception {}
class AnchorProgrammerException extends Exception {}

final class AnchorDefaultAdapter {
	function notFound()
	{
		if (!headers_sent()) {
			header("HTTP/1.1 404 Not Found");
		}
		?>
		
		<html>
			<head>
				<title>404 Not Found :-(</title>

				<link href='http://fonts.googleapis.com/css?family=Oswald&amp;v1' rel='stylesheet' type='text/css'>

				<style type="text/css">
					body {
						margin: 0;
						padding: 0;
						background: #ddd;
						color: #444;
						font-size: 20px;
						font-family: 'Oswald', Helvetica, Arial, sans-serif;
					}
					#container {
						width: 900px;
						margin: 30px auto;
					}
					.troubleshooting {
						background: #aaa;
						padding: 15px 30px 30px;
						border-radius: 10px;
						margin: 0 -30px;
					}
					h1, h2 {
						text-transform: uppercase;
					}
					h1 {
						color: #333;
						font-size: 60px;
					}
					h2 {
						color: #333;
						font-size: 30px;
					}
				</style>
			</head>
			<body>
				<div id="container">
					<h1>Anchor: 404</h1>
					
					<div class="troubleshooting">
						<h2>Troubleshooting</h2>
						
						<p>Anchor can't find a route to this URL or a valid controller method.</p>
					
						<ul>
							<li>Check your controller path location. See <code>::setControllerPath()</code></li>
							<li>Is your controller class authorized for execution? See <code>::authorize()</code></li>
							<li>Is your controller method a public instance method?</li>
							<li>Don't want to see this page? Set your own 404 callback. See <code>::setNotFoundCallback()</code></li>
							<li>Have you set up a route to this URL? See <code>::add()</code></li>
							<li>Are you licensed to be building web apps? See <code>::applyForWebSiteLicense()</code></li>
						</ul>
					</div>
				</div>
			</body>
			

		</html>
		
		<? 
	}
}

final class Anchor {
	/**
	 * The parsed URL from Anchor::parseURL() that is matched against
	 *
	 * @var stdClass
	 */
	private $url;
	
	/**
	 * An array of headers to match against
	 *
	 * @var array
	 */
	private $headers;

	/**
	 * The parsed callback from Anchor::parseCallback() that is executed upon
	 * a successful match of the $url
	 *
	 * @var stdClass
	 */
	private $callback;

	/**
	 * The closure to execute, if this route's callback was a closure
	 *
	 * @var Closure
	 */
	private $closure;
	
	/**
	 * Render map aliases
	 *
	 * @var string
	 */
	private static $aliases = array();

	/**
	 * The path to search for controllers in. used by load()
	 *
	 * @var string
	 */
	private static $controller_path = '';

	/**
	 * The controller loading callback
	 *
	 * @var string
	 */
	private static $loading_callback = 'Anchor::load';

	/**
	 * A stack containing all of the data objects
	 *
	 * @var array
	 */
	private static $active_data = array();
	
	/**
	 * An array of class names that can be used as callbacks with Anchor
	 * 
	 * This helps provide security by creating a whitelist of the valid
	 * adapter classes when using parts of a URL to create the name of the
	 * callback to execute.
	 *
	 * @var array
	 */
	private static $authorized_adapters = array(
		'AnchorDefaultAdapter'
	);
	
	/**
	 * A stack containing all of the callbacks that have been run
	 * 
	 * The most recently called callback will be at the end of the array.
	 *
	 * @var array
	 */
	private static $active_callback = array();

	/**
	 * An array used internally for caching simple, deterministic values
	 *
	 * @var array
	 */
	private static $cache = array(
		'find'         => array(),
		'underscorize' => array(),
		'camelize'     => array()
	);
	
	/**
	 * An associative array with keys being explicit names give to closures
	 * and the values being the internal identifier
	 *
	 * @var array
	 */
	private static $closure_aliases = array();
	
	/**
	 * Same as above, only flipped
	 *
	 * @var array
	 */
	private static $closure_aliases_flipped = array();
	
	/**
	 * The persistent data that is passed to the currently executing callback
	 *
	 * @var stdClass
	 */
	private static $persistent_data = NULL;
	
	/**
	 * An array of stdClass objects representing the callbacks to be executed
	 * for each hook/URL combination
	 *
	 * @var array
	 */
	private static $hooks = array();
	
	private static $global_hooks = array();
	private static $active_hooks = array();
	
	/**
	 * Shortcut token for use when matching against HTTP headers in URLs
	 *
	 * @var array
	 */
	private static $tokens = array(
		'get'    => '[request-method=get]',
		'post'   => '[request-method=post]',
		'put'    => '[request-method=put]',
		'delete' => '[request-method=delete]',
		'html'   => '[accept-type=application/xml]',
		'json'   => '[accept-type=application/json]',
		'xml'    => '[accept-type=text/xml]'
	);
	
	/**
	 * The callback to execute when a matching route is not found
	 *
	 * @var string
	 */
	private static $not_found_callback = 'AnchorDefaultAdapter::notFound';
	
	/**
	 * All of the defined routes, each of which is an Anchor object
	 *
	 * @var array
	 */
	private static $routes = array();
	
	/**
	 * The path part of the URL for the current request
	 *
	 * @var string
	 */
	private static $request_path = NULL;
	
	/**
	 * The symbols for params and what characters such params should match
	 *
	 * @var array
	 */
	private static $param_types = array(
		':' => '[^/]+',
		'!' => '[A-Za-z][A-Za-z0-9_]+',
		'^' => '[0-9]+',
		'@' => '[A-Za-z]+'
	);

	private static $templates = array();
	
	/**
	 * The special param names to use when building a callback string from
	 * a URL.
	 * 
	 * The key is the callback string part and the value is the param name.
	 *
	 * @var array
	 */
	private static $callback_param_names = array(
		'namespace'    => 'namespace',
		'short_class'  => 'class',
		'short_method' => 'method'
	);
	
	/**
	 * The callbacks to use to format parts of a callback string
	 * 
	 * The key is the callback string part, the value is the callback to pass
	 * the string to.
	 *
	 * @var array
	 */
	private static $callback_param_formatters = array(
		'namespace'    => 'Anchor::underscorize',
		'short_class'  => 'Anchor::upperCamelize',
		'short_method' => 'Anchor::lowerCamelize'
	);
	
	/**
	 * undocumented variable
	 *
	 * @var string
	 */
	private static $namespace_separator = '\\';
	
	private static $url_param_formatter = 'Anchor::makeUrlFriendly';
	
	private static $running = FALSE;

	// ==============
	// = Public API =
	// ==============
	
	/**
	 * Adds a route to a callback
	 * 
	 * 
	 * The simplest $map is an explict path:
	 * 
	 *   /users/browse
	 * 
	 * Parameters may be pulled out of the URL by specifying a :param_name
	 * 
	 *   /users/view/:id
	 * 
	 * There are different sets of values that params can match, based on the
	 * first character of the param:
	 * 
	 *  - :param will match anything but a slash
	 *  - !param will match any valid PHP identifier name, i.e. letters, numbers and underscore
	 *  - %param will match digits
	 *  - @param will match letters
	 * 
	 * The following $map will match numeric user IDs:
	 * 
	 *   /users/view/%id/preferences
	 * 
	 * A * will match any character, but is only valid at the end of a $map:
	 * 
	 *   /resources/%id/*
	 * 
	 * A $map can also match specific HTTP headers by using the [header=value]
	 * syntax before a path name. A space must be present between the closing ]
	 * and the beginning of the path.
	 * 
	 *   [accept-type=application/json] /api/users/list
	 * 
	 * The following headers are supported:
	 * 
	 *  - request-method
	 *  - accept-type
	 *  - accept-language
	 * 
	 * Matching of headers is done by comparing strings in a case-insensitive
	 * manner. The accept-type and accept-language headers will only match on
	 * the value with the highest q value.
	 * 
	 * The following predefined shortcuts have been set up for matching headers:
	 * 
	 *  - get:    [request-method=get]
	 *  - post:   [request-method=post]
	 *  - put:    [request-method=put]
	 *  - delete: [request-method=delete]
	 *  - html:   [accept-type=text/html]
	 *  - json:   [accept-type=application/json]
	 *  - xml:    [accept-type=text/xml]
	 * 
	 * The following $map would match GET requests with the accept-type header
	 * of application/json:
	 * 
	 *   get json /api/users
	 * 
	 * 
	 * The $callback parameter accepts a callback string for the class
	 * and method to call when the $map is matched.
	 * 
	 * The following callback string would call the browse method of the Users
	 * class:
	 * 
	 *   Users::browse
	 *
	 * In addition to a class and method, it is also possible to specify the
	 * namespace:
	 * 
	 *   Controllers\Users::browse
	 * 
	 * Both PHP 5.3 namespaces (Namespace\Class) and PHP 5.2-style namespaces
	 * (Namespace_Class) are detected.
	 * 
	 * The namespace, class and method can be inferred from the $map by using
	 * the special param names of "namespace", "class" and "method" and then
	 * a * in the appropriate place in the $callback:
	 * 
	 *   Anchor::add('/!namespace/!class/!method', '*_*::*');
	 *   Anchor::add('/!method', 'MySite_Users::*);
	 * 
	 * In addition to allowing any value for part of the callback, a specific
	 * list of namespaces, classes or method can be specified by separating
	 * them with a pipe:
	 * 
	 *   Anchor::add('/!class/browse', 'Users|Groups::browse');
	 *   Anchor::add('/users/!method', '*::add|list');
	 * 
	 * @param string  $map       The URL to route - see method description for valid syntax
	 * @param string  $callback  The callback to execute - see method description for valid syntax
	 * @param Closure $closure   The closure to execute for the route - if this is provided, $callback will be a name to reference the closure by
	 * @return void
	 */
	public static function add($map, $callback='*_*::*', $closure=NULL)
	{
		$headers = array();
		$url     = NULL;
		$data    = new stdClass;
		
		// add closure name aliases, using spl_object_hash internally
		if ($closure instanceof Closure) {
			$alias = $callback;
			$callback = 'Anchor_' . spl_object_hash($closure);
			self::$closure_aliases[$alias] = $callback;
			self::$closure_aliases_flipped[$callback] = $alias;
		}
		
		if ($map == '404') {
			self::$not_found_callback = $callback;
			return;
		}
		
		// if callback is closure, swap args and generate closure name
		if ($callback instanceof Closure) {
			$closure = $callback;
			$callback = 'Anchor_' . spl_object_hash($closure);
		} 
		
		// use array or non-closure object in $closure arg as $data
		if (is_array($closure) || (is_object($closure) && !($closure instanceof Closure))) {
			$data = (object) $closure;
			$closure = '';
		}
		
		// set url and headers conditions
		foreach(preg_split('/\s+/', $map, NULL, PREG_SPLIT_NO_EMPTY) as $cond) {
			// translate tokens
			if (isset(self::$tokens[strtolower($cond)])) {
				$cond = self::$tokens[strtolower($cond)];
			}
			
			// match url
			if (preg_match('#^/.*#', $cond, $matches)) {
				$url = $matches[0];
			// match special conditions
			} else if (preg_match_all('/\[([^\]]+)\=([^\]]+)\]/', $cond, $matches)) {
				$headers = array_merge(
					$headers,
					array_combine(
						$matches[1],
						$matches[2]
					)
				);
			}       
		}
		
		$route = new Anchor();
		$route->headers  = $headers;
		$route->url      = self::parseUrl($url);
		$route->data     = $data;
		$route->callback = self::parseCallback($callback);
		$route->closure  = $closure;
		
		array_push(self::$routes, $route);
	}

	/**
	 * Adds an alias for a link map
	 * 
	 * @param string $alias      The alias name
	 * @param string $render_map The render map
	 * @return void
	 */
	public static function alias($alias, $render_map)
	{
		self::$aliases[$alias] = $render_map;
	}

	/**
	 * Authorizes a class or parent class to be used as a controller
	 * 
	 * For security, the router will skip over any non-authorized class.
	 *
	 * @param string $controller  The class name to authorize as a controller
	 * @return void
	 */
	public static function authorize($controller)
	{
		array_push(self::$authorized_adapters, $controller);
	}
	
	/**
	 * Destroys all hooks and routes. This is most useful for running tests
	 *
	 * @return void
	 */
	public static function clear()
	{
		self::$routes = array();
		self::$hooks = array();
	}
	
	/**
	 * dispatch a callable (closure or method string)
	 *
	 * @param string|Closure $callable  The string callback or Closure to dispatch
	 * @param object         $instance  The object to dispatch
	 * @param object         &$data     The data for the request
	 * @return void
	 */
	private static function dispatchCallable($callable, $instance, &$data)
	{
		if ($callable instanceof Closure) {
			call_user_func_array(
				$callable,
				array(&$data)
			);
			return TRUE;
		}

		$short_method = self::parseCallback($callable)->short_method;
		$instance->$short_method($data);
		return TRUE;
	}

	/**
	 * Instantiate controller classes
	 *
	 * @param string|Closure $callable 
	 * @return void
	 */
	private static function instantiateCallable($callable)
	{
		if ($callable instanceof Closure) {
			return $callable;
		}

		$callback = self::parseCallback($callable);

		$class = $callback->class;
		$short_method = $callback->short_method;
		return new $class();
	}
	
	/**
	 * Runs a callback/closure as run() would
	 *
	 * @param string|Closure  $callable  The string callback or closure to call
	 * @param stdClass        $data      The persistent data object that is passed to $callable
	 * @return boolean        If $callable was successfully executed
	 */
	public static function &call($callable, $data=NULL, $exit=FALSE)
	{
		// merge any incoming data
		if ($data === NULL) {
			$data = (object) '';
			unset($data->scalar);
		}
		
		if (!($callable instanceof Closure)) {
		$callable = self::format($callable);

		if (!self::validateCallback($callable)) {
			$false = FALSE;
			return $false;
		}
		}
		
		$hooks = array();
		
		self::pushActiveData($data);
		self::pushActiveCallback($callable);
		self::pushActiveHooks($hooks);
		
		try {
			$active_data = self::getActiveData();
			
			$hooks = self::collectHooks($callable);

			$instance = self::instantiateCallable($callable);
			
			self::callHookCallbacks($hooks, 'init', $active_data);
				
			$hooks = array_merge($hooks, self::collectHooks($callable, TRUE));

			try {
				self::callHookCallbacks($hooks, 'before', $active_data);
			} catch (Exception $e) {
				self::catchExceptionFromCall($hooks, $e, $active_data);
			}
			
			try {
				self::dispatchCallable($callable, $instance, $active_data);
			} catch (Exception $e) {
				self::catchExceptionFromCall($hooks, $e, $active_data);
			}
			
			try {
				self::callHookCallbacks($hooks, 'after', $active_data);
			} catch (Exception $e) {
				self::catchExceptionFromCall($hooks, $e, $active_data);
			}
			
			self::callHookCallbacks($hooks, 'finish', $active_data);
			
			self::popActiveCallback();
			self::popActiveHooks();
			
			if (method_exists($instance, '__destruct')) {
				$instance->__destruct();
			}

			if ($exit) {
				exit();
			}
			
			$active_data = self::popActiveData();
			return $active_data;
		} catch (Exception $e) {
			self::popActiveCallback();
			self::popActiveData();
			self::popActiveHooks();
			throw $e;
		}
	}
	
	private static function catchExceptionFromCall($hooks, $e, &$active_data) {
		$exception = new ReflectionClass($e);
	
		do {
			$hook_name = "catch " . $exception->getName();
			if (self::callHookCallbacks($hooks, $hook_name, $active_data, $e)) {
				break;
			}
		} while ($exception = $exception->getParentClass());
	
		if (!$exception) { 
			self::popActiveData();
			self::popActiveCallback();
			self::popActiveHooks();
			throw $e; 
		}
	}
	
	/**
	 * Formats a string, replacing symbols with active callback values
	 *
	 *   %n => active namespace
	 *   %N => active parent/outermost namespace
	 *   %c => active class
	 *   %C => active short class
	 *   %m => active method
	 *   %M => active short method
	 *   %p => active full path
	 *   %P => active class path
	 *
	 * @param string $format         The string to format
	 * @param boolean $underscorize  If the active callback values should be underscorized, does not affect active path(s)
	 * @return string                The formatted string
	 */
	public static function format($format, $underscorize=FALSE, $default=NULL)
	{
		// no callback, or format not a string, just return $format
		if (!($callback = self::getActiveCallback()) || !is_string($format)) {
			return $format;
		}
		
		if ($callback instanceof Closure) {
			$callback = 'Anchor_' . spl_object_hash($callback);
		}
		if (isset(self::$closure_aliases_flipped[$callback])) {
			$callback = self::$closure_aliases_flipped[$callback];
		}
		
		// parse the callback
		$callback = self::parseCallback($callback);
		
		// make the path and class path
		$short_class  = self::underscorize($callback->short_class);
		$short_method = self::underscorize($callback->short_method);

		$path = "{$short_class}/{$short_method}";
		$class_path = $short_class;
				
		// make the class path
		$class_path = self::underscorize($callback->short_class);
		
		// alter the full and class paths with namespace
		if ($callback->namespace) {
			$namespace = self::underscorize($callback->namespace);
			$path =  "{$namespace}/{$path}";
			$class_path = "{$namespace}/{$class_path}";
		}
		
		$formatter_pattern = "/[^%]%.{1}/i";
		
		// set the replacements
		$replacements = array(
			'%n' => ($underscorize) ? self::underscorize($callback->namespace) : $callback->namespace,
			'%N' => ($underscorize) ? self::underscorize($callback->parent_namespace) : $callback->parent_namespace,
			'%c' => ($underscorize) ? self::underscorize($callback->class) : $callback->class,
			'%C' => ($underscorize) ? self::underscorize($callback->short_class) : $callback->short_class,
			'%m' => ($underscorize) ? self::underscorize($callback->method) : $callback->method,
			'%M' => ($underscorize) ? self::underscorize($callback->short_method) : $callback->short_method,
			'%p' => ($path == '/') ? '' : $path,
			'%P' => ($class_path == '/') ? '' : $class_path
		);
		
		$formatted = str_replace(
			array_keys($replacements),
			array_values($replacements),
			$format
		);
		
		if ($default && (!$formatted || preg_match("/[^%]%.{1}/", $formatted))) {
			return $default;
		}
		
		return $formatted;
	}
	
	/**
	 * Generates a link from a callback/params string
	 * 
	 * Example usage, without or with : before param name:
	 * 
	 *   Anchor::link('Users::view id', 5);
	 *   Anchor::link('Users::view :id', 5);
	 *
	 * @param string $callback_key  A string containing a callback and (optionally) param names - params should be separated by space and may begin with a :
	 * @return string  The matching URL
	 */
	public static function link($callback_key)
	{
		$param_values = func_get_args();
		$callback_key = trim(array_shift($param_values));
		
		if (isset(self::$aliases[$callback_key])) {
			$callback_key = self::$aliases[$callback_key];
		}
		
		$param_names  = preg_split('/\s+/', $callback_key);
		$callback     = array_shift($param_names);
		
		if (isset(self::$closure_aliases[$callback])) {
			$callback = self::$closure_aliases[$callback];
		}
		
		$callback = self::format($callback);
		
		if (isset($param_values[0])) {
			$data =& $param_values[0];
			
			if (is_object($data)) {
				$param_values = array();
				
				foreach($param_names as $key => $name) {
					list($name, $property) = explode(':', $name);

					$value = NULL;

					if (!$property) {
						$property = $name;
					}
					
					if (substr($property, -2, 2) == '()') {
						$property = substr($property, 0, -2);
						if (method_exists($data, $property) || method_exists($data, '__call')) {
							$value = $data->$property();
						}
					} else if (isset($data->$property)) {
						$value = $data->$property;
					}

					$param_names[$key] = $name;
					$param_values[$key] = $value;
				}


			} else if (is_array($data)) {
				$param_values = array();

				foreach($param_names as $key => $name) {
					list($name, $property) = explode(':', $name);

					$value = NULL;

					if (isset($data[$property])) {
						$value = $data[$property];      
					} else if (isset($data[$name])) {
						$value = $data[$name];
					}

					$param_names[$key] = $name;
					$param_values[$key] = $value;
				}
			}
		}
		
		$param_names_count = count($param_names);               
		$param_values = array_slice($param_values, 0, $param_names_count);
		$param_values = array_pad($param_values, $param_names_count, '');

		$url_params = ($param_names_count) 
			? array_combine($param_names, $param_values) 
			: array();

		$param_names_flipped = array_flip($param_names);

		foreach(self::$routes as $route) {
			$callback_params = array();
			
			if (self::matchCallback($route->callback, $callback, $callback_params)) {
				if (strpos($route->url->subject, '*') === 0) {
					continue;
				}
				
				$params = array_merge($url_params, $callback_params);
				$dist   = levenshtein($route->callback->scalar, $callback);
				$sect   = count(array_intersect_key($route->url->params, $params));
				$diff   = count(array_diff_key($route->url->params, $params));
				
				$is_best = (
					!isset($best_route) ||
					$low_dist > $dist && $low_diff >= $diff ||
					$low_dist >= $dist && $high_sect < $sect ||
					$low_dist >= $dist && $high_sect <= $sect && $low_diff > $diff
				);
				
				if ($is_best) {
					$best_route = $route;
					$low_dist   = $dist;
					$high_sect  = $sect;
					$low_diff   = $diff;
				}
			}
		}
		
		if (!isset($best_route)) {
			trigger_error("No route could be found matching the callback ($callback)", E_USER_WARNING);
			return '#';
		}
		
		return self::buildUrl($best_route, $params);
	}
	
	/**
	 * Adds a callback to be called at a pre-defined hook during the execution of a specific route callback
	 *
	 * @todo DOCUMENT HOOKS
	 *
	 * @param string $hook_name       The hook to attach the callback to
	 * @param string $route_callback  The route callback to attach the callback to - if not qualified to a namespace and class, *-*:: will be prepended
	 * @param string $hook_callback   The callback to attach
	 * @param string ...
	 * @return void
	 */
	public static function hook($hook_name, $route_callback, $hook_callback) 
	{
		$args = func_get_args();
		if (count($args) > 3) {
			$hook_callbacks = array_slice($args, 2);
		} else {
			$hook_callbacks = array($hook_callback);
		}

		$hooks =& self::$global_hooks;
		
		// push hooks to active hooks if running
		if (self::$running) {
			$hooks =& self::getActiveHooks();
		}

		if (strpos($route_callback, '::') === FALSE) {
			$route_callback = '*_*::' . $route_callback;
		}
		
		$route_callbacks = preg_split('/\s*,\s*/', $route_callback);
		
		foreach($route_callbacks as $route_callback) {
			if (isset(self::$closure_aliases[$route_callback])) {
				$route_callback = self::$closure_aliases[$route_callback];
			}
			
			foreach ($hook_callbacks as $hook_callback) {
				$hook = (object) 'Hook';
				$hook->hook = $hook_name;
				$hook->route_callback = self::parseCallback($route_callback);
				$hook->callback  = $hook_callback;
				array_push($hooks, $hook);
			}
		}
	}
	
	
	/**
	 * Returns a callback that matches the URL, headers and params passed
	 *
	 * @param string   $url      The URL to match against
	 * @param array    $headers  An associative array of headers to match against - keys must be lower case
	 * @param array    &$params  The GET parameters to match against - param mapping from a route may affect values in this array
	 * @param stdClass &$data    The data from the route, if one is matched
	 * @param integer  $offset   The index in the array of routes to restart from - this allows resolving multiple times in sequence
	 * @return FALSE|stdClass  Returns FALSE if no matching callback was found, otherwise
	 */
	public static function resolve($url, $headers=array(), &$params=array(), &$data=NULL, &$offset=0)
	{
		if ($offset > (count(self::$routes) - 1)) {
		return FALSE;
		}

		foreach(self::$routes as $key => $route) {
		// skip any routes lower than offset
		if ($key < $offset) {
			continue;
		}

		// match headers
		if (!self::matchHeaders($route->headers, $headers)) {
			continue;
		}

		// match url
		if (!self::matchUrl($route->url, $url, $params)) {
			continue;
		}

		// match and map param aliases
		foreach($route->url->param_aliases as $from => $to) {
			if (!isset($params[$from])) {
			continue;
			}

			$params[$to] = $params[$from];
			unset($params[$from]);
		}

		$offset = $key;

		// return closure name string or closure
		if ($route->closure) {
			$data = $route->data;
			return $route->closure;

		// return callback string
		} else {
			$callback = self::buildCallback($route, $params);
			if (!self::matchCallback($route->callback, $callback)) {
			continue;
			}
			$data = $route->data;
			return $callback;
		}		
		}

		$offset = count(self::$routes);

		return FALSE;
	}

	
	/**
	 * Run the routes for the current request
	 *
	 * @param  boolean $exit  If execution of PHP should stop after routing
	 * @return void
	 */
	public static function run($exit=TRUE) 
	{
		self::$running = TRUE;
		
		$old_GET = $_GET;
		$offset = 0;
		
		while(TRUE) {
			$_GET = $old_GET;
			
			try {
				$callable = self::resolve(
					self::getRequestPath(), 
					self::getHeaders(),
					$_GET,
					$data,
					$offset
				);
				
				if ($callable === FALSE) {
					throw new AnchorNotFoundException();
				}
								
				if (self::call($callable, $data)) {
					break;
				} else {
					$offset++;
					continue;
				}
			} catch (AnchorNotFoundException $e) {
				self::call(self::$not_found_callback);
				break;
			} catch (AnchorContinueException $e) {
				continue;
			}
		}
		
		if ($exit) {
			exit();
		}
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $request_path 
	 * @return void
	 */
	public static function setRequestPath($request_path) {
		self::$request_path = $request_path;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 */
	public static function setFragmentRouting() {
		if (isset($_GET) && isset($_GET['_escaped_fragment_'])) {
			self::setRequestPath($_GET['_escaped_fragment_']);
		}
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 */
	public static function setLegacyNamespacing() {
		self::$callback_param_formatters['namespace'] = 'Anchor::upperCamelize';
		self::$namespace_separator = '_';
	}
	
	/**
	 * Set a token to use as shorthand for header conditions in in a route
	 *
	 * @param string $token       The token to create
	 * @param string $conditions  The header conditions for the token to represent
	 * @return void
	 */
	public static function addToken($token, $conditions)
	{
		$token = strtolower($token);
		self::$tokens[$token] = $conditions;
	}
	
	// ===============
	// = Private API =
	// ===============
	
	/**
	 * undocumented function
	 *
	 * @param object $route 
	 * @param array  &$params 
	 * @return void
	 */
	private static function buildCallback($route, &$params) 
	{
		$namespace    = null;
		$short_class  = null;
		$short_method = null;
		
		$namespace_param    = self::$callback_param_names['namespace'];
		$short_class_param  = self::$callback_param_names['short_class'];
		$short_method_param = self::$callback_param_names['short_method'];
		
		$namespace    = $route->callback->namespace;
		$short_class  = $route->callback->short_class;
		$short_method = $route->callback->short_method;
		
		if ($namespace == '*' && isset($params[$namespace_param])) {
			$namespace = call_user_func_array(
				self::$callback_param_formatters['namespace'],
				array($params[$namespace_param])
			);
			unset($params[$namespace_param]);
		}
		
		if ($short_class == '*' && isset($params[$short_class_param])) {
			$short_class = call_user_func_array(
				self::$callback_param_formatters['short_class'],
				array($params[$short_class_param])
			);
			unset($params[$short_class_param]);
		}
		
		if ($short_method == '*' && isset($params[$short_method_param])) {
			$short_method = call_user_func_array(
				self::$callback_param_formatters['short_method'],
				array($params[$short_method_param])
			);
			unset($params[$short_method_param]);
		}
		
		$callback = $short_method;
		
		if ($short_class) {
			$callback = $short_class . '::' . $callback;
		}
		
		if ($namespace) {
			$callback = $namespace . self::$namespace_separator . $callback;
		}
		
		return $callback;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $hooks 
	 * @param string $hook 
	 * @param stdClass  $data 
	 * @param Exception $exception 
	 * @return void
	 */
	private static function callHookCallbacks(&$hooks, $hook, &$data=NULL, $exception=NULL)
	{
		if (isset($hooks[$hook])) {
			foreach($hooks[$hook] as $hook_obj) {
				$callback = self::format($hook_obj->callback);
				
				if (!is_callable($callback)) {
					continue;
				}
				
				call_user_func_array($callback, array(&$data, $exception));
			}
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Converts an `underscore_notation` or `camelCase` string to `camelCase`
	 * 
	 * Derived from MIT fGrammer::camelize
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
	 *
	 * @param  string  $original The string to convert
	 * @param  boolean $upper    If the camel case should be `UpperCamelCase`
	 * @return string  The converted string
	 */
	private static function &camelize($original, $upper=FALSE)
	{
		$upper = (int) $upper;
		$key   = "{$upper}/{$original}";
		
		if (isset(self::$cache['camelize'][$key])) {
			return self::$cache['camelize'][$key];
		}
		
		$string = $original;
		
		// Check to make sure this is not already camel case
		if (strpos($string, '_') === FALSE) {
			if ($upper) { 
				$string = strtoupper($string[0]) . substr($string, 1);
			}
			
		// Handle underscore notation
		} else {
			$string = strtolower($string);
			if ($upper) { $string = strtoupper($string[0]) . substr($string, 1); }
			$string = preg_replace('/(_([a-z0-9]))/e', 'strtoupper("\2")', $string);
		}
		
		self::$cache['camelize'][$key] =& $string;
		return $string;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $callback 
	 * @return void
	 */
	private static function collectHooks($callable, $active=FALSE) 
	{ 
		$added_hooks =& self::$global_hooks;
		if ($active) {
		$added_hooks =& self::getActiveHooks();
		}
		
		if ($callable instanceof Closure) {
		$callable = 'Anchor_' . spl_object_hash($callable);
		}
		
		if (isset(self::$closure_aliases_flipped[$callable])) {
		$callable = self::$closure_aliases_flipped[$callable];
		}
		
		$hooks = array();
		
		foreach($added_hooks as $hook) {
		if (self::matchDerivativeCallback($hook->route_callback, $callable)) {
			if (!isset($hooks[$hook->hook])) {
				$hooks[$hook->hook] = array();
			}
			array_push($hooks[$hook->hook], $hook);
		}
		}
		
		return $hooks;
	}
	
	/**
	 * Returns the current domain name, with protcol prefix. Port will be included if not 80 for HTTP or 443 for HTTPS.
	 * 
	 * Derived from MIT fURL::getDomain
	 * Source: http://flourishlib.com/browser/fRequest.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
	 *
	 * @return string  The current domain name, prefixed by `http://` or `https://`
	 */
	private static function getDomain()
	{
		$port = (isset($_SERVER['SERVER_PORT'])) ? $_SERVER['SERVER_PORT'] : NULL;
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			return 'https://' . $_SERVER['SERVER_NAME'] . ($port && $port != 443 ? ':' . $port : '');
		} else {
			return 'http://' . $_SERVER['SERVER_NAME'] . ($port && $port != 80 ? ':' . $port : '');
		}
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $route 
	 * @param string $params 
	 * @return void
	 */
	private static function buildUrl($route, &$params)
	{
		$anchor = $route->url->subject;
		
		if ($route->closure) {
			unset($params[self::$callback_param_names['short_method']]);
		}
		
		if (count($route->url->params) > count($params)) {
			trigger_error('The required params to build the URL were not supplied', E_USER_WARNING);
			return '#';
		}
		
		// replace all url param symbols with callback values
		foreach($route->url->params as $name => $param) {
			if (isset($params[$name])) {
				$anchor = str_replace(
					$param->symbol,
					call_user_func_array(self::$url_param_formatter, array($params[$name])),
					$anchor
				);
				unset($params[$name]);
			}
		}
		
		// look for param aliases and swap
		foreach($params as $name => $param) {
			if ($alias = array_search($name, $route->url->param_aliases)) {
				$params[$alias] = $param;
			}
		}

		// attempt to fill in the fragment
		$fragment = $route->url->fragment;

		foreach($route->url->fragment_params as $name => $param) {
			if (!isset($params[$name])) {
				$fragment = '';
				break;
			}
			$fragment = str_replace($param->symbol, urlencode($params[$name]), $fragment);
			unset($params[$name]);
		}

		// unset any callback params (assume they aren't dynamic)
		foreach($params as $name => $param) {
			if (in_array($name, self::$callback_param_names)) {
				unset($params[$name]);
			}
		}
		
		// build and append query string
		if (!empty($params)) {
			$anchor .= '?' . http_build_query($params);
		}

		if ($fragment) {
			$anchor .= '#' . $fragment;
		}

		return $anchor;
	}
	
	/**
	 * Validate that the callback meets the following criteria:
	 * 
	 *  - Authorized to be run. See validateAuthorization
	 *  - Is a public method
	 *  - Is an instance method
	 *  - Does not start with double underscore i.e. looks like a magic method 
	 * 
	 * or that that callbacks class is authorized and has a public __call method.
	 *
	 * @param string $callback  A callback string
	 * @return boolean  Whether or not the callback validates
	 */
	private static function validateCallback($callback)
	{
		if ($callback instanceof Closure) {
			return TRUE;
		}

		if (isset(self::$closure_aliases[$callback])) {
			return TRUE;
		}
		if (isset(self::$closure_aliases_flipped[$callback])) {
			return TRUE;
		}

		$callback = self::parseCallback($callback);

		$reflected_method = NULL;
		$reflected_call   = NULL;
		
		// don't autoload classes, use the configured or default loader
		if (!class_exists($callback->class, FALSE)) {
			if (!self::callLoadCallback($callback)) {
				return FALSE;
			}
		}
		
		try {
			$reflected_method = new ReflectionMethod($callback->scalar);
			
			// authorize declaring class
			if (!self::validateAuthorization($reflected_method->getDeclaringClass())) {
				return FALSE;
			}
			
			// only allow public methods
			if (!$reflected_method->isPublic()) {
				return FALSE;
			}
			
			// don't allow static methods
			if ($reflected_method->isStatic()) {
				return FALSE;
			}
			
			// don't allow anything that looks like a magic method
			if (strpos($reflected_method->getName(), '__') === 0) {
				return FALSE;
			}
		} catch (ReflectionException $e) {}
		
		if (!$reflected_method) {
			try {
				$reflected_call = new ReflectionMethod($callback->class . '::__call');
				
				// authorize declaring class
				if (!self::validateAuthorization($reflected_method->getDeclaringClass())) {
					return FALSE;
				}
			
				// make sure __call is public
				if (!$reflected_call->isPublic()) {
					return FALSE;
				}
			
			} catch (ReflectionException $e) {}
		}
		
		if (!$reflected_method && !$reflected_call) {
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $callback 
	 * @return void
	 */
	private static function callLoadCallback($callback)
	{
		return call_user_func_array(self::$loading_callback, array($callback));
	}
	
	
	/**
	 * validate the authorization of the controller class
	 *
	 * @param object $class  a ReflectionClass object
	 * @return boolean       whether or not the class authorizes
	 */
	private static function validateAuthorization($class)
	{
		// auto authorize any class in the controller path
		if (self::$controller_path && $class->getFilename()) {
			if (strpos(realpath($class->getFilename()), realpath(self::$controller_path)) === 0) {
			return TRUE;
			}
		}

		do {
			foreach(self::$authorized_adapters as $adapter) {
				$adapter = str_replace('*', '.+', $adapter);
				if (preg_match('/^' . $adapter . '$/i', $class->getName())) {
					return TRUE;
				}
			}
		} while ($class = $class->getParentClass());
		
		return FALSE;
	}
	
	/**
	 * Default controller loader callback
	 *
	 * @param string $callback 
	 * @return void
	 */
	private static function load($callback)
	{
		$class = $callback->class;
		
		if (class_exists($class, FALSE)) {
			return TRUE;
		}
		
		if (strpos($class, '\\') !== FALSE) {
			$path = explode('\\', $class);
		} else if (strpos($class, '_') !== FALSE) {
			$path = explode('_', $class);
		} else {
			$file = $class;
		}
		
		if (!isset($file)) {
			$file = array_pop($path);

			foreach($path as $key => $piece) {
				$piece = preg_replace('/([a-zA-Z])([0-9])/', '\1_\2', $piece);
				$piece = preg_replace('/([a-z0-9A-Z])([A-Z])/', '\1_\2', $piece);
				$path[$key] = strtolower($piece);
			}

			array_push($path, $file);
			$file = join('/', $path);

			if ($file[0] == '/') {
				$file = substr($file, 1);
			}
		}
		
		$path = realpath(self::$controller_path . '/' . $file . '.php');
		
		if (!file_exists($path)) {
			return FALSE;
		}
		
		require_once $path;
		return TRUE;
	}
	
	public static function setControllerPath($path)
	{
		self::$controller_path = realpath($path);
	}
	
	public static function setGlobalFunctions()
	{
		if (!function_exists('a')) {
			function a() {
				$args = func_get_args();
				return call_user_func_array(
					array('Anchor', 'link'),
					$args
				);
			}
		}
	}
	
	public static function setURLParamFormatter($callback)
	{
		self::$url_param_formatter = $callback;
	}
	
	/**
	 * convert a string to lowerCamelCase
	 *
	 * @param string $string 
	 * @return string  The lowerCamelCase version of the string
	 */
	private static function lowerCamelize($string)
	{
		return self::camelize($string);
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $parsed_callback 
	 * @param string $callback_string 
	 * @param string $params 
	 * @return void
	 */
	private static function matchCallback($parsed_callback, $callback_string, &$params=NULL)
	{
		if (preg_match($parsed_callback->pattern, $callback_string, $matches)) {
			if (is_array($params)) {
				foreach($matches as $name => $param) {
					if (is_string($name)) {
						$params[$name] = self::underscorize($param);
					}
				}
			}
			return TRUE;
		}
		return FALSE;
	}
	
	private static function matchDerivativeCallback($parsed_callback, $callback_string)
	{       
		if (!preg_match($parsed_callback->parent_pattern, $callback_string)) {
			return FALSE;
		}
		
		if (in_array('*', $parsed_callback->parent_options)) {
			return TRUE;
		}

		$callback = self::parseCallback($callback_string);
		
		if (in_array($callback->short_class, $parsed_callback->parent_options)) {
			return TRUE;
		}
		
		foreach($parsed_callback->parent_options as $parent_class) {
			if (is_subclass_of($callback->class, $parent_class)) {
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Indicates if a set of header conditions matches the headers
	 * 
	 * Each conditions is tested against the headers by comparing the two
	 * in a case-insensitive manner.
	 *
	 * @param array  $conditions  An associative array of {lowercase header name} => {value}
	 * @param array  $headers     An associative array of {lowercase header name} => {value}
	 * @return boolean  If each condition matches a header
	 */
	private static function matchHeaders($conditions, $headers=array())
	{
		foreach($conditions as $key => $val) {
			if (!isset($headers[$key])) {
				return FALSE;
			}
			if (strtolower($val) != strtolower($headers[$key])) {
				return FALSE;
			}
			
		}
		return TRUE;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $parsed_url 
	 * @param string $url_string 
	 * @param string $params 
	 * @return void
	 */
	private static function matchUrl($parsed_url, $url_string, &$params=NULL)
	{
		if (preg_match($parsed_url->pattern, $url_string, $matches)) {
			foreach($matches as $name => $param) {
				if (is_string($name)) {
					$params[$name] = urldecode($param);
				}
			}
			
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Parses a string callback into an object representation
	 * 
	 * The object will have the following attributes:
	 *  - pattern:      The PCRE regex patterns to match callbacks against
	 *  - method:       The full callback, including namespace, short_class and short_method
	 *  - class:        The namespace and short_class (or class name)
	 *  - namespace:    The class namespace, if applicable
	 *  - short_class:  The class name
	 *  - short_method: The method name
	 *
	 * @param string|object $callback 
	 * @return stdClass  The parsed callback
	 */
	private static function parseCallback($callback) 
	{
		if (is_object($callback)) {
			return $callback;
		}
		
		$callback = preg_replace('/\*+/', '*', $callback);
		
		preg_match(
			'/
				(?P<method>
					((?P<class>
						((?P<namespace>[A-Za-z0-9\|\*\?]+)(\\\\|_))?
						(?P<short_class>[A-Za-z0-9\|\*\?]+)
					)::)?
					(?P<short_method>[A-Za-z0-9\|\*\?\s_-]+)
				)$
			/x',
			$callback,
			$matches
		);
	
		$callback = (object) $callback;
		
		// set callback properties
		foreach($matches as $key => $value) {
			if (is_string($key)) {
				$callback->$key = $value;
			}
		}
		
		$callback->parent_namespace = preg_replace('/(\\\\|_[A-Z]).*$/', '', $callback->namespace);
		
		// create callback pattern
		$wild_pattern = ($callback->short_method == '*') ? '.+' : '.*';
		$pattern = '(?P<' . self::$callback_param_names['short_method'] . '>' . str_replace('*', $wild_pattern, $callback->short_method) . ')';
		$derivative_pattern = $pattern;
		
		if ($callback->short_class) {
			$wild_pattern = ($callback->short_class == '*') ? '.+' : '.*';
			$pattern = '(?P<' . self::$callback_param_names['short_class'] . '>' . str_replace('*', $wild_pattern, $callback->short_class) . ')::' . $pattern;
			$derivative_pattern = '(?P<' . self::$callback_param_names['short_class'] . '>.+)::' . $derivative_pattern;
			
			
		}
		
		$separator = str_replace('\\', '\\\\', self::$namespace_separator);
		
		if ($callback->namespace) {
			$wild_pattern = ($callback->namespace == '*') ? '.+' : '.*';
			$pattern = '(?P<' . self::$callback_param_names['namespace'] . '>' . str_replace('*', $wild_pattern, $callback->namespace) . ')' . $separator . $pattern;
			$derivative_pattern = '(?P<' . self::$callback_param_names['namespace'] . '>' . str_replace('*', '.+', $callback->namespace) . ')' . $separator . $derivative_pattern;
		}
		
		$callback->pattern = '/^' . $pattern . '$/';
		$callback->parent_pattern = '/^' . $derivative_pattern . '$/';
		$callback->parent_options = array($callback->short_class);
		
		if (strpos($callback->short_method, '|')) {
			$callback->short_method = '*';
		}
		if (strpos($callback->short_class, '|')) {
			$callback->parent_options = explode('|', $callback->short_class);
			$callback->short_class = '*';
		}
		if (strpos($callback->namespace, '|')) {
			$callback->namespace = '*';
		}
		
		return $callback;
	}
	
	/**
	 * Parses a URL into an object
	 * 
	 * The object will have the following attributes:
	 *  - pattern:  The PCRE regex to match against
	 *  - subject:  The original text of the URL, with trailing * and / removed
	 *  - params:   An associative array of {param name} => {object with attributes}:
	 *    - symbol:  The full type and name, e.g. :id
	 *    - type:    The type, e.g. :
	 *    - name:    The name, e.g. id
	 *  - param_aliases: An associative array to map param names {source param} => {destination param}
	 *
	 * @param  string $url  The URL to parse
	 * @return stdClass  An object representing the parsed URL
	 */
	private static function parseUrl($url) 
	{
		if (!$url) {
			return NULL;
		}
	
		$query = parse_url($url, PHP_URL_QUERY);
		$fragment = parse_url($url, PHP_URL_FRAGMENT);
		$url   = parse_url($url, PHP_URL_PATH);

		parse_str($query, $param_aliases);
		
		$url = str_replace(' ', '', $url);
		$url = str_replace('`', '\`', $url);            
		$url = ($url == '/') ? $url : rtrim($url, '/');
		
		if (substr($url, 0, 1) != '*' && $url != '*') {
			$url = '/' . $url;
		}
		
		$url = preg_replace('#/+#', '/', $url);
		
		$url = (object) $url;
		$url->pattern = $url->scalar;
		$url->subject = preg_replace('/\*$/', '', $url->scalar);
		$url->params = self::parseParams($url->scalar);

		
		foreach($url->params as $param) {
			$replacement = sprintf(
				'(?P<%s>%s)', 
				$param->name, 
				self::$param_types[$param->type]
			);
			
			$url->pattern = str_replace(
				$param->symbol, 
				$replacement,
				$url->pattern
			);
		}
		
		$url->pattern = (substr($url->pattern, 0, 1) != '*')
			? '^' . $url->pattern
			: substr($url->pattern, 1);
		
		$url->pattern = (substr($url->pattern, -1) != '*')
			? $url->pattern . '$'
			: substr($url->pattern, 0, strlen($url->pattern) - 1);
		
		$url->pattern = '`' . $url->pattern . '`';
		
		$url->param_aliases = array();

		$url->fragment = $fragment;
		$url->fragment_params = self::parseParams($fragment);
		
		foreach($param_aliases as $from => $to) {
			if (preg_match('/^[:@\$%]/', $to)) {
				$to = substr($to, 1);
			}
			$url->param_aliases[$from] = $to;
		}
		
		return $url;
	}

	private static function parseParams($string)
	{
		$param_pattern = '/\{?((:|!|\^|@)([a-z][_a-z0-9]*))\}?/i';
		
		preg_match_all($param_pattern, $string, $matches);
				
		$param_symbols = $matches[0];
		$param_types   = $matches[2];
		$param_names   = $matches[3];

		// validate no dupe symbols
		if (count($param_names) != count(array_unique($param_names))) {
			throw new AnchorProgrammerException('Error: Duplicate param names found.');
		}

		$params = array();

		foreach($param_symbols as $key => $param_symbol) {
			$param = (object) $param_names[$key];
			$param->symbol = $param_symbol;
			$param->type   = $param_types[$key];
			$param->name   = $param_names[$key];
			$params[$param->name] = $param;
		}

		return $params;
	}
	
	/**
	 * Changes a string into a URL-friendly string
	 *
	 * Derived from MIT fURL::makeFriendly
	 * Source: http://flourishlib.com/browser/fURL.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
	 * 
	 * @param  string   $string      The string to convert
	 * @param  integer  $max_length  The maximum length of the friendly URL
	 * @param  string   $delimiter   The delimiter to use between words, defaults to `_`
	 * @param  string   :$string
	 * @param  string   :$delimiter
	 * @return string  The URL-friendly version of the string
	 */
	private static function makeUrlFriendly($string, $max_length=NULL, $delimiter=NULL)
	{
		// This allows omitting the max length, but including a delimiter
		if ($max_length && !is_numeric($max_length)) {
			$delimiter  = $max_length;
			$max_length = NULL;
		}

		//$string = fHTML::decode(fUTF8::ascii($string));
		$string = strtolower(trim($string));
		$string = str_replace("'", '', $string);

		if (!strlen($delimiter)) {
			$delimiter = '_';
		}

		$delimiter_replacement = strtr($delimiter, array('\\' => '\\\\', '$' => '\\$'));
		$delimiter_regex       = preg_quote($delimiter, '#');

		$string = preg_replace('#[^a-z0-9\-_]+#', $delimiter_replacement, $string);
		$string = preg_replace('#' . $delimiter_regex . '{2,}#', $delimiter_replacement, $string);
		$string = preg_replace('#_-_#', '-', $string);
		$string = preg_replace('#(^' . $delimiter_regex . '+|' . $delimiter_regex . '+$)#D', '', $string);
		
		$length = strlen($string);
		if ($max_length && $length > $max_length) {
			$last_pos = strrpos($string, $delimiter, ($length - $max_length - 1) * -1);
			if ($last_pos < ceil($max_length / 2)) {
				$last_pos = $max_length;
			}
			$string = substr($string, 0, $last_pos);
		}
		
		return $string;
	}

	
	/**
	 * Converts a `camelCase` or `underscore_notation` string to `underscore_notation`
	 *
	 * Derived from MIT fGrammer::camelize
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
	 *
	 * @param  string $string  The string to convert
	 * @return string  The converted string
	 */
	private static function &underscorize($string)
	{
		$key = $string;
		
		if (!$string) {
			return $string;
		}
		
		if (isset(self::$cache['underscorize'][$key])) {
			return self::$cache['underscorize'][$key];
		}
		
		$original = $string;
		
		$string = strtolower($string[0]) . substr($string, 1);
		
		// If the string is already underscore notation then leave it
		if (strpos($string, '_') !== FALSE) {
		
		// Allow humanized string to be passed in
		} elseif (strpos($string, ' ') !== FALSE) {
			$string = strtolower(preg_replace('#\s+#', '_', $string));
			
		} else {
			do {
				$old_string = $string;
				$string = preg_replace('/([a-zA-Z])([0-9])/', '\1_\2', $string);
				$string = preg_replace('/([a-z0-9A-Z])([A-Z])/', '\1_\2', $string);
			} while ($old_string != $string);
			
			$string = strtolower($string);
		}
		
		self::$cache['underscorize'][$key] =& $string;
		return $string;
	}
	
	/**
	 * Convert a string to UpperCamelCase
	 *
	 * @param string $string 
	 * @return string  The upperCamelCase version of the string
	 */
	private static function upperCamelize($string)
	{
		return self::camelize($string, TRUE);
	}
	
	/**
	 * Returns the most recently run callback
	 * 
	 * @return callable  The most recently run callback
	 */
	private static function getActiveCallback()
	{
		return end(self::$active_callback);
	}
	
	/**
	 * Adds a callback to the stack of executing callbacks
	 * 
	 * @param callable $callback  The callback being executed
	 * @return callable  The $callback
	 */
	private static function pushActiveCallback($callback)
	{
		array_push(self::$active_callback, $callback);
		return $callback;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 */
	private static function popActiveCallback() {
		return array_pop(self::$active_callback);
	}

	private static function &getActiveHooks() {
		$keys = array_keys(self::$active_hooks);
		$key  = end($keys);
		return self::$active_hooks[$key];
	}
	
	private static function &pushActiveHooks($hooks) {
		array_push(self::$active_hooks, $hooks);
		return $hooks;
	}
	
	private static function &popActiveHooks() {
		$hooks = array_pop(self::$active_hooks);
		return $hooks;
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 */
	private static function &getActiveData() {
		$keys = array_keys(self::$active_data);
		$key = end($keys);
		return self::$active_data[$key];
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $data 
	 * @return void
	 */
	private static function &pushActiveData($data) {
		array_push(self::$active_data, $data);
		return $data;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 */
	private static function popActiveData() {
		return array_pop(self::$active_data);
	}
	
	/**
	 * Returns the request URI minus the query string
	 *
	 * @return string  The request path
	 */
	private static function getRequestPath()
	{
		if (self::$request_path === NULL) {
			self::$request_path = urldecode(
				preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI'])
			);
		}
		
		return self::$request_path;
	}
	
	/**
	 * Returns simplified up headers for matching during the routing process
	 * 
	 * Most HTTP headers are returned verbatim, but Accept-* headers are parsed
	 * selecting the value with the highest Q.
	 *
	 * @return array  An associative array of {lower case header name} => {simplified header value}
	 */
	private static function getHeaders()
	{
		return array(
			'request-method'  => (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : NULL),
			'accept-type'     => (isset($_SERVER['HTTP_ACCEPT']) ? self::getBestAcceptHeader($_SERVER['HTTP_ACCEPT']) : NULL),
			'accept-language' => (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? self::getBestAcceptHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']) : NULL)
		);
	}
	
	/**
	 * Returns the best value from one of the HTTP `Accept-*` headers
	 * 
	 * Derived from MIT fRequest::processAcceptHeader
	 * Source: http://flourishlib.com/browser/fRequest.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2011 Will Bond <will@flourishlib.com>
	 *
	 * @param  string  The accept header string
	 * @return string  The best accept value
	 */
	private static function getBestAcceptHeader($header)
	{
		$types  = explode(',', $header);
		$output = array();
		
		// We use this suffix to force stable sorting with the built-in sort function
		$suffix = sizeof($types);
		
		foreach ($types as $type) {
			$parts = explode(';', $type);
			
			if (!empty($parts[1]) && preg_match('#^q=(\d(?:\.\d)?)#', $parts[1], $match)) {
				$q = number_format((float)$match[1], 5);
			} else {
				$q = number_format(1.0, 5);     
			}
			$q .= $suffix--;
			
			$output[$q] = $parts[0];
		}
		
		krsort($output, SORT_NUMERIC);

		return reset($output);
	}
}