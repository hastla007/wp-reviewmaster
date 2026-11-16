<?php
/**
 * Module contract.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain;

/**
 * A self-contained feature area that hooks itself into WordPress.
 */
interface Module {

	/**
	 * Register hooks. Must not perform database writes or remote requests.
	 */
	public function register( Plugin $plugin ): void;
}
