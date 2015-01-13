<?php
namespace samson\cms\web\gallery;

/**
 * SamsonCMS application for interacting with material gallery
 * @author egorov@samsonos.com
 */
class App extends \samson\cms\App
{
    /** Application name */
    public $name = 'Галлерея';

    /** Hide application access from main menu */
    public $hide = true;

    /** Identifier */
    protected $id = 'gallery';

    /** @var \samson\fs\FileService File service pointer */
    protected $fs;

    // TODO: @omaximus comments?
    private $priority = array();

    /**
     * Initialize module
     * @param array $params Collection of parameters
     * @return bool True if success
     */
    public function init(array $params = array())
    {
        // Set pointer to file service
        $this->fs = & m('fs');

        // Subscribe to event - add gallery field additional field type
        \samsonphp\event\Event::subscribe('cms_field.select_create', array($this, 'fieldSelectCreate'));

        return parent::init($params);
    }

    /** Field select creation event handler */
    public function fieldSelectCreate(&$list)
    {
        $list[t('Галлерея', true)] = 9;
    }

    /**
     * @see \samson\core\ExternalModule::init()
     * @return bool|void Returns module check result
     */
    public function prepare()
    {
        // TODO: Change this logic to make tab loading more simple
        // Create new gallery tab object to load it
        class_exists(\samson\core\AutoLoader::className('MaterialTab', 'samson\cms\web\gallery'));
    }

    /**
     * Controller for deleting material image from gallery
     * @param string $id Gallery Image identifier
     * @return array Async response array
     */
    public function __async_delete($id)
    {
        // Async response
        $result = array( 'status' => false );

        /** @var \samson\activerecord\gallery $db_image */
        $db_image = null;

        // Find gallery record in DB
        if (dbQuery('gallery')->id($id)->first($db_image)) {
            if ($db_image->Path != '') {

                // Get image path
                $imagePath = $this->formImagePath($db_image->Path, $db_image->Src);
                // Physically remove file from server
                if ($this->imageExists($imagePath)) {
                    $this->fs->delete($imagePath);
                }

                /** @var \samson\scale\ScaleController $scale */
                $scale = m('scale');

                // Delete thumbnails
                if (class_exists('\samson\scale\ScaleController', false)) {
                    foreach (array_keys($scale->thumnails_sizes) as $folder) {
                        // Form image path for scale module
                        $imageScalePath = $this->formImagePath($db_image->Path . $folder . '/', $db_image->Src);
                        if ($this->imageExists($imageScalePath)) {
                            $this->fs->delete($imageScalePath);
                        }
                    }
                }
            }

            // Remove record from DB
            $db_image->delete();

            $result['status'] = true;
        }

        return $result;
    }

    /**
	 * Controller for rendering gallery images list
	 * @param string $materialId Material identifier
	 * @return array Async response array
	 */
    public function __async_update($materialId)
    {
        return array('status' => true, 'html' => $this->html_list($materialId));
    }

    /**
	 * Controller for image upload
	 * @param string $material_id Material identifier 
	 * @return array Async response array
	 */
    public function __async_upload($material_id)
    {
        // Async response
        s()->async(true);

        $result = array('status' => false);

        /** @var \samson\upload\Upload $upload  Pointer to uploader object */
        $upload = null;
        // Uploading file to server and path current material identifier
        if (uploadFile($upload, array(), $material_id)) {
            /** @var \samson\activerecord\material $material Current material object */
            $material = null;
            /** @var array $children List of related materials */
            $children = null;
            // Check if participant has not uploaded remix yet
            if (dbQuery('material')->cond('MaterialID', $material_id)->cond('Active', 1)->first($material)) {
                // Create empty db record
                $photo = new \samson\activerecord\gallery(false);
                $photo->Name = $upload->realName();
                $photo->Src = $upload->name();
                $photo->Path = $upload->path();
                $photo->MaterialID = $material->id;
                $photo->size = $upload->size();
                $photo->Active = 1;
                $photo->save();

                if (dbQuery('material')->cond('parent_id', $material->id)->cond('type', 2)->exec($children)) {
                    foreach ($children as $child) {
                        $childPhoto = new \samson\activerecord\gallery(false);
                        $childPhoto->Name = $upload->realName();
                        $childPhoto->Src = $upload->name();
                        $childPhoto->Path = $upload->path();
                        $childPhoto->MaterialID = $child->id;
                        $childPhoto->size = $upload->size();
                        $childPhoto->Active = 1;
                        $childPhoto->save();
                    }
                }

                // Call scale if it is loaded
                if (class_exists('\samson\scale\ScaleController', false)) {
                    /** @var \samson\scale\ScaleController $scale */
                    $scale = m('scale');
                    $scale->resize($upload->fullPath(), $upload->name(), $upload->uploadDir);
                }

                $result['status'] = true;
            }
        }

        return $result;
    }

