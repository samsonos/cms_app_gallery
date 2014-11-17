/** Javascript SamsonCMS Gallery function-object */
var SJSGallery = function( container )
{
	// Cache reference
	var o = this;
    var containerDOMElement = container.DOMElement;
    var uploadUrl = (containerDOMElement.hasAttribute('__action_upload')) ? containerDOMElement.getAttribute('__action_upload') : 'upload/';
    var updateUrl = (containerDOMElement.hasAttribute('__action_update')) ? containerDOMElement.getAttribute('__action_update') : 'update/';

	// Safely save container object
	o.container = s(container);

	// Create loader object
	//o.loader = new Loader( o.container.parent() );
	
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
					//o.loader.hide();
					
					o.container.show();
				});			
			}
		}		
		catch(e){ s.trace('Ошибка обработки ответа полученного от сервера, повторите попытку отправки данных:'+e); };	
				
		// Init SamsonJS Gallery plugin on container
		o.container.gallery();

		// Bind delete event
		s('.btn-delete',o.container).click(function(btn)
		{
			// Ask for confirmation
			if(confirm('Delete image?'))
			{
				//o.loader.show('Обновление галлереи',true);
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
            items: "> li:not(:last-child)",
            stop: function(event, ui) {
                var ids = [];
                $('.scms-gallery li').each(function(idx, item){
                    if (item.hasAttribute('image_id')) {
                        ids[idx] = item.getAttribute('image_id');
                    }
                });
                $.ajax({
                    url: '/gallery/priority',
                    type: 'POST',
                    async: true,
                    data: {ids:ids},
                    success: function(response){
                    }
                });
            }
        });

        s('.scms-gallery').dropFileUpload({
            url: uploadUrl,
            drop: function(elem){
                elem.css('background-color', 'inherit');
                var btn = s('.btn-upload').DOMElement;
                btn.parentNode.removeChild(btn);
                //o.loader.show('Обновление галлереи',true);
            },
            completeAll: function(){
                s.ajax(updateUrl, init);
            }
        });

        s('.btn-upload').fileUpload({
            url: uploadUrl,
            //inputSelector: '.__image-upload',
            start: function(){
                //o.loader.show('Обновление галлереи',true);
            },
            completeAll: function(){
                s.ajax(updateUrl, init);
            }
        });
	};
	
	// Base init
	o.init();
};

// Load gallery if class found
s('.scms-gallery').pageInit( SJSGallery );