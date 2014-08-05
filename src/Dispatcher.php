<?php

namespace Toadsuck\Core;

use Aura\Router\RouterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Dispatcher
{
	public $opts = [];
	public $router;
	public $namespace = 'Toadsuck\Skeleton';
	public $controller;
	public $action;
	public $id;
	public $app_dir;

	public function __construct($opts = [])
	{
		foreach ($opts as $key => $value) {
			$this->$key = $value;
		}

		$this->opts = $opts;

		// @todo I feel a little dirty about this. Will revisit later.
		if (array_key_exists('app_dir', $opts)) {
			$GLOBALS['TOADSUCK_BASE_PATH'] = $opts['app_dir'];
		}
		
		$this->initializeRoutes();
	}

	public function dispatch()
	{
		// What resource was requested?
		$request = Request::createFromGlobals();

		$path = $request->getPathInfo();

		// Remove trailing slash from the path. This gives us a little more forgiveness in routing
		if ($path != '/') {
			$path = rtrim($path, '/');
		}

		// Find a matching route
		$route = $this->router->match($path, $_SERVER);

		if (!$route) {
			// no route object was returned
			return $this->pageNotFound();
		}

		list($controller, $action, $id) = $this->getAction($route);

		$_SERVER['TS_NAMESPACE'] = $this->namespace;
		$this->controller = strtolower($controller);
		$this->action = $action;

		// instantiate the controller class
		$class = join('\\', [$this->namespace, 'Controllers', $controller]);

		if (!class_exists($class)) {
			return $this->pageNotFound();
		} else {
			$GLOBALS['app_instance'] = $this;
			$page = new $class();
			$page->app = $this;

			// invoke the action method with the id
			$page->$action($id);
		}
	}

	/**
	 * What routes have been configured for this app?
	 */
	public function initializeRoutes()
	{
		$router_factory = new RouterFactory;
		$router = $router_factory->newInstance();

		$routes_file = $this->getAppResourcePath('config/routes.php');

		if (file_exists($routes_file)) {
			// Let the app specify it's own routes.
			include_once($routes_file);
		} else {
			// Fall back on some sensible defaults.
			$router->add(null, '/');
			$router->add(null, '/{controller}');
			$router->add(null, '/{controller}/{action}');
			$router->add(null, '/{controller}/{action}/{id}');
		}

		$this->router = $router;
	}

	public function pageNotFound($message = 'Not Found')
	{
		$response = new Response($message, 404, ['Content-Type' => 'text/plain']);
		$response->send();
	}

	public function getAppResourcePath($file = null)
	{
		return $this->getSrcDir() . '/' . $file;
	}

	public function getSrcDir()
	{
		return array_key_exists('app_dir', $this->opts) ? $this->opts['app_dir'] . '/src' : dirname(__DIR__) . '/src';
	}

	/**
	 * @param $route
	 * @return array
	 */
	public function getAction($route)
	{
		// does the route indicate a controller?
		if (isset($route->params['controller'])) {
			// take the controller class directly from the route
			$controller = ucfirst(strtolower($route->params['controller']));
		} else {
			// use a default controller
			$controller = 'Home';
		}

		// does the route indicate an action?
		if (isset($route->params['action'])) {
			// take the action method directly from the route
			$action = $route->params['action'];
		} else {
			// use a default action
			$action = 'index';
		}

		// does the route indicate an id?
		if (isset($route->params['id'])) {
			// take the action method directly from the route
			$id = $route->params['id'];
			return array($controller, $action, $id);
		} else {
			// use a default action
			$id = null;
			return array($controller, $action, $id);
		}
	}
}
