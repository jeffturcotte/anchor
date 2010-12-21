<?php
class AnchorNotFoundException extends Exception {}
class AnchorContinueException extends Exception {}
class AnchorProgrammerException extends Exception {}

class AnchorDefaultController {
	function notFound() {
		header("HTTP/1.1 404 Not Found");
		echo '<h1>NOT FOUND</h1>';
		echo "\n\n";
	}
}

class Anchor {
	private static $active_callback = NULL;

	private static $cache = array(
		'find' => array(),
		'underscorize' => array(),
		'camelize' => array()
	);

	private static $data = NULL;
	private static $hooks = array();
	private static $not_found_callback = 'AnchorDefaultController::notFound';
	private static $routes = array();
	private static $request_path = NULL;
	
	private static $param_types = array(
		':' => '[^/]+',
		'$' => '[A-Za-z][A-Za-z0-9_]+',
		'%' => '[0-9]+',
		'@' => '[A-Za-z]+'
	);

	private static $callback_param_names = array(
		'namespace'    => 'namespace',
		'short_class'  => 'class',
		'short_method' => 'method'
	);
	
	private static $callback_param_formatters = array(
		'namespace'    => 'Anchor::underscorize',
		'short_class'  => 'Anchor::upperCamelize',
		'short_method' => 'Anchor::lowerCamelize'
	);
	
	private static $namespace_separator = '\\';
	
