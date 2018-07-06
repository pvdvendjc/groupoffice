go.modules.community.multi_instance.Modules = Ext.extend(Ext.form.FormPanel, {
	initComponent: function() {
		Ext.apply(this, {
			title: t('Modules'),
			autoScroll: true,
			iconCls: 'ic-modules',
			items: [{
				xtype: 'fieldset',
				defaults: {
					width: dp(240)
				},
				items: [

				]
			}]

		})
		go.modules.community.multi_instance.Modules.superclass.initComponent.call(this);
	},

	onLoad: function() {
		var modules = [{}];

		this.getForm().setValues(modules[0]);
	}
});