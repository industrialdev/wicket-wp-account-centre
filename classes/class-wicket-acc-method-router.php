<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Magic wrapper Class for WACC() helpers
 */
class MethodRouter
{
	private $instances = [];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Register all classes
		$this->instances = [
			'Front'        => new Front(),
			'BlocksLoader' => new Blocks(),
			'Helpers'      => new Helpers(),
			'Router'       => new Router(),
		];
	}

	/**
	 * Call magic method
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call($name, $arguments)
	{
		foreach ($this->instances as $instance) {
			if (method_exists($instance, $name)) {
				return call_user_func_array([$instance, $name], $arguments);
			}
		}
		throw new \Exception("Method $name does not exist in any registered class.");
	}

	/**
	 * Static call magic method
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function __callStatic($name, $arguments)
	{
		$router = new self();
		return $router->__call($name, $arguments);
	}
}
