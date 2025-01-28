<?php

/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */

namespace lib;

use ImagickException;

class ImageResize
{

    /**
     * @var array
     */
    public array $opts;
    /**
     * @var bool
     */
    protected $image;
    /**
     * @var
     */
    protected $image_object;
    /**
     * @var int
     */
    protected $width;
    /**
     * @var int
     */
    protected $height;


    /**
     * image constructor.
     *
     * @param       $fileName
     * @param  array  $opts
     */
    public function __construct($fileName, $opts = [])
    {

        // Default Class Options
        $this->opts = $opts + [

                // E.g.: jpegoptim --strip-all --all-normal -o -q -p {file}
                'optimize-jpeg' => false,

                // Eg.: pngquant -f --speed 1 {file} -o {file}
                'optimize-png'  => false,

            ];

        // *** Open up the file
        if ($this->image = $this->openImage($fileName)) {

            // *** Get width and height
            $this->width  = imagesx($this->image);
            $this->height = imagesy($this->image);

        } else {

            $this->image = false;

        }

    }


    /**
     * Opens image file for modification
     *
     * @param $file
     *
     * @return bool|resource
     */
    private function openImage($file)
    {

        // *** Get extension
        $extension = strtolower(strrchr($file, '.'));

        // *** Use appropriate function depending on file extension
        switch ($extension) {
            case '.jpg':
            case '.jpeg':
                $img = @imagecreatefromjpeg($file);
                break;
            case '.gif':
                $img = @imagecreatefromgif($file);
                break;
            case '.png':
                $img = @imagecreatefrompng($file);
                break;
            case '.webp':
                $img = @imagecreatefromwebp($file);
                break;
            default:
                $img = false;
                break;
        }

        return $img;

    }


    /**
     * Wrap function for this object to make every thing much faster image::handle()
     *
     * @param  string  $image
     * @param  string  $size
     * @param  null  $mode
     * @param  null  $new_filename
     * @param  null  $opt
     *
     * @return string
     */
    public static function handle($image, $size, $mode = null, $new_filename = null, $opt = [])
    {

        // Check if file exits
        if ( ! is_file($image)) {
            return false;
        }

        // Create new self object and resize image
        $object = new self($image);
        $object->resize($size, $mode, $opt)->save(( ! empty($new_filename)) ? $new_filename : $image);

        return $object->image;

    }


    /**
     * Save image to a file
     *
     * @param  string  $savePath  Where to save the image including new file name
     * @param  int  $imageQuality  Quality percentage
     *
     * @return bool
     */
    public function save($savePath, $imageQuality = 100)
    {

        // Exit on error
        if ( ! $this->image) {
            return false;
        }

        // *** Get extension
        $extension = strtolower(strrchr($savePath, '.'));
        $return    = false;

        // Depends on extension
        switch ($extension) {

            case '.jpg':
            case '.jpeg':
                if (imagetypes() & IMG_JPG) {
                    $return = imagejpeg($this->image_object, $savePath, $imageQuality);
                }
                break;

            case '.webp':
                if (imagetypes() & IMG_WEBP) {
                    $return = imagewebp($this->image_object, $savePath, $imageQuality);
                }
                break;

            case '.gif':
                if (imagetypes() & IMG_GIF) {
                    $return = imagegif($this->image_object, $savePath);
                }
                break;

            case '.png':

                // *** Scale quality from 0-100 to 0-9
                $scaleQuality = round(($imageQuality / 100) * 9);

                // *** Invert quality setting as 0 is best, not 9
                $invertScaleQuality = 9 - $scaleQuality;

                if (imagetypes() & IMG_PNG) {
                    $return = imagepng($this->image_object, $savePath, $invertScaleQuality);
                }

                break;

            default:
                // *** No extension - No save.
                break;
        }

        imagedestroy($this->image_object);
        $this->compress($savePath);

        return $return;

    }


