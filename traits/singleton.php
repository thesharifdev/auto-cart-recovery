<?php

namespace Auto_Cart_Recovery\Traits;

defined('ABSPATH') || exit;

trait Singleton {

	/**
	 * Single instance of the class.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return static
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Constructor is private.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @return void
	 */
	public function __wakeup() {}
}

