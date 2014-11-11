/** Javascript SamsonCMS Gallery function-object */
var SJSGallery = function( container )
{
	// Cache reference
	var o = this;
	
	// Safely save container object
	o.container = s(container);

	// Create loader object
	o.loader = new Loader( o.container.parent() );	
	
	/** Upload initialization */
	o.uploadInit = function( fileField )
	{
		// Bind upload handler
		uploadFileHandler( fileField,{
			
			/* File uploading finished */
			finish: function()
			{	
				o.loader.show('Обновление галлереи',true); 
			},
		
			/* File contoller finished */
			response : init
		});
	};
	
	/** Gallery initialization */
	o.init = function( response )
	{			
		// If we have responce from server
		if( response ) try
		{	
			// Parse JSON responce
			response = JSON.parse( response );
					
			// If we have html - update it
			if( response.html ) 
			{	
				// Fill new HTML
				o.container = o.container.replace( response.html );
				
				o.container.hide();		
				
				// Check image loading
				isImagesLoaded( s('img', o.container), function()
				{
					o.loader.hide();
					
					o.container.show();
				});			
			}
		}		
		catch(e){ s.trace('Ошибка обработки ответа полученного от сервера, повторите попытку отправки данных:'+e); };	
				
		// Init SamsonJS Gallery plugin on container
		o.container.gallery();
		
		// Init uploader
		o.uploadInit( s('.__image-upload') );
		
		// Bind delete event
		s('.btn-delete',o.container).click(function(btn)
		{
			// Ask for confirmation
			if(confirm('Delete image?'))
			{
				o.loader.show('Обновление галлереи',true);
				s.ajax( btn.a('href'), init );
			}
			
		}, true, true );

        $('.scms-gallery').sortable({
            axis: "x,y",
            revert: true,
            scroll: true,
            placeholder: "sortable-placeholder",
            cursor: "move",
            containment: "parent",
            delay: 150,
            stop: function(event, ui) {
                var ids = [];
                $('.scms-gallery li').each(function(idx, item){
                    if (item.hasAttribute('image_id')) {
                        ids[idx] = item.getAttribute('image_id');
                    }
                });
                s.trace(ids);
                $.ajax({
                    url: '/gallery/priority',
                    type: 'POST',
                    async: true,
                    data: {ids:ids},
                    success: function(response){
                        //var obj = $.parseJSON(response);
                        //s.trace(obj.status);
                    }
                });
                //s.ajax('/gallery/priority', )
            }
        });
		
	};
	
	// Base init
	o.init();
};

// Load gallery if class found
s('.scms-gallery').pageInit( SJSGallery );