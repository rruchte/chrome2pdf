<?php
declare(strict_types=1);

namespace Tesla\Chrome2Pdf;

trait HasPdfAttributes
{
    /**
     * Pdf content
     *
     * @var string
     */
    private $content;

    /**
     * Print background graphics
     *
     * @var bool
     */
    private $printBackground = false;

	/**
	 * Generate a tagged PDF
	 *
	 * @var bool
	 */
	private $generateTaggedPDF = false;
	
	/**
	 * Generate a document outline in the PDF
	 * Generating a document outline at the moment requires a tagged pdf!
	 *
	 * @var bool
	 */
	private $generateDocumentOutline = false;

    /**
     * Give any CSS @page size declared in the page priority over what is declared
     * in width and height or format options.
     * Defaults to false, which will scale the content to fit the paper size.
     *
     * @var bool
     */
    private $preferCSSPageSize = false;

    /**
     * Paper orientation
     *
     * @var string
     */
    private $orientation = 'portrait';

    /**
     * HTML template for the print header. Should be valid HTML markup.
     * Script tags inside templates are not evaluated.
     * Page styles are not visible inside templates.
     *
     * @var string|null
     */
    private $header = null;

    /**
     * HTML template for the print footer. Should be valid HTML markup.
     * Script tags inside templates are not evaluated.
     * Page styles are not visible inside templates.
     *
     * @var string|null
     */
    private $footer = null;

    /**
     * Paper width in inches
     *
     * @var float
     */
    private $paperWidth = 8.27;

    /**
     * Paper height in inches
     *
     * @var float
     */
    private $paperHeight = 11.7;

    /**
     * Page margins in inches
     *
     * @var array
     */
    private $margins = [
        'top' => 0.4,
        'right' => 0.4,
        'bottom' => 0.4,
        'left' => 0.4,
    ];

    /**
     * Default paper formats
     *
     * @var array
     */
    private $paperFormats = [
        'letter' => [8.5, 11],
        'a0' => [33.1, 46.8],
        'a1' => [23.4, 33.1],
        'a2' => [16.54, 23.4],
        'a3' => [11.7, 16.54],
        'a4' => [8.27, 11.7],
        'a5' => [5.83, 8.27],
        'a6' => [4.13, 5.83],
        'legal' => [8.5, 14],
        'tabloid' => [11, 17],
        'ledger' => [17, 11],
    ];

    /**
     * Used for converting measurement units
     * Inspired by https://github.com/GoogleChrome/puppeteer
     *
     * @var array
     */
    private $unitToPixels = [
        'px' => 1,
        'in' => 96,
        'cm' => 37.8,
        'mm' => 3.78
    ];

    /**
     * Scale of the webpage rendering.
     * Scale amount must be between 0.1 and 2.
     *
     * @var int|float
     */
    private $scale = 1;

    /**
     * Display header and footer.
     *
     * @var bool
     */
    private $displayHeaderFooter = false;

    /**
     * Paper ranges to print, e.g., '1-5, 8, 11-13'.
     * By default prints all pages.
     *
     * @var string|null
     */
    private $pageRanges = null;

    public function setPaperFormat(string $format): Chrome2Pdf
    {
        $format = mb_strtolower($format);

        if (!array_key_exists($format, $this->paperFormats)) {
            throw new InvalidArgumentException('Paper format "' . $format . '" does not exist');
        }

        $this->paperWidth = $this->paperFormats[$format][0];
        $this->paperHeight = $this->paperFormats[$format][1];

        return $this;
    }

    public function portrait(): Chrome2Pdf
    {
        $this->orientation = 'portrait';

        return $this;
    }

    public function landscape(): Chrome2Pdf
    {
        $this->orientation = 'landscape';

        return $this;
    }

    public function setMargins(float $top, float $right, float $bottom, float $left, string $unit = 'in'): Chrome2Pdf
    {
        $top = $this->convertToInches($top, $unit);
        $right = $this->convertToInches($right, $unit);
        $bottom = $this->convertToInches($bottom, $unit);
        $left = $this->convertToInches($left, $unit);

        $this->margins['top'] = $top;
        $this->margins['right'] = $right;
        $this->margins['bottom'] = $bottom;
        $this->margins['left'] = $left;

        return $this;
    }

    public function setContent(string $content): Chrome2Pdf
    {
        $this->content = $content;

        return $this;
    }

    public function setHeader(?string $header): Chrome2Pdf
    {
        $this->header = $header;

        return $this;
    }

    public function setFooter(?string $footer): Chrome2Pdf
    {
        $this->footer = $footer;

        return $this;
    }

    public function setPreferCSSPageSize(bool $preferCss): Chrome2Pdf
    {
        $this->preferCSSPageSize = $preferCss;

        return $this;
    }

    public function setPaperWidth(float $width, string $unit = 'in'): Chrome2Pdf
    {
        $this->paperWidth = $this->convertToInches($width, $unit);

        return $this;
    }

    public function setPaperHeight(float $height, string $unit = 'in'): Chrome2Pdf
    {
        $this->paperHeight = $this->convertToInches($height, $unit);

        return $this;
    }

    public function setScale($scale): Chrome2Pdf
    {
        $this->scale = $scale;

        return $this;
    }

    public function setDisplayHeaderFooter(bool $displayHeaderFooter): Chrome2Pdf
    {
        $this->displayHeaderFooter = $displayHeaderFooter;

        return $this;
    }

    public function setPrintBackground(bool $printBg): Chrome2Pdf
    {
        $this->printBackground = $printBg;

        return $this;
    }
    
    public function setGenerateTaggedPDF(bool $generateTaggedPDF): Chrome2Pdf
    {
        $this->generateTaggedPDF = $generateTaggedPDF;
        
        return $this;
    }
    
	public function setGenerateDocumentOutline(bool $generateOutline): Chrome2Pdf
	{
		$this->generateDocumentOutline = $generateOutline;
		
		// generating a document outline at the moment requires a tagged pdf
		$this->generateTaggedPDF = $generateOutline;
		
		return $this;
	}

    public function setPageRanges(?string $pageRanges): Chrome2Pdf
    {
        $this->pageRanges = $pageRanges;

        return $this;
    }

    protected function convertToInches(float $value, string $unit): float
    {
        $unit = mb_strtolower($unit);

        if (!array_key_exists($unit, $this->unitToPixels)) {
            throw new InvalidArgumentException('Unknown measurement unit "' . $unit . '"');
        }

        return ($value * $this->unitToPixels[$unit]) / 96;
    }
}
