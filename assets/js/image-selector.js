/**
 * Image Selector Module
 * Handles image selection workflow for document processing
 */

(function($) {
    'use strict';

    window.PDMImageSelector = {
        /**
         * Documents pending image selection
         */
        pendingDocuments: [],

        /**
         * Current document index being processed
         */
        currentIndex: 0,

        /**
         * Image selections for each document
         */
        imageSelections: {},

        /**
         * Template image requirements
         */
        templateRequirements: [],

        /**
         * Initialize image selection for documents
         * 
         * @param {Array} documents Array of document objects
         * @param {number} templateId Template ID
         * @param {Function} onComplete Callback when all images selected
         */
        init: function(documents, templateId, onComplete) {
            this.pendingDocuments = documents;
            this.currentIndex = 0;
            this.imageSelections = {};
            this.onCompleteCallback = onComplete;
            this.templateId = templateId;

            // Get image requirements for the template
            this.getImageRequirements(templateId);
        },

        /**
         * Get image requirements from template
         */
        getImageRequirements: function(templateId) {
            const self = this;

            $.ajax({
                url: pdmBulkUpload.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdm_get_image_requirements',
                    nonce: pdmBulkUpload.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        self.templateRequirements = response.data.requirements;

                        if (response.data.count > 0) {
                            // Start image selection workflow
                            self.showImageSelectionUI();
                        } else {
                            // No images needed, proceed directly
                            self.completeSelection();
                        }
                    } else {
                        console.error('Failed to get image requirements:', response.data.message);
                        self.completeSelection(); // Proceed anyway
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting image requirements:', error);
                    self.completeSelection(); // Proceed anyway
                }
            });
        },

        /**
         * Show image selection UI
         */
        showImageSelectionUI: function() {
            const self = this;
            const doc = this.pendingDocuments[this.currentIndex];
            const imageCount = this.templateRequirements.length;

            // Create modal overlay
            const $overlay = $('<div>')
                .attr('id', 'pdm-image-selection-overlay')
                .css({
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    zIndex: 100000,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center'
                });

            // Create modal content
            const $modal = $('<div>')
                .css({
                    backgroundColor: '#fff',
                    padding: '30px',
                    borderRadius: '8px',
                    maxWidth: '600px',
                    width: '90%',
                    boxShadow: '0 4px 20px rgba(0,0,0,0.3)'
                });

            // Modal header
            $modal.append(
                $('<h2>').text('Select Images for: ' + doc.title).css({ marginTop: 0 })
            );

            // Progress indicator
            $modal.append(
                $('<p>').html(
                    'Document <strong>' + (this.currentIndex + 1) + '</strong> of <strong>' + 
                    this.pendingDocuments.length + '</strong>'
                ).css({ color: '#666', marginBottom: '20px' })
            );

            // Image requirements list
            if (imageCount > 0) {
                const $reqList = $('<div>').css({
                    backgroundColor: '#f5f5f5',
                    padding: '15px',
                    borderRadius: '4px',
                    marginBottom: '20px'
                });

                $reqList.append(
                    $('<h3>').text('Images Needed (' + imageCount + ')').css({
                        fontSize: '16px',
                        marginTop: 0,
                        marginBottom: '10px'
                    })
                );

                const $list = $('<ul>').css({
                    margin: '0',
                    paddingLeft: '20px'
                });

                this.templateRequirements.forEach(function(req, index) {
                    $list.append(
                        $('<li>').html(
                            '<strong>' + req.context + '</strong>' +
                            '<br><small style="color: #666;">Block: ' + req.block_name + '</small>'
                        ).css({ marginBottom: '8px' })
                    );
                });

                $reqList.append($list);
                $modal.append($reqList);
            }

            // Selected images preview
            const $previewContainer = $('<div>')
                .attr('id', 'pdm-selected-images-preview')
                .css({
                    marginBottom: '20px',
                    minHeight: '60px'
                });

            $modal.append($previewContainer);

            // Update preview if images already selected
            if (this.imageSelections[doc.fileName]) {
                this.updatePreview($previewContainer, this.imageSelections[doc.fileName]);
            } else {
                $previewContainer.append(
                    $('<p>').text('No images selected yet').css({ color: '#999', fontStyle: 'italic' })
                );
            }

            // Buttons
            const $buttonContainer = $('<div>').css({
                display: 'flex',
                gap: '10px',
                justifyContent: 'flex-end'
            });

            const $selectBtn = $('<button>')
                .text('Select Images (' + imageCount + ')')
                .addClass('button button-primary')
                .css({ fontSize: '16px', padding: '8px 20px' })
                .on('click', function() {
                    self.openMediaModal(doc, imageCount);
                });

            const $skipBtn = $('<button>')
                .text('Skip')
                .addClass('button')
                .css({ fontSize: '16px', padding: '8px 20px' })
                .on('click', function() {
                    self.skipDocument();
                });

            const $continueBtn = $('<button>')
                .text('Continue')
                .addClass('button button-primary')
                .css({ fontSize: '16px', padding: '8px 20px' })
                .on('click', function() {
                    self.nextDocument();
                });

            $buttonContainer.append($skipBtn, $selectBtn, $continueBtn);
            $modal.append($buttonContainer);

            $overlay.append($modal);
            $('body').append($overlay);
        },

        /**
         * Open WordPress media modal
         */
        openMediaModal: function(doc, imageCount) {
            const self = this;

            // Create media frame starting in gallery-library (Add to Gallery) for easy multi-select
            const frame = wp.media({
                title: 'Select Images for ' + doc.title,
                button: {
                    text: 'Use Selected Images'
                },
                multiple: true,
                frame: 'post',
                state: 'gallery-library',
                library: {
                    type: 'image'
                }
            });

            // Pre-select existing selections when modal opens
            frame.on('open', function() {
                const state = frame.state('gallery-library');
                
                // Pre-select existing selections
                if (self.imageSelections[doc.fileName] && state) {
                    const selection = state.get('selection');
                    const ids = self.imageSelections[doc.fileName];
                    
                    ids.forEach(function(id) {
                        const attachment = wp.media.attachment(id);
                        attachment.fetch();
                        selection.add(attachment ? [attachment] : []);
                    });
                }
            });

            // Handle selection from gallery-library state
            frame.on('update', function(selection) {
                const attachments = selection.toJSON();
                const selectedIds = attachments.map(function(attachment) {
                    return attachment.id;
                });
                
                // Warn if wrong count
                if (selectedIds.length !== imageCount) {
                    const msg = 'Warning: You selected ' + selectedIds.length + 
                               ' images, but the template requires ' + imageCount + ' images.';
                    alert(msg);
                }

                // Store selections
                self.imageSelections[doc.fileName] = selectedIds;

                // Update preview
                self.updatePreview($('#pdm-selected-images-preview'), selectedIds);
            });

            frame.open();
        },

        /**
         * Update image preview
         */
        updatePreview: function($container, imageIds) {
            $container.empty();

            if (!imageIds || imageIds.length === 0) {
                $container.append(
                    $('<p>').text('No images selected').css({ color: '#999', fontStyle: 'italic' })
                );
                return;
            }

            $container.append(
                $('<p>').html('<strong>' + imageIds.length + '</strong> images selected').css({
                    marginBottom: '10px'
                })
            );

            const $thumbs = $('<div>').css({
                display: 'flex',
                gap: '10px',
                flexWrap: 'wrap'
            });

            imageIds.forEach(function(id) {
                const attachment = wp.media.attachment(id);
                attachment.fetch().done(function() {
                    const thumbUrl = attachment.get('sizes') && attachment.get('sizes').thumbnail 
                        ? attachment.get('sizes').thumbnail.url 
                        : attachment.get('url');

                    $thumbs.append(
                        $('<img>')
                            .attr('src', thumbUrl)
                            .css({
                                width: '80px',
                                height: '80px',
                                objectFit: 'cover',
                                border: '2px solid #ddd',
                                borderRadius: '4px'
                            })
                    );
                });
            });

            $container.append($thumbs);
        },

        /**
         * Skip current document
         */
        skipDocument: function() {
            this.nextDocument();
        },

        /**
         * Move to next document
         */
        nextDocument: function() {
            this.currentIndex++;

            // Remove current modal
            $('#pdm-image-selection-overlay').remove();

            if (this.currentIndex < this.pendingDocuments.length) {
                // Show selection for next document
                this.showImageSelectionUI();
            } else {
                // All documents processed
                this.completeSelection();
            }
        },

        /**
         * Complete image selection process
         */
        completeSelection: function() {
            // Remove any modal
            $('#pdm-image-selection-overlay').remove();

            // Build image mapping for each document
            const self = this;
            this.pendingDocuments.forEach(function(doc) {
                const selections = self.imageSelections[doc.fileName];
                if (selections && selections.length > 0) {
                    // Map images to block paths
                    doc.imageMapping = self.buildImageMapping(selections);
                } else {
                    doc.imageMapping = {};
                }
            });

            // Call completion callback
            if (this.onCompleteCallback) {
                this.onCompleteCallback(this.pendingDocuments);
            }
        },

        /**
         * Build image mapping from selections
         */
        buildImageMapping: function(selectedIds) {
            const mapping = {};

            // Map each selected image to its corresponding block path
            this.templateRequirements.forEach(function(req, index) {
                if (index < selectedIds.length) {
                    const path = req.block_path.join('.');
                    mapping[path] = selectedIds[index];
                }
            });

            return mapping;
        }
    };

})(jQuery);
