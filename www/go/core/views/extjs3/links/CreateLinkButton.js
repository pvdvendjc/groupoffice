/* global Ext, go */

go.links.CreateLinkButton = Ext.extend(Ext.Button, {
	//iconCls: 'ic-link',
	text: t("Links"),
	newLinks : [],
	cls: "go-create-link-btn",
	totalCount: 0,
	addLink : function(entity, entityId) {	
		
		var me = this;
		//We need to query the ID of the search cache so the "to" relation can be resolved.
		go.Db.store("Search").query({
			filter: {
				entities: [{name: entity}],
				entityId: entityId
			}
		}).then(function(response) {
			var newLink = {
				"toId": entityId,
				"toEntity": entity,
				"toSearchId": response.ids[0]
			};

			me.newLinks.push(newLink);
			me.linkGrid.store.loadData({"records" :[newLink]}, true);
			me.setCount(++me.totalCount);
		});		
		
	},
					
	initComponent: function () {

		this.searchField = new go.search.SearchField({
			
			anchor: "100%",
			hideLabel: true,
			listeners: {
				scope: this,
				select: function (cmb, record, index) {					
					this.linkGrid.store.loadData({"records" :[{
						"toId": record.get('entityId'),
						"toEntity": record.get('entity'),
						"toSearchId": record.get('id')
					}]}, true);
					// this.searchField.reset();
					
					this.newLinks.push({						
						toEntity: record.get('entity'),
						toId: record.get('entityId')
					});
					this.setCount(++this.totalCount);
				}
			},
			getListParent: function () {
				//this avoids hiding the menu on click in the list
				return this.el.up('.x-menu');
			}
		});

		// this.searchField = new go.search.SearchCombo({
		// 	anchor: "100%",
		// 	hideLabel: true,
		// 	listeners: {
		// 		scope: this,
		// 		select: function (cmb, record, index) {					
		// 			this.linkGrid.store.loadData({"records" :[{
		// 				"toId": record.get('entityId'),
		// 				"toEntity": record.get('entity'),
		// 				"toSearchId": record.get('id')
		// 			}]}, true);
		// 			this.searchField.reset();
					
		// 			this.newLinks.push({						
		// 				toEntity: record.get('entity'),
		// 				toId: record.get('entityId')
		// 			});
		// 			this.setCount(++this.totalCount);
		// 		}
		// 	},
		// 	getListParent: function () {
		// 		//this avoids hiding the menu on click in the list
		// 		return this.el.up('.x-menu');
		// 	}
		// });

		this.linkGrid = new go.grid.GridPanel({
			columns: [
				{
					id: 'name',
					header: t('Name'),					
					sortable: true,
					dataIndex: 'to',
					renderer: function (value, metaData, record, rowIndex, colIndex, store) {						
						var linkIconCls = go.Entities.getLinkIcon(record.data.toEntity, record.data.to.filter);

						return '<i class="entity ' + linkIconCls + '"></i> ' + record.data.to.name;
					}
				},
				{
					width: dp(80),
					menuDisabled: true,
					draggable: false,
					hidable: false,
					align: "right",
					sortable: false,
					dataIndex: "entityId",
					renderer: function (v, meta, record) {						
						return "<button class='icon'>delete</button>";						
					}
				}
			],
			autoExpandColumn: 'name',
			store: new go.data.Store({
				autoDestroy: true,
				fields: ['id', 'toId', 'toEntity', {name: "to", type: "relation"}, 'description', {name: 'modifiedAt', type: 'date'}],
				entityStore: "Link",
				sortInfo: {
					field: 'modifiedAt',
					direction: 'DESC'
				},
				baseParams: {
					filter: {}
				}
			}),
			tbar: new Ext.Toolbar({
				layout: "fit",
				items: [{
						xtype: "fieldset",
						items: [this.searchField]
					}]
			}),
			listeners: {
				scope: this,
				rowclick: function (grid, rowIndex, e) {
					if (e.target.tagName !== "BUTTON") {
						return false;
					}
					
					var record = grid.store.getAt(rowIndex);
					grid.store.remove(record);
					this.setCount(--this.totalCount);
					
					var i = this.newLinks.findIndex(function(l) {
						return l.toId === record.get('toId') && l.toEntity === record.get('toEntity');
					});
					
					if(i > -1) {
						this.newLinks = this.newLinks.splice(i, 1);
					} else
					{
						go.Db.store("Link").set({
							destroy: [record.id]
						});
					}
				}
			},
			width: dp(800),
			height: dp(400)
		}
		);

		var me = this;

		this.menu = new Ext.menu.Menu({
			items: [this.linkGrid],
			doFocus: function () {
				me.searchField.focus();
			}
//			listeners: {
//				scope: this,	
//				show: function() {
//					if(this.linkGrid.store.baseParams.filter.entityId) {
//						
//					}
//				}
//			}
		});

		go.links.CreateLinkButton.superclass.initComponent.call(this);
		
		this.origText = this.text;

	},	
	
	setCount : function(count) {		
		this.totalCount = count;
		this.setText(this.origText + " <span class='badge'>" + (this.totalCount) + "</span>");
	},
	
	setEntity : function(entity, entityId) {
		
		var f = this.linkGrid.store.baseParams.filter;
		
		if(f.entity === entity && f.entityId === entityId) {
			return;
		}	
		
		f.entity = entity;
		f.entityId = entityId;		
		
		if(!entityId) {
			this.reset();
			return;
		}
		
		this.linkGrid.store.load({
			scope: this,
			callback: function() {
				this.setCount(this.linkGrid.store.getTotalCount());			
			}
		});
		//this.menu.on("show", this.load, this, {single: true});
	},
	
	reset : function() {
		
		// Clear the new attached links list
		this.newLinks = [];		
		this.linkGrid.store.removeAll();
		this.linkGrid.store.baseParams.filter.entity = null;
		this.linkGrid.store.baseParams.filter.entityId = null;	
		this.setCount(0);
		//this.menu.un("show", this.load);
	},
	
	load: function() {
		this.linkGrid.store.load();		
	},	
	
	getNewLinks : function() {
		var links = {}, i = 0, id;		
		
		this.newLinks.forEach(function(l) {
			id = "new" + (i++);
			l.fromEntity = this.linkGrid.store.baseParams.filter.entity;
			l.fromId = this.linkGrid.store.baseParams.filter.entityId;
			//comes from store record relation
			delete l.to;
			links[id] = l;
		}, this);
		
		return links;
	},
	
	save : function() {
		
		if(this.newLinks.length === 0) {
			return;
		}

		var me = this;
		
		go.Db.store("Link").set({
			create: this.getNewLinks()
		})
		.then(function(result) {
			if(result.notCreated) {
				Ext.MessageBox.alert(t("Error"), t("Sorry, the link could not be created."));
			}
		})
		
		.finally(function() {
			if(!me.isDestroyed) {
				var e = me.linkGrid.store.baseParams.filter.entity, id = me.linkGrid.store.baseParams.filter.entityId;
				me.reset();
				me.setEntity(e, id);
			}
		});
	}
});


Ext.reg("createlinkbutton", go.links.CreateLinkButton);