	/**
	 * undocumented function
	 *
	 * @param string $url 
	 * @param string $to 
	 * @param string $closure 
	 * @return void
	 */
	public function add($url, $callback, $closure=NULL) {
		if ($callback instanceof Closure) {
			$closure = $callback;
			$callback = '';
		}
		
		$route = (object) 'Route';
		$route->url = self::parseUrl($url);
		$route->callback = self::parseCallback($callback);
		$route->closure = $closure;
		
		array_push(self::$routes, $route);
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $callback_key 
	 * @return void
	 */
	public function find($callback_key) {
		$param_values = func_get_args();
		$callback_key = trim(array_shift($param_values));
		$param_names  = preg_split('/(\s+:)|(\s+)|((?<!:):(?!:))/', $callback_key);
		$callback     = array_shift($param_names);
		
		if (!isset($param_names))
		$param_names_flipped = array_flip($param_names);
		$url_params = (count($param_names))
			? array_combine($param_names, $param_values)
			: array();
		
		$best_route = NULL;

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
					$best_route === NULL ||
					$low_dist > $dist && $low_diff >= $diff ||
					$low_dist >= $dist && $high_sect < $sect ||
					$low_dist >= $dist && $high_sect <= $sect && $low_diff > $diff
				);
				
				if ($is_best) {
					$best_route = $route;
					$low_dist = $dist;
					$high_sect = $sect;
					$low_diff = $diff;
				}
			}
		}
		
		$route  = $best_route;		
		
		if (!$route) {
			throw new AnchorProgrammerException("No route could be found matching the callback. ($callback)");
		}
		
		$anchor = $route->url->subject;
		
		foreach($route->url->params as $name => $param) {
			if (isset($params[$name])) {
				$anchor = str_replace(
					$param->symbol, 
					urlencode($params[$name]),
					$anchor
				);
				unset($params[$name]);
			}
		}
		
		foreach($params as $name => $param) {
			if (in_array($name, self::$callback_param_names)) {
				unset($params[$name]);
			}
		}
		
		if (!empty($params)) {
			$anchor .= '?' . http_build_query($params);
		}
		
		return $anchor;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $hook_name 
	 * @param string $route_callback 
	 * @param string $hook_callback 
	 * @return void
	 */
	public function hook($hook_name, $route_callback, $hook_callback) 
	{
		$hook = (object) 'Hook';
		$hook->hook = $hook_name;
		$hook->route_callback = self::parseCallback($route_callback);
		$hook->callback  = $hook_callback;
		array_push(self::$hooks, $hook);
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $url 
	 * @param string $redirect 
	 * @param string $permanent 
	 * @return void
	 */
	public function redirect($url, $redirect, $permanent=FALSE)
	{
		$route = (object) 'Redirect';
		$route->url = self::parseUrl($url);
		$route->redirect = self::parseUrl($redirect);
		$route->permanent_redirect = $permanent_redirect;
		array_push(self::$routes, $route);
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 */
	public static function run() {
		self::add('*', self::$not_found_callback);
		
		self::$request_path = urldecode(
			preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI'])
		);
		
		foreach(self::$routes as $route) {
			try {
				$params = array();

				if (!self::matchUrl($route->url, self::$request_path, $params)) {
					continue;
				}
				
				$params = array_merge($_GET, $params);
				
				self::dispatch($route, $params);
			} catch (AnchorNotFoundException $e) {
				self::dispatch(end(self::$routes), $params);
			} catch (AnchorContinueException $e) {
				continue;
			}
		}
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $route 
	 * @param string $params 
	 * @return void
	 */
	private function buildCallback($route, &$params) 
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
	 * 
	 *
	 * @param string $hooks 
	 * @param string $hook 
	 * @param string $data 
	 * @param string $exception 
	 * @return void
	 */
	private function callHookCallbacks(&$hooks, $hook, &$data, &$exception=NULL)
	{
		if (isset($hooks[$hook])) {
			foreach($hooks[$hook] as $hook_obj) {
				call_user_func_array(
					self::resolveCallback($hook_obj->callback),
					array($data, $exception)
				);
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
	 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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
	private function collectHooks($callback) 
	{	
		$callback = self::parseCallback($callback);
		
		$hooks = array();
		
		foreach(self::$hooks as $hook) {
			if (self::matchCallback($hook->route_callback, $callback->scalar)) {
				if (!isset($hooks[$hook->hook])) {
					$hooks[$hook->hook] = array();
				}
				array_push($hooks[$hook->hook], $hook);
			}
		}
		
		return $hooks;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $route 
	 * @param string $params 
	 * @return void
	 */
	private static function dispatch($route, &$params) 
	{
		if ($route->closure) {
			self::dispatchClosure($route, $params);
		} else if ($route->redirect) {
			self::dispatchRedirect($route, $params);
		} else {
			self::dispatchCallback($route, $params);
		}
		
		exit();
	}
	
	private static function dispatchRedirect($route, &$params) 
	{
		$url    = self::getDomain() . self::buildUrl($route->redirect, $params);
		$status = ($route->permanent_redirect) ? 301 : 302;

		header('Location: ' . $url, TRUE, $status);
		echo $url;
		exit();
	}
	
	/**
	 * Returns the current domain name, with protcol prefix. Port will be included if not 80 for HTTP or 443 for HTTPS.
	 * 
	 * @return string  The current domain name, prefixed by `http://` or `https://`
	 */
	static public function getDomain()
	{
		$port = (isset($_SERVER['SERVER_PORT'])) ? $_SERVER['SERVER_PORT'] : NULL;
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			return 'https://' . $_SERVER['SERVER_NAME'] . ($port && $port != 443 ? ':' . $port : '');
		} else {
			return 'http://' . $_SERVER['SERVER_NAME'] . ($port && $port != 80 ? ':' . $port : '');
		}
	}
	
	private static function buildUrl($url, &$params)
	{
		$anchor = $url->subject;

		foreach($url->params as $name => $param) {
			if (isset($params[$name])) {
				$anchor = str_replace(
					$param->symbol, 
					urlencode($params[$name]),
					$anchor
				);
				unset($params[$name]);
			}
		}

		foreach($params as $name => $param) {
			if (in_array($name, self::$callback_param_names)) {
				unset($params[$name]);
			}
		}

		if (!empty($params)) {
			$anchor .= '?' . http_build_query($params);
		}

		return $anchor;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $route 
	 * @param string $params 
	 * @return void
	 */
	private static function dispatchCallback($route, &$params=array())
	{
		$callback = self::buildCallback($route, $params);
		$callback = self::parseCallback($callback);
		
		$reflected_method = NULL;
		$reflected_call   = NULL;
		
		try {
			$reflected_method = new ReflectionMethod($callback->scalar);

			if (!$reflected_method->isPublic()) {
				exit('method not public');
			}
			
			if (strpos('__', $reflected_method->getName()) === 0) {
				exit('method looks magic');
			}
			
			if ($reflected_method->isStatic()) {
				exit('method is static');
			}
		} catch (ReflectionException $e) {}
		
		if (!$reflected_method) {
			try {
				$reflected_call = new ReflectionMethod($callback->class . '::__call');
			
				if (!$reflected_call->isPublic()) {
					exit('__call not public');
				}
			
			} catch (ReflectionException $e) {}
		}
		
		if (!$reflected_method && !$reflected_call) {
			throw new AnchorContinueException();
		}
		
		self::$active_callback = $callback;
		
		// initialize data object
		if (!is_object(self::$data)) {
			self::$data = (object) '';
			unset(self::$data->scalar);
		}
		
		$_GET = $params;
		
		$hooks = self::collectHooks($callback->scalar);
		self::callHookCallbacks($hooks, 'init', self::$data);
		$hooks = self::collectHooks($callback->scalar);
		
		$class = $callback->class;
		$short_method = $callback->short_method;
		$instance = new $class();
			
		self::callHookCallbacks($hooks, 'before', self::$data);			
			
		try {
			$instance->$short_method(self::$data);
		} catch (Exception $e) {
		    $exception = new ReflectionClass($e);
				
			do {
				$hook_name = "catch:" . $exception->getName();
				if (self::callHookCallbacks($hooks, $hook_name, self::$data, $e)) {
					break;
				}
			} while ($exception = $exception->getParentClass());
			
 			if (!$exception) {
				throw $e;
			}
		}
			
		self::callHookCallbacks($hooks, 'after', self::$data);
	}

	/**
	 * undocumented function
	 *
	 * @param string $route 
	 * @param string $params 
	 * @return void
	 */
	private static function dispatchClosure($route, &$params=array())
	{
		// initialize data object
		if (!is_object(self::$data)) {
			self::$data = (object) '';
			unset(self::$data->scalar);
		}
		
		$hooks = self::collectHooks($route->callback->scalar);
		self::callHookCallbacks($hooks, 'init', self::$data);
		$hooks = self::collectHooks($route->callback->scalar);
		
		call_user_func_array(
			$route->closure,
			array(self::$data)
		);
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
	private function matchCallback($parsed_callback, $callback_string, &$params=NULL)
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
	
	/**
	 * undocumented function
	 *
	 * @param string $parsed_url 
	 * @param string $url_string 
	 * @param string $params 
	 * @return void
	 */
	private function matchUrl($parsed_url, $url_string, &$params=NULL)
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
	 * undocumented function
	 *
	 * @param string $callback 
	 * @return void
	 */
	private function parseCallback($callback) 
	{
		if (is_object($callback)) {
			return $callback;
		}
		
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

		// create callback pattern
		$pattern = '(?P<' . self::$callback_param_names['short_method'] . '>' . str_replace('*', '.+', $callback->short_method) . ')';

		if ($callback->short_class) {
			$pattern = '(?P<' . self::$callback_param_names['short_class'] . '>' . str_replace('*', '.+', $callback->short_class) . ')::' . $pattern;
		}
		
		$separator = str_replace('\\', '\\\\', self::$namespace_separator);
		
		if ($callback->namespace) {
			$pattern = '(?P<' . self::$callback_param_names['namespace'] . '>' . str_replace('*', '.+', $callback->namespace) . ')' . $separator . $pattern;
		}
		
		$callback->pattern = '/^' . $pattern . '$/';
		
		return $callback;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $url 
	 * @return void
	 */
	private function parseUrl($url) 
	{
		$url = str_replace(' ', '', $url);
		$url = str_replace('`', '\`', $url);
		$url = rtrim($url, '/');
		
		if (substr($url->pattern, 0, 1) != '*' && $url->pattern != '*') {
			$url = '/' . $url;
		}
		
		$url = preg_replace('/\/+/', '/', $url);
		
		$url = (object) $url;
		$url->pattern = $url->scalar;
		$url->subject = rtrim($url->scalar, '/*');
		$url->params = array();
		
		preg_match_all(
			'/\{?((:|\$|%|@)([a-z][_a-z0-9]*))\}?/i', 
			$url->scalar, 
			$matches
		);
	
		$param_symbols = $matches[0];
		$param_types   = $matches[2];
		$param_names   = $matches[3];
		
		// validate no dupe symbols
		if (count($param_names) != count(array_unique($param_names))) {
			exit('error: dupe names');
		}
		
		foreach($param_symbols as $key => $param_symbol) {
			$param = (object) $param_names[$key];
			$param->symbol = $param_symbol;
			$param->type = $param_types[$key];
			$param->name = $param_names[$key];
			$url->params[$param->name] = $param;
			
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
		
		return $url;
	}
	
	/**
	 * undocumented function
	 *
	 * @param string $callback 
	 * @return void
	 */
	private function resolveCallback($callback)
	{
		if ($callback instanceof Closure) {
			return $callback;
		}
		
		if (strpos($callback, '?') === FALSE || !self::$active_callback) {
			return $callback;
		}
		
		$callback = self::parseCallback($callback);
		
		if ($callback->short_method == '?' && self::$active_callback->short_method) {
			$callback->short_method = self::$active_callback->short_method;
		}
		if ($callback->short_class == '?' && self::$active_callback->short_class) {
			$callback->short_class = self::$active_callback->short_class;
		}
		if ($callback->namespace == '?' && self::$active_callback->namespace) {
			$callback->namespace = self::$active_callback->namespace;
		}
		
		$resolved = $callback->short_method;
		
		if ($callback->short_class) {
			$resolved = $callback->short_class . '::' . $resolved;
		}
		
		if ($callback->namespace) {
			$resolved = $callback->namespace . self::$namespace_separator . $resolved;
		}
		
		return $resolved;
	}
	
	/**
	 * Converts a `camelCase` or `underscore_notation` string to `underscore_notation`
	 *
	 * Derived from MIT fGrammer::camelize
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 * License: http://flourishlib.com/docs/LicenseAgreement
	 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
	 *
	 * @param  string $string  The string to convert
	 * @return string  The converted string
	 */
	private static function &underscorize($string)
	{
		$key = $string;
		
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
	
	public static function enableLegacyNamespacing()
	{
		self::$callback_param_formatters['namespace'] = 'Anchor::upperCamelize';
		self::$namespace_separator = "_";
	}
	
	public static function triggerContinue()
	{
		throw new AnchorContinueException();
	}
	
	public static function triggerNotFound()
	{
		throw new AnchorNotFoundException();
	}
	
	public static function setNamespaceParameter($name) 
	{
		self::$callback_param_names['namespace'] = $name;
	}
	
	public static function setShortClassParameter($name) 
	{
		self::$callback_param_names['short_class'] = $name;
	}
	
	public static function setShortMethodParameter($name) 
	{
		self::$callback_param_names['short_method'] = $name;
	}
	
	public static function setNotFoundCallback($callback)
	{
		self::$not_found_callback = $callback;
	}
}