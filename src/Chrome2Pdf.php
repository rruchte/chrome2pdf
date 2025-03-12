<?php
declare(strict_types=1);

namespace Tesla\Chrome2Pdf;

use RuntimeException;
use InvalidArgumentException;
use ChromeDevtoolsProtocol\Context;
use ChromeDevtoolsProtocol\ContextInterface;
use ChromeDevtoolsProtocol\Instance\Launcher;
use ChromeDevtoolsProtocol\Model\Page\EnableRequest;
use ChromeDevtoolsProtocol\Model\Page\NavigateRequest;
use ChromeDevtoolsProtocol\Model\Page\PrintToPDFRequest;
use ChromeDevtoolsProtocol\Model\Page\SetLifecycleEventsEnabledRequest;
use ChromeDevtoolsProtocol\Model\Emulation\SetScriptExecutionDisabledRequest;
use ChromeDevtoolsProtocol\Model\Emulation\SetEmulatedMediaRequest;

class Chrome2Pdf
{
    use HasPdfAttributes;

    /**
     * Context for operations
     *
     * @var ContextInterface
     */
    private $ctx;

    /**
     * Chrome launcher
     *
     * @var Launcher
     */
    private $launcher;

    /**
     * Path to temporary html files
     *
     * @var string|null
     */
    private $tmpFolderPath = null;

    /**
     * Path to Chrome binary
     *
     * @var string|null
     */
    private $chromeExecutablePath = null;

    /**
     * Additional Chrome command line arguments
     *
     * @var array
     */
    private $chromeArgs = [];

    /**
     * Wait for a given lifecycle event before printing pdf
     *
     * @var string|null
     */
    private $waitForLifecycleEvent = null;

    /**
     * Whether script execution should be disabled in the page.
     *
     * @var bool
     */
    private $disableScriptExecution = false;

    /**
     * Web socket connection timeout
     *
     * @var int
     */
    private $timeout = 10;

    /**
     * Emulates the given media for CSS media queries
     *
     * @var string|null
     */
    private $emulateMedia = null;

    public function __construct()
    {
        $this->launcher = new Launcher();
    }

    public function setTempFolder(string $path): Chrome2Pdf
    {
        $this->tmpFolderPath = $path;

        return $this;
    }

    public function getTempFolder(): string
    {
        if ($this->tmpFolderPath === null) {
            return sys_get_temp_dir();
        }

        return $this->tmpFolderPath;
    }

    public function setBrowserLauncher(Launcher $launcher): Chrome2Pdf
    {
        $this->launcher = $launcher;

        return $this;
    }

    public function setContext(ContextInterface $ctx): Chrome2Pdf
    {
        $this->ctx = $ctx;

        return $this;
    }

    public function appendChromeArgs(array $args): Chrome2Pdf
    {
        $this->chromeArgs = array_unique(array_merge($this->chromeArgs, $args));

        return $this;
    }

    public function setChromeExecutablePath(?string $chromeExecutablePath): Chrome2Pdf
    {
        $this->chromeExecutablePath = $chromeExecutablePath;

        return $this;
    }

    public function setWaitForLifecycleEvent(?string $event): Chrome2Pdf
    {
        $this->waitForLifecycleEvent = $event;

        return $this;
    }

    public function setDisableScriptExecution(bool $disableScriptExecution): Chrome2Pdf
    {
        $this->disableScriptExecution = $disableScriptExecution;

        return $this;
    }

    public function setTimeout(int $timeout): Chrome2Pdf
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setEmulateMedia(?string $emulateMedia): Chrome2Pdf
    {
        $this->emulateMedia = $emulateMedia;

        return $this;
    }

