<?php

namespace MarceliTo\ImageCache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

class Medium implements ModifierInterface
{
	/**
	 * Maximum width for medium landscape images
	 */    
	protected int $max_width = 1200;    

	/**
	 * Maximum height for medium portrait images
	 */    
	protected int $max_height = 675;
	
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
