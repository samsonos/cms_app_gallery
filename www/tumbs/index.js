/** Javascript SamsonCMS Gallery function-object */
var SJSGallery = function( container )
{
    // Safely save container object
    var container = s(container);
    var containerDOMElement = container.DOMElement;
    var uploadUrl, updateUrl, priorityUrl;

    // Try to get action urls from container
    if (containerDOMElement.hasAttribute('__action_upload')) {
        uploadUrl = containerDOMElement.getAttribute('__action_upload')
    } else {
        console.error('No upload URL was set, please add "__action_upload" attribute and proper URL to gallery container');
    }
    if (containerDOMElement.hasAttribute('__action_update')) {
        updateUrl = containerDOMElement.getAttribute('__action_update')
    } else {
        console.error('No update URL was set, please add "__action_update" attribute and proper URL to gallery container');
    }
    if (containerDOMElement.hasAttribute('__action_priority')) {
        priorityUrl = containerDOMElement.getAttribute('__action_priority')
    } else {
        console.error('No priority URL was set, please add "__action_priority" attribute and proper URL to gallery container');
    }

    /** Gallery initialization */
    var initFunction = function( response )
    {
        // If we have responce from server
        if (response) {
            try {
                // Parse JSON responce
                response = JSON.parse(response);

                // If we have html - update it
                if (response.html) {

                    // Create hidden block
                    var hidden = s(response.html).hide();

                    // Fill new HTML
                    container = container.replace(hidden);
                }
            }
            catch (e) {
                s.trace('Ошибка обработки ответа полученного от сервера, повторите попытку отправки данных:' + e);
            }
        }

        // Init SamsonJS Gallery plugin on container
        container.gallery();

        if (response) {
            // Check image loading
            isImagesLoaded(s('img', container), function () {
                container.show();
            });
        }

        // Bind delete event
        s('.btn-delete',container).click(function(btn)
        {
            // Ask for confirmation
            if(confirm('Delete image?'))
            {
                loader.show('Обновление галлереи',true);
                s.ajax(btn.a('href'), function(response){
                    loader.hide();
                    initFunction(response);
                });
            }

        }, true, true );

		/**
         * The function for async update value alt for image.
         * */
        $(function() {

            // Check ajax send
            var ajaxSend = true;
            // Check push key "enter" from textArea
            var keyDownEnter = false;
            // Check change value textArea
            var changeInput = false;

            // We hang event on clicked out focus textarea or pushed keydown "enter" in textarea for all textArea from class "btn-displayTA"
            s('.btn-displayTA', container).each(function (btn) {

                // Field with value alt for image
                var textArea = s('.updateAltImage', btn.parent());
                //Getting value form
                var textAreaValue = textArea.val();
                // The function detecting click out focus text area form
                textArea.blur(function () {
                    if (!keyDownEnter) {
                        ajaxUpdate(textArea, textAreaValue);
                    }

                    keyDownEnter = false;
                });

                // The function for detecting pressing key when we input text
                textArea.keydown(function (elem, q, e) {
                    // Detect pressing 'enter'
                    if (e.keyCode == '13') {
                        keyDownEnter = true;
                        ajaxUpdate(s(elem), textAreaValue);
                        return false;
                    }
                    changeInput = true;
                });

                // We hang event click for show textArea
                btn.click(function () {
                    // Show text area for input text
                    textArea.show();
                    // Focus element and setting cursor in end
                    textArea.focus().val('');
                    textArea.val(textAreaValue);
                });
            });

            // Function for async update value alt from image
            var ajaxUpdate = function (obj) {
                if (ajaxSend && obj !== undefined && changeInput) {
                    ajaxSend = false;

                    //position for loader
                    var position = new Object();
                    position.type = 'absolute';
                    position.top = '0';
                    position.left = '0';

                    //Init loader
                    var loader = new Loader(obj.parent(), position);
                    loader.show('', true);

                    // Url for async send
                    var href = obj.a('data-action');

                    //if url not empty
                    if (href.length != '') {
                        // Send async request
                        s.ajax(href, function (response) {
                            response = JSON.parse(response);

                            if (response.status) {
                                // Hide current text area
                                obj.hide();

                                if (response.description) {
                                    // Update title for alt under image
                                    s('.btn-displayTA > span', obj.parent()).html(response.description);
                                }

                                if (response.value) {
                                    // Update value textArea
                                    obj.val(response.value);
                                }

                                ajaxSend = true;
                                // remove loader
                                loader.remove();
                            }
                        }, {'value': obj.val()});
                    }

                    changeInput = false;
                } else if (!changeInput) {
                    // Hide current text area
                    obj.hide();
                }
            };
        });
		
        s('.btn-edit', container).click(function(btn){
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
                            loader.show('Применение изменений', true);
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
                                    loader.hide();
                                    image.cropper('replace', image.attr('image_src') + '?' + new Date().getTime());
                                }
                            };
                            return false;
                        });
                        // End binding

                    } else {
                        alert('Изображение не найдено!');
                    }
                } catch (e) {
                    console.log('Can\'t load editor');
                }
            });

            return false;
        });

        $(container.DOMElement).sortable({
            axis: "x,y",
            revert: true,
            scroll: true,
            placeholder: "sortable-placeholder",
            cursor: "move",
            containment: "parent",
            delay: 150,
            items: "> li:not(:last-child)",
            stop: function() {
                var ids = [];
                $('li', container.DOMElement).each(function(idx, item){
                    if (item.hasAttribute('image_id')) {
                        ids[idx] = item.getAttribute('image_id');
                    }
                });
                $.ajax({
                    url: priorityUrl,
                    type: 'POST',
                    async: true,
                    data: {ids:ids},
                    headers: {
                        'SJSAsync': 'true'
                    }
                });
            }
        });

        container.dropFileUpload({
            url: uploadUrl,
            drop: function(elem){
                elem.css('background-color', 'inherit');
                var btn = s('.btn-upload', container).DOMElement;
                btn.parentNode.removeChild(btn);
            },
            completeAll: function(){
                s.ajax(updateUrl, initFunction);
            }
        });

        s('.btn-upload', container).fileUpload({
            url: uploadUrl,
            completeAll: function(){
                s.ajax(updateUrl, initFunction);
            },
            textUpload: 'Загрузить картинку',
            textProcess: 'Загрузка картинки'
        });
		
		/** 
		* Updated quantity image in gallery. Quantity added to name
		* Send async request and getting quantity.
		*/
        s.ajax(s(container).a('__action_getCount'), function(response) {
            // Encoding response
            response = JSON.parse(response);
            // Tab gallery
            var parentTab = s(container).parent().parent().parent();
            // Elements with name
            var headerName = s('.template-block-header > .tab-header > span:first-child', parentTab);
            // Current value name
            var text = headerName.html();
            // Regular expression. Getting current quantity
            var reg = text.match(/\(([^\(\)]+)\)$/);
            // Check regular response. If exist then cut this quantity from text
            if (reg && reg[1]) {
                text = text.substr(0, text.length - (reg[1].length + 3));
            }
            // if ajax response exist count then update name in tab
            if (response.count) {
                headerName.html(text+' ('+response.count+')');
            }
        });
    };

    // Base init
    initFunction();
};

// Load gallery if class found
s('.scms-gallery').pageInit( SJSGallery );