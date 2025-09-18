<?php
namespace Jet_Engine\Listings\Components;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Stack {

	/**
	 * Holds stack of called objects at the current moment.
	 *
	 * @var Component[]
	 */
	private $stack = array();

	/**
	 * Returns current stack
	 *
	 * @return Component[] Stack of components
	 */
	public function get_stack() {
		return $this->stack;
	}

	/**
	 * Returns stack depth
	 *
	 * @return int Stack depth
	 */
	public function get_depth() {
		return count( $this->stack );
	}

	/**
	 * Returns currently rendered component
	 *
	 * @return Component|null Current component instance or null if no component is being processed
	 */
	public function get_current_component() {
		return $this->stack[ count( $this->stack ) - 1 ] ?? null;
	}

	/**
	 * Check if component is processed now
	 *
	 * @param  Component $component Component instance to check;
	 *                              if not set, check if any component is being processed
	 * @return bool                 True if component being processed, false otherwise
	 */
	public function is_in_stack( $component = null ) {
		if ( empty( $component ) ) {
			return count( $this->stack ) > 0;
		}

		return in_array( $component, $this->stack );
	}

	/**
	 * Add component to the stack
	 *
	 * @param  Component $component Component instance to add to the stack
	 * 
	 * @return bool                 True if component was added, false if already in stack
	 */
	public function increase_stack( $component ) {
		if ( ! $this->is_in_stack( $component ) ) {
			$this->stack[] = $component;
			return true;
		}

		return false;
	}

	/**
	 * Remove component from the stack
	 *
	 * @param Component $component Component instance to remove from the stack
	 */
	public function decrease_stack() {
		array_pop( $this->stack );
	}

}
