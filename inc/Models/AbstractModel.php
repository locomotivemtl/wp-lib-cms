<?php

namespace WpLib\Models;

use WpLib\Contracts\Bootable;
use WpLib\Contracts\Models\Model;

/**
 * Base Model
 */
abstract class AbstractModel implements
    Bootable,
    Model
{
    /**
     * Boots the model.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->__register_hooks();
        $this->register_hooks();
    }

    /**
     * Registers required actions and filters.
     *
     * @abstract Useful for custom abstract classes that must always hook core functionality.
     *
     * @return void
     */
    protected function __register_hooks(): void
    {
        // Do nothing.
    }

    /**
     * Registers actions and filters.
     *
     * @abstract Useful for concrete classes to hook custom functionality.
     *
     * @return void
     */
    protected function register_hooks(): void
    {
        // Do nothing.
    }
}
