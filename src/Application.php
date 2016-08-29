<?php
namespace samsoncms\app\gallery;

use samson\activerecord\Field;
use samsoncms\api\CMS;
use samsoncms\api\Material;
use samsoncms\api\MaterialField;
use samsonphp\event\Event;
use samsoncms\app\gallery\tab\Gallery;
use samsonframework\resource\ResourceMap;

/**
 * SamsonCMS application for interacting with material gallery
 * @author egorov@samsonos.com
 */
class Application extends \samsoncms\Application
{
    /** Application name */
    public $name = 'Галлерея';

    /** Hide application access from main menu */
    public $hide = true;

    /** @var string Entity class name */
    protected $entity = '\samson\activerecord\gallery';

    /** Identifier */
    protected $id = 'gallery';

    /** @var \samsonphp\fs\FileService File service pointer */
    protected $fs;

    /**
     * Initialize module
     * @param array $params Collection of parameters
     * @return bool True if success
     */
    public function init(array $params = array())
    {
        // TODO: Should be change to DI in future
        // Set pointer to file service
        $this->fs = & $this->system->module('fs');

        // Subscribe to material form created event for custom tab rendering
        Event::subscribe('samsoncms.material.form.created', array($this, 'tabBuilder'));

        // Subscribe to event - add gallery field additional field type
        Event::subscribe('cms_field.select_create', array($this, 'fieldSelectCreate'));

        return parent::init($params);
    }

    /**
     * Render all gallery additional fields as material form tabs
     * @param \samsoncms\app\material\form\Form $form Material form insctance
     */
    public function tabBuilder(\samsoncms\app\material\form\Form & $form)
    {
        // If we have related structures
        if (count($form->navigationIDs)) {
            // Get all gallery additional field for material form structures
            $galleryFields = $this->query->entity(\samsoncms\api\Field::class)
                ->where('Type', 9)
                ->join('structurefield')
                ->where('structurefield_StructureID', $form->navigationIDs)
                ->exec();

            // Create tab for each additional gallery field
            foreach ($galleryFields as $field) {
                $form->tabs[] = new Gallery($this, $this->query, $form->entity, $field);
            }
        }
    }

    /** Field select creation event handler */
    public function fieldSelectCreate(&$list)
    {
        $list[t('Галлерея', true)] = 9;
    }

    /**
     * Controller for deleting material image from gallery
     * @param string $imageId Gallery Image identifier
     * @return array Async response array
     */
    public function __async_delete($imageId, $materialFieldID = null)
    {
        // Async response
        $result = array();

        /** @var \samson\activerecord\gallery $image */
        $image = null;

        // Find gallery record in DB
        if ($this->findAsyncEntityByID($imageId, $image, $result)) {
            if ($image->Path != '') {
                // Get image path
                $imagePath = $this->formImagePath($image->Path, $image->Src);
                // Physically remove file from server
                if ($this->imageExists($imagePath)) {
                    $this->fs->delete($imagePath);
                }

                // Delete thumbnails
                if (class_exists('\samson\scale\ScaleController', false)) {
                    /** @var \samson\scale\ScaleController $scale */
                    $scale = $this->system->module('scale');

                    foreach (array_keys($scale->thumnails_sizes) as $folder) {
                        // Form image path for scale module
                        $imageScalePath = $this->formImagePath($image->Path . $folder . '/', $image->Src);
                        if ($this->imageExists($imageScalePath)) {
                            $this->fs->delete($imageScalePath);
                        }
                    }
                }
            }

            // Remove record from DB
            $image->delete();
        }

        return $this->__async_update($materialFieldID);
    }

    /**
     * Controller for rendering gallery images list
     * @param int $materialFieldId Gallery identifier, represented as materialfield id
     * @return array Async response array
     */
    public function __async_update($materialFieldId)
    {
        return array('status' => true, 'html' => $this->getHTML($materialFieldId));
    }

	/**
     * Controller for getting quantity image in gallery.
     *
     * @param integer $materialFieldId identefier Table MaterialField
     * @return array Async response array with additional param count.
     */
    public function __async_getCount($materialFieldId)
    {
        // @var array $result Result of asynchronous controller
        $response = array('status' => 1);
        // Getting quantity from DB by param materialFieldId
        $response['count'] = $this->query
            ->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)
            ->where(MaterialField::F_PRIMARY, $materialFieldId)
            ->count();

