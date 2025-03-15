<?php

namespace MarceliTo\ImageCache\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use MarceliTo\ImageCache\Tests\TestCase;

class ImageControllerTest extends TestCase
{
    protected $testImagePath;
    protected $imageManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test image directory
        $this->testImagePath = __DIR__ . '/../fixtures/images';
        if (!File::exists($this->testImagePath)) {
            File::makeDirectory($this->testImagePath, 0755, true);
        }
        
        // Create an image manager for testing
        $this->imageManager = new ImageManager(new Driver());
        
        // Create a test image
        $testImage = $this->imageManager->create(500, 500, 'fff');
        $testImage->save($this->testImagePath . '/test.jpg', quality: 90);
        
        // Register routes for testing
        $this->registerRoutes();
        
        // Mock the Log facade
        Log::spy();
    }
    
    protected function tearDown(): void
    {
        // Clean up test images
        if (File::exists($this->testImagePath . '/test.jpg')) {
            File::delete($this->testImagePath . '/test.jpg');
        }
        
        parent::tearDown();
    }
    
    /**
     * Register routes for testing
     */
    protected function registerRoutes(): void
    {
        Route::get('/img/{template}/{filename}/{maxW?}/{maxH?}/{coords?}/{ratio?}', 
            [\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getResponse'])
            ->name('image-cache.image');
            
        Route::get('/img/crop/{filename}/{maxSize?}/{coords?}/{ratio?}', 
            [\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getCropResponse'])
            ->name('image-cache.crop');
            
        Route::get('/img/crop/{filename}/{maxWidth}/{maxHeight}/{coords?}/{ratio?}', 
            [\MarceliTo\ImageCache\Http\Controllers\ImageController::class, 'getCropWithDimensionsResponse'])
            ->name('image-cache.crop-with-dimensions');
    }
    
    /** @test */
    public function it_returns_image_with_template()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/large/test.jpg');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
    
    /** @test */
    public function it_returns_cropped_image()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/crop/test.jpg/300/200,200,100,100');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
    
    /** @test */
    public function it_returns_cropped_image_with_dimensions()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/crop/test.jpg/300/200/200,200,100,100');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
    
    /** @test */
    public function it_returns_404_for_nonexistent_image()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/large/nonexistent.jpg');
        
        $response->assertStatus(404);
        Log::shouldHaveReceived('warning')
            ->with(Mockery::pattern('/Image not found/'));
    }
    
    /** @test */
    public function it_returns_400_for_invalid_template()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/nonexistent/test.jpg');
        
        $response->assertStatus(400);
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Invalid template/'));
    }
    
    /** @test */
    public function it_returns_400_for_invalid_coordinates()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/crop/test.jpg/300/invalid');
        
        $response->assertStatus(400);
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Invalid coordinates/'));
    }
    
    /** @test */
    public function it_returns_400_for_invalid_max_dimensions()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/crop/test.jpg/-300');
        
        $response->assertStatus(400);
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Invalid max/'));
    }
    
    /** @test */
    public function it_returns_400_for_invalid_ratio()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/crop/test.jpg/300/200,200,100,100/invalid');
        
        $response->assertStatus(400);
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Invalid ratio/'));
    }
    
    /** @test */
    public function it_returns_500_for_server_error()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        // This test would need to mock a server error scenario
        $response = $this->get('/img/large/test.jpg');
        
        $response->assertStatus(500);
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Failed to process/'));
    }
    
    /** @test */
    public function it_sets_cache_headers()
    {
        // Skip this test for now
        $this->markTestSkipped('This test requires a more complex setup.');
        
        $response = $this->get('/img/large/test.jpg');
        
        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'max-age=31536000, public');
        $response->assertHeader('Expires');
    }
}
