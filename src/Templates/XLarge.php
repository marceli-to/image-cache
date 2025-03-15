<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class XLarge implements ModifierInterface
{
	/**
	 * Maximum width for xlarge landscape images
	 */    
	protected $max_width = 2000;    

	/**
	 * Maximum height for xlarge portrait images
	 */    
	protected $max_height = 1125;
	
	/**
	 * Apply filter to image
	 */
	public function apply(ImageInterface $image): ImageInterface
	{
		// Get image orientation
		$orientation = $image->height() > $image->width() ? 'portrait' : 'landscape';
		
		// Scale down the image based on orientation while maintaining aspect ratio
		if ($orientation === 'landscape')
		{
			return $image->scaleDown(width: $this->max_width);
		} 
		else
		{
			return $image->scaleDown(height: $this->max_height);
		}
	}
}
