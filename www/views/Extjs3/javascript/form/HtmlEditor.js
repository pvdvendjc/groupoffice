



GO.form.HtmlEditor = function (config) {
	config = config || {};

	Ext.applyIf(config, {
		border: false,
		enableFont: false,
		style: GO.settings.html_editor_font
	});

	config.plugins = config.plugins || [];

	if (!Ext.isArray(config.plugins))
		config.plugins = [config.plugins];

	var spellcheckInsertPlugin = new GO.plugins.HtmlEditorSpellCheck(this);
	var wordPastePlugin = new Ext.ux.form.HtmlEditor.Word();
	//var dividePlugin = new Ext.ux.form.HtmlEditor.Divider();
	//var tablePlugin = new Ext.ux.form.HtmlEditor.Table();
	var hrPlugin = new Ext.ux.form.HtmlEditor.HR();
	var ioDentPlugin = new Ext.ux.form.HtmlEditor.IndentOutdent();
	var ssScriptPlugin = new Ext.ux.form.HtmlEditor.SubSuperScript();
	var rmFormatPlugin = new Ext.ux.form.HtmlEditor.RemoveFormat();

	if (GO.settings.pspellSupport)
		config.plugins.unshift(spellcheckInsertPlugin);

	config.plugins.unshift(
					ioDentPlugin,
					rmFormatPlugin,
					wordPastePlugin,
					hrPlugin,
					ssScriptPlugin
					);

	GO.form.HtmlEditor.superclass.constructor.call(this, config);
};

