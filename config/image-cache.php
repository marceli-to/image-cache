<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Image Cache Configuration
	|--------------------------------------------------------------------------
	|
	| This file contains the configuration for the image cache package.
	|
	*/

	// Cache path relative to storage_path()
	'cache_path' => 'app/public/cache',

	// Cache lifetime in minutes (default: 30 days)
	'lifetime' => 43200,

	// Paths to search for original images
	'paths' => [
		storage_path('app/public/uploads'),
	],

	// Available templates
	'templates' => [
		'xsmall' => \MarceliTo\ImageCache\Templates\XSmall::class,
		'small' => \MarceliTo\ImageCache\Templates\Small::class,
		'medium' => \MarceliTo\ImageCache\Templates\Medium::class,
		'large' => \MarceliTo\ImageCache\Templates\Large::class,
		'xlarge' => \MarceliTo\ImageCache\Templates\XLarge::class,
		'xxlarge' => \MarceliTo\ImageCache\Templates\XXLarge::class,
		'huge' => \MarceliTo\ImageCache\Templates\Huge::class,
		'thumbnail' => \MarceliTo\ImageCache\Templates\Thumbnail::class,
		'crop' => \MarceliTo\ImageCache\Templates\Crop::class,
	],
	
	// Route configuration
	'register_routes' => true,
	
	// Middleware for the image routes
	'middleware' => ['web'],
	
	// Crop filter type: 'maxSize' or 'dimensions' (maxWidth and maxHeight)
	'crop_filter_type' => 'maxSize',
];
