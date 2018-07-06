go.panels.TabWindow = Ext.extend(go.Window, {

	modal:true,
	resizable:true,
	maximizable:true,
	iconCls: 'ic-settings',
	title: '',
	currentId: 0,
	tabPanel: new Ext.TabPanel({
		headerCfg: {cls:'x-hide-display'},
		region: "center",
		items: []
	}),
	tabStore: new Ext.data.ArrayStore({
		fields: ['name', 'icon', 'visible'],
		data: []
	}),

	initComponent: function () {

		this.saveButton = new Ext.Button({
			text: t('Save'),
			handler: this.submit,
			scope:this
		});

		this.closeButton = new Ext.Button({
			text: t('Close'),
			handler: this.close,
			scope: this
		});

		this.selectMenu = new Ext.Panel({
			region:'west',
			cls: 'go-sidenav',
			layout:'fit',
			width:dp(220),
			items:[this.selectView = new Ext.DataView({
				xtype: 'dataview',
				cls: 'go-nav',
				store:this.tabStore,
				singleSelect: true,
				overClass:'x-view-over',
				itemSelector:'div',
				tpl:'<tpl for=".">\
					<div><i class="icon {icon}"></i>\
					<span>{name}</span></div>\
				</tpl>',
				columns: [{dataIndex:'name'}],
				listeners: {
					selectionchange: function(view, nodes) {
						if(nodes.length) {
							this.tabPanel.setActiveTab(nodes[0].viewIndex);
						} else
						{
							//restore selection if user clicked outside of view
							view.select(this.tabPanel.items.indexOf(this.tabPanel.getActiveTab()));
						}
					},
					scope:this
				}
			})]
		});

		Ext.apply(this,{
			width:dp(1000),
			height:dp(800),
			layout:'border',
			closeAction:'hide',
			items: [
				this.selectMenu,
				this.tabPanel
			],
			buttons:[
				this.saveButton,
				this.closeButton
			]
		});

		this.addEvents({
			'loadStart' : true,
			'loadComplete' : true,
			'submitStart' : true,
			'submitComplete' : true
		});

		this.loadModulePanels();

		go.panels.TabWindow.superclass.initComponent.call(this);
	},

	loadModulePanels : function() {

	},

	show: function(){
		go.panels.TabWindow.superclass.show.call(this);
		this.selectView.select(this.tabStore.getAt(0));
		this.load();
	},

	submit : function(){

		this.submitCount = 0;
		// loop through child panels and call onSubmitStart function if available
		this.tabPanel.items.each(function(tab) {
			if(tab.rendered && tab.onSubmit) {
				this.submitCount++;
				tab.onSubmit(this.onSubmitComplete, this);
			}
		},this);
	},

	load: function() {
		// loop through child panels and call onSubmitStart function if available
		this.tabPanel.items.each(function(tab) {
			if(tab.onLoad) {
				tab.onLoad(this.onLoadComplete, this);
			}
		},this);
	},

	onSubmitComplete : function(tab, success) {
		if(success) {
			this.submitCount--;
			if(this.submitCount == 0) {
				this.hide();
			}
		}
	},

	onLoadComplete : function() {

	},

	/**
	 * Add a panel to the tabpanel of this dialog
	 *
	 * @param string panelID
	 * @param string panelClass
	 * @param object panelConfig
	 * @param int position
	 * @param boolean passwordProtected
	 */
	addPanel : function(panelClass, position){
		var cfg = {
			header: false,
			loaded:false,
			submitted:false
		};

		var pnl = new panelClass(cfg);

		var menuRec = new Ext.data.Record({
			'name':pnl.title,
			'icon':pnl.iconCls,
			'visible':true
		});

		if(Ext.isEmpty(position)){
			this.tabPanel.add(pnl);
			this.tabStore.add(menuRec);
		}else{
			this.tabPanel.insert(position,pnl);
			this.tabStore.insert(position,menuRec);
		}
	}

});