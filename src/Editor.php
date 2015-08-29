<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 29.08.2015
 * Time: 11:12
 */
namespace samsoncms\app\gallery;

/**
 * Image editor logic
 * @package samsoncms\app\gallery
 */
class Editor
{
    /**
     * Function to reduce code size in __async_edit method
     * @param resource $imageResource Image to crop
     * @return bool|resource Cropped image
     */
    public function cropImage($imageResource)
    {
        /** @var int $imageTransparency Transparent color */
        $imageTransparency = imagecolorallocatealpha($imageResource, 255, 255, 255, 127);
        /** @var resource $rotatedImage Rotated image resource */
        $rotatedImage = imagerotate($imageResource, -($_POST['rotate']), $imageTransparency);
        /** @var resource $croppedImage Cropped image resource */
        $croppedImage = imagecrop($rotatedImage, array('x' => $_POST['crop_x'],
            'y' => $_POST['crop_y'],
            'width' => $_POST['crop_width'],
            'height' => $_POST['crop_height']));
        // Delete temp image resource
        imagedestroy($rotatedImage);
        // Return cropped image
        return $croppedImage;
    }

    /**
     * Same as cropImage() for transparent images
     * @param resource $imageResource Image to crop
     * @return bool|resource Cropped image
     */
    public function cropTransparentImage($imageResource)
    {
        /** @var int $imageTransparency Transparent color */
        $imageTransparency = imagecolorallocatealpha($imageResource, 255, 255, 255, 127);
        /** @var resource $croppedImage Cropped image resource */
        $croppedImage = imagecreatetruecolor($_POST['crop_width'], $_POST['crop_height']);
        // Fill new image with transparent color
        imagefill($croppedImage, 0, 0, $imageTransparency);
        // Save Alpha chanel
        imagesavealpha($croppedImage, true);
        /** @var resource $rotatedImage Rotated image resource */
        $rotatedImage = imagerotate($imageResource, -($_POST['rotate']), $imageTransparency);
        // Copy rotated image to cropped one
        imagecopy(
            $croppedImage,
            $rotatedImage,
            0,
            0,
            $_POST['crop_x'],
            $_POST['crop_y'],
            $_POST['crop_width'],
            $_POST['crop_height']
        );
        // Delete temp image resource
        imagedestroy($rotatedImage);
        // Return result image
        return $croppedImage;
    }
}
