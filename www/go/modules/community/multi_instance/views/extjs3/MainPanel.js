/** 
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @version $Id: MainPanel.js 19225 2015-06-22 15:07:34Z wsmits $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */

go.modules.community.multi_instance.MainPanel = Ext.extend(go.grid.GridPanel, {

	initComponent: function () {

		this.store = new go.data.Store({
			fields: [
				'id', 
				'hostname', 
				'userCount',
				{name: 'createdAt', type: 'date'}, 
				{name: 'lastLogin', type: 'date'}		
			],
			entityStore: go.Stores.get("Instance")
		});

		Ext.apply(this, {		
			tbar: [ '->', {					
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (e, toolEl) {
						var dlg = new go.modules.community.multi_instance.InstanceDialog();
						dlg.show();
					}
				}
				
			],
			columns: [
				{
					id: 'id',
					hidden: true,
					header: 'ID',
					width: 40,
					sortable: true,
					dataIndex: 'id'
				},
				{
					id: 'hostname',
					header: t('Hostname'),
					width: 75,
					sortable: true,
					dataIndex: 'hostname'
				},
				{
					id: 'userCount',
					header: t('User count'),
					width: 160,
					sortable: false,
					dataIndex: 'userCount',
					hidden: false
				},
				{
					xtype:"datecolumn",
					id: 'createdAt',
					header: t('Created at'),
					width: 160,
					sortable: true,
					dataIndex: 'createdAt',
					hidden: false
				},
				{
					xtype:"datecolumn",
					id: 'lastLogin',
					header: t('Last login'),
					width: 160,
					sortable: false,
					dataIndex: 'lastLogin',
					hidden: false
				}
			],
			viewConfig: {
				emptyText: 	'<i>description</i><p>' +t("No items to display") + '</p>'
			},
			autoExpandColumn: 'hostname',
			// config options for stateful behavior
			stateful: true,
			stateId: 'multi_instance-grid'
		});

		go.modules.community.multi_instance.MainPanel.superclass.initComponent.call(this);
		
		this.on('render', function() {
			this.store.load();
		}, this);

		this.on('rowdblclick', function (grid, rowIndex, e) {

			var record = grid.getStore().getAt(rowIndex);
			if (record.get('permissionLevel') < GO.permissionLevels.write) {
				return;
			}

			var dialog = new go.modules.community.multi_instance.Dialog();
			dialog.currentId = record.id;
			dialog.show();

		});
	}
});

