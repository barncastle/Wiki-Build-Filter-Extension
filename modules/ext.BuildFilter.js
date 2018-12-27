'use strict';
var build_filter = {
	blanklinemode: 0, // -1 = no formatting, 0 = remove all blank lines, 1 = allow single line spacing
	cache: {
		data: [],

		getOrCreate: function(ele) {
			var entry = this.data.filter(function(x) { return x.element === ele; })[0];
			if(!entry) {		
				entry = { element: ele, conditions: [] };
				this.data.push(entry);
			}
			return entry;
		}
	},
	imagelookup: [
		'https://wowdev.wiki/images/8/8f/PreVanilla-Logo-Small.png',
		'https://wowdev.wiki/images/5/54/Vanilla-Logo-Small.png',
		'https://wowdev.wiki/images/5/56/BC-Logo-Small.png',
		'https://wowdev.wiki/images/c/c1/Wrath-Logo-Small.png',
		'https://wowdev.wiki/images/e/ef/Cata-Logo-Small.png',
		'https://wowdev.wiki/images/2/26/Mists-Logo-Small.png',
		'https://wowdev.wiki/images/7/71/WoD-Logo-Small.png',
		'https://wowdev.wiki/images/f/fd/Legion-Logo-Small.png',
		'https://wowdev.wiki/images/9/94/Battle-Logo-Small.png',
    ],
	load: function() {
		// bind the dropdown onchange event
		$('.build-filter-select').each(function() {	
			$(this).change(function() { build_filter.onChangeEvent(this); });
			
			// apply filter if not All
			if($(this).val() !== '-1')
				build_filter.onChangeEvent(this);
		});
	},
	onChangeEvent: function(ele) {
		var $this = $(ele);

		// split value into build parts [exp, build]
		var build = $this.val().split('.').map(function(i) { return parseInt(i); });
		
		// load (or create) the cache entry for this element
		var cache = this.cache.getOrCreate(ele);

		// load any previously calculated content otherwise run the validation
		var lookup = cache.conditions.filter(function(x) { return x.exp === build[0] && x.build === build[1]; })[0];
		if(!lookup) {	
			lookup = this.parseBuild($this, build);
			cache.conditions.push(lookup);
		}

		// set the icon
		$this.parent().find('img.build-filter-image').attr('src', this.imagelookup[lookup.exp] || '');
		// update the content
		$this.parent().siblings('pre').html(lookup.content);
	},
	
	parseBuild: function(ele, build) {
		// new cache entry
		var lookup = { exp: build[0], build: build[1], content: '' };

		// get and parse the json from the script element
		var script = ele.parent().siblings('.build-filter-data').html() || '[]';
		var groups = JSON.parse(script);
		
		var content = [];
		for(var g = 0; g < groups.length; g++)			
			content = content.concat(build_filter.validate(lookup, groups[g]));

		// apply blank line filtering
		if(this.blanklinemode > -1) {
			content = content.filter(function(x, i) {
				var m = build_filter.blanklinemode;
				return !(i >= m && !!content[i - m].match(/^\s+$/g) && !!x.match(/^\s+$/g));
			});
		}

		// set content and store in the cache
		lookup.content = content.join('\n');
		return lookup;
	},
	
	validate: function(lookup, conditions) {
		
		var content = [];
		for(var c = 0; c < conditions.length; c++) {
			
			var condition = conditions[c];
			
			// build == All OR a blank condition shows all content
			if( lookup.exp === -1 || condition.condition === '' ) {
				// iterate and store all data including children
				content = content.concat(condition.data);
				for(var i = 0; i < condition.children.length; i++)
					content = content.concat(condition.children[i].data);
				
				continue;
			}
			
			// ignore endifs
			if( condition.condition === 'endif' )
				continue;
		
			// validate build range if applicable
			// else conditions will fall through and be displayed
			var valid = true;
			if( condition.condition !== 'else' ) {
				// split builds into parts [exp,maj,min,build]
				// only exp and build are validated currently
				var min = condition.builds['min_build'].split('.').map(function(i) { return parseInt(i); });
				var max = condition.builds['max_build'].split('.').map(function(i) { return parseInt(i); });
				valid = ( min[0] <= lookup.exp && max[0] >= lookup.exp && min[3] <= lookup.build && max[3] >= lookup.build );
			}
			
			if( valid ) {
				// apply content excluding the template line and process child nodes
				content = content.concat(condition.data.slice(1));
				if(condition.children.length > 0)
					content = content.concat(this.validate(lookup, condition.children));
				
				return content;
			}
		}
		
		return content;
	}
};

// hacky pageload injection
$( document ).ready(function() { build_filter.load(); });