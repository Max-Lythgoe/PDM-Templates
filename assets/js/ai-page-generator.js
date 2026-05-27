/**
 * AI Page Generator - Admin JavaScript
 */
(function ($) {
	'use strict';

	const AIGenerator = {

		/** Stores { pathKey: attachmentId } for the current template */
		imageMapping: {},

		/** wp.media frame instance (reused) */
		mediaFrame: null,

		/** Path key of the slot currently being edited */
		activeSlotKey: null,

		init: function () {
this.form              = $('#pdm-ai-generator-form');
		this.progress          = $('#pdm-ai-progress');
		this.progressLabel     = this.progress.find('.pdm-ai-progress-label');
		this.result            = $('#pdm-ai-result');
		this.submitBtn         = $('#pdm-ai-generate-btn');
		this.templateSelect    = $('#pdm-ai-template');
		this.templateInfo      = $('#pdm-ai-template-info');
		this.imageSection      = $('#pdm-ai-image-section');
		this.imageSlotsWrap    = $('#pdm-ai-image-slots');
		this.imageMappingField = $('#pdm-ai-image-mapping');
		this.autoImagesCheck   = $('#pdm-ai-auto-images');
		this.manualSlotsWrap   = $('#pdm-ai-manual-slots');
		this.toneSlider        = $('#pdm-ai-tone');
		this.toneName          = $('#pdm-ai-tone-name');
		this.toneDesc          = $('#pdm-ai-tone-desc');

			this.bindEvents();
			this.handleToneChange(); // set initial description
		},

		bindEvents: function () {
			this.form.on('submit', this.handleSubmit.bind(this));
			this.templateSelect.on('change', this.handleTemplateChange.bind(this));		this.autoImagesCheck.on('change', this.handleAutoImagesToggle.bind(this));
			this.toneSlider.on('input', this.handleToneChange.bind(this));
			// Delegate image slot button events
			$(document).on('click', '.pdm-ai-slot-select', this.handleSelectImage.bind(this));
			$(document).on('click', '.pdm-ai-slot-remove', this.handleRemoveImage.bind(this));
		},

		// ---- Tone slider ---------------------------------------------------

		handleToneChange: function () {
			const val  = this.toneSlider.val();
			const tone = pdmAIGenerator.tones[val];
			if (tone) {
				this.toneName.text(tone.label);
				this.toneDesc.text(' — ' + tone.description);
			}			// Update the track fill colour
			const pct         = ((val - 1) / 4) * 100;
			const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--wp-admin-theme-color').trim() || '#2271b1';
			this.toneSlider.css('background',
				'linear-gradient(to right, ' + accentColor + ' ' + pct + '%, #ddd ' + pct + '%)');		},

		// ---- Auto-images toggle --------------------------------------------

		handleAutoImagesToggle: function () {
			if (this.autoImagesCheck.prop('checked')) {
				// Hide manual slots; clear any manual mapping
				this.manualSlotsWrap.hide();
				this.imageMapping = {};
				this.imageMappingField.val('{}');
			} else {
				// Show manual slots if the image section is visible (i.e. template has images)
				if (this.imageSection.is(':visible')) {
					this.manualSlotsWrap.show();
				}
			}
		},

		// ---- Template change ------------------------------------------------

		handleTemplateChange: function () {
			const templateId = this.templateSelect.val();

			// Reset image state when template changes
			this.imageMapping = {};
			this.imageSlotsWrap.empty();
			this.imageSection.hide();
			this.imageMappingField.val('{}');

			if (! templateId) {
				this.templateInfo.text('').removeClass('has-info');
				return;
			}

			$.post(pdmAIGenerator.ajaxUrl, {
				action:      'pdm_ai_get_template_info',
				nonce:       pdmAIGenerator.nonce,
				template_id: templateId,
			})
			.done(function (response) {
				if (response.success && response.data) {
					const data = response.data;

					AIGenerator.templateInfo
						.text(data.description)
						.addClass('has-info');

					if (data.image_count > 0) {
						AIGenerator.renderImageSlots(data.image_requirements);
						AIGenerator.imageSection.show();
						// If manual mode is active, show the slots panel
						if (! AIGenerator.autoImagesCheck.prop('checked')) {
							AIGenerator.manualSlotsWrap.show();
						}
					}
				}
			});
		},

		// ---- Image slots rendering ------------------------------------------

		renderImageSlots: function (requirements) {
			this.imageSlotsWrap.empty();

			requirements.forEach(function (req) {
				const pathKey = req.block_path.join('.');
				const label   = req.context || req.block_name || 'Image';
				const current = req.image_info ? req.image_info.url : null;

				const $slot = $('<div>')
					.addClass('pdm-ai-image-slot')
					.attr('data-path', pathKey);

				const $label = $('<span>').addClass('pdm-ai-slot-label').text(label);

				const $preview = $('<div>').addClass('pdm-ai-slot-preview');
				if (current) {
					$preview.html('<img src="' + AIGenerator.escAttr(current) + '" alt="">');
				} else {
					$preview.addClass('empty').html('<span class="dashicons dashicons-format-image"></span>');
				}

				const $actions = $('<div>').addClass('pdm-ai-slot-actions');
				const $selectBtn = $('<button>')
					.attr({ type: 'button', 'data-path': pathKey })
					.addClass('button pdm-ai-slot-select')
					.text(pdmAIGenerator.strings.selectImage);

				const $removeBtn = $('<button>')
					.attr({ type: 'button', 'data-path': pathKey })
					.addClass('button pdm-ai-slot-remove')
					.text(pdmAIGenerator.strings.removeImage)
					.css('display', 'none');

				$actions.append($selectBtn, $removeBtn);
				$slot.append($label, $preview, $actions);
				AIGenerator.imageSlotsWrap.append($slot);
			});
		},

		// ---- Media picker ---------------------------------------------------

		handleSelectImage: function (e) {
			const pathKey = $(e.currentTarget).data('path');
			this.activeSlotKey = pathKey;

			if (typeof wp === 'undefined' || ! wp.media) {
				alert('WordPress media library is not available.');
				return;
			}

			if (! this.mediaFrame) {
				this.mediaFrame = wp.media({
					title:    pdmAIGenerator.strings.mediaTitle,
					button:   { text: pdmAIGenerator.strings.mediaButton },
					library:  { type: 'image' },
					multiple: false,
				});

				this.mediaFrame.on('select', function () {
					const attachment = AIGenerator.mediaFrame.state().get('selection').first().toJSON();
					AIGenerator.applyImage(AIGenerator.activeSlotKey, attachment);
				});
			}

			this.mediaFrame.open();
		},

		handleRemoveImage: function (e) {
			const pathKey = $(e.currentTarget).data('path');
			this.clearSlot(pathKey);
		},

		applyImage: function (pathKey, attachment) {
			this.imageMapping[pathKey] = attachment.id;
			this.imageMappingField.val(JSON.stringify(this.imageMapping));

			const $slot    = this.imageSlotsWrap.find('[data-path="' + pathKey + '"]');
			const thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$slot.find('.pdm-ai-slot-preview')
				.removeClass('empty')
				.html('<img src="' + this.escAttr(thumbUrl) + '" alt="">');

			$slot.find('.pdm-ai-slot-select').text(pdmAIGenerator.strings.changeImage);
			$slot.find('.pdm-ai-slot-remove').show();
		},

		clearSlot: function (pathKey) {
			delete this.imageMapping[pathKey];
			this.imageMappingField.val(JSON.stringify(this.imageMapping));

			const $slot = this.imageSlotsWrap.find('[data-path="' + pathKey + '"]');
			$slot.find('.pdm-ai-slot-preview')
				.addClass('empty')
				.html('<span class="dashicons dashicons-format-image"></span>');

			$slot.find('.pdm-ai-slot-select').text(pdmAIGenerator.strings.selectImage);
			$slot.find('.pdm-ai-slot-remove').hide();
		},

		// ---- Form submission ------------------------------------------------

		handleSubmit: function (e) {
			e.preventDefault();

			if (! this.validateForm()) {
				return;
			}

			this.setLoading(true, pdmAIGenerator.strings.generating);
			this.result.hide().html('');

			const formData = {
				action:        'pdm_ai_generate_page',
				nonce:         pdmAIGenerator.nonce,
				page_name:     $('#pdm-ai-page-name').val(),
				description:   $('#pdm-ai-description').val(),
				template_id:   $('#pdm-ai-template').val(),
				post_type:     $('#pdm-ai-post-type').val(),
				post_status:   $('#pdm-ai-post-status').val(),
				image_mapping: this.imageMappingField.val(),
				auto_images:   this.autoImagesCheck.prop('checked') ? 1 : 0,
				tone:          (pdmAIGenerator.tones[this.toneSlider.val()] || {}).slug || 'friendly',
			};

			$.ajax({
				url:     pdmAIGenerator.ajaxUrl,
				type:    'POST',
				data:    formData,
				timeout: 90000,
			})
			.done(function (response) {
				AIGenerator.setLoading(false);
				if (response.success) {
					AIGenerator.showSuccess(response.data);
				} else {
					const msg = (response.data && response.data.message)
						? response.data.message
						: pdmAIGenerator.strings.error;
					AIGenerator.showError(msg);
				}
			})
			.fail(function (xhr, status) {
				AIGenerator.setLoading(false);
				const msg = status === 'timeout'
					? 'The request timed out. Please try again.'
					: pdmAIGenerator.strings.error;
				AIGenerator.showError(msg);
			});
		},

		validateForm: function () {
			const name = $.trim($('#pdm-ai-page-name').val());
			const tmpl = $('#pdm-ai-template').val();

			if (! name) {
				alert('Please enter a page name.');
				$('#pdm-ai-page-name').focus();
				return false;
			}
			if (! tmpl) {
				alert('Please select a template.');
				$('#pdm-ai-template').focus();
				return false;
			}
			return true;
		},

		setLoading: function (isLoading, label) {
			if (isLoading) {
				this.submitBtn.prop('disabled', true).addClass('disabled');
				this.progressLabel.text(label || '');
				this.progress.show();
			} else {
				this.submitBtn.prop('disabled', false).removeClass('disabled');
				this.progress.hide();
			}
		},

		showSuccess: function (data) {
			let html = '<div class="pdm-ai-result is-success">';
			html += '<p class="pdm-ai-result-title">&#10003; ' + data.message + '</p>';
			html += '<div class="pdm-ai-result-actions">';

			if (data.edit_url) {
				html += '<a href="' + this.escAttr(data.edit_url) + '" class="button button-primary" target="_blank" rel="noopener">'
					+ pdmAIGenerator.strings.editPage + '</a>';
			}
			if (data.view_url) {
				html += '<a href="' + this.escAttr(data.view_url) + '" class="button button-secondary" target="_blank" rel="noopener">'
					+ pdmAIGenerator.strings.viewPage + '</a>';
			}

			html += '<button type="button" class="button button-secondary pdm-ai-reset-btn">'
				+ pdmAIGenerator.strings.generateAnother + '</button>';
			html += '</div></div>';

			this.result.html(html).show();
			$('html, body').animate({ scrollTop: this.result.offset().top - 80 }, 300);

			$(document).on('click', '.pdm-ai-reset-btn', function () {
				AIGenerator.resetForm();
			});
		},

		showError: function (message) {
			const html = '<div class="pdm-ai-result is-error">'
				+ '<p class="pdm-ai-result-title">&#9888; ' + this.escHtml(message) + '</p>'
				+ '</div>';
			this.result.html(html).show();
			$('html, body').animate({ scrollTop: this.result.offset().top - 80 }, 300);
		},

		resetForm: function () {
			this.form[0].reset();
			this.result.hide().html('');
			this.templateInfo.text('').removeClass('has-info');
			this.imageMapping = {};
			this.imageSlotsWrap.empty();
			this.manualSlotsWrap.hide();
			this.imageSection.hide();
			this.imageMappingField.val('{}');
			this.autoImagesCheck.prop('checked', true); // reset to default on
			$('#pdm-ai-page-name').focus();
		},

		// Basic HTML escape helpers
		escHtml: function (str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		},

		escAttr: function (str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},
	};

	$(function () {
		if (typeof pdmAIGenerator !== 'undefined') {
			AIGenerator.init();
		}
	});

}(jQuery));