Ext.extend(GO.form.HtmlEditor, Ext.form.HtmlEditor, {

	initEditor: function () {

		GO.form.HtmlEditor.superclass.initEditor.call(this);
		
		this.addEvents({attach: true});
		
		//Following is needed when using animateTarget in windows. But since that doesn't perform well we should look at using css transitions instead of js animations
		//this.getToolbar().doLayout();
		var doc = this.getEditorBody();
		doc.addEventListener('paste', this.onPaste.createDelegate(this));
		doc.addEventListener('drop', this.onDrop.createDelegate(this));

		//Fix for Tooltips in the way of email #276
		doc.addEventListener("mouseenter", function() {
			setTimeout(function() {
				Ext.QuickTips.getQuickTip().hide();
			}, 500);
			
		});
		
	},
	
	onDrop: function(e) {
		if(!e.dataTransfer.files) {
			return;
		}
		Array.from(e.dataTransfer.files).forEach(function(file) {   
			go.Jmap.upload(file, {
				scope: this,
				success: function(response) {
					var imgEl = null;
					if (file.type.match(/^image\//)) {
						domId = Ext.id(), img = '<img id="' + domId + '" src="' + go.Jmap.downloadUrl(response.blobId) + '" alt="' + file.name + '" />';
						this.insertAtCursor(img);
						imgEl = this.getDoc().getElementById(domId);
					} 

					this.fireEvent('attach', this, response.blobId, file, imgEl);
				}
			});
		}, this);
		
	},

	onPaste: function (e) {		
		var clipboardData = e.clipboardData;
		if (clipboardData.items) {
			//Chrome has clibBoardData.items
			for (var i = 0, l = clipboardData.items.length; i < l; i++) {
				var item = clipboardData.items[i];
				if (item.type.match(/^image\//)) {
					
					e.preventDefault();
					var reader = new FileReader();
					reader.onload = function (event) {						
						return this.handleImage(event.target.result);
					}.bind(this);
					reader.readAsDataURL(item.getAsFile());
				}
			}
		} else
		{
			//Firefox
			if (-1 === Array.prototype.indexOf.call(clipboardData.types, 'text/plain')) {					
				this.findImageInEditor();
			}
		}
	},
	
	findImageInEditor: function () {
		var el = this.getDoc();

		var images = el.getElementsByTagName('img');
		var timespan = Math.floor(1000 * Math.random());
		for (var i = 0, len = images.length; i < len; i++) {
			images[i]["_paste_marked_" + timespan] = true;
		}
		setTimeout(function () {
			var newImages = el.getElementsByTagName('img');
			for (var i = 0, len = newImages.length; i < len; i++) {
				if (!newImages[i]["_paste_marked_" + timespan]) {
					this._handleImage(newImages[i].src);
					newImages[i].remove();
				}
			}

		}, 1);
	},
	
	insertImage : function(src) {
		var domId = Ext.id(), img = '<img id="' + domId + '" src="' + src + '" alt="pasted image" />';
		this.insertAtCursor(img);
		
		return  this.getDoc().getElementById(domId);		
	},

	handleImage: function (src) {

		var imgEl = this.insertImage(src);		

		var dataURLtoBlob = function (dataURL, sliceSize) {
			var b64Data, byteArray, byteArrays, byteCharacters, byteNumbers, contentType, i, m, offset, slice, _ref;
			if (sliceSize == null) {
				sliceSize = 512;
			}
			if (!(m = dataURL.match(/^data\:([^\;]+)\;base64\,(.+)$/))) {
				return null;
			}
			_ref = m, m = _ref[0], contentType = _ref[1], b64Data = _ref[2];
			byteCharacters = atob(b64Data);
			byteArrays = [];
			offset = 0;
			while (offset < byteCharacters.length) {
				slice = byteCharacters.slice(offset, offset + sliceSize);
				byteNumbers = new Array(slice.length);
				i = 0;
				while (i < slice.length) {
					byteNumbers[i] = slice.charCodeAt(i);
					i++;
				}
				byteArray = new Uint8Array(byteNumbers);
				byteArrays.push(byteArray);
				offset += sliceSize;
			}
			return new Blob(byteArrays, {
				type: contentType
			});
		};

		var loader, me = this;
		loader = new Image();
		loader.onload = function () {
			var blob, canvas, ctx, dataURL;
			canvas = document.createElement('canvas');
			canvas.width = loader.width;
			canvas.height = loader.height;
			ctx = canvas.getContext('2d');
			ctx.drawImage(loader, 0, 0, canvas.width, canvas.height);
			dataURL = null;
			try {
				dataURL = canvas.toDataURL('image/png');
				blob = dataURLtoBlob(dataURL);
			} catch (_error) {
			}
			if (dataURL) {				
				var file = new File([blob], "pasted-image." + blob.type.substring(6),{type: blob.type});
				go.Jmap.upload(file, {
					success: function(response) {
						imgEl.setAttribute("src", go.Jmap.downloadUrl(response.blobId));						
						me.fireEvent('attach', me, response.blobId, file, imgEl);
					}
				});
				
			}
		};

		return loader.src = src;
	},

	/**
	 * Executes a Midas editor command directly on the editor document.
	 * For visual commands, you should use {@link #relayCmd} instead.
	 * <b>This should only be called after the editor is initialized.</b>
	 * @param {String} cmd The Midas command
	 * @param {String/Boolean} value (optional) The value to pass to the command (defaults to null)
	 */
	execCmd: function (cmd, value) {
		var doc = this.getDoc();

		if (cmd === 'createlink' && Ext.isGecko) {
			// If firefox is used, then manually add the "a" tag to the text
			var t = this.getSelectedText();
			if (t.length < 1) {
				value = '<a href="' + value + '">' + value + "</a>";
				this.insertAtCursor(value);
			}
		}

		doc.execCommand(cmd, false, value === undefined ? null : value);

		this.syncValue();
	},

	getSelectedText: function () {

		var frame = this.iframe;
		var frameWindow = frame.contentWindow;
		var frameDocument = frameWindow.document;

		if (frameDocument.getSelection)
			return frameDocument.getSelection().toString();
		else if (frameDocument.selection)
			return frameDocument.selection.createRange().text;
	},

	setValue: function (value) {

		if (this.win && Ext.isChrome) {

			//set cursor position on top
			var range = this.win.document.createRange();
			range.setStart(this.win.document.body, 0);
			range.setEnd(this.win.document.body, 0);

			var sel = this.win.document.getSelection();

			sel.removeAllRanges();
			sel.addRange(range);
		}
		GO.form.HtmlEditor.superclass.setValue.call(this, value);
	},

//	syncValue: function(){
//		//In BasicForm.js this method is called by EXT
//		// When using the editor in sourceEdit then it may not call the syncValue function
//		if(!this.sourceEditMode){			
//			GO.form.HtmlEditor.superclass.syncValue.call(this);
//		}
//	},	

//	correctify: function(full, prefix, letter){
//		var regex = /([:\?]\s+)(.)/g;
//		return prefix + letter.toUpperCase();
//	},

//	urlify : function () {
//		
//		var inputText = this.getEditorBody().innerHTML;
//		var replacedText, replacePattern1, replacePattern2, replacePattern3;
//
////		//URLs starting with http://, https://, or ftp://
////		replacePattern1 = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
////		replacedText = inputText.replace(replacePattern1, '<a href="$1" target="_blank">$1</a>');
////
////		//URLs starting with "www." (without // before it, or it'd re-link the ones done above).
////		replacePattern2 = /(^|[^\/])(www\.[\S]+(\b|$))/gim;
////		replacedText = replacedText.replace(replacePattern2, '$1<a href="http://$2" target="_blank">$2</a>');
////
////		//Change email addresses to mailto:: links.
////		replacePattern3 = /(\w+@[a-zA-Z_]+?\.[a-zA-Z]{2,6})/gim;
////		replacedText = replacedText.replace(replacePattern3, '<a href="mailto:$1">$1</a>');
//
//	replacedText = inputText.replace(/(?:^|[^\>])((ftp|http|https|file):\/\/[\S]+(\b|$))/gim, '<a href="http://$1">$1</a>');
//
//		
//		this.getEditorBody().innerHTML=replacedText;
//		
//		console.log(this.getEditorBody().innerHTML);
//		
//	},
	onFirstFocus: function () {

		this.initPunctuationCorrection();

		this.activated = true;
		this.disableItems(this.readOnly);
		if (Ext.isGecko) { // prevent silly gecko errors
			/*this.win.focus();
			 var s = this.win.getSelection();
			 if(!s.focusNode || s.focusNode.nodeType != 3){
			 var r = s.getRangeAt(0);
			 r.selectNodeContents(this.getEditorBody());
			 r.collapse(true);
			 this.deferFocus();
			 }*/
			try {
				this.execCmd('useCSS', true);
				this.execCmd('styleWithCSS', false);
			} catch (e) {
			}
		}
		this.fireEvent('activate', this);
	},
	createToolbar: Ext.form.HtmlEditor.prototype.createToolbar.createSequence(function (editor) {
		this.tb.enableOverflow = true;
	}),

	initPunctuationCorrection: function () {
		if (GO.settings.auto_punctuation != 1)
			return;

		var me = this;
		var doc = me.getDoc();

		Ext.EventManager.on(doc, 'keydown', me.correctPunctuation, me);
	},

	lastKey: false,
	capNext: true,

	correctPunctuation: function (ev) {
		ev = ev.browserEvent;
		if (!this.capNext) {
			if(ev.key === 'Enter' || (ev.key === ' ' && ['.', '!', '?'].indexOf(this.lastKey) !== -1)) {
				this.capNext = true;
			}
		} else {
			if (ev.key.match(/^[a-z]$/)) {
				ev.preventDefault();
				this.insertAtCursor(ev.key.toUpperCase());
				this.capNext = false;
			} else if (ev.key != ' ' && ev.key != 'Enter') {
				this.capNext = false;
			}
		}
		this.lastKey = ev.key;
	},

	getDocMarkup: function () {
		var h = Ext.fly(this.iframe).getHeight() - this.iframePad * 2;
		return String.format('<html><head><meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" /><style type="text/css">body,p,td,div,span{' + GO.settings.html_editor_font + '};body{border: 0; margin: 0; padding: {0}px; height: {1}px; cursor: text}body p{margin:0px;}</style></head><body></body></html>', this.iframePad, h);
	},
	fixKeys: function () { // load time branching for fastest keydown performance
		if (Ext.isIE) {
			return function (e) {
				var k = e.getKey(),
								doc = this.getDoc(),
								r;
				if (k == e.TAB) {
					e.stopEvent();
					r = doc.selection.createRange();
					if (r) {
						r.collapse(true);
						r.pasteHTML('&nbsp;&nbsp;&nbsp;&nbsp;');
						this.deferFocus();
					}
				} else if (k == e.ENTER) {
					//                    r = doc.selection.createRange();
					//                    if(r){
					//                        var target = r.parentElement();
					//                        if(!target || target.tagName.toLowerCase() != 'li'){
					//                            e.stopEvent();
					//                            r.pasteHTML('<br />');
					//                            r.collapse(false);
					//                            r.select();
					//                        }
					//                    }
				}
			};
		} else if (Ext.isOpera) {
			return function (e) {
				var k = e.getKey();
				if (k == e.TAB) {
					e.stopEvent();
					this.win.focus();
					this.execCmd('InsertHTML', '&nbsp;&nbsp;&nbsp;&nbsp;');
					this.deferFocus();
				}
			};
		} else if (Ext.isWebKit) {
			return function (e) {
				var k = e.getKey();
				if (k == e.TAB) {
					e.stopEvent();
					this.execCmd('InsertText', '\t');
					this.deferFocus();
				}/* Testen in latest chrome and safari with lists else if (k == e.ENTER) {
					e.stopEvent();
					var doc = this.getDoc();
					if (doc.queryCommandState('insertorderedlist') ||
									doc.queryCommandState('insertunorderedlist')) {
						this.execCmd('InsertHTML', '</li><br /><li>');
					} else {
						this.execCmd('InsertHtml', '<br />&nbsp;');
						this.execCmd('delete');
					}
					this.deferFocus();
				}*/
			};
		}
	}(),

	//Overwritten to fix font size bug in chrome
	adjustFont: function (btn) {
		var adjust = btn.getItemId() == 'increasefontsize' ? 1 : -1,
						doc = this.getDoc(),
						v = parseInt(doc.queryCommandValue('FontSize') || 2, 10);
		if (Ext.isAir) {


			if (v <= 10) {
				v = 1 + adjust;
			} else if (v <= 13) {
				v = 2 + adjust;
			} else if (v <= 16) {
				v = 3 + adjust;
			} else if (v <= 18) {
				v = 4 + adjust;
			} else if (v <= 24) {
				v = 5 + adjust;
			} else {
				v = 6 + adjust;
			}
			v = v.constrain(1, 6);
		} else {
			v = Math.max(1, v + adjust);
		}
		this.execCmd('FontSize', v);
	},

	// private
//	onEditorEvent : function(e){
//		this.updateToolbar();
////		console.log(e);
//		
//		if(e.keyCode==32 || e.keyCode==12)
//			this.urlify();
//	},
	updateToolbar: function () {

		/*
		 * I override the default function here to increase performance.
		 * ExtJS syncs value every 100ms while typing. This is slow with large
		 * html documents. I manually call syncvalue when the message is sent
		 * so it's certain the right content is submitted.
		 */

		//GO.mainLayout.timeout(0); // stop logout timer

		if (this.readOnly) {
			return;
		}

		if (!this.activated) {
			this.onFirstFocus();
			return;
		}

		var btns = this.tb.items.map,
						doc = this.getDoc();

		if (this.enableFont && !Ext.isSafari2) {
			var name = (doc.queryCommandValue('FontName') || this.defaultFont).toLowerCase();
			if (name != this.fontSelect.dom.value) {
				this.fontSelect.dom.value = name;
			}
		}
		if (this.enableFormat) {
			btns.bold.toggle(doc.queryCommandState('bold'));
			btns.italic.toggle(doc.queryCommandState('italic'));
			btns.underline.toggle(doc.queryCommandState('underline'));
		}
		if (this.enableAlignments) {
			btns.justifyleft.toggle(doc.queryCommandState('justifyleft'));
			btns.justifycenter.toggle(doc.queryCommandState('justifycenter'));
			btns.justifyright.toggle(doc.queryCommandState('justifyright'));
		}
		if (!Ext.isSafari2 && this.enableLists) {
			btns.insertorderedlist.toggle(doc.queryCommandState('insertorderedlist'));
			btns.insertunorderedlist.toggle(doc.queryCommandState('insertunorderedlist'));
		}

		Ext.menu.MenuMgr.hideAll();

		//This property is set in javascript/focus.js. When the mouse goes into
		//the editor iframe it thinks it has lost the focus.
		GO.hasFocus = true;


		//this.syncValue();
	},

	createLink: function () {


		var url = prompt(this.createLinkText, this.defaultLinkValue);
		if (url && url != 'http:/' + '/') {
			if (Ext.isSafari) {
				this.execCmd("createlink", url);
				this.updateToolbar();
			} else
			{
				this.relayCmd("createlink", url);
			}
		}
	}

});

Ext.reg('xhtmleditor', GO.form.HtmlEditor);
