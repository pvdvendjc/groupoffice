/* global Ext, go */

/**
 * Chips multiselect component
 * 
 * For an example see go/modules/community/addressbook/views/extjs3/CustomFieldSetDialog.js
 * 
 * With entityStore:
 * 
 * {
				xtype: "chips",
				entityStore: "Contact",
				displayField: "name",
				name: "addressBooks",
				storeBaseParams: { //Optional base params
					filter: {
						isOrganization: true
					}
				},
				fieldLabel: t("Organization")
			}
 * 
 * With custom store. Data must be local!:
 * 
 * {
				xtype: "chips",
				comboStore: new Ext.data.JsonStore({
					data: [],
					id: 'id',
					root: "options",
					fields: ['id','text'],
					remoteSort:true
				}),
				displayField: "name",
				name: "addressBooks",
				fieldLabel: t("Options")
			}
 * 
 */
go.form.Chips = Ext.extend(Ext.Container, {
	name: null,
	displayField: "name",
	valueField: "id",
	map: false,
	entityStore: null,
	comboStore: null,
	store: null,
	autoHeight: true,
	storeBaseParams: null,
	allowBlank: true,
	
	initComponent: function () {

		var tpl = new Ext.XTemplate(
						'<tpl for=".">',
						'<div class="go-chip">{' + this.displayField + '} <button type="button" class="icon">delete</button></div>',
						'</tpl>',
						'<div class="x-clear"></div>'
						);		
	
		this.dataView = new Ext.DataView({
			store: new Ext.data.JsonStore({
				fields: [this.valueField, this.displayField],
				root: "records",
				idProperty: this.valueField				
			}),
			tpl: tpl,
			autoHeight: true,
			multiSelect: true,
			overClass: 'x-view-over',
			itemSelector: 'div.go-chip',
			listeners: {
				click: this.onClick,
				scope: this
			}
		});
		
	
		this.dataView.store.on("add", function(store, records) {
			if(this.entityStore) {
				this.comboStore.baseParams.filter.exclude = this.dataView.store.getRange().column(this.valueField);				
			}
			this.comboStore.remove(records);//this.comboBox.store.find(this.valueField, records[0].get(this.valueField)));
			this._isDirty = true;
		}, this);
		this.dataView.store.on("remove", function(store, record) {
			if(this.entityStore) {
				this.comboStore.baseParams.filter.exclude = this.dataView.store.getRange().column(this.valueField);							
			} 
			this.comboStore.add([record]);
			this._isDirty = true;
		}, this);		
		
		this.items = [];
		var cb = this.createComboBox();
		if(cb) {
			this.items.push({
				layout: "form",				
				items: [cb]
			});
		}
		this.items.push(this.dataView);
		
		//adds back removed records from static stores.
		this.on("beforedestroy", function() {
			this.reset();
		}, this);

		go.form.Chips.superclass.initComponent.call(this);
	},
	isFormField: true,
	getName: function () {
		return this.name;
	},
	
	reset : function() {
		this.dataView.store.each(function(r) {
			this.dataView.store.remove(r);
		}, this);
		this._isDirty = false;
	},
	
	_isDirty: false,
	
	isDirty: function () {
		return this._isDirty;
	},
	
	setValue: function (values) {
		
		if(this.entityStore) {	
			var ids = this.map ? Object.keys(values) : values;

			this.mapValues = values;

			this.entityStore.get(ids, function (entities) {				
					this.dataView.store.loadData({records: entities}, true);
					this._isDirty = false;
			}, this);
		} else
		{
			var me = this;
			values.forEach(function(v){
				var index = me.comboStore.find(me.valueField, v);
				
				if(index > -1) {
					me.dataView.store.add([me.comboStore.getAt(index)]);
				}
			});
			this._isDirty = false;
		}
		
		
	},
	getValue: function () {		
		var records = this.dataView.store.getRange(), me = this;

		if(this.map) {
			var v = {}, id;
			records.forEach(function(r) {
				id = r.get(me.valueField);
				v[id] = me.mapValues[id] || true;
			});
		} else{
			var v = [];
			records.forEach(function(r) {
				v.push(r.get(me.valueField));
			});
		}
		
		
		return v;
		
	},
	markInvalid: function (msg) {		
		if(this.comboBox) {
			this.comboBox.getEl().addClass('x-form-invalid');
		}
		Ext.form.MessageTargets.qtip.mark(	this.comboBox, msg);
	},
	clearInvalid: function () {
		if(this.comboBox) {
			this.comboBox.getEl().removeClass('x-form-invalid');
		}
		Ext.form.MessageTargets.qtip.clear(this.comboBox);
	},
	createComboBox: function () {
		if(this.store) {
			this.comboStore = this.store;
			return;
		}
		
		if(this.entityStore){
			var baseParams = this.storeBaseParams || {filter : {}};
			if(!baseParams.filter) {
				baseParams.filter = {};				
			}
			baseParams.filter.exclude = [];

			this.comboStore = new go.data.Store({
				fields: [this.valueField, this.displayField],
				entityStore: this.entityStore,
				baseParams: baseParams				
			});

			

		} else
		{
			//clone the store.
//			var records = [];
//			this.comboStore.each(function(r){
//				records.push(r.copy());
//			});
//			
//			this.comboStore = new Ext.data.Store({
//				recordType: this.comboStore.recordType
//			});
//			this.comboStore.add(records);
		}
		
		this.comboBox = new go.form.ComboBox({
			listeners: {
				focus: function(combo){
						combo.onTriggerClick();
				}
			},
			lazyInit: false,
			hideLabel: true,
			anchor: '100%',
			emptyText: t("Please select..."),
			pageSize: this.entityStore ? 50 : null,
			valueField: 'id',
			displayField: this.displayField,
			triggerAction: 'all',
			editable: true,
			selectOnFocus: true,
			forceSelection: true,
			mode: this.entityStore ? 'remote' : 'local',
			store: this.comboStore,
			value:"",
			tpl: new Ext.XTemplate(
				'<tpl for=".">',
				'<div class="x-combo-list-item"><tpl if="!values.' + this.valueField + '"><b>' + t("Create new") + ':</b> </tpl>{' + this.displayField + '}</div>',
				'</tpl>')
		});		
		
		this.comboBox.on('select', function(combo, record, index) {			

			if(this.entityStore && !record.data[this.valueField]) {
				this.createNew(record);
			} else{
				this.dataView.store.add([record]);			
			}
			combo.reset();			
		}, this);

		if(this.allowNew) {
			this.comboStore.on("load", this.addCreateNewRecord, this);
		}
		
		return this.comboBox;
	},
	
	onClick: function(dv, index, node,e) {
		if(e.target.tagName === "BUTTON") {
			this.dataView.store.removeAt(index);
		}
	},
	
	validate: function () {

		if(!this.allowBlank && go.util.empty(this.getValue())) {
			this.markInvalid(Ext.form.TextField.prototype.blankText);
			return false;
		}
		return true;
	},

	createNew : function(record) {

		var data = record.data;
		if(Ext.isObject(this.allowNew)) {
			Ext.apply(data, this.allowNew);
		}
		var create = {"newid" : data}, me = this;

	 this.entityStore.set({
			create: create
		}).then(function(response) {
			record.id = response.created.newid.id;
			record.set(id, record.id);
			me.dataView.store.add([record]);	
			me.comboBox.reset();		
		});
	},

	addCreateNewRecord: function() {
		var text =  this.comboBox.getRawValue();
		if(!text) {
			return;
		}
		var def = Ext.data.Record.create([{
			name: this.valueField
		},{
			name: this.displayField
		}]);

		var recordData = {};
		recordData[this.displayField] = text;						
		var record = new def(recordData);
		this.comboStore.insert(0, record);
		
		if(this.comboStore.getCount() > 1) {								
			this.comboBox.select(0);
		} 
		
	}


});


Ext.reg('chips', go.form.Chips);
