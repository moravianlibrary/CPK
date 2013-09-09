$(document).ready(function()
{
    // global variable for init checkboxes
    init = false;
    // parameters from GET request
    var urlParams = $('#urlParams').text();
    var jsTreeContainers = $('.jstreeContainer');
    $.each(jsTreeContainers, function(index, jsTreeContainer)
    {
        var facetName = $(jsTreeContainer).attr('id').replace('JSTContainer', '');
        var facetCheckBox = $($('.facetCheckBox', $(jsTreeContainer))[0]).text();
        var facets = $('li a', jsTreeContainer);
        var jstData = [];
        $.each(facets, function(index, facet)
        {
            facet = $(facet);
            jstData.push({
                'data': {
                    'title': facet.text(),
                    'attr': {
                        'href': facet.attr('href')
                    },
                },
                'attr': {
                    'id': facet.attr('id')
                },
                'state': 'closed'
            });
        });
        var appliedFacets = $('li.appliedJSTFacet');
        // create jstree
        var jstreeConfig = {
                'json_data': {
                    'data': jstData,
                    'ajax': {
                        'url': function(node)
                        {
                            var facetPrefix = node.attr('id').replace('JSTFacet', '');
                            var levelPattern = /\d+/g;
                            var facetLevel = parseInt(levelPattern.exec(facetPrefix)) + 1;
                            var url = 
                                path
                                + '/AJAX/JSON?method=getFacets&facetName=' + facetName
                                + '&facetPrefix=' + facetPrefix
                                + '&facetLevel=' + facetLevel
                                + urlParams.replace('?', '&');
                            console.log(url);
                            return url;
                        },
                        'success': function(data)
                        {
                            var facetsData = new Array();
                            if (data.status == 'OK' && data.data != null) {
                                $.each(data.data, function(index, facet)
                                {
                                    facetsData.push(
                                    {
                                        'data': {
                                            'title': facet.displayText + ' (' + facet.count + ')',
                                            'attr': {
                                                'href': 
                                                    path + '/Search/Results' + urlParams
                                                    + '&filter%5B%5D='+ facetName
                                                    + '%3A%22' + encodeURIComponent(facet.value) + '%22'
                                            }
                                        },
                                        'attr': {
                                            'id': facet.value + 'JSTFacet'
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
                    'theme': 'blueprint',
                    'icons': false,
                    'dots': false
                },
                'plugins': ['themes', 'json_data']
        };
        // Multivalue facets
        if (facetCheckBox == "true") {
            jstreeConfig.plugins.push('checkbox');
            jstreeConfig.plugins.push('ui');
            jstreeConfig.checkbox = {
                    'real_checkboxes': true	
            };
        }
        var jstreeId = '#' + facetName + 'JSTContainer';
        $(jstreeId)
        .bind('check_node.jstree', function(event, data)
        {
            if (facetCheckBox == "true" && init) {
                var li = $(data.args[0]).parent().parent();
                var input = $($('input', li)[0]);
                var value = input.attr('id').replace('check_', '').replace('JSTFacet', '');
                var url = path + "/Search/Results" + urlParams
                + "&filter%5B%5D="+ facetName
                + "%3A%22" + encodeURIComponent(value) + "%22";
                window.location.href = url;
            }
        })
		.bind('loaded.jstree', function(event, data)
		{
		    if (facetCheckBox == "true") {
		        $.each(appliedFacets, function(index, facet) {
		            var id = $($('a', $(facet))[0]).attr('id');
		            // get actual li node
		            // TODO: $('#' + id) not working correctly
		            var li = document.getElementById(id);
		            $.jstree._reference(jstreeId).check_node(li);
		        });
		        init = true;
		    }
		})
		.jstree(jstreeConfig);
    });
});