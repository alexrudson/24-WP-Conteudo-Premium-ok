jQuery(document).ready(function($) {
    'use strict';

    // --- SHARED FUNCTIONS ---
    function updateItemIndexes(containerSelector, inputNamePrefix) {
        $(containerSelector).children().each(function(i) {
            $(this).find('input, textarea, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + i + ']');
                    $(this).attr('name', name);
                }
                // Update IDs for labels if necessary (more complex, depends on specific ID usage)
                var id = $(this).attr('id');
                if (id) {
                    id = id.replace(/_\d+_/, '_' + i + '_').replace(/__INDEX__/, '_' + i + '_');
                     // Handle cases like wppc_module_content___INDEX__
                    id = id.replace(/content__INDEX__/, 'content_' + i);
                    $(this).attr('id', id);
                    // Update label 'for'
                    $(this).siblings('label[for^="'+id.substring(0, id.lastIndexOf('_'))+'"]').attr('for', id);
                }
            });
             // For wp_editor, the ID is critical.
            $(this).find('.wp-editor-wrap').each(function() {
                var wrapId = $(this).attr('id');
                if(wrapId) {
                    wrapId = wrapId.replace(/_\d+$/, '_' + i).replace(/__INDEX__$/, '_' + i);
                    $(this).attr('id', wrapId);
                }
                // also update text editor id
                $(this).find('.wp-editor-area').each(function(){
                    var editorAreaId = $(this).attr('id');
                    if(editorAreaId){
                        editorAreaId = editorAreaId.replace(/_\d+$/, '_' + i).replace(/__INDEX__$/, '_' + i);
                        $(this).attr('id', editorAreaId);
                    }
                });
            });
        });
    }
    
    function initializeNewWPEditor(textarea) {
        var $textarea = $(textarea);
        var editorId = $textarea.attr('id');
        var rich = (typeof tinymce !== "undefined"); // Check if TinyMCE is loaded

        if (rich) {
            tinymce.init({ selector: '#' + editorId }); // Basic TinyMCE init
            // For a more complete setup, you might need to replicate WP's settings.
            // Or, consider wp.editor.initialize(editorId, { tinymce: true, quicktags: true });
            // However, wp.editor.initialize might be tricky with dynamically added elements without more setup.
        }
        
        // Initialize Quicktags
        if (typeof quicktags !== "undefined") {
            quicktags({id : editorId});
            QTags._buttonsInit(); // Refresh buttons
        }
        // Remove the placeholder class
        $textarea.removeClass('wppc-module-content-textarea');
    }


    // --- MODULES ---
    var modulesContainer = $('#wppc-modules-container');
    var moduleNextIndex = modulesContainer.find('.wppc-module-item').length;

    modulesContainer.sortable({
        items: '.wppc-module-item',
        handle: '.hndle',
        placeholder: 'wppc-sortable-placeholder',
        axis: 'y',
        update: function() {
            updateItemIndexes('#wppc-modules-container', 'wppc_modules');
            // Renumber moduleNextIndex based on current count after potential reordering/deletion
            moduleNextIndex = modulesContainer.find('.wppc-module-item').length;
        }
    });

    $('#wppc-add-module').on('click', function() {
        var template = $('#wppc-module-template').html();
        template = template.replace(/__INDEX__/g, moduleNextIndex);
        
        var $newModule = $(template);
        modulesContainer.append($newModule);

        // Initialize wp_editor for the new module's textarea
        var $textarea = $newModule.find('.wppc-module-content-textarea');
        if ($textarea.length) {
            initializeNewWPEditor($textarea);
        }
        
        // Update title on input
        $newModule.find('.wppc-module-title-input').on('input', function() {
            var newTitle = $(this).val() || wppc_admin_ajax.text.new_module_title;
            $newModule.find('.hndle span').text(newTitle);
        });

        moduleNextIndex++;
        updateItemIndexes('#wppc-modules-container', 'wppc_modules'); // Ensure indexes are correct after adding
    });

    modulesContainer.on('click', '.wppc-remove-module', function() {
        if (confirm(wppc_admin_ajax.text.confirm_remove)) {
            $(this).closest('.wppc-module-item').remove();
            updateItemIndexes('#wppc-modules-container', 'wppc_modules');
            moduleNextIndex = modulesContainer.find('.wppc-module-item').length; // Recalculate next index
        }
    });

    // Toggle module content and fetch preview
    modulesContainer.on('click', '.postbox-header .handlediv, .postbox-header .hndle', function(e) {
        if ($(e.target).is('input, textarea, select, button, a')) {
            return; // Don't toggle if interacting with an input within the handle
        }
        var $moduleItem = $(this).closest('.wppc-module-item');
        $moduleItem.toggleClass('closed');
        $moduleItem.find('.inside').slideToggle(200);

        if (!$moduleItem.hasClass('closed')) { // If opening
            var $previewArea = $moduleItem.find('.wppc-module-preview-area');
            var $editorTextarea;

            // Find the editor: could be TinyMCE or plain textarea
            var editorInstance = tinymce.get($moduleItem.find('textarea[id^="wppc_module_content_"]').attr('id'));
            var contentValue = '';

            if (editorInstance) {
                contentValue = editorInstance.getContent({format: 'text'}); // Get plain text content
            } else {
                $editorTextarea = $moduleItem.find('textarea[name*="[content]"]');
                contentValue = $editorTextarea.val();
            }
            
            // Basic URL detection (could be more robust)
            var urlPattern = /(https?:\/\/[^\s]+)/g;
            var urls = contentValue.match(urlPattern);

            if (urls && urls.length > 0) {
                // For simplicity, take the first URL found.
                // A more complex solution might look for specific video platform URLs.
                var firstUrl = urls[0];

                // Only fetch for YouTube for now as per screenshot example
                if (firstUrl.includes('youtube.com') || firstUrl.includes('youtu.be')) {
                    $previewArea.html(wppc_admin_ajax.text.preview_loading).show();
                    $.ajax({
                        url: wppc_admin_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wppc_get_module_preview',
                            nonce: wppc_admin_ajax.nonce,
                            content_url: firstUrl
                        },
                        success: function(response) {
                            if (response.success && response.data.embed_code) {
                                $previewArea.html(response.data.embed_code);
                            } else {
                                var errorMessage = response.data && response.data.message ? response.data.message : wppc_admin_ajax.text.preview_error;
                                $previewArea.html('<p>' + errorMessage + '</p>');
                            }
                        },
                        error: function() {
                            $previewArea.html('<p>' + wppc_admin_ajax.text.preview_error + '</p>');
                        }
                    });
                } else {
                     // If not a YouTube URL, don't try to embed, just show a message or hide
                    //$previewArea.html('<p>Prévia de URL não suportada.</p>').show();
                    $previewArea.hide(); // Or hide if no preview
                }
            } else {
                $previewArea.hide(); // Hide if no URL found
            }
        }
    });
    
    // Initial state for existing modules: ensure they are closed and titles are set
    modulesContainer.find('.wppc-module-item').each(function() {
        var $moduleItem = $(this);
        if(!$moduleItem.hasClass('wppc-item-processed')) { // Avoid re-processing
            $moduleItem.addClass('closed wppc-item-processed');
            $moduleItem.find('.inside').hide();
            
            // Update title display from input
            var title = $moduleItem.find('.wppc-module-title-input').val();
            if (title) {
                $moduleItem.find('.hndle span').text(title);
            }
             // Update title on input for existing items too
            $moduleItem.find('.wppc-module-title-input').on('input', function() {
                var newTitle = $(this).val() || wppc_admin_ajax.text.new_module_title;
                $moduleItem.find('.hndle span').text(newTitle);
            });
        }
    });


    // --- RESOURCE LINKS ---
    var linksContainer = $('#wppc-links-container');
    var linkNextIndex = linksContainer.find('.wppc-link-item').length;

    linksContainer.sortable({
        items: '.wppc-link-item',
        handle: '.hndle',
        placeholder: 'wppc-sortable-placeholder',
        axis: 'y',
        update: function() {
            updateItemIndexes('#wppc-links-container', 'wppc_links');
            linkNextIndex = linksContainer.find('.wppc-link-item').length;
        }
    });

    $('#wppc-add-link').on('click', function() {
        var template = $('#wppc-link-template').html();
        template = template.replace(/__LINDEX__/g, linkNextIndex);
        
        var $newLink = $(template);
        linksContainer.append($newLink);
        
        // Update title on description input
        $newLink.find('.wppc-link-description-input').on('input', function() {
            var newTitle = $(this).val() || wppc_admin_ajax.text.new_link_title;
            $newLink.find('.hndle span').text(newTitle);
        });

        linkNextIndex++;
        updateItemIndexes('#wppc-links-container', 'wppc_links');
    });

    linksContainer.on('click', '.wppc-remove-link', function() {
        if (confirm(wppc_admin_ajax.text.confirm_remove)) {
            $(this).closest('.wppc-link-item').remove();
            updateItemIndexes('#wppc-links-container', 'wppc_links');
            linkNextIndex = linksContainer.find('.wppc-link-item').length;
        }
    });
    
    // Toggle link content
    linksContainer.on('click', '.postbox-header .handlediv, .postbox-header .hndle', function(e) {
         if ($(e.target).is('input, textarea, select, button, a')) {
            return; 
        }
        var $linkItem = $(this).closest('.wppc-link-item');
        $linkItem.toggleClass('closed');
        $linkItem.find('.inside').slideToggle(200);
         // No AJAX preview for simple links, but you could add one for URL title fetching if desired
    });

    // Initial state for existing links
    linksContainer.find('.wppc-link-item').each(function() {
        var $linkItem = $(this);
         if(!$linkItem.hasClass('wppc-item-processed')) {
            $linkItem.addClass('closed wppc-item-processed');
            $linkItem.find('.inside').hide();

            var description = $linkItem.find('.wppc-link-description-input').val();
            if (description) {
                $linkItem.find('.hndle span').text(description);
            }
            $linkItem.find('.wppc-link-description-input').on('input', function() {
                var newTitle = $(this).val() || wppc_admin_ajax.text.new_link_title;
                $linkItem.find('.hndle span').text(newTitle);
            });
        }
    });

    // IMPORTANT: Make sure TinyMCE saves its content before form submission
    $('form#post').on('submit', function() {
        if (typeof tinymce !== "undefined") {
            tinymce.triggerSave();
        }
    });

});