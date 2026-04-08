/**
 * Section Mapping for Block Editor
 */
(function(wp) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { PanelRow, Button, TextControl, SelectControl } = wp.components;
	const { Component, Fragment } = wp.element;
	const { compose } = wp.compose;
	const { withSelect, withDispatch } = wp.data;
	const { __ } = wp.i18n;
	
	class SectionMappingPanel extends Component {
		constructor(props) {
			super(props);
			
			// Parse saved mappings
			const savedMappings = props.sectionMappings ? JSON.parse(props.sectionMappings) : [];
			
			this.state = {
				mappings: savedMappings.length > 0 ? savedMappings : [
					{ id: this.generateId(), sectionName: '', blockId: '' }
				]
			};
		}
		
		generateId() {
			return 'section-' + Math.random().toString(36).substr(2, 9);
		}
		
		addMapping = () => {
			this.setState(prevState => ({
				mappings: [
					...prevState.mappings,
					{ id: this.generateId(), sectionName: '', blockId: '' }
				]
			}), () => {
				this.saveMappings();
			});
		}
		
		removeMapping = (id) => {
			this.setState(prevState => ({
				mappings: prevState.mappings.filter(m => m.id !== id)
			}), () => {
				this.saveMappings();
			});
		}
		
		updateMapping = (id, field, value) => {
			this.setState(prevState => ({
				mappings: prevState.mappings.map(m => 
					m.id === id ? { ...m, [field]: value } : m
				)
			}), () => {
				this.saveMappings();
			});
		}
		
		saveMappings = () => {
			const { editPost } = this.props;
			editPost({
				meta: {
					_pdm_section_mappings: JSON.stringify(this.state.mappings)
				}
			});
		}
		
		getBlockOptions = () => {
			const blocks = wp.data.select('core/block-editor').getBlocks();
			const options = [{ label: __('— Select Block —', 'pdm-templates'), value: '' }];
			
			const addBlockOptions = (blocks, prefix = '') => {
				blocks.forEach((block, index) => {
					if (block.name && block.name !== 'core/freeform') {
						const label = prefix + (block.attributes?.metadata?.name || block.name.replace('core/', '') + ' ' + (index + 1));
						const value = block.clientId;
						options.push({ label, value });
					}
					
					if (block.innerBlocks && block.innerBlocks.length > 0) {
						addBlockOptions(block.innerBlocks, prefix + '  ');
					}
				});
			};
			
			addBlockOptions(blocks);
			return options;
		}
		
		render() {
			const { mappings } = this.state;
			const blockOptions = this.getBlockOptions();
			
			return (
				<PluginDocumentSettingPanel
					name="pdm-section-mapping"
					title={pdmSectionMapping.strings.title}
					className="pdm-section-mapping-panel"
				>
					<PanelRow>
						<p style={{ margin: '0 0 16px', fontSize: '13px', color: '#646970' }}>
							{pdmSectionMapping.strings.instructions}
						</p>
					</PanelRow>
					
					{mappings.map((mapping, index) => (
						<div key={mapping.id} style={{ 
							marginBottom: '16px', 
							padding: '12px', 
							border: '1px solid #dcdcde',
							borderRadius: '4px',
							background: '#f6f7f7'
						}}>
							<div style={{ marginBottom: '8px', fontWeight: 500, fontSize: '13px' }}>
								Section {index + 1}
							</div>
							
							<TextControl
								label={pdmSectionMapping.strings.sectionName}
								value={mapping.sectionName}
								onChange={(value) => this.updateMapping(mapping.id, 'sectionName', value)}
								placeholder="e.g., Introduction"
								style={{ marginBottom: '8px' }}
							/>
							
							<SelectControl
								label={pdmSectionMapping.strings.blockPlaceholder}
								value={mapping.blockId}
								options={blockOptions}
								onChange={(value) => this.updateMapping(mapping.id, 'blockId', value)}
							/>
							
							<Button
								isDestructive
								isSmall
								onClick={() => this.removeMapping(mapping.id)}
								style={{ marginTop: '8px' }}
							>
								{pdmSectionMapping.strings.removeSection}
							</Button>
						</div>
					))}
					
					<PanelRow>
						<Button
							isPrimary
							onClick={this.addMapping}
						>
							{pdmSectionMapping.strings.addSection}
						</Button>
					</PanelRow>
				</PluginDocumentSettingPanel>
			);
		}
	}
	
	const SectionMappingPanelWithData = compose([
		withSelect((select) => {
			const meta = select('core/editor').getEditedPostAttribute('meta');
			return {
				sectionMappings: meta ? meta._pdm_section_mappings : ''
			};
		}),
		withDispatch((dispatch) => ({
			editPost: dispatch('core/editor').editPost
		}))
	])(SectionMappingPanel);
	
	registerPlugin('pdm-section-mapping', {
		render: SectionMappingPanelWithData,
		icon: 'editor-table'
	});
	
	// Add block attribute for section ID
	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'pdm-templates/add-section-id',
		function(settings) {
			if (settings.name.startsWith('core/')) {
				settings.attributes = {
					...settings.attributes,
					pdmSectionId: {
						type: 'string',
						default: ''
					}
				};
			}
			return settings;
		}
	);
	
})(window.wp);
