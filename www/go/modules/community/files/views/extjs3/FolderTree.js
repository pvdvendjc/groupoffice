go.modules.community.files.FolderTree = Ext.extend(Ext.tree.TreePanel, {
	rootNodeEntity:null,
	contextMenu: null,
	animate: true,
	enableDD:true,
	dropConfig: {
		appendOnly:true,
		ddGroup:'files-center-dd'
	},
	dragConfig: {
		ddGroup:'files-center-dd'
	},
	folderSelectMode:false, // Mode to make from the tree a folder select component.
	browser:null,
	loader: new go.tree.TreeLoader({
		baseAttrs:{
			iconCls:'ic-folder',
			uiProvider:go.modules.community.files.FolderTreeNodeUI
		},
		entityStore: go.Stores.get("Node"),
		getParams : function(node) {
	
			var filter = {
				isDirectory:true
			};

			if(node.attributes.entity){ // Root nodes don't have an entity set
				filter.parentId=node.attributes.entityId;
			}

			return {
				filter:filter
			};
		}
	}),
	lines: true,
	containerScroll: false,

	rootVisible: false,
	autoScroll:true,

	initComponent: function () {
		
		if(!this.browser){
			throw "Parameter 'browser' is required!";
		}
		
		this.root = {
			expanded: true,
			entityId:'ROOT', // Needed so it can be handled exactly as other nodes
//				children: this.browser.rootNodes,
			children:[],
			uiProvider:  Ext.tree.RootTreeNodeUI // needed to make "rootVisible" work
		};
		
		this.browser.on("rootNodesChanged", function(browser, rootNodes) {
			this.initRootNodes(rootNodes);
		},this);
		
		go.modules.community.files.FolderTree.superclass.initComponent.call(this);
		
		this.on('click',function(node,e){
			this.browser.goto(this.getPath(node));
		},this);
	
		this.on('beforenodedrop', function(dropEvent){

			if(dropEvent.dropNode){
				// Dragged from tree
				this.moveFolder(dropEvent.dropNode.attributes.entityId,dropEvent.target.attributes.entityId);
			} else {
				// Dragged from grid (Can be multiple items)
				for(var i=0;i<dropEvent.data.selections.length;i++) {
					this.moveFolder(dropEvent.data.selections[i].json.id,dropEvent.target.attributes.entityId);
				}
			}
			
		},this);
	
		this.on('nodedragover', function(overEvent){
			var dropOnEntity = overEvent.target.attributes.entity;
			
			if(!dropOnEntity){
				
				if(overEvent.target.attributes.entityId){
					dropOnEntity = {
						id: overEvent.target.attributes.params.filter.parentId
					};
				} else {
					return false;
				}
			}
			
			var dragEntities = [];
			
			if(overEvent.data.node){
				// It's a tree node
				dragEntities.push(overEvent.data.node.attributes.entity);
			} else {
				// Dragged from grid (Can be multiple items)
				for(var i=0;i<overEvent.data.selections.length;i++) {
					dragEntities.push(overEvent.data.selections[i].json);
				}
			}
			
			for(var j=0;j<dragEntities.length;j++) {
				if(dragEntities[j].parentId == dropOnEntity.id){
					// Drop on its parent
					return false;
				}
				
				if(dragEntities[j].id == dropOnEntity.id){
					// Drop on itself
					return false;
				}
			}
			
			return true;
			
		},this);
	
		this.on('contextmenu', function(node, event){
			event.stopEvent();

			var selModel = this.getSelectionModel();

			if(!selModel.isSelected(node)){
				selModel.clearSelections();
				selModel.select(node);
			}

			if(node.attributes && node.attributes.entityId && node.attributes.entity){
				var record = node.attributes.entity;
				this.getContextMenu().showAt(event.getXY(), [record]);
			}
		}, this);
		
		this.browser.on('pathchanged', function(){
			this.openPath(this.browser.getPath(true));
		},this);
				
		// When an entity is updated in the store. We'll need to update the tree too
		this.getLoader().entityStore.on('changes', function(store, added, changed, destroyed){
			
			var nodeMap = this.getChangesNodeMap(added, changed, destroyed);

			this.processAddedItems(store,nodeMap.added);
			this.processChangedItems(store,nodeMap.changed);
			this.processDestroyedItems(store,nodeMap.destroyed);
			
		},this);
		
	},
	
	processAddedItems : function(store,addedItems){
		//	addedItems = object(
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode)
		//	)		
		var nodesToReloadParents = addedItems.filter(function(item){
			return item == null;
		});
		
		var newItemNodes = store.get([nodesToReloadParents]);
		
		
		
		for(var entityId in addedItems){
			 
			if(addedItems[entityId] == null){
				nodesToRealodParents
				
				
				
				console.log(entityId);
			}
			
			
			
			
		}
		
		
		
		
	},
	
	processChangedItems : function(store,changedItems){
//		console.log(changedItems);
		//	changedItems = object(
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode)
		//	)
		var bookmarksNeedUpdate = false;
		var sharedWithMeNeedUpdate = false;
		var foldersToRefresh = [];
		var me = this;
		
		for(var entityId in changedItems){
			var updatedNode = store.get([entityId]);
			var nodesInTree = changedItems[entityId];
			
			nodesInTree.forEach(function(nodeInTree){
				var entity = nodeInTree.attributes.entity?nodeInTree.attributes.entity:false;
				if(!entity){
					return;
				}
				var diff = go.util.getDiff(entity,updatedNode[0]);

				// The bookmarked property of the entity is changed
				if(Ext.isDefined(diff.bookmarked)){
					bookmarksNeedUpdate = true;
				}
				
				// The internalShared property of the entity is changed
				if(Ext.isDefined(diff.internalShared)){
					sharedWithMeNeedUpdate = true;
				}

				// The entity id moved
				if(Ext.isDefined(diff.parentId)){
					// Todo: improve this by removing the node and adding the node manually instead of reloading the full parents
					foldersToRefresh.push(entity.parentId);
					foldersToRefresh.push(diff.parentId);
				}
				
				// Update the button entity when something is changed
				if(nodeInTree.contextMenuButton){
					nodeInTree.contextMenuButton.entity = updatedNode[0];
				}
				
				// Update the treenode text (Renamed)
				if(Ext.isDefined(diff.name)){
					nodeInTree.setText(diff.name);
				}

				// If there is a bookmark or share update, then update the icon
				if(bookmarksNeedUpdate || sharedWithMeNeedUpdate){
					me.updateIcon(nodeInTree, updatedNode[0]);
				}
				
				//Update the tree entity
				nodeInTree.attributes.entity = updatedNode[0];
			});
		}

		// Refresh the folder nodes that have changes
		this.reloadNodes(foldersToRefresh);

		// Refresh the bookmarks node when there are changes in bookmarked items
		if(bookmarksNeedUpdate){
			var bookmarkNodes = this.getTreeNodesByEntityId('bookmarks');
			if(bookmarkNodes.length === 1){
				if(bookmarkNodes[0].expanded){ // Only when the node is expanded
					bookmarkNodes[0].reload();
				}
			}
		}	
	},
	
	updateIcon : function(nodeInTree,updatedNode){
		
		var iconClass = 'ic-folder';
		
		if(updatedNode.bookmarked){
			iconClass = 'ic-folder-special';
		}
		
		if(updatedNode.internalShared || updatedNode.externalShared){
			iconClass = 'ic-folder-shared';
		}
		
		nodeInTree.setIconCls(iconClass);
	},
	
	processDestroyedItems : function(store,deletedItems){
//		console.log(deletedItems);
		//	deletedItems = object(
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode)
		//	)	
	},
	
	/**
	 * Get an object that tells which nodes are added, updated and deleted
	 * For example:
	 *	{
	 *		added:{
	 *			4:[Ext.tree.AsyncTreeNode,Ext.tree.AsyncTreeNode]
	 *		},
	 *		changed:{
	 *			6:[Ext.tree.AsyncTreeNode,Ext.tree.AsyncTreeNode]
	 *		},
	 *		destroyed:{
	 *			5:[Ext.tree.AsyncTreeNode,Ext.tree.AsyncTreeNode],
	 *			7:[Ext.tree.AsyncTreeNode]
	 *		}
	 *	}
	 * 
	 * 
	 * @param int nodeId[] added
	 * @param int nodeId[] changed
	 * @param int nodeId[] destroyed
	 * @return {FolderTree.getChangesNodeMap.map}
	 */
	getChangesNodeMap: function(added, changed, destroyed){

		var map = {
			added:{},
			changed:{},
			destroyed:{}
		};

		for(var i in this.nodeHash){
			if(this.nodeHash[i].attributes && this.nodeHash[i].attributes.entityId){
				
				// Check if the item is in the "added" array
				if(added.length){
					var found = added.indexOf(this.nodeHash[i].attributes.entityId);
					if(found !== -1){
						
						if(!map.added[added[found]]){
							map.added[added[found]] = [];
						}
						
						map.added[added[found]].push(this.nodeHash[i]);
					}
				}
				
				// Check if the item is in the "changed" array
				if(changed.length){
					var found = changed.indexOf(this.nodeHash[i].attributes.entityId);
					if(found !== -1){
						
						if(!map.changed[changed[found]]){
							map.changed[changed[found]] = [];
						}
						
						map.changed[changed[found]].push(this.nodeHash[i]);
					}
				}
				
				// Check if the item is in the "destroyed" array
				if(destroyed.length){
					var found = destroyed.indexOf(this.nodeHash[i].attributes.entityId);
					if(found !== -1){
						
						if(!map.destroyed[destroyed[found]]){
							map.destroyed[destroyed[found]] = [];
						}
						
						map.destroyed[destroyed[found]].push(this.nodeHash[i]);
					}
				}
			}
				
		}
		
		// Process all new items that where not yet found in the tree
		var newItems = added.filter(function(add){
			var result = !(add in map.added);
			return result;
		});
				
		Ext.each(newItems, function(itemId){
			if(!map.added[itemId]){
				map.added[itemId] = null;
			}
		});
		return map;
	},
	
	/**
	 * Reload the given nodes
	 * 
	 * @param array(int) nodeIds
	 */
	reloadNodes : function(nodeIds){
		for(var i=0; i < nodeIds.length; i++){
			var nodesToReload = this.getTreeNodesByEntityId(nodeIds[i]);
			if(nodesToReload.length >= 1){
				for(var j=0; j < nodesToReload.length; j++){
					nodesToReload[j].reload();
				}
			}
		}
	},
	
	
	getTreeNodesByEntityId : function(entityId){
		
		var foundNodes = [];
				
		for(var i in this.nodeHash){
			if(this.nodeHash[i].attributes && this.nodeHash[i].attributes.entityId && this.nodeHash[i].attributes.entityId  == entityId){
				foundNodes.push(this.nodeHash[i]);
			}
		}
		return foundNodes;
	},
	
	initRootNodes : function(nodes){
		
			Ext.each(nodes, function(node) {
				this.root.appendChild({
					iconCls: node.iconCls,
					entity: node.entity,
					entityId:node.entityId,
					params: {filter: node.filter},
					text: node.text //TODO this should be 
				});
			},this);
		
	},
	
	getContextMenu : function(){
		if(!this.contextMenu){
			this.contextMenu = new go.modules.community.files.ContextMenu({
				store: this.browser.store
			});
		}
		return this.contextMenu;
	},
	
	/**
	 * 
	 * @param int nodeToUpdateId
	 * @param int newParentId
	 * @return {undefined}
	 */
	moveFolder : function(nodeToUpdateId,newParentId){
				
		// TODO: CHECK IF FOLDERNAME ALREADY EXISTS		
				
		var params = {};
		
		params.update = {};
		params.update[nodeToUpdateId] = {
			parentId:newParentId
		};

		go.Stores.get("Node").set(params, function (options, success, response) {
			
			var saved = response.updated || {};
			if (saved[nodeToUpdateId]) {				
				this.fireEvent("save", this, params.update[nodeToUpdateId]);
			} else {
				//something went wrong
				var notSaved = response.notUpdated || {};
				if (!notSaved[nodeToUpdateId]) {
					notSaved[nodeToUpdateId] = {type: "unknown"};
				}

				switch (notSaved[nodeToUpdateId].type) {
					case "forbidden":
						Ext.MessageBox.alert(t("Access denied"), t("Sorry, you don't have permissions to update this item"));
						break;

					default:
						Ext.MessageBox.alert(t("Error"), t("Sorry, something went wrong. Please try again."));
						break;
				}
			}

		});
	},
	
	/**
	 * Get the path of the given node.
	 * Walks from the node back to the top (rootNode)
	 * 
	 * @param {type} node
	 * @return {Array}
	 */
	getPath : function(node){
		var p = node.parentNode;
    var b = [node.attributes['entityId']];
		while(p){
			if(p.attributes['entityId'] && p.attributes['entityId'] != 'ROOT'){
				b.unshift(p.attributes['entityId']);
			}
			p = p.parentNode;
		}
		return b;
	},
	
	/**
	 * Open child nodes in the tree based on the given path
	 * 
	 * @param array path
	 */
	openPath : function(path){
		var treePath = this.pathSeparator;
		
		if(this.getRootNode().attributes.entityId === "ROOT"){
			treePath += 'ROOT'+this.pathSeparator+path.join(this.pathSeparator);
		} else {
			treePath += path.join(this.pathSeparator);
		}
		
		this.expandPath(treePath,'entityId');
	}
});
