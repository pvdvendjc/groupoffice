go.modules.community.multi_instance.Dialog = Ext.extend(go.panels.TabWindow, {

	title: t("Instances"),

	initComponent: function () {
		go.modules.community.multi_instance.Dialog.superclass.initComponent(this);

		this.addPanel(go.modules.community.multi_instance.InstanceDialog);
	}

});