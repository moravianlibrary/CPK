document.ready = function() {
	var urlParams = $('#urlParams').text();
	var jsTreeContainers = $('.jstreeContainer');
	$.each(jsTreeContainers, function(index, jsTreeContainer) {
		var facetName = $(jsTreeContainer).attr('id').replace('JSTContainer', '');
		var facets = $('li a', jsTreeContainer);
		var jstData = [];
		$.each(facets, function(index, facet) {
			facet = $(facet);
			jstData.push({
				"data": {
					'title': facet.text(),
					'attr': {
						'href': facet.attr('href')
					},
				},
				"attr": {
					"id": facet.attr('id')
				},
				"state": "closed"
			});
		});
		// create jstree
		$('#' + facetName + 'JSTContainer').jstree({
			'json_data': {
				'data': jstData,
				'ajax': {
					'url': function(node) {
						var facetPrefix = node.attr('id').replace('JSTFacet', '');
						var levelPattern = /\d+/g;
						var facetLevel = parseInt(levelPattern.exec(facetPrefix)) + 1;
						console.log(facetLevel);
						var url = 
							path
							+ "/AJAX/JSON?method=getFacets&facetName=" + facetName
							+ "&facetPrefix=" + facetPrefix
							+ "&facetLevel=" + facetLevel
							+ urlParams.replace("?", "&");
						console.log(url);
						return url;
					},
					'success': function(data) {
						var facetsData = new Array();
						if (data.status == "OK" && data.data != null) {
							$.each(data.data, function(index, facet) {
								facetsData.push({
									"data": {
										'title': facet.displayText + " (" + facet.count + ")",
										'attr': {
											'href': 
												path + "/Search/Results" + urlParams
												+ "&filter%5B%5D="+ facetName
												+ "%3A%22" + encodeURIComponent(facet.value) + "%22"
										}
									},
									"attr": {
										"id": facet.value + "JSTFacet"
									},
									'state': 'closed'
								});
							});
						}
						return facetsData;
					}
				}
			},
			'themes' : {
				'theme': 'default',
				'icons': false,
				'dots': false
			},
			'plugins': ['themes', 'json_data']
		});
	});
};