go.modules.community.multi_instance.Dialog = Ext.extend(go.panels.TabWindow, {

	title: t("Instances"),
	stateId: 'multi_instance-Dialog',
	entityStore: go.Stores.get("Instance"),

	initComponent: function () {

		this.addPanel(go.modules.community.multi_instance.InstanceDialog);
		this.addPanel(go.modules.community.multi_instance.Modules);

		go.modules.community.multi_instance.Dialog.superclass.initComponent.call(this);

	}

});