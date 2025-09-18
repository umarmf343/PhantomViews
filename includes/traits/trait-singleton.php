<?php
/**
 * Simple singleton trait.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Traits;

trait Singleton {
/**
 * Instance of the class.
 *
 * @var static
 */
protected static $instance = null;

/**
 * Get singleton instance.
 *
 * @return static
 */
public static function instance() {
if ( null === static::$instance ) {
static::$instance = new static();
}

return static::$instance;
}

/**
 * Cloning is forbidden.
 */
private function __clone() {}

/**
 * Unserializing instances of this class is forbidden.
 */
final public function __wakeup() {
throw new \RuntimeException( 'Cannot unserialize singleton.' );
}
}
