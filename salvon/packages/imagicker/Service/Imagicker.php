<?php

namespace Salvon\Imagicker\Service;

use Imagick;
use ImagickException;
use Salvon\Imagicker\Enum\Position;
use Salvon\Imagicker\DTO\Dimensions;
use Salvon\Imagicker\Enum\Extension;
use Salvon\Imagicker\Config\Defaults;
use Salvon\Imagicker\DTO\Compression;
use Salvon\Imagicker\Enum\Orientation;
use Salvon\Imagicker\Enum\ThumbnailSize;
use Salvon\Imagicker\DTO\IPTC as ParsedIPTC;
use Salvon\Imagicker\Enum\ExtensionSaveMode;
use Salvon\Imagicker\Exception\OverwriteException;
use Salvon\Imagicker\Exception\UnsupportedExtension;
use Salvon\Imagicker\Exception\OpeningFailedException;

final class Imagicker
{
    private Imagick $file;

    /**
     * @throws OpeningFailedException
     */
    public function __construct(string $path, private ?array $thumbSizes = null, private ?int $thumbQuality = null)
    {
        if (is_null($this->thumbSizes)) {
            $this->thumbSizes = Defaults::thumbSizes();
        }

        if (is_null($this->thumbQuality)) {
            $this->thumbQuality = Defaults::quality();
        }

        $this->file = $this->imagick($path);
    }

    /**
     * @throws ImagickException
     * @throws OverwriteException
     * @throws UnsupportedExtension
     */
    public function save(string $path, ExtensionSaveMode $extensionSaveMode = ExtensionSaveMode::FROM_GIVEN_PATH_WITH_FORMAT_UPDATE): bool
    {
        if ($path === $this->filePath()) {
            throw new OverwriteException();
        }

        $info = pathinfo($path);

        $extension = $info['extension'] ?? $this->extension(true);

        if (is_string($extension) && $extensionSaveMode === ExtensionSaveMode::FROM_GIVEN_PATH_WITH_FORMAT_UPDATE) {
            $this->setExtension($extension);
        }

        if ($extensionSaveMode === ExtensionSaveMode::FROM_ORIGINAL_MIME_TYPE) {
            $extension = $this->extension(true);
        }

        $filename = $info['filename'] ?? $this->baseName();
        $dirname = $info['dirname'] ?? $this->pathInfo('dirname');

        if ($extensionSaveMode === ExtensionSaveMode::FROM_GIVEN_PATH) {
            return $this->write(sprintf('%s/%s', $dirname, $filename));
        }

        return $this->write(sprintf('%s/%s.%s', $dirname, $filename, $extension));
    }

    /**
     * @throws ImagickException
     */
    public function overwrite(): string
    {
        $this->write($this->filePath());

        return $this->filePath();
    }

    /**
     * @throws ImagickException
     */
    public function pathInfo(string $key): string
    {
        return pathinfo($this->filePath())[$key];
    }

    /**
     * @throws ImagickException
     */
    public function filePath(): string
    {
        return $this->file->getImageFileName();
    }

    /**
     * @throws ImagickException
     */
    public function baseName(): string
    {
        return $this->pathInfo('basename');
    }

    /**
     * @throws ImagickException
     */
    public function fileName(): string
    {
        return $this->pathInfo('filename');
    }

    public function file(): Imagick
    {
        return $this->file;
    }

    public function quality(): int
    {
        return $this->file->getImageCompressionQuality();
    }

    /**
     * @throws ImagickException
     */
    public function setQuality(int $quality): self
    {
        if ($quality > 100 || $quality < 1) {
            $quality = Defaults::quality();
        }

        $this->file->setImageCompressionQuality($quality);

        return $this;
    }

    public function compression(): ?Compression
    {
        return Compression::tryFrom($this->file->getImageCompression());
    }

    /**
     * @throws ImagickException
     */
    public function setCompression(Compression $compression = Compression::UNDEFINED): self
    {
        $this->file->setImageCompression($compression->value);

        return $this;
    }

    /**
     * @throws ImagickException
     * @throws UnsupportedExtension
     */
    public function extension(bool $asString = false): Extension|string
    {
        $ext = Extension::cast($this->file->getImageFormat());

        if ($asString) {
            return $ext->value;
        }

        return $ext;
    }

