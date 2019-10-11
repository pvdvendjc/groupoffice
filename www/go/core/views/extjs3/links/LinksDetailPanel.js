/* global Ext, go */


go.links.DetailPanel = Ext.extend(Ext.Panel, {
	cls: 'go-links-detail',
	limit: 5,
	initComponent: function () {
		var store = this.store = new go.data.Store({
			baseParams: {
				limit: this.limit,
				position: 0,
				calculateTotal:true,				
			},
			filters: {
				toEntity: {entities: [{name: this.link.entity, filter: this.link.filter}]}
			},
			fields: [
				'id', 
				{name: "to", type: "relation"}, 
				'toId', 
				'toSearchId',
				{name: 'createdAt', type: 'date'}, 
				'toEntity'
			],
			entityStore: "Link",
			listeners: {
				datachanged: function () {
					this.setVisible(this.store.getCount() > 0);
				},
				scope: this
			}
		});
		
		
		var tpl = new Ext.XTemplate('<div class="icons"><tpl for=".">\
				<p data-id="{id}">\
				<tpl if="xindex === 1">\
					<i class="label ' + this.link.iconCls + '" ext:qtip="{toEntity}"></i>\
				</tpl>\
				<tpl for="to">\
				<a>{name}</a>\
				<small class="go-top-right">{[go.util.Format.shortDateTime(values.modifiedAt)]}</small>\
				<label>{description}</label>\
				<a class="right show-on-hover"><i class="icon">delete</i></a>\
				</tpl>\
			</p>\
		</tpl>\
		{[this.printMore(values)]}\
		</div>', {			
			
			printMore : function(values) {
				if(store.getCount() < store.getTotalCount()) {
					return "<a class=\"show-more\">" + t("Show more...") + "</a>";
				} else
				{
					return "";
				}
			}
		});
		
		
		Ext.apply(this, {
			listeners: {
				added: function(me, dv, index) {
					this.stateId = 'go-links-' + (dv.entity ? dv.entity : dv.entityStore.entity.name);
				},
				scope: this
			},
//			header: false,
			collapsible: true,
			titleCollapse: true,
			title: this.link.title,
			items: this.dataView = new Ext.DataView({
				store: this.store,
				tpl: tpl,
				autoHeight: true,
				multiSelect: true,
				itemSelector: 'p',
				listeners: {
					scope: this,
					containerclick: function(dv, e) {
		
						if(e.target.classList.contains("show-more")) {
							this.store.baseParams.position += this.limit;
							this.store.load({
								add: true,
								callback: function() {
									this.dataView.refresh();
								},
								scope: this
							});
						}
					},
					click: function (dv, index, node, e) {
						
						
						var record = this.store.getAt(index);
						
						if(e.target.tagName === "I" && e.target.innerHTML === 'delete') {							
							//this.delete(record);
							
							Ext.MessageBox.confirm(t("Delete"), t("Are you sure you want to delete this item?"), function(btn){
								if(btn == "yes") {
									go.Db.store("Link").set({
										destroy: [record.id]
									});
								}
							}, this);
							
						} else 
						{
							var record = this.store.getById(node.getAttribute('data-id'));
							var win = new go.links.LinkDetailWindow({
								entity: record.data.toEntity
							});
							
							win.load(record.data.toId);

//								var lb = new go.links.LinkBrowser({
//									entity: this.store.baseParams.filter.entity,
//									entityId: this.store.baseParams.filter.entityId
//								});
//
//								lb.show();
//								lb.load(record.data.toEntity, record.data.toId);
						}
					}
				}
			})

		});
		
		go.links.DetailPanel.superclass.initComponent.call(this);
	},
	
	onLoad: function (dv) {
		
		this.detailView = dv;	
		
		this.hide();
		
		this.store.setFilter('fromEntity', {
			entity: dv.entity ? dv.entity : dv.entityStore.entity.name, //dv.entity exists on old DetailView or display panels
			entityId: dv.model_id ? dv.model_id : dv.currentId //model_id is from old display panel
		});

		this.store.baseParams.position = 0;
		this.store.load();
	}
});

go.links.getDetailPanels = function() {
	
	var panels = [];
	
	go.Entities.getLinkConfigs().forEach(function (e) {
		if(e.linkDetailCards) {
			var clss = e.linkDetailCards();
			if(!Ext.isArray(clss)) {
				clss = [clss];
			}
			panels = panels.concat(clss);
		} else {
			panels.push(new go.links.DetailPanel({
				link: e
			}));
		}
	});
	
	return panels;
};