        return $response;
    }
	
	/**
     *  Controller for update material image properties alt from gallery.
     *
     *  @param int $imageId Gallery image identifier
     *  @return array async response
     */
    public function __async_updateAlt($imageId)
    {
        // @var array $result Result of asynchronous controller
        $result = array('status' => false);
        // @var \samson\activerecord\gallery $image Image to insert into editor
        $image = null;
        //get data from ajax
        $data = json_decode(file_get_contents('php://input'), true);
        //set input value
        $value = trim($data['value']);


        // Getting first field image
        if ($this->query->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)
            ->where('PhotoID', $imageId)->first($image)) {
            // Update value alt
            $image->Description = $value;
            // Save result in datebase
            $image->save();
            // Set success status
            $result['status'] = true;
            // Reduce number of characters to 25
            $result['description'] = utf8_limit_string($value, 25, '...');
            // Return result value
            $result['value'] = $value;
        }

        return $result;
    }
	
    /**
     * Controller for image upload
     * @param string $materialFieldId Gallery identifier, represented as materialfield id
     * @return array Async response array
     */
    public function __async_upload($materialFieldId)
    {
        $result = array('status' => false);

        /** @var \samsonphp\upload\Upload $upload Pointer to uploader object */
        $upload = null;
        // Verify extension image
        if ($this->verifyExtensionFile()) {
            // Uploading file to server and path current material identifier
            if (uploadFile($upload, array(), $materialFieldId)) {
                /** @var \samson\activerecord\materialfield $materialField MaterialField object to identify gallery */
                $materialField = null;
//            /** @var array $children List of related materials */
//            $children = null;
                // Check if participant has not uploaded remix yet
                if (
                $this->query->entity(MaterialField::ENTITY)
                    ->where('MaterialFieldID', $materialFieldId)
                    ->where('Active', 1)
                    ->first($materialField)
                ) {
                    // Create empty db record
                    $photo = new \samson\activerecord\gallery(false);
                    $photo->Name = $upload->realName();
                    $photo->Src = $upload->name();
                    $photo->Path = $upload->path();
                    $photo->materialFieldId = $materialField->id;
                    $photo->MaterialID = $materialField->MaterialID;
                    $photo->size = $upload->size();
                    $photo->Active = 1;
                    $photo->save();


                    // Call scale if it is loaded
                    if (class_exists('\samson\scale\ScaleController', false)) {
                        /** @var \samson\scale\ScaleController $scale */
                        $scale = $this->system->module('scale');
                        $scale->resize($upload->fullPath(), $upload->name(), $upload->uploadDir);
                    }

                    $result['status'] = true;
                }
            }
        } else {
            $errorText = "Файл ( " . urldecode($_SERVER['HTTP_X_FILE_NAME']) . " ) не является картинкой!";
            $result = array('status' => false, 'errorText' => $errorText);
        }

        return $result;
    }

    /**
     * method for verify extension file
     * @return boolean true - file is image, false - file not image
     * */
    private function verifyExtensionFile()
    {
        $supported_image = array(
            'gif',
            'jpg',
            'jpeg',
            'png'
        );

        $fileName = $_SERVER['HTTP_X_FILE_NAME'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, $supported_image)) {
            return true;
        }
        return false;
    }

    /**
     * Function to save image priority
     * @return array Async response array
     */
    public function __async_priority()
    {
        $result = array('status' => true);

        // If we have changed priority of images
        if (isset($_POST['ids'])) {
            // For each received image id
            for ($i = 0; $i < count($_POST['ids']); $i++) {
                /** @var \samson\activerecord\gallery $photo Variable to store image info */
                $photo = null;
                // If we have such image in database
                if ($this->query->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)->where('PhotoID', $_POST['ids'][$i])->first($photo)) {
                    // Reset it's priority and save it
                    $photo->priority = $i;
                    $photo->save();
                } else {
                    $result['status'] = false;
                    $result['message'] = 'Can not find images with specified ids!';
                }
            }
        } else {
            $result['status'] = false;
            $result['message'] = 'There are no images to sort!';
        }
        return $result;
    }

    /**
     * Asynchronous function to get image editor
     * @param int $imageId Image identifier to insert into editor
     * @return array Result array
     */
    public function __async_show_edit($imageId)
    {
        /** @var array $result Result of asynchronous controller */
        $result = array('status' => false);
        /** @var \samson\activerecord\gallery $image Image to insert into editor */
        $image = null;
        if ($this->query->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)->where('PhotoID', $imageId)->first($image)) {

            /** @var string $path Path to image */
            $path = $this->formImagePath($image->Path, $image->Src);

            // If there is image for this path
            if ($this->imageExists($path)) {
                $result['status'] = true;
                $result['html'] = $this->view('editor/index')
                    ->set($image, 'image')
                    ->set($path, 'path')
                    ->output();
            }
        }
        return $result;
    }

    /**
     * Applies all changes with the image and save it
     * @param int $imageId Edit image identifier
     * @return array
     */
    public function __async_edit($imageId)
    {
        /** @var array $result Result of asynchronous controller */
        $result = array('status' => false);
        /** @var \samson\activerecord\gallery $image Image to insert into editor */
        $image = null;
        /** @var resource $imageResource Copy of edit image */
        $imageResource = null;
        /** @var resource $croppedImage Resource of cropped image */
        $croppedImage = null;

        // If there is such image in database
        if ($this->query->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)->where('PhotoID', $imageId)->first($image)) {

            // Form proper path
            $path = $this->formImagePath($image->Path, $image->Src);

            // Check image extension
            switch (pathinfo($path, PATHINFO_EXTENSION)) {
                case 'jpeg':
                case 'jpg':
                    $imageResource = imagecreatefromjpeg($path);
                    $croppedImage = $this->cropImage($imageResource);
                    $result['status'] = imagejpeg($croppedImage, $path);
                    break;
                case 'png':
                    $imageResource = imagecreatefrompng($path);
                    $croppedImage = $this->cropTransparentImage($imageResource);
                    $result['status'] = imagepng($croppedImage, $path);
                    break;
                case 'gif':
                    $imageResource = imagecreatefromgif($path);
                    $croppedImage = $this->cropTransparentImage($imageResource);
                    $result['status'] = imagegif($croppedImage, $path);
                    break;
            }

            // delete temporary images
            imagedestroy($croppedImage);
            imagedestroy($imageResource);
        }
        return $result;
    }

    /**
     * Render gallery images list
     * @param string $materialFieldId Material identifier
     * @return string html representation of image list
     */
    public function getHTML($materialFieldId)
    {
        // Get all material images
        $items_html = '';
        /** @var array $images List of gallery images */
        $images = null;
        // there are gallery images
        if ($this->query->entity(CMS::MATERIAL_IMAGES_RELATION_ENTITY)->where('materialFieldId', $materialFieldId)->orderBy('priority')->exec($images)) {
            /** @var \samson\cms\CMSGallery $image */
            foreach ($images as $image) {
                // Get image size string
                $size = ', ';
                // Get image path
                $path = $this->formImagePath($image->Path, $image->Src);

                // if file doesn't exist
                if (!$this->imageExists($path)) {
                      $path = ResourceMap::find('www/img/no-img.png', $this);
                }

                // set image size string representation, if it is not 0
                $size = ($image->size == 0) ? '' : $size . $this->humanFileSize($image->size);

                // Render gallery image tumb
                $items_html .= $this->view('tumbs/item')
                    ->set($image, 'image')
                    ->set(utf8_limit_string($image->Description, 25, '...'), 'description')
                    ->set(utf8_limit_string($image->Name, 18, '...'), 'name')
                    ->set($path, 'imgpath')
                    ->set($size, 'size')
                    ->set($materialFieldId, 'material_id')
                    ->output();
            }
        }

        // Render content into inner content html
        return $this->view('tumbs/index')
            ->set($items_html, 'images')
            ->set($materialFieldId, 'material_id')
            ->output();
    }

    /**
     * Function to form image size
     * @param int $bytes Bytes count
     * @param int $decimals Decimal part of number(count of numbers)
     * @return string Generated image size
     */
    public function humanFileSize($bytes, $decimals = 2)
    {
        /** @var string $sizeLetters Size shortcuts */
        $sizeLetters = 'BKBMBGBTBPB';
        $factor = (int)(floor((strlen($bytes) - 1) / 3));
        $sizeLetter = ($factor <= 0) ? substr($sizeLetters, 0, 1) : substr($sizeLetters, $factor * 2 - 1, 2);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $sizeLetter;
    }

    /**
     * Checks if image exists, supports old database structure
     * @param string $imagePath Path to image(Full or not)
     * @param string $imageSrc Image name, if it wasn't in $imagePath
     * @return bool
     */
    private function imageExists($imagePath, $imageSrc = null)
    {
        // If image name is sewhere parameter
        if (isset($imageSrc)) {
            // Form path to the image
            $imageFullPath = $this->formImagePath($imagePath, $imageSrc);
        } else {
            // Path was already set
            $imageFullPath = $imagePath;
        }

        // Call file service existence method
        return $this->fs->exists($imageFullPath);
    }

    /**
     * Function to form image path correctly, also supports old database structure
     * @param string $imagePath Path to the image
     * @param string $imageSrc Image name
     * @return string Full path to image
     */
    private function formImagePath($imagePath, $imageSrc)
    {
        // Get old-way image path, remove full path to check file
        if (empty($imagePath)) {
            $path = $imageSrc;
        } else { // Use new CORRECT way
            $path = $imagePath . $imageSrc;
        }

        // form relative path to the image
        $dir = quotemeta(__SAMSON_BASE__);
        // TODO: WTF? Why do we need this, need comments!!!
        if (strpos($path, 'http://') === false) {
            if ($dir == '/') {
                return substr($path, 1);
            } else {
                return preg_replace('/' . addcslashes($dir, '/') . '/', '', $path);
            }
        }

        return $path;
    }
}
