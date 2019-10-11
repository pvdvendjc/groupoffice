

go.util.Filters = {
	normalize: function (filters) {
		if (Ext.isObject(filters)) {
			return filters;
		}
		

		var normalized = {};
		filters.map(function (f) {
			if (Ext.isObject(f)) {
				f.name = f.name.toLowerCase();
				normalized[f.name] = f;
			} else
			{
				var name = f.toLowerCase();
				normalized[name] = {
					name: name,
					multiple: true,
					type: "text",
					title: t(name)
				}
			}
		});
		
		return normalized;
	},

	parseQueryString: function (string, filters) {

		if(Ext.isEmpty(string)) {
			return null;
		}

		var and = [], not = [], arr = and;

		filters = this.normalize(filters);

		defaultFilter = Object.keys(filters)[0];
	
		//Simple text check
		if(string.indexOf(':') === -1) {
			var data = {};
			
			data[defaultFilter] = [];
			data[defaultFilter].push(string);
			return data;
		}

		var stripBackSlash = function (val) {
			// Strip backslashes respecting escapes
			return  (val + '').replace(/\\(.?)/g, function (s, n1) {
				switch (n1) {
					case '\\':
						return '\\';
					case '0':
						return '\u0000';
					case '':
						return '';
					default:
						return n1;
				}
			});
		};
		
		// eg. "name: Merijn,Jan name:Beton -name: Piet"
		var tokens = (string).split(':'), data = [], currentFilterName = defaultFilter;

		for (var i = 0, l = tokens.length; i < l; i++) {
			var words = tokens[i].split(' ');

			//Last word is the next filter name
			if (i !== l - 1) {
				var filterName = words.pop();
			}

			// Not allowed filter name
			if (!filters[currentFilterName]) {
				continue;
			}

			//After the next filter name has been taken off make it a string again,
			var f = {}, str = words.join(' ').trim();

			//Empty string can be ignored
			if (str) {
				if (filters[currentFilterName].multiple) {
					//Values will be split into array values
					f[currentFilterName] = str.splitCSV().map(function (v) {
						//strip backslash and remove quotes
						v = stripBackSlash(v.trim().replace(/^\"|\"$|^\'|\'$/g, ''));
						
						if(filters[currentFilterName].type === "string") {
							v = "%" + v + "%";
						}
						
						return v;
					});
				} else
				{
					//strip backslash and remove quotes
					f[currentFilterName] = stripBackSlash(str.trim().replace(/^\"|\"$|^\'|\'$/g, ''));
					
					if(filters[currentFilterName].type === "string") {
						f[currentFilterName] = "%" + f[currentFilterName] + "%";
					}
				}

				//push it to the not or and array
				arr.push(f);
			}

			currentFilterName = filterName;

			//filter name prefixed with - will become a NOT condition
			if (currentFilterName.substring(0, 1) == "-") {
				arr = not;
				currentFilterName = currentFilterName.substring(1, currentFilterName.length);
			} else
			{
				arr = and;
			}
		}
		;

		if (not.length) {
			and.push({
				operator: "NOT",
				conditions: not
			});
		}

		return {
			operator: "AND",
			conditions: and
		};

	}
};