    /**
     * @param        $size
     * @param  string  $option
     * @param  array  $opts
     *
     * @return $this
     */
    public function resize($size, $option = "auto", $opts = [])
    {

        // Exit on error!
        if ( ! $this->image) {
            return $this;
        }

        // Size takes (width)x(height) or 50% or auto arguments
        if ($size === "auto") {

            // Auto, usually to just resample image
            $newWidth  = (float) $this->width;
            $newHeight = (float) $this->height;

        } elseif (strpos($size, ':') !== false) {

            // Crop by ratio (16:9, 4:3, 1:1, etc...)
            $ratio  = explode(':', $size);
            $divide = $ratio[0] / $ratio[1];

            $newWidth  = $this->width;
            $newHeight = round($this->width / $divide);

        } elseif (strpos($size, 'x') === false) {

            // Resize by %
            $newWidth  = round(($this->width / 100) * $size);
            $newHeight = round(($this->height / 100) * $size);

        } else {

            // Resize by numXnum pix
            $size = explode('x', $size);
            [$newWidth, $newHeight] = $size;

        }

        // *** Get optimal width and height - based on $option
        $optionArray   = $this->getDimensions($newWidth, $newHeight, $option);
        $optimalWidth  = $optionArray['optimalWidth'];
        $optimalHeight = $optionArray['optimalHeight'];

        // *** Resample - create image canvas of x, y size
        $this->image_object = imagecreatetruecolor($optimalWidth, $optimalHeight);

        // *** Alpha Fix
        imagealphablending($this->image_object, false);
        imagesavealpha($this->image_object, true);

        // *** Resample image
        imagecopyresampled($this->image_object, $this->image, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);

        // *** if option is 'crop', then crop too
        if ($option === 'crop') {

            $this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight, $opts);

        }

