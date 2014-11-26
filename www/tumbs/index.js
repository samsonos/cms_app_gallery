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

                                // On "Применить" button click
                                $('.__image_editor_btn_confirm').click(function(){
                                    var width = $('.__image_editor_width').val();
                                    var height = $('.__image_editor_height').val();
                                    var aspectRatio = $('.__image_editor_aspect_ratio').val();
                                    image.cropper('setData', {width: width, height: height});
                                });
                                
                            },
                            done: function(data){
                                $('.__image_editor_width').val(data.width);
                                $('.__image_editor_height').val(data.height);
                                $('.__image_editor_aspect_ratio').val(data.width/data.height);
                            }
                        });
                    } else {
                        alert('Can\'t find image!');
                    }
                } catch (e) {
                    console.log('Can\'t load editor');
                }
            });
            //var imageSrc = $('img', e.target.parentNode).attr('src');
            //console.log(imageSrc);
            //$('body').append('<div class="__cropper_container"><img></div>');
            //var container = $('.__cropper_container');
            //container.width(1000);
            //container.height(600);
            //var image = $('img', container);
            //image.attr('src', imageSrc);
            //image.cropper({
            //    //aspectRatio: 16 / 9,
            //    dashed: false,
            //    zoomable: false
            //});

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