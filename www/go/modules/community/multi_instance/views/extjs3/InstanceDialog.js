go.modules.community.multi_instance.InstanceDialog = Ext.extend(Ext.form.FormPanel, {
	stateId: 'multi_instance-InstanceDialog',
	title: t('Instance'),
	entityStore: go.Stores.get("Instance"),
	autoHeight: true,

	onLoad(callback, tabPanel) {
		if (tabPanel.currentId > 0) {
			this.hostnameField.disable()
		}
		this.load(tabPanel.currentId);
	},

	initFormItems: function () {
		this.hostnameField = new Ext.form.TextField({
			name: 'hostname',
			fieldLabel: t("Hostname"),
			anchor: '100%',
			required: true
		});
		return [{
				xtype: 'fieldset',
				items: [
					this.hostnameField
				]
			}
		];
	}
});

