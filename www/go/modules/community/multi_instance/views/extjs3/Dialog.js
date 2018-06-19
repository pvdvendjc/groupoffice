go.modules.community.multi_instance.Dialog = Ext.extend(go.panels.TabWindow, {

	title: t("Instances"),
	stateId: 'multi_instance-Dialog',
	entityStore: go.Stores.get("Instance"),
	autoHeight: true,

	initComponent: function () {
		go.modules.community.multi_instance.Dialog.superclass.initComponent.call(this);

		this.addPanel(go.modules.community.multi_instance.InstanceDialog);
	}

});