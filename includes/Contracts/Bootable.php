<?php

namespace Locomotive\Cms\Contracts;

/**
 * Bootable interface
 */
interface Bootable
{
    /**
     * Boots the class.
     *
     * @return void
     */
    public function boot(): void;
}
