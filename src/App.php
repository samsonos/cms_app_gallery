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

	/** @see \samson\core\ExternalModule::init() */
	public function prepare( array $params = null )
	{
        // TODO: Change this logic to make tab loading more simple
		// Create new gallery tab object to load it 
		class_exists( ns_classname('MaterialTab','samson\cms\web\gallery') );
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
		
		// Find gallery record in DB
		if( dbQuery('gallery')->id( $id )->first( $db_image ))
		{
			if($db_image->Path != '')
			{
				$upload_dir = $db_image->Path;
				// Physycally remove file from server
				if( file_exists( $db_image->Path.$db_image->Src )) unlink( $db_image->Path.$db_image->Src );
	
				// Delete thumnails
				if(class_exists('\samson\scale\Scale', false)) foreach (m('scale')->thumnails_sizes as $folder=>$params)
				{
					$folder_path = $upload_dir.$folder;
					if( file_exists( $folder_path.'/'.$db_image->Path.$db_image->Src )) unlink( $folder_path.'/'.$db_image->Path.$db_image->Src );
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
	public function __async_upload( $material_id )
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
			if (dbQuery('material')->MaterialID($material_id)->Active(1)->first($material)) {
				// Create empty db record
				$photo = new \samson\activerecord\gallery(false);
				$photo->Name = $upload->realName();
				$photo->Src = $upload->name();
                $photo->Path = $upload->path();
				$photo->MaterialID = $material->id;
                $photo->size = $upload->size();
                $photo->Active = 1;
				$photo->save();

				// Call scale if it is loaded
				if (class_exists('\samson\scale\Scale', false)) {
                    m('scale')->resize($upload->fullPath(), $upload->name());
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
//        $result['priority'] = $priorities;
        return $result;
    }
	
	/**
	 * Render gallery images list
	 * @param string $material_id Material identifier
	 */
	public function html_list( $material_id )
	{
		// Get all material images
		$items_html = '';
        $images = array();
		if( dbQuery('gallery')->MaterialID( $material_id )->order_by('priority')->exec( $images ))foreach ( $images as $image )
		{
            // Get old-way image path, remove full path to check file
            $src = str_replace(__SAMSON_BASE__, '', $image->Src);
            if (file_exists($src)) {
                $path = $image->Src;
            } else { // Use new CORRECT way
                $path = $image->Path.$image->Src;
            }

            //trace($image->Src.'-'.$image->Path);

            //Set priority array
            $this->priority[$image->priority] = $image->PhotoID;

            // Render gallery image tumb
			$items_html .= $this->view( 'tumbs/item')
			    ->image($image)
                ->imgpath($path)
                ->size($this->humanFileSize($image->size))
			    ->material_id($material_id)
			->output();
		}
	
		// Render content into inner content html
		return $this->view( 'tumbs/index' )
		    ->images( $items_html )
		    ->material_id($material_id)
		->output();
	}

    public function humanFileSize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = (int)(floor((strlen($bytes) - 1) / 3));
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }
}