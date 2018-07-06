go.modules.community.multi_instance.InstanceDialog = Ext.extend(Ext.form.FormPanel, {
	initComponent: function() {
		this.hostnameField = new Ext.form.TextField({
			name: 'hostname',
			fieldLabel: t("Hostname"),
			anchor: '100%',
			required: true
		});
		Ext.apply(this, {
			title: t('Properties'),
			autoScroll: true,
			iconCls: 'ic-description',
			items: [{
				xtype: 'fieldset',
				defaults: {
					width: dp(240)
				},
				items: [
					this.hostnameField,
					{
						xtype: 'textfield',
						fieldLabel: 'Description',
						anchor: '100%',
						name: 'description'
					}
				]
			}]
		});
		go.modules.community.multi_instance.InstanceDialog.superclass.initComponent.call(this)
	},
	onLoad: function(cb, tabPanel) {

		var instances = tabPanel.entityStore.get([tabPanel.currentId]);

		this.getForm().setValues(instances[0]);

		if (tabPanel.currentId > 0) {
			this.hostnameField.disable();
		}
	}
});

