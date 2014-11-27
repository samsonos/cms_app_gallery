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

        s('.btn-edit', o.container).click(function(btn){
            s.ajax(btn.a('href'), function(response){
                if (response) try {
                    response = JSON.parse(response);
                    if (response.html) {
                        var editor = s(response.html);
                        editor.css('display', 'none');
                        editor.appendTo(s('body'));
                        var tb = tinybox('.__image_editor', true, true, true);
                        tb.show();
                        var image = $('.__image_container > img');
                        image.cropper({
                            dashed:false,
                            zoomable: false,
                            built: function(){
                                $('.cropper-container').css('top', '0px');
                                $('img.cropper-invisible').css('display', 'none');
                            },
                            done: function(data){
                                $('.__image_editor_width').val(data.width);
                                $('.__image_editor_height').val(data.height);
                                $('.__image_editor_aspect_ratio').val(data.width/data.height);
                            }
                        });

                        // Bind buttons on popup starts from here
                        // Disable text highlighting on buttons
                        $('.__image_editor_btn').mousedown(function(){
                            return false;
                        });

                        // On zoom In button click
                        var timeoutId = 0;
                        $('.__image_editor_btn_zoom_in').mousedown(function(){
                            var zoom = $('.__image_editor_input_zoom').val();
                            image.cropper('zoom', zoom);
                            timeoutId = setInterval(function(){
                                image.cropper('zoom', zoom);
                            }, 300);
                            return false;
                        }).bind('mouseup mouseleave', function() {
                            clearTimeout(timeoutId);
                        });

                        // On zoom out button click
                        $('.__image_editor_btn_zoom_out').mousedown(function(){
                            var zoom = $('.__image_editor_input_zoom').val();
                            image.cropper('zoom', -zoom);
                            timeoutId = setInterval(function(){
                                image.cropper('zoom', -zoom);
                            }, 300);
                            return false;
                        }).bind('mouseup mouseleave', function() {
                            clearTimeout(timeoutId);
                        });

                        // On rotate left button click
                        $('.__image_editor_btn_rotate_left').click(function(){
                            var angle = $('.__image_editor_input_degree').val();
                            image.cropper('rotate', -angle);
                        });

                        // On rotate right button click
                        $('.__image_editor_btn_rotate_right').click(function(){
                            var angle = $('.__image_editor_input_degree').val();
                            image.cropper('rotate', angle);
                        });

                        // On cropped zone width change
                        $('.__image_editor_width').change(function(){
                            var width = $('.__image_editor_width').val();
                            image.cropper('setData', {width: width});
                        });

                        // On cropped zone height change
                        $('.__image_editor_height').change(function(){
                            var height = $('.__image_editor_height').val();
                            image.cropper('setData', {height: height});
                        });

                        // On checkbox click
                        $('.__image_editor_check').change(function(){
                            if (this.checked) {
                                var aspectRatio = $('.__image_editor_aspect_ratio').val();
                                image.cropper('setAspectRatio', aspectRatio);
                            } else {
                                image.cropper('setAspectRatio', 'auto');
                            }
                        });

                        // On "Save"("") button click
                        var sendButton = $('.__image_editor_btn_save');
                        sendButton.click(function(){
                            var imageData = image.cropper('getImageData');
                            var cropData = image.cropper('getData');
                            var formData = new FormData();
                            formData.append('rotate', imageData.rotate);
                            formData.append('crop_x', cropData.x);
                            formData.append('crop_y', cropData.y);
                            formData.append('crop_width', cropData.width);
                            formData.append('crop_height', cropData.height);
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', sendButton.attr('href'), true);
                            xhr.setRequestHeader("Cache-Control", "no-cache");
                            xhr.setRequestHeader('SJSAsync', 'true');
                            xhr.send(formData);
                            xhr.onreadystatechange = function(){
                              if (xhr.readyState == 4) {
                                  image.cropper('replace', image.attr('image_src') + '?' + new Date().getTime());
                              }
                            };
                            return false;
                        });
                        // End binding

                    } else {
                        alert('Can\'t find image!');
                    }
                } catch (e) {
                    console.log('Can\'t load editor');
                }
            });

            return false;
        });

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