    /**
     * @throws ImagickException | UnsupportedExtension
     */
    public function setExtension(Extension|string $extension): self
    {
        if (is_string($extension)) {
            $extension = Extension::cast($extension);
        }

        $this->file->setImageFormat($extension->value);

        return $this;
    }

    /**
     * @throws ImagickException
     */
    public function resize(int $width = null, int $height = null, bool $oversize = false): self
    {
        $dimensions = Determine::newDimensions($this->file, $width, $height, $oversize);

        $this->file->resizeImage(
            $dimensions->width,
            $dimensions->height,
            Imagick::FILTER_LANCZOS,
            1,
        );

        return $this;
    }

    /**
     * @throws ImagickException
     */
    public function rescale(int $width = null, int $height = null): self
    {
        $dimensions = Determine::newDimensions($this->file, $width, $height, true);

        $this->file->scaleImage(
            $dimensions->width,
            $dimensions->height,
        );

        return $this;
    }

    /**
     * @throws ImagickException
     */
    public function thumbnail(ThumbnailSize $size, ?int $quality = null): void
    {
        $orientation = $this->orientation();
        $height = null;
        $width = null;

        if ($orientation === Orientation::HORIZONTAL) {
            $width = $this->thumbSizes[$size->value];
        }

        if ($orientation === Orientation::VERTICAL) {
            $height = $this->thumbSizes[$size->value];
        }

        $this->resize($width, $height);
        $this->setQuality($quality ?? $this->thumbQuality);
    }

    /**
     * @throws ImagickException
     * @throws OpeningFailedException
     */
    public function watermark(string $watermarkPath, Position $startingPoint = Position::TOP_LEFT, int $offsetX = 0, int $offsetY = 0, ?float $scaleOfBaseImage = null): void
    {
        $watermark = $this->imagick($watermarkPath);

        Watermark::add($this->file, $watermark, $startingPoint, $offsetX, $offsetY, $scaleOfBaseImage);
    }

    /**
     * !! EXPERIMENTAL FEATURE !!
     * @throws ImagickException
     */
    public function backgroundToAlpha(): self
    {
        // Get the color of the top-left pixel of the image
        $colorToTransparent = $this->file->getImagePixelColor(0, 0);

        // Add a 10-pixel border around the image using the color of the top-left pixel
        $this->file->borderImage($colorToTransparent, 10, 10);

        // Replace all pixels of the color rgb(0, 0, 255) with transparency, except the border pixels
        $this->file->floodFillPaintImage('rgb(0, 0, 255)', 2500, $colorToTransparent, 0, 0, false);
        $this->file->transparentPaintImage('rgb(0,0,255)', 0, 100, false);

        $this->file->despeckleImage();
        $this->file->trimImage(0.3);
        $this->file->setAntiAlias(true);

        return $this;
    }

    /**
     * @throws ImagickException
     */
    public function dpi(): int
    {
        return Determine::dpi($this->file);
    }

    /**
     * @throws ImagickException
     */
    public function orientation(): Orientation
    {
        return Determine::orientation($this->file);
    }

    /**
     * @throws ImagickException
     */
    public function dimensions(): Dimensions
    {
        return Determine::dimensions($this->file);
    }

    /**
     * @throws ImagickException
     */
    public function mimeType(): string
    {
        return $this->file->getImageMimeType();
    }

    public function iptc(): ParsedIPTC
    {
        return IPTC::parse($this->filePath());
    }

    /**
     * @throws ImagickException
     */
    public function imageBlob(): string
    {
        return $this->file->getImageBlob();
    }

    /**
     * @throws ImagickException
     */
    public function stream(): never
    {
        header(sprintf('Content-Type: %s', $this->mimeType()));
        echo $this->imageBlob();
        die();
    }

    /**
     * @throws OpeningFailedException
     */
    private function imagick(string $path): Imagick
    {
        try {
            return new Imagick($path);
        } catch (ImagickException $imagickException) {
            throw new OpeningFailedException($path, $imagickException->getMessage());
        }
    }

    /**
     * @throws ImagickException
     */
    private function write(?string $as = null): bool
    {
        return $this->file->writeImage($as);
    }
}
