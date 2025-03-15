<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class Thumbnail implements ModifierInterface
{
	/**
	 * Size for the thumbnail (width and height)
	 */
	protected int $size = 300;
	
	/**
	 * Apply filter to image
	 */
	public function apply(ImageInterface $image): ImageInterface
	{
		return $image->cover($this->size, $this->size);
	}
}