    // TODO: @omaximus Comments?
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
                if (dbQuery('gallery')->cond('PhotoID', $_POST['ids'][$i])->first($photo)) {
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
        if (dbQuery('gallery')->cond('PhotoID', $imageId)->first($image)) {

            /** @var string $path Path to image */
            $path = $this->formImagePath($image->Path, $image->Src);

            // If there is image for this path
            if ($this->imageExists($path)) {
                $result['status'] = true;
                $result['html'] = $this->view('editor/index')
                    ->set($image, 'image')
                    ->set('path', $path)
                    ->output();
            }
        }
        return $result;
    }

    /**
     * TODO: @omaximus Comments?
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

        if (dbQuery('gallery')->cond('PhotoID', $imageId)->first($image)) {

            $path = $this->formImagePath($image->Path, $image->Src);

            switch (pathinfo($path, PATHINFO_EXTENSION)) {
                case 'jpeg':
                    $imageResource = imagecreatefromjpeg($path);
                    $croppedImage = $this->cropImage($imageResource);
                    $result['status'] = imagejpeg($croppedImage, $path);
                    break;
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

            imagedestroy($croppedImage);
            imagedestroy($imageResource);
        }
        return $result;
    }

    /**
     * Render gallery images list
     * @param string $material_id Material identifier
     * @return string html representation of image list
     */
    public function html_list($material_id)
    {
        // Get all material images
        $items_html = '';
        /** @var array $images List of gallery images */
        $images = null;
        // there are gallery images
        if (dbQuery('gallery')->cond('MaterialID', $material_id)->order_by('priority')->exec($images)) {
            /** @var \samson\cms\CMSGallery $image */
            foreach ($images as $image) {
                // Get image size string
                $size = ', ';
                // Get image path
                $path = $this->formImagePath($image->Path, $image->Src);

                // if file doesn't exist
                if (!$this->imageExists($path)) {
                    $path = \samson\resourcer\ResourceRouter::url('www/img/no-img.png', $this);
                }

                // set image size string representation, if it is not 0
                $size = ($image->size == 0) ? '' : $size . $this->humanFileSize($image->size);

                //Set priority array
                $this->priority[$image->priority] = $image->PhotoID;

                // Render gallery image tumb
                $items_html .= $this->view('tumbs/item')
                    ->set($image, 'image')
                    ->set('name', utf8_limit_string($image->Name, 18, '...'))
                    ->set('imgpath', $path)
                    ->set('size', $size)
                    ->set('material_id', $material_id)
                    ->output();
            }
        }

        // Render content into inner content html
        return $this->view('tumbs/index')
            ->set('images', $items_html)
            ->set('material_id', $material_id)
        ->output();
    }

    // TODO: @omaximus Comments?
    public function humanFileSize($bytes, $decimals = 2)
    {
        $sizeLetters = 'BKBMBGBTBPB';
        $factor = (int)(floor((strlen($bytes) - 1) / 3));
        $sizeLetter = ($factor <= 0) ? substr($sizeLetters, 0, 1) : substr($sizeLetters, $factor * 2 - 1, 2);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $sizeLetter;
    }


    /**
     * Function to reduce code size in __async_edit method
     * @param $imageResource
     * @return bool|resource
     */
    public function cropImage($imageResource)
    {
        $imageTransparency = imagecolorallocatealpha($imageResource, 255, 255, 255, 127);
        $rotatedImage = imagerotate($imageResource, -($_POST['rotate']), $imageTransparency);
        $croppedImage = imagecrop($rotatedImage, array('x' => $_POST['crop_x'],
            'y' => $_POST['crop_y'],
            'width' => $_POST['crop_width'],
            'height' => $_POST['crop_height']));
        imagedestroy($rotatedImage);
        return $croppedImage;
    }

    // TODO: @omaximus Comments?
    public function cropTransparentImage($imageResource)
    {
        $imageTransparency = imagecolorallocatealpha($imageResource, 255, 255, 255, 127);
        $croppedImage = imagecreatetruecolor($_POST['crop_width'], $_POST['crop_height']);
        imagefill($croppedImage, 0, 0, $imageTransparency);
        imagesavealpha($croppedImage, true);
        $rotatedImage = imagerotate($imageResource, -($_POST['rotate']), $imageTransparency);
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
        imagedestroy($rotatedImage);
        return $croppedImage;
    }

    // TODO: @omaximus Comments?
    private function imageExists($imagePath, $imageSrc = null)
    {
        if (isset($imageSrc)) {
            $imageFullPath = $this->formImagePath($imagePath, $imageSrc);
        } else {
            $imageFullPath = $imagePath;
        }

        // Call file service existence method
        return $this->fs->exists($imageFullPath);
    }

    // TODO: @omaximus Comments?
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
        if ($dir == '/') {
            return substr($path, 1);
        } else {
            return preg_replace('/' . addcslashes($dir, '/') . '/', '', $path);
        }
    }
}
