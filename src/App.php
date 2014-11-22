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

    private $priority = array();

    /** @see \samson\core\ExternalModule::init()
     * @param array $params Parameters
     * @return bool|void Returns module check result
     */
	public function prepare(array $params = null)
	{
        // TODO: Change this logic to make tab loading more simple
		// Create new gallery tab object to load it 
		class_exists(\samson\core\AutoLoader::className('MaterialTab','samson\cms\web\gallery'));
	}
	
	/**
	 * Controller for deleting material image from gallery 
	 * @param string $id Gallery Image identifier
	 * @return array Async response array
	 */
	public function __async_delete( $id )
	{
		// Async response
		$result = array( 'status' => false );

        /** @var \samson\activerecord\gallery $db_image */
        $db_image = null;
		
		// Find gallery record in DB
		if (dbQuery('gallery')->id($id)->first($db_image)) {
			if ($db_image->Path != '') {
				$upload_dir = $db_image->Path;
				// Physically remove file from server
				if (file_exists($db_image->Path.$db_image->Src)) {
                    unlink($db_image->Path.$db_image->Src);
                }

                /** @var \samson\scale\Scale $scale */
                $scale = m('scale');
				// Delete thumbnails
				if (class_exists('\samson\scale\Scale', false)) {
                    foreach ($scale->thumnails_sizes as $folder=>$params) {
                        $folder_path = $upload_dir.$folder;
                        if (file_exists($folder_path.'/'.$db_image->Path.$db_image->Src)) {
                            unlink($folder_path.'/'.$db_image->Path.$db_image->Src);
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
	 * @param string $material_id Material identifier 
	 * @return array Async response array
	 */
	public function __async_update( $material_id )
	{				
		return array('status' => true, 'html' => $this->html_list($material_id));
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
            //trace($upload);
            /** @var \samson\activerecord\material $material */
            $material = null;
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
				if (class_exists('\samson\scale\Scale', false)) {
                    /** @var \samson\scale\Scale $scale */
                    $scale = m('scale');
                    $scale->resize($upload->fullPath(), $upload->name(), $upload->uploadDir);
                }

				$result['status'] = true;			
			}
		}
		
		return $result;
	}

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
	 * Render gallery images list
	 * @param string $material_id Material identifier
     * @return string html representation of image list
	 */
	public function html_list($material_id)
	{
		// Get all material images
		$items_html = '';
        $images = array();
		if(dbQuery('gallery')->cond('MaterialID', $material_id )->order_by('priority')->order_by('Loaded')->exec($images)) {
            foreach ($images as $image) {
                // Get image size string
                $size = ', ';
                // Get old-way image path, remove full path to check file
                if (empty($image->Path)) {
                    $path = $image->Src;
                } else { // Use new CORRECT way
                    $path = $image->Path . $image->Src;
                }

                /*$ch = curl_init(url_build($path));
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
                    $path = 'img/no-img.png';
                }
                curl_close($ch);*/

                $size = ($image->size == 0) ? '' : $size . $this->humanFileSize($image->size);

                //Set priority array
                $this->priority[$image->priority] = $image->PhotoID;

                // Render gallery image tumb
                $items_html .= $this->view('tumbs/item')
                    ->set('name', utf8_limit_string($image->Name, 18, '...'))
                    ->set('imgpath', $path)
                    ->set('size', $size)
                    ->set('material_id', $material_id)
					->image($image)
                    ->output();
            }
        }
	
		// Render content into inner content html
		return $this->view( 'tumbs/index' )
		    ->set('images', $items_html )
		    ->set('material_id', $material_id)
		->output();
	}

    public function humanFileSize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = (int)(floor((strlen($bytes) - 1) / 3));
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }
}