<?php

namespace MarceliTo\ImageCache\Facades;

use Illuminate\Support\Facades\Facade;

class ImageCache extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'image-cache';
	}
}