    /**
     * Generate PDF
     *
     * @return string|null
     */
    public function pdf(): ?string
    {
        $this->ctx = Context::withTimeout(Context::background(), $this->timeout);

        if (!$this->content) {
            throw new InvalidArgumentException('Missing content, set content by calling "setContent($html)" method');
        }

        $launcher = $this->launcher;
        if ($this->chromeExecutablePath) {
            $launcher->setExecutable($this->chromeExecutablePath);
        }
        $ctx = $this->ctx;
        $instance = $launcher->launch($ctx, ...$this->chromeArgs);

        $filename = $this->writeTempFile();
        $pdfOptions = $this->getPDFOptions();

        $pdfResult = null;

        try {
            $tab = $instance->open($ctx);
            $tab->activate($ctx);

            $devtools = $tab->devtools();
            try {
                if ($this->disableScriptExecution) {
                    $devtools->emulation()->setScriptExecutionDisabled($ctx, SetScriptExecutionDisabledRequest::builder()->setValue(true)->build());
                }

                if ($this->emulateMedia !== null) {
                    $devtools->emulation()->setEmulatedMedia($ctx, SetEmulatedMediaRequest::builder()->setMedia($this->emulateMedia)->build());
                }

                $devtools->page()->enable($ctx, EnableRequest::builder()->build());
                $devtools->page()->setLifecycleEventsEnabled($ctx, SetLifecycleEventsEnabledRequest::builder()->setEnabled(true)->build());
                $devtools->page()->navigate($ctx, NavigateRequest::builder()->setUrl('file://' . $filename)->build());
                $devtools->page()->awaitLoadEventFired($ctx);

                if (null !== $this->waitForLifecycleEvent) {
                    do {
                        $lifecycleEvent = $devtools->page()->awaitLifecycleEvent($ctx)->name;
                    } while($lifecycleEvent !== $this->waitForLifecycleEvent);
                }

                $response = $devtools->page()->printToPDF($ctx, $pdfOptions);
                $pdfResult = base64_decode($response->data);
            } finally {
                $devtools->close();
            }
        } finally {
            $instance->close();
        }

        $this->deleteTempFile($filename);

        return $pdfResult;
    }

    /**
     * Write content to temporary html file
     *
     * @return string
     */
    protected function writeTempFile(): string
    {
        $filepath = rtrim($this->getTempFolder(), DIRECTORY_SEPARATOR);

        if (!is_dir($filepath)) {
            if (false === @mkdir($filepath, 0777, true) && !is_dir($filepath)) {
                throw new RuntimeException(sprintf("Unable to create directory: %s\n", $filepath));
            }
        } elseif (!is_writable($filepath)) {
            throw new RuntimeException(sprintf("Unable to write in directory: %s\n", $filepath));
        }

        $filename = $filepath . DIRECTORY_SEPARATOR . uniqid('chrome2pdf_', true) . '.html';

        file_put_contents($filename, $this->content);

        return $filename;
    }

    /**
     * Delete temporary file
     *
     * @param string $filename
     * @return void
     */
    protected function deleteTempFile(string $filename): void
    {
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * Populate PDF options
     *
     * @return array
     */
    protected function getPDFOptions(): PrintToPDFRequest
    {
        $pdfOptions = PrintToPDFRequest::make();

        $pdfOptions->landscape = $this->orientation === 'landscape';
        $pdfOptions->marginTop = $this->margins['top'];
        $pdfOptions->marginRight = $this->margins['right'];
        $pdfOptions->marginBottom = $this->margins['bottom'];
        $pdfOptions->marginLeft = $this->margins['left'];
        $pdfOptions->preferCSSPageSize = $this->preferCSSPageSize;
        $pdfOptions->printBackground = $this->printBackground;
        $pdfOptions->scale = $this->scale;
        $pdfOptions->displayHeaderFooter = $this->displayHeaderFooter;

        if ($this->paperWidth) {
            $pdfOptions->paperWidth = $this->paperWidth;
        }

        if ($this->paperHeight) {
            $pdfOptions->paperHeight = $this->paperHeight;
        }

        if ($this->pageRanges) {
            $pdfOptions->pageRanges = $this->pageRanges;
        }

        if ($this->header || $this->footer) {
            if ($this->header === null) {
                $this->header = '<p></p>';
            }

            if ($this->footer === null) {
                $this->footer = '<p></p>';
            }

            $pdfOptions->displayHeaderFooter = true;
            $pdfOptions->headerTemplate = $this->header;
            $pdfOptions->footerTemplate = $this->footer;
        }

		if($this->generateTaggedPDF)
		{
			$pdfOptions->generateTaggedPDF = true;
		}
		
		if($this->generateDocumentOutline)
		{
			$pdfOptions->generateDocumentOutline = true;
			// generating a document outline at the moment requires a tagged pdf
			$pdfOptions->generateTaggedPDF = true;
		}

        return $pdfOptions;
    }
}
