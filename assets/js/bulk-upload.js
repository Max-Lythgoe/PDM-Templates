/**
 * Bulk Upload Handler
 */
(function($) {
	'use strict';
	
	const BulkUpload = {
		files: [],
		processedDocs: [],
		
		init: function() {
			this.setupDropZone();
			this.setupFileInput();
			this.setupButtons();
		},
		
		setupDropZone: function() {
			const dropZone = $('#pdm-drop-zone');
			const fileInput = $('#pdm-file-input');
			
			// Ensure file input exists
			if (!fileInput.length) {
				console.error('File input #pdm-file-input not found');
				return;
			}
			
		
		// Remove any existing handlers to prevent duplicates
		dropZone.off('click').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			// Trigger click on the actual DOM element
			const nativeInput = document.getElementById('pdm-file-input');
			if (nativeInput) {
				nativeInput.click();
			} else {
				console.error('Native file input not found!');
			}
			
			return false;
		});
		
		dropZone.off('dragover').on('dragover', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('dragover');
		});
		
		dropZone.off('dragleave').on('dragleave', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('dragover');
		});
		
		dropZone.off('drop').on('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('dragover');
			
			const files = e.originalEvent.dataTransfer.files;
			BulkUpload.handleFiles(files);
		});
	},
	
	setupFileInput: function() {
		$('#pdm-file-input').off('change').on('change', function(e) {
			BulkUpload.handleFiles(this.files);
			$(this).val(''); // Reset input
		});
	},
	
	setupButtons: function() {
		$('#pdm-process-btn').on('click', function() {
			BulkUpload.processDocuments();
		});
		
		$('#pdm-clear-btn').on('click', function() {
			BulkUpload.clearAll();
		});
	},
		
	handleFiles: function(files) {
			Array.from(files).forEach(file => {
				if (file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || 
					file.name.endsWith('.docx')) {
					this.addFile(file);
				}
			});
			
			this.updateUI();
		},
		
		addFile: function(file) {
			// Check if file already exists
			const exists = this.files.some(f => f.name === file.name && f.size === file.size);
			if (exists) {
				return;
			}
			
			this.files.push(file);
			this.displayFile(file);
		},
		
		displayFile: function(file) {
			const fileList = $('#pdm-file-list');
			const fileSize = this.formatFileSize(file.size);
			
			const fileItem = $(`
				<div class="pdm-file-item" data-file-name="${file.name}">
					<div class="pdm-file-info">
						<span class="dashicons dashicons-media-document pdm-file-icon"></span>
						<div>
							<div class="pdm-file-name">${file.name}</div>
							<div class="pdm-file-size">${fileSize}</div>
						</div>
					</div>
					<button type="button" class="pdm-file-remove" aria-label="Remove">
						<span class="dashicons dashicons-no"></span>
					</button>
				</div>
			`);
			
			fileItem.find('.pdm-file-remove').on('click', function() {
				BulkUpload.removeFile(file.name);
			});
			
			fileList.append(fileItem);
		},
		
		removeFile: function(fileName) {
			this.files = this.files.filter(f => f.name !== fileName);
			$(`.pdm-file-item[data-file-name="${fileName}"]`).remove();
			this.updateUI();
		},
		
		clearAll: function() {
			this.files = [];
			this.processedDocs = [];
			$('#pdm-file-list').empty();
			$('#pdm-progress-container').hide();
			$('#pdm-results-list').empty();
			this.updateUI();
		},
		
		updateUI: function() {
			if (this.files.length > 0) {
				$('#pdm-process-btn').prop('disabled', false);
			} else {
				$('#pdm-process-btn').prop('disabled', true);
			}
		},
		
		formatFileSize: function(bytes) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
		},
		
		async processDocuments() {
			const templateId = $('#pdm-template-select').val();
			const postType = $('#pdm-post-type-select').val();
			const postStatus = $('#pdm-post-status-select').val();
			
			if (!templateId) {
				alert(pdmBulkUpload.strings.selectTemplate);
				return;
			}
			
			if (!postType) {
				alert(pdmBulkUpload.strings.selectPostType);
				return;
			}
			
			// Show progress container
			$('#pdm-progress-container').show();
			$('#pdm-results-list').empty();
			
			// Disable buttons
			$('#pdm-process-btn').prop('disabled', true);
			$('#pdm-clear-btn').prop('disabled', true);
			
			// First, parse all documents
			const parsedDocuments = [];
			const total = this.files.length;
			
			for (let i = 0; i < this.files.length; i++) {
				const file = this.files[i];
				this.updateProgress(i + 1, total, 'Parsing documents...');
				
				try {
					const docData = await this.parseDocument(file);
					parsedDocuments.push(docData);
				} catch (error) {
					console.error('Error parsing file:', file.name, error);
					this.addResult(file.name, 'error', 'Failed to parse: ' + (error.message || 'Unknown error'));
				}
			}
			
			// If no documents parsed successfully, stop
			if (parsedDocuments.length === 0) {
				alert('No documents were successfully parsed.');
				$('#pdm-process-btn').prop('disabled', false);
				$('#pdm-clear-btn').prop('disabled', false);
				return;
			}
			
			// Show image selection UI
			PDMImageSelector.init(parsedDocuments, templateId, async (documentsWithImages) => {
				// After image selection, process each document
				let processed = 0;
				
				for (const docData of documentsWithImages) {
					try {
						// Create the page with image mapping
						await BulkUpload.createPage(docData, templateId, postType, postStatus);
						
						processed++;
						BulkUpload.updateProgress(processed, documentsWithImages.length, 'Creating pages...');
						
					} catch (error) {
						console.error('Error processing file:', docData.fileName, error);
						BulkUpload.addResult(docData.fileName, 'error', error.message || 'Unknown error');
						processed++;
						BulkUpload.updateProgress(processed, documentsWithImages.length, 'Creating pages...');
					}
				}
				
				// Re-enable buttons
				$('#pdm-clear-btn').prop('disabled', false);
			});
		},
		
		async parseDocument(file) {
			return new Promise((resolve, reject) => {
				const reader = new FileReader();
				
				reader.onload = async function(e) {
					try {
						const arrayBuffer = e.target.result;
						
						// Use Mammoth.js to extract HTML
						const result = await mammoth.convertToHtml({arrayBuffer: arrayBuffer});
						const html = result.value;
						
						// Parse HTML to extract sections
						const parser = new DOMParser();
						const doc = parser.parseFromString(html, 'text/html');
						
						// Extract title
						// Word exports title as first <p> tag, not H1
						let title = file.name.replace('.docx', '');
						const titleElement = doc.querySelector('h1');
						if (titleElement) {
							// If H1 exists, use it
							title = titleElement.textContent.trim();
						} else {
							// Otherwise use first paragraph (Word's "Title" style becomes <p>)
							const firstP = doc.querySelector('p');
							if (firstP) {
								const pText = firstP.textContent.trim();
								// Use first paragraph as title if it's not too long (< 100 chars)
								if (pText && pText.length < 100) {
									title = pText;
								}
							}
						}
						
						
						// Extract sections based on headings
						const sections = BulkUpload.extractSections(doc);
						
						resolve({
							fileName: file.name,
							title: title,
							sections: sections,
							html: html
						});
						
					} catch (error) {
						reject(error);
					}
				};
				
				reader.onerror = function() {
					reject(new Error('Failed to read file'));
				};
				
				reader.readAsArrayBuffer(file);
			});
		},
		
		extractSections: function(doc) {
			const sections = [];
			const allH2s = doc.querySelectorAll('h2');
			
			// Get all body children to find intro content
			const bodyChildren = Array.from(doc.body.children);
			const firstH2 = allH2s[0];
			
			// SECTION 1: Intro section (everything before first H2)
			// This includes the title paragraph and any intro paragraphs
			if (firstH2) {
				const contentParts = [];
				
				// Find index of first H2
				const firstH2Index = bodyChildren.indexOf(firstH2);
				
				// Collect all content BEFORE the first H2 (title + intro paragraphs)
				for (let i = 0; i < firstH2Index; i++) {
					const element = bodyChildren[i];
					const text = element.textContent.trim();
					if (text || element.querySelector('img, table')) {
						contentParts.push(element.outerHTML);
					}
				}
				
				// Remove the FIRST paragraph (title) since it's used as the H1
				if (contentParts.length > 0 && contentParts[0].startsWith('<p>')) {
					contentParts.shift(); // Remove first element
				}
				
				// Create intro section with NO heading (content is just intro paragraphs)
				if (contentParts.length > 0) {
					sections.push({
						id: 'section-intro',
						heading: '', // Empty heading for intro
						level: 2,
						content: contentParts.join('\n'),
						isFAQ: false,
						accordions: []
					});
				}
			}
			
			// SECTIONS 2-N: Process each H2 heading
			allH2s.forEach((h2, index) => {
				const headingText = h2.textContent.trim();
				
				const section = {
					id: `section-${index + 1}`,
					heading: headingText,
					level: 2,
					content: '',
					isFAQ: false,
					accordions: []
				};
				
				// Check if this is an FAQ section
				const isFAQ = /frequently asked questions|faq|f\.a\.q/i.test(headingText);
				
				if (isFAQ) {
					section.isFAQ = true;
					// Extract accordion items (H3 + content)
					section.accordions = this.extractAccordions(h2);
				} else {
					// Regular section: Get content until next H2
					const contentParts = [];
					let nextElement = h2.nextElementSibling;
					
					// Collect all content until the next H2 (or end of document)
					while (nextElement && !nextElement.matches('h2')) {
						const text = nextElement.textContent.trim();
						if (text || nextElement.querySelector('img, table')) {
							contentParts.push(nextElement.outerHTML);
						}
						nextElement = nextElement.nextElementSibling;
					}
					
					section.content = contentParts.join('\n');
				}
				
				sections.push(section);
			});
			
			// Fallback: If no H2s found, create one section with all content
			if (allH2s.length === 0) {
				sections.push({
					id: 'section-0',
					heading: 'Content',
					level: 2,
					content: doc.body.innerHTML,
					isFAQ: false,
					accordions: []
				});
			}
			
			
			return sections;
		},
		
		extractAccordions: function(faqHeading) {
			const accordions = [];
			let nextElement = faqHeading.nextElementSibling;
			
			// Find all H3 headings until the next H2
			while (nextElement && !nextElement.matches('h2')) {
				if (nextElement.matches('h3')) {
					const title = nextElement.textContent.trim();
					
					const accordion = {
						title: title,
						content: ''
					};
					
					// Get content until next H3 or H2
					let contentElement = nextElement.nextElementSibling;
					const contentParts = [];
					
					while (contentElement && !contentElement.matches('h2, h3')) {
						const text = contentElement.textContent.trim();
						if (text) {
							contentParts.push(contentElement.outerHTML);
						}
						contentElement = contentElement.nextElementSibling;
					}
					
					accordion.content = contentParts.join('\n');
					accordions.push(accordion);
					
					// Update nextElement to where contentElement ended (the next H3/H2)
					nextElement = contentElement;
				} else {
					// Not an H3, skip this element
					nextElement = nextElement.nextElementSibling;
				}
			}
			
			return accordions;
		},
		
		async createPage(docData, templateId, postType, postStatus) {
			return new Promise((resolve, reject) => {
				$.ajax({
					url: pdmBulkUpload.ajaxUrl,
					type: 'POST',
					data: {
						action: 'pdm_process_docx',
						nonce: pdmBulkUpload.nonce,
						template_id: templateId,
						post_type: postType,
						post_status: postStatus,
						document: JSON.stringify(docData),
						image_mapping: JSON.stringify(docData.imageMapping || {})
					},
					success: function(response) {
						if (response.success) {
							BulkUpload.addResult(
								docData.fileName, 
								'success', 
								'Page created successfully',
								response.data.edit_url,
								response.data.post_title
							);
							resolve(response.data);
						} else {
							reject(new Error(response.data.message || 'Failed to create page'));
						}
					},
					error: function(xhr, status, error) {
						reject(new Error(error));
					}
				});
			});
		},
		
		updateProgress: function(current, total, status = null) {
			const percent = Math.round((current / total) * 100);
			$('.pdm-progress-fill').css('width', percent + '%');
			const statusText = status ? ` (${status})` : '';
			$('.pdm-progress-text').text(`${current} / ${total}${statusText}`);
		},
		
		addResult: function(fileName, status, message, editUrl = null, postTitle = null) {
			const resultList = $('#pdm-results-list');
			const icon = status === 'success' ? 'yes-alt' : 'dismiss';
			
			let actionsHtml = '';
			if (editUrl) {
				actionsHtml = `
					<div class="pdm-result-actions">
						<a href="${editUrl}" class="button button-small" target="_blank">
							Edit Page
						</a>
					</div>
				`;
			}
			
			const resultItem = $(`
				<div class="pdm-result-item ${status}">
					<div class="pdm-result-info">
						<span class="dashicons dashicons-${icon} pdm-result-icon"></span>
						<div>
							<div class="pdm-result-title">${postTitle || fileName}</div>
							<div class="pdm-result-message">${message}</div>
						</div>
					</div>
					${actionsHtml}
				</div>
			`);
			
			resultList.append(resultItem);
		}
	};
	
	// Initialize on document ready
	$(document).ready(function() {
		BulkUpload.init();
	});
	
})(jQuery);
