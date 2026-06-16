<?php
declare(strict_types=1);

namespace Tesla\Chrome2Pdf;

trait HasScreenshotAttributes
{
	/**
	 * Pdf content
	 *
	 * @var string
	 */
	private $content;
	
	/**
	 * Specify screenshot type, can be either jpeg or png. Defaults to 'png'.
	 *
	 * @var string
	 */
	private $format = 'png';
	
	/**
	 * The quality of the image, between 0-100. Not applicable to png images.
	 *
	 * @var int
	 */
	private $quality = 80;
	
	/**
	 * When true, takes a screenshot of the full scrollable page. Defaults to true.
	 *
	 * @var bool
	 */
	private bool $captureBeyondViewport = true;
	
	/**
	 * An object which specifies clipping region of the page. Should have the following fields:
	 *
	 * x <number> x-coordinate of top-left corner of clip area
	 * y <number> y-coordinate of top-left corner of clip area
	 * width <number> width of clipping area
	 * height <number> height of clipping area
	 * scale <number> Page scale factor
	 *
	 * @var array|int[]
	 */
	private array $clip = [
		'x' => 0,
		'y' => 0,
		'width' => 1024,
		'height' => 1024,
		'scale' => 1.0
	];
	
	/**
	 * Capture the screenshot from the surface, rather than the view. Defaults to true.
	 *
	 * @var bool
	 */
	private bool $fromSurface = true;
	
	/**
	 * Optimize image encoding for speed, not for resulting size (defaults to false)
	 *
	 * @var bool|null
	 */
	public bool $optimizeForSpeed = false;
	
	/**
	 * Allowed types
	 *
	 * @var array
	 */
	private $formats = [
		'jpeg',
		'png'
	];
	
	public function setContent(string $content): Chrome2Screenshot
	{
		$this->content = $content;
		
		return $this;
	}
	
	public function setFormat(string $format): Chrome2Screenshot
	{
		$format = mb_strtolower($format);
		
		if (!in_array($format, $this->formats))
		{
			throw new InvalidArgumentException('Screenshot type "' . $format . '" does not exist');
		}
		
		$this->format = $format;
		
		return $this;
	}
	
	public function setQuality(int $quality): Chrome2Screenshot
	{
		
		if ($quality < 0 || $quality > 100)
		{
			throw new InvalidArgumentException('Quality must be between 0 and 100');
		}
		
		$this->quality = $quality;
		
		return $this;
	}
	
	public function setCaptureBeyondViewport(bool $captureBeyondViewport): Chrome2Screenshot
	{
		$this->captureBeyondViewport = $captureBeyondViewport;
		
		return $this;
	}
	
	public function setClip(int $x, int $y, int $width, int $height, float $scale=1.0): Chrome2Screenshot
	{
		$this->clip['x'] = $x;
		$this->clip['y'] = $y;
		$this->clip['width'] = $width;
		$this->clip['height'] = $height;
		$this->clip['scale'] = $scale;
		
		return $this;
	}
	
	public function setFromSurface(bool $fromSurface): Chrome2Screenshot
	{
		$this->fromSurface = $fromSurface;
		
		return $this;
	}
	
	public function setOptimizeForSpeed(bool $optimizeForSpeed): Chrome2Screenshot
	{
		$this->optimizeForSpeed = $optimizeForSpeed;
		
		return $this;
	}
}
