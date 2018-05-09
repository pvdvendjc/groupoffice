go.modules.community.files.FolderTree = Ext.extend(Ext.tree.TreePanel, {
	rootNodeEntity:null,
	contextMenu: null,
	animate: true,
	enableDD:true,
	folderSelectMode:false, // Mode to make from the tree a folder select component.
	dropConfig: {
		appendOnly:true
	},
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

	initComponent: function () {

		go.modules.community.files.FolderTree.superclass.initComponent.call(this);

		this.on('click',function(node,e){
			this.browser.goto(this.getPath(node));
		},this);
	
		this.on('nodedrop',function(dropEvent){
			this.moveFolder(dropEvent.dropNode,dropEvent.target);
		},this);
		
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
		
		this.initRootNode(this.rootNodeEntity);
	},
	
	processAddedItems : function(store,addedItems){
		
	},
	
	processChangedItems : function(store,changedItems){
		console.log(changedItems);
		//	changedItems = object(
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode),
		//		int:entityId => array(treenode,treenode)
		//	)
		
		var bookmarksNeedUpdate = false;
		var foldersToRefresh = [];
		
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

				// The entity id moved
				if(Ext.isDefined(diff.parentId)){
					// Todo: improve this by removing the node and adding the node manually instead of reloading the full parents
					foldersToRefresh.push(entity.parentId);
					foldersToRefresh.push(diff.parentId);
				}
			});
		}

		// Refresh the folder nodes that have changes
		this.reloadNodes(foldersToRefresh);

		// Refresh the bookmarks node when there are changes in bookmarked items
		if(bookmarksNeedUpdate){
			var bookmarkNodes = this.getTreeNodesByEntityId('bookmarks');
			if(bookmarkNodes.length === 1){
				bookmarkNodes[0].reload();
			}
		}	
	},
	
	processDestroyedItems : function(store,deletedItems){
		
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
			added:[],
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
		return map;
	},
	
	/**
	 * Reload the given nodes
	 * 
	 * @param array(int) nodeIds
	 */
	reloadNodes : function(nodeIds){
		for(var i=0; i < nodeIds.length; i++){
			console.log(nodeIds[i]);
			var nodesToReload = this.getTreeNodesByEntityId(nodeIds[i]);
			if(nodesToReload.length >= 1){
				for(var j=0; j < nodesToReload.length; j++){
					console.log('reload');
					console.log(nodesToReload[j]);
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
	
	initRootNode : function(nodeEntity){
		
		var rootNodeConfig = {};

		if(nodeEntity){
			rootNodeConfig = {
				iconCls:'ic-folder',
				text: nodeEntity.name,
				entityId:nodeEntity.id,
				draggable:false,
				params:{
					filter: {
						parentId: nodeEntity.id
					}
				}
			};
			
			this.rootVisible=true; // Set root visible
			
		} else {
			rootNodeConfig = {
				expanded: true,
				text: 'ROOT',
				entityId:'ROOT', // Needed so it can be handled exactly as other nodes
				draggable: false,
				children:this.browser.rootNodes
			};
		}

		var root = new Ext.tree.TreeNode(rootNodeConfig);
		
		this.setRootNode(root);
		this.getLoader().load(root);
	},
	
	getContextMenu : function(){
		if(!this.contextMenu){
			this.contextMenu = new go.modules.community.files.ContextMenu();
		}
		return this.contextMenu;
	},
	
	/**
	 * 
	 * @param Ext.tree.AsyncTreeNode nodeToMove
	 * @param Ext.tree.AsyncTreeNode targetNode
	 * @return {undefined}
	 */
	moveFolder : function(nodeToMove,targetNode){
		
		var nodeToUpdateId = nodeToMove.attributes.entityId;
		
		var params = {}, me=this, newParentId=targetNode.attributes.entityId;
		
		// Workaround for myfiles
		if(newParentId === 'my-files'){
			newParentId = go.User.storage.rootFolderId;
		}
		
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