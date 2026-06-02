<?php
/**
 * Hook loader.
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{0: string, 1: string, 2: int, 3: int}>
	 */
	protected array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array{0: string, 1: string, 2: int, 3: int}>
	 */
	protected array $filters = array();

	/**
	 * Add action.
	 *
	 * @param string          $hook Hook name.
	 * @param object|array    $component Object instance, or callable array e.g. array( Class::class, 'method' ).
	 * @param string          $callback Method name when $component is an object.
	 * @param int             $priority Priority.
	 * @param int             $accepted_args Accepted args.
	 */
	public function add_action( string $hook, $component, string $callback = '', int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = array( $hook, $this->build_callback( $component, $callback ), $priority, $accepted_args );
	}

	/**
	 * Add filter.
	 *
	 * @param string          $hook Hook name.
	 * @param object|array    $component Object instance, or callable array.
	 * @param string          $callback Method name when $component is an object.
	 * @param int             $priority Priority.
	 * @param int             $accepted_args Accepted args.
	 */
	public function add_filter( string $hook, $component, string $callback = '', int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = array( $hook, $this->build_callback( $component, $callback ), $priority, $accepted_args );
	}

	/**
	 * Build WordPress-compatible callback.
	 *
	 * @param object|array $component Component or callable.
	 * @param string       $callback Method name for object callbacks.
	 * @return array<int, mixed>
	 */
	private function build_callback( $component, string $callback ): array {
		if ( is_array( $component ) ) {
			return $component;
		}
		return array( $component, $callback );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $filter ) {
			add_filter( $filter[0], $filter[1], $filter[2], $filter[3] );
		}
		foreach ( $this->actions as $action ) {
			add_action( $action[0], $action[1], $action[2], $action[3] );
		}
	}
}