        return $this;

    }


    /**
     * @param $file
     *
     * @return bool|string
     */
    public function compress($file)
    {

        // Preferred method
        if ( ! extension_loaded('imagick')) {
            return $this->compressMagic($file);
        }

        // Only if imagick is not available
        return $this->compressExt($file);

    }


    /**
     * @param $newWidth
     * @param $newHeight
     * @param $option
     *
     * @return array
     */
    private function getDimensions($newWidth, $newHeight, $option)
    {

        // get image dimensions based on option
        switch ($option) {

            case 'exact':
                $optimalWidth  = $newWidth;
                $optimalHeight = $newHeight;
                break;

            case 'portrait':
                $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;
                break;

            case 'landscape':
                $optimalWidth  = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);
                break;

            case 'auto':
                $optionArray   = $this->getSizeByAuto($newWidth, $newHeight);
                $optimalWidth  = $optionArray['optimalWidth'];
                $optimalHeight = $optionArray['optimalHeight'];
                break;

            case 'crop':
                $optionArray   = $this->getOptimalCrop($newWidth, $newHeight);
                $optimalWidth  = $optionArray['optimalWidth'];
                $optimalHeight = $optionArray['optimalHeight'];
                break;
        }

        return [
            'optimalWidth'  => $optimalWidth,
            'optimalHeight' => $optimalHeight,
        ];

    }


    /**
     * @param  int  $optimalWidth
     * @param  int  $optimalHeight
     * @param  int  $newWidth
     * @param  int  $newHeight
     * @param  array  $opt
     *
     * @return $this
     */
    private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight, $opt = [])
    {

        // *** Find center - this will be used for the crop
        if (( ! isset($opt['cropY']) || $opt['cropY'] < 0) && ( ! isset($opt['cropX']) || $opt['cropX'] < 0)) {

            $opt['cropX'] = round(($optimalWidth / 2) - ($newWidth / 2));
            $opt['cropY'] = round(($optimalHeight / 2) - ($newHeight / 2));

        }

        // Get most current image object
        $crop = $this->image_object;

        // *** Create back
        $this->image_object = imagecreatetruecolor($newWidth, $newHeight);

        // *** Preserve Alpha
        imagealphablending($this->image_object, false);
        imagesavealpha($this->image_object, true);

        // *** Now resample image
        imagecopyresampled(
            $this->image_object,
            $crop,
            0,
            0,
            (int) $opt['cropX'],
            (int) $opt['cropY'],
            (int) $newWidth,
            (int) $newHeight,
            (int) $newWidth,
            (int) $newHeight
        );

        return $this;

    }


    /**
     * Optimize image via image magic (image::compress($path);)
     *
     * @param $file
     *
     * @return bool|string
     */
    public function compressMagic($file)
    {

        // Return if not loaded
        if ( ! extension_loaded('imagick')) {
            return false;
        }

        // Some vars
        $extension = strtolower(strrchr($file, '.'));
        $file      = realpath($file);

        try {

            // JPG/JPEG
            if ($extension === '.jpeg' || $extension === '.jpg') {

                $image = new Imagick($file);
                $image->setImageFormat('jpg');
                $image->stripImage();
                $image->setImageCompressionQuality(85);
                $image->optimizeImageLayers();
                $image->writeImage($file);

                //PNG
            } elseif ($extension === '.png') {

                $image = new Imagick($file);
                $image->setImageFormat('png');
                $image->stripImage();
                $image->setImageCompressionQuality(85);
                $image->optimizeImageLayers();
                $image->writeImage($file);

            }

        } catch (ImagickException $e) {

            // Lets ignore exception since this function is just compressing images

        }

        return false;

    }


    /**
     * Optimize image via external software (image::compressExt($path);)
     *
     * @param $file
     *
     * @return bool|string
     */
    public function compressExt($file)
    {

        // Some vars
        $extension = strtolower(strrchr($file, '.'));
        $file      = realpath($file);

        // Exit if real path returns empty string
        if (empty($file)) {
            return false;
        }

        // Match JPEG/JPG Extension
        if ($extension === '.jpeg' || $extension === '.jpg') {

            // Try to use specified software to losslessly compress jpeg image
            if ($this->opts['optimize-jpeg'] !== false && ! empty($this->opts['optimize-jpeg']) && is_file($file)) {
                return exec(str_replace('{file}', $file, $this->opts['optimize-jpeg']));
            }

            // Match PNG
        } elseif ($extension === '.png') {

            // Try to use specified software to losslessly compress png image
            if ($this->opts['optimize-png'] !== false && ! empty($this->opts['optimize-png']) && is_file($file)) {
                return exec(str_replace('{file}', $file, $this->opts['optimize-png']));
            }

        }

        return false;

    }


    /**
     * @param $newHeight
     *
     * @return mixed
     */
    private function getSizeByFixedHeight($newHeight)
    {

        $ratio = $this->width / $this->height;

        return round($newHeight * $ratio);

    }


    /**
     * @param $newWidth
     *
     * @return mixed
     */
    private function getSizeByFixedWidth($newWidth)
    {

        $ratio = $this->height / $this->width;

        return round($newWidth * $ratio);

    }


    /**
     * @param $newWidth
     * @param $newHeight
     *
     * @return array
     */
    private function getSizeByAuto($newWidth, $newHeight)
    {

        if ($this->height < $this->width) {

            // *** Image to be resized is wider (landscape)
            $optimalWidth  = $newWidth;
            $optimalHeight = $this->getSizeByFixedWidth($newWidth);

        } elseif ($this->height > $this->width) {

            // *** Image to be resized is taller (portrait)
            $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
            $optimalHeight = $newHeight;

        } else {

            // *** Image to be resizerd is a square
            if ($newHeight < $newWidth) {

                $optimalWidth  = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);

            } elseif ($newHeight > $newWidth) {

                $optimalWidth  = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;

            } else {

                // *** Square being resized to a square
                $optimalWidth  = $newWidth;
                $optimalHeight = $newHeight;

            }
        }

        return ['optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight];
    }


    /**
     * @param $newWidth
     * @param $newHeight
     *
     * @return array
     */
    private function getOptimalCrop($newWidth, $newHeight)
    {

        $heightRatio = $this->height / $newHeight;
        $widthRatio  = $this->width / $newWidth;

        if ($heightRatio < $widthRatio) {

            $optimalRatio = $heightRatio;

        } else {

            $optimalRatio = $widthRatio;

        }

        $optimalHeight = round($this->height / $optimalRatio);
        $optimalWidth  = round($this->width / $optimalRatio);

        return [
            'optimalWidth'  => $optimalWidth,
            'optimalHeight' => $optimalHeight,
        ];

    }

}