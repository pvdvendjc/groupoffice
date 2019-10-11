Ext.ns("go.customfields.type");

go.customfields.type.Text = Ext.extend(Ext.util.Observable, {
	
	name : "Text",
	
	label: t("Text"),
	
	iconCls: "ic-description",	
	
	/**
	 * Return dialog to edit this type of field
	 * 
	 * @returns {go.customfields.FieldDialog}
	 */
	getDialog : function() {
		return new go.customfields.type.TextDialog();
	},
	
	/**
	 * Render's the custom field value for the detail views
	 * 
	 * If nothing is returned then you must manage the value in the function itself
	 * by calling detailComponent.setValue();
	 * 
	 * See User.js for an asynchronous example.
	 * 
	 * @param {mixed} value
	 * @param {object} data Complete entity
	 * @param {object} customfield Field entity from custom fields
	 * @param {go.detail.Property} detailComponent The property component that renders the value
	 * @returns {string}|undefined
	 */
	renderDetailView: function (value, data, customfield, detailComponent) {
		return Ext.util.Format.htmlEncode(value);
	},
	
	/**
	 * Returns config oject to create the form field 
	 * 
	 * @param {object} customfield customfield Field entity from custom fields
	 * @param {object} config Extra config options to apply to the form field
	 * @returns {Object}
	 */
	createFormFieldConfig : function( customfield, config) {
		config = config || {};

		if (!GO.util.empty(customfield.options.validationRegex)) {

			if (!GO.util.empty(customfield.options.validationModifiers))
				config.regex = new RegExp(customfield.options.validationRegex, customfield.options.validationModifiers);
			else
				config.regex = new RegExp(customfield.options.validationRegex);
		}

		if (!GO.util.empty(customfield.hint)) {
			config.hint = customfield.hint;
		}

		var fieldLabel = customfield.name;

		if (!GO.util.empty(customfield.prefix))
			fieldLabel = fieldLabel + ' (' + customfield.prefix + ')';

		if (!GO.util.empty(customfield.required)) {
			fieldLabel += '*';
		}

		if (customfield.options.maxLength) {
			config.maxLength = customfield.options.maxLength;
		}

		return Ext.apply({			
			xtype: 'textfield',
			serverFormats: false, //for backwars compatibility with old framework. Can be removed when all is refactored.
			name: 'customFields.' + customfield.databaseName,
			fieldLabel: fieldLabel,
			anchor: '100%',
			allowBlank: !customfield.required,
			value: customfield.default
		}, config);
	},
	
	/**
	 * Return's a form field to render
	 * 
	 * @param {object} customfield
	 * @param {object} config
	 * @returns {Ext.form.Field}
	 */
	renderFormField: function (customfield, config) {		
		var field = this.createFormFieldConfig(customfield, config);
		return this.applySuffix(customfield, field);
	},
	
	getDetailField: function(customfield, config) {
		return new go.detail.Property({
			itemId: customfield.databaseName,
			label: customfield.name,
			icon: this.iconCls
		});
	}, 
	
	applySuffix: function (customfield, field) {
		if (!GO.util.empty(customfield.suffix)) {
			field.flex = 1;
			delete field.anchor;
			var hint = field.hint;
			delete field.hint;
			return {
				hint: hint,
				anchor: '-20',
				xtype: 'compositefield',
				fieldLabel: field.fieldLabel,
				items: [field, {
						xtype: 'label',
						text: customfield.suffix,
//							hideLapplySuffixabel: true,
						columnWidth: '.1'
					}]
			};
		} else {
			return field;
		}
	},
	
	getFieldType : function() {
		return "string";
	},
	
	/**
	 * Get the field definition for creating Ext.data.Store's
	 * 
	 * Also the customFieldType (this) and customField (Entity Field) are added
	 * 
	 * @see https://docs.sencha.com/extjs/3.4.0/#!/api/Ext.data.Field
	 * @returns {Object}
	 */
	getFieldDefinition : function(field) {
		return {
			name: "customFields." + field.databaseName,
			type: this.getFieldType(),
			customField: field,
			customFieldType: this,
			columnxtype: this.getColumnXType()
		};
	},

	getRelations : function(customfield) {
		return {};
	},
	
	/**
	 * Get grid column defnitition
	 * 
	 * @param {type} field
	 * @returns {TextAnonym$0.getColumn.TextAnonym$6}
	 */
	getColumn : function(field) {
		
		var def = this.getFieldDefinition(field);
		
		return {
			dataIndex: def.name,
			header: def.customField.name,
			hidden: true,
			id: "custom-field-" + encodeURIComponent(def.customField.databaseName),
			sortable: true,
			hideable: true,
			draggable: true,
			xtype: this.getColumnXType()
		};
	},
	
	/**
	 * See https://docs.sencha.com/extjs/3.4.0/#!/api/Ext.grid.Column-cfg-xtype
	 * @returns {String}
	 */
	getColumnXType : function() {
		return "gridcolumn";
	},
	
	getFilter : function(field) {
		return {
			name: field.databaseName,
			type: "string",
			multiple: true,
			title: field.name,
			customfield: field
		};
	}
	
	
});

go.customfields.CustomFields.registerType(new go.customfields.type.Text());
