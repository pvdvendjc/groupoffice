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

go.modules.community.notes.MainPanel = Ext.extend(go.modules.ModulePanel, {
	id: "notes",
	title: t("Notes"),

	layout: 'responsive',
	layoutConfig: {
		triggerWidth: 1000
	},

	initComponent: function () {

		this.createNoteGrid();

		this.sidePanel = new Ext.Panel({
			layout: 'anchor',
			defaults: {
				anchor: '100%'
			},
			width: dp(300),
			cls: 'go-sidenav',
			region: "west",
			split: true,
			autoScroll: true,			
			items: [
				this.createNoteBookGrid(),
				this.createFilterPanel()
			]
		});

		this.noteDetail = new go.modules.community.notes.NoteDetail({
			region: 'center',
			split: true,
			tbar: [{
					cls: 'go-narrow',
					iconCls: "ic-arrow-back",
					handler: function () {
						//this.westPanel.show();
						go.Router.goto("notes");
					},
					scope: this
				}]
		});

		this.westPanel = new Ext.Panel({
			region: "west",
			layout: "responsive",
			stateId: "go-notes-west",
			split: true,
			width: dp(700),
			narrowWidth: dp(400), //this will only work for panels inside another panel with layout=responsive. Not ideal but at the moment the only way I could make it work
			items: [
				this.noteGrid, //first is default in narrow mode
				this.sidePanel
			]
		});

		this.items = [
			this.westPanel, //first is default in narrow mode
			this.noteDetail
		];

		go.modules.community.notes.MainPanel.superclass.initComponent.call(this);
		
		
		this.on("afterrender", this.runModule, this);
	},
	
	runModule : function() {
		//load note books and select the first
		this.noteBookGrid.getStore().load({
			callback: function (store) {
				var index = this.noteBookGrid.store.indexOfId(go.User.notesSettings.defaultNoteBookId);
				if(index == -1) {
					index = 0;
				}

				this.noteBookGrid.getSelectionModel().selectRow(index);
			},
			scope: this
		});
	},

	createFilterPanel: function () {
		
		
		return new Ext.Panel({
			
			tbar: [
				{
					xtype: 'tbtitle',
					text: t("Filters")
				},
				'->',
				{
					xtype: "button",
					iconCls: "ic-add",
					handler: function() {
						var dlg = new go.filter.FilterDialog({
							entity: "Note"
						});
						dlg.show();
					},
					scope: this
				}
			],
			items: [
				this.filterGrid = new go.filter.FilterGrid({
					filterStore: this.noteGrid.store,
					entity: "Note"
				})
			]
		});
		
		
	},
	
	createNoteBookGrid : function() {
		this.noteBookGrid = new go.modules.community.notes.NoteBookGrid({
			autoHeight: true,
			tbar: [{
					xtype: 'tbtitle',
					text: t('Notebooks')
				}, '->', {
					//disabled: go.Modules.get("community", 'notes').permissionLevel < go.permissionLevels.write,
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (e, toolEl) {
						var dlg = new go.modules.community.notes.NoteBookDialog();
						dlg.show();
					}
				}, 
				{
					cls: 'go-narrow',
					iconCls: "ic-arrow-forward",
					tooltip: t("Notes"),
					handler: function () {
						this.noteGrid.show();
					},
					scope: this
				}],
			listeners: {
				rowclick: function(grid, row, e) {
					if(e.target.className != 'x-grid3-row-checker') {
						//if row was clicked and not the checkbox then switch to grid in narrow mode
						this.noteGrid.show();
					}
				},
				scope: this
			}
		});

		this.noteBookGrid.getSelectionModel().on('selectionchange', this.onNoteBookSelectionChange, this, {buffer: 1}); //add buffer because it clears selection first

		return this.noteBookGrid;
	},
	
	
	createNoteGrid : function() {
		this.noteGrid = new go.modules.community.notes.NoteGrid({
			region: 'center',
			tbar: [
				{
					cls: 'go-narrow',
					iconCls: "ic-menu",
					handler: function () {
//						this.westPanel.getLayout().setActiveItem(this.noteBookGrid);
						//this.noteBookGrid.show();
						this.sidePanel.show();
					},
					scope: this
				},
				'->',
				{
					xtype: 'tbsearch',
					filters: [
						'text',
						'name', 
						'content',
						{name: 'modified', multiple: false},
						{name: 'created', multiple: false}						
					]
				},
				this.addButton = new Ext.Button({
					disabled: true,
					iconCls: 'ic-add',
					tooltip: t('Add'),
					handler: function (btn) {
						var noteForm = new go.modules.community.notes.NoteDialog();
						noteForm.show();
						noteForm.setValues({
								noteBookId: this.addNoteBookId
							});
					},
					scope: this
				}),
				{
					iconCls: 'ic-more-vert',
					menu: [
						{
							itemId: "delete",
							iconCls: 'ic-delete',
							text: t("Delete"),
							handler: function () {
								this.noteGrid.deleteSelected();
							},
							scope: this
						}
					]
				}
				
//				,{
//					disabled: go.Modules.get("community", 'notes').permissionLevel < go.permissionLevels.write,
//					iconCls: 'ic-add',
//					tooltip: t('Add test'),
//					handler: function (e, toolEl) {
//						var store = this.noteGrid.store;
//						var myRecordDef = Ext.data.Record.create(store.fields);
//
//						store.insert(0, new myRecordDef({
//							name: "New",
//							content: "Testing",
//							noteBookId: this.addNoteBookId
//						}));
//						
//						store.commitChanges();
//					},
//					scope: this
//				}
			],
			listeners: {				
				rowdblclick: this.onNoteGridDblClick,
				scope: this,				
				keypress: this.onNoteGridKeyPress
			}
		});

		this.noteGrid.on('navigate', function (grid, rowIndex, record) {
			go.Router.goto("note/" + record.id);
		}, this);
		
		return this.noteGrid;
	
	},
	
	onNoteBookSelectionChange : function (sm) {
		var ids = [];

		this.addNoteBookId = false;

		Ext.each(sm.getSelections(), function (r) {
			ids.push(r.id);
			if (!this.addNoteBookId && r.get('permissionLevel') >= go.permissionLevels.write) {
				this.addNoteBookId = r.id;
			}
		}, this);

		this.addButton.setDisabled(!this.addNoteBookId);
		
		this.noteGrid.store.setFilter("notebooks", {noteBookId: ids});;
		this.noteGrid.store.load();
	},
	
	onNoteGridDblClick : function (grid, rowIndex, e) {

		var record = grid.getStore().getAt(rowIndex);
		if (record.get('permissionLevel') < go.permissionLevels.write) {
			return;
		}

		var dlg = new go.modules.community.notes.NoteDialog();
		dlg.load(record.id).show();
	},
	
	onNoteGridKeyPress : function(e) {
		if(e.keyCode != e.ENTER) {
			return;
		}
		var record = this.noteGrid.getSelectionModel().getSelected();
		if(!record) {
			return;
		}

		if (record.get('permissionLevel') < go.permissionLevels.write) {
			return;
		}

		var dlg = new go.modules.community.notes.NoteDialog();
		dlg.load(record.id).show();

	}

			
});

