/*global htmlEncode, VuFind */
function buildFacetNodes(data, currentPath, allowExclude, excludeTitle, counts)
{
  var json = [];

  $(data).each(function() {
    var html = '';

    var lastFacetInString = this.exclude.split( '=' ).pop();
    var facetName = lastFacetInString.split( '%3A' ).shift().substring(1);
    var facetFilterBase = facetName + ':"' + this.value + '"';
    var facetFilter;
    if (this.operator == 'OR') {
      facetFilter = '~' + facetFilterBase;
    } else {
      facetFilter = facetFilterBase;
    }
    if (!this.isApplied && counts) {
      html = "<span class='badge";
      if (facetName == "cpk_detected_format_facet_str_mv") {
          html += " show-numbers";
      }

      html += "' style='float: right'>" + this.count.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate("number_thousands_separator"));
      if (allowExclude) {
        var excludeURL = currentPath + this.exclude;
        excludeURL.replace("'", "\\'");
        // Just to be safe
        html += " <a href='" + facetFilter + "' title='" + htmlEncode(excludeTitle) + "'><i class='fa fa-times'></i></a>";
      }
      html += '</span>';
    }

    var institutionCategory = facetFilter.split('/')[1];

    var url = currentPath + this.href;
    // Just to be safe
    url.replace("'", "\\'");
    html += "<span data-facet='" + facetFilter + "' class='main" + (this.isApplied ? " applied" : "");

    if (facetName == "local_region_institution_facet_str_mv") {
        html +="";
    }
    else {
        if (facetName == "local_statuses_facet_str_mv" || facetName == "cpk_detected_format_facet_str_mv") {
            html += "";
        }
        else {
            if (facetName == "conspectus_str_mv" ) {
                html += "";
            }
            else {
                html += " facet-filter";
            }
        }
    }

    html += "' title='" + htmlEncode(this.tooltiptext) + "'>";
    if (this.operator == 'OR') {
      if (this.isApplied) {
        html += '<i class="fa fa-check-square-o"></i>';
      } else {
        html += '<i class="fa fa-square-o"></i>';
      }
    } else if (this.isApplied) {
      html += '<i class="fa fa-check pull-right"></i>';
    }
    if (this.displayText == 'online') {
    	html += ' <b>' + this.displayText + '<b>';
    } else {
    	html += ' ' + this.displayText;
    }
    html += '</span>';

    var children = null;
    if (typeof this.children !== 'undefined' && this.children.length > 0) {
      children = buildFacetNodes(this.children, currentPath, allowExclude, excludeTitle, counts);
    }
    
    var appliedFacetFilters = [];
    
    $( '#hiddenFacetFilters .hidden-filter' ).each( function( index, element ) {
		//if( $( element ).val() != facetFilter) {
			appliedFacetFilters.push($( element ).val());
		//}
    });
    
    var filters = appliedFacetFilters;
    
    // Add current facetFilter to applied facetFilters
    filters.push(facetFilter);
    
    //console.log( 'Compressed facetFilters:' );
    var filtersAsString = filters.join( '|' );
    
    //console.log( 'Compressed facetFilters:' );
    var compressedFilters = specialUrlEncode( LZString.compressToBase64( filtersAsString ) );
    
      if (facetName == "local_statuses_facet_str_mv" || facetName == "conspectus_str_mv" || facetName == "cpk_detected_format_facet_str_mv" || facetName == "region_disctrict_facet_str_mv") {
          json.push({
              'id': facetFilter,
              'text': html,
              'children': children,
              'applied': this.isApplied,
              'state': {
                  'opened': this.hasAppliedChildren,
                  'selected': this.isApplied
              },
              'li_attr': (this.count==0) ? { 'class': 'emptyFacet' } : {},
              'a_attr': this.isApplied ? { 'class': 'active facet-filter-or' } :
              { 'href': window.location.href + "&filter%5B%5D=" + compressedFilters ,
                  'class' : 'facet-filter-or'
              },
          });
      }
      else {
          json.push({
              'id': facetFilter,
              'text': html,
              'children': children,
              'applied': this.isApplied,
              'state': {
                  'opened': this.hasAppliedChildren,
                  'selected': this.isApplied
              },
              'li_attr': (this.count == 0) ? {'class': 'emptyFacet'} : {},
              'a_attr': this.isApplied ? {'class': 'active'} :
              {
                  'href': window.location.href + "&filter%5B%5D=" + compressedFilters,
              },
          });
      };
  });

  return json;
}

function initFacetTree(treeNode, inSidebar, expandAll)
{
  var loaded = treeNode.data('loaded');
  if (loaded) {
    return;
  }
  treeNode.data('loaded', true);

  var facet = treeNode.data('facet');
  var operator = treeNode.data('operator');
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var sort = treeNode.data('sort');
  var query = window.location.href.split('?')[1];
  if (inSidebar) {
    treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
  } else {
    treeNode.prepend('<div><i class="fa fa-spinner fa-spin"></i><div>');
  }
  $.getJSON(VuFind.getPath() + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      facetOperator: operator
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle, inSidebar);
        treeNode.find('.fa-spinner').parent().remove();
        if (inSidebar) {
          treeNode.on('loaded.jstree open_node.jstree', function (e, data) {
            treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
            treeNode.find('ul.jstree-container-ul > li.jstree-node .jstree-icon').attr( 'title', VuFind.translate( 'Expand or collapse' ) );
          });
        }
        var tree = treeNode.jstree({
          'core': {
            'data': results
          }
        });
        if (expandAll) {
          tree.bind( "loaded.jstree", function ( event, data ) {
            $( this ).jstree( "open_all" );
          });
        }
      }
    }
  );
}

function initFacetOrTree(treeNode, inSidebar, expandAll)
{
    var loaded = treeNode.data('loaded');
    if (loaded) {
        return;
    }
    treeNode.data('loaded', true);

    var facet = treeNode.data('facet');
    var operator = treeNode.data('operator');
    var currentPath = treeNode.data('path');
    var allowExclude = treeNode.data('exclude');
    var excludeTitle = treeNode.data('exclude-title');
    var sort = treeNode.data('sort');
    var query = window.location.href.split('?')[1];

    if (inSidebar) {
        treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
    } else {
        treeNode.prepend('<div><i class="fa fa-spinner fa-spin"></i><div>');
    }
    $.getJSON(VuFind.getPath() + '/AJAX/JSON?' + query,
        {
            method: "getFacetData",
            facetName: facet,
            facetSort: sort,
            facetOperator: operator
        },
        function(response, textStatus) {
            if (response.status == "OK") {
                var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle, inSidebar);
                treeNode.find('.fa-spinner').parent().remove();
                if (inSidebar) {
                    treeNode.on('loaded.jstree open_node.jstree', function (e, data) {

                        // Open online in availability facet
                        if (facet == 'local_statuses_facet_str_mv') {
                            treeNode.jstree('open_node', $('#\\~local_statuses_facet_str_mv\\:\\"0\\/online\\/\\"'));
                        }

                        treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
                        treeNode.find('ul.jstree-container-ul > li.jstree-node .jstree-icon').attr( 'title', VuFind.translate( 'Expand or collapse' ) );
                    });
                }
                var tree = treeNode.jstree({
                    'plugins': ["wholerow", "checkbox"],
                    'core': {
                        'data': results,
                        'themes': {
                            'name': 'proton',
                            'responsive': true,
                            "icons":false
                        }
                    }
                });
                if (expandAll) {
                    tree.bind( "loaded.jstree", function ( event, data ) {
                        $( this ).jstree( "open_all" );
                    });
                }
            }
        }
    );
}


function initInstitutionsTree(treeNode, inSidebar, expandAll)
{
  var loaded = treeNode.data('loaded');
  if (loaded) {
    return;
  }
  treeNode.data('loaded', true);

  var facet = treeNode.data('facet');
  var operator = treeNode.data('operator');
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var sort = treeNode.data('sort');
  var query = window.location.href.split('?')[1];

  if (inSidebar) {
    treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
  } else {
    treeNode.prepend('<div><i class="fa fa-spinner fa-spin"></i><div>');
  }
  $.getJSON(VuFind.getPath() + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      facetOperator: operator
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle, inSidebar);
        treeNode.find('.fa-spinner').parent().remove();
        if (inSidebar) {
          treeNode.on('loaded.jstree open_node.jstree', function (e, data) {
            treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
            treeNode.find('ul.jstree-container-ul > li.jstree-node .jstree-icon').attr( 'title', VuFind.translate( 'Expand or collapse' ) );
          });
        }
        var tree = treeNode.jstree({
          'plugins': ["wholerow", "checkbox"],
          'core': {
            'data': results,
            'themes': {
              'name': 'proton',
              'responsive': true,
              "icons":false
            }
          }
        });
        if (expandAll) {
          tree.bind( "loaded.jstree", function ( event, data ) {
            $( this ).jstree( "open_all" );
          });
        }
      }
    }
  );
}

jQuery( document ).ready( function( $ ) {

	/*
	 * Save chosen institutions to DB
	 */
	$( 'body' ).on( 'click', '#save-these-institutions', function( event ) {
		event.preventDefault();

		var data = {};
		var institutions = [];

        var selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
        $.each( selectedInstitutions, function( index, value ){
            var explodedArray = value.split(":");
            institutions.push(explodedArray[1].slice(1, -1));
        });

		data['institutions'] = institutions;

		$.ajax({
        	type: 'POST',
        	cache: false,
        	dataType: 'json',
        	url: VuFind.getPath() + '/AJAX/JSON?method=saveTheseInstitutions',
        	data: data,
        	beforeSend: function() {
        	},
        	success: function( response ) {
        		console.log( 'Save these institutions: ' );
        		console.log( data );
        		if (response.status == 'OK') {

        			$( '#save-these-institutions-confirmation' ).modal( 'show' );

        			setTimeout( function() {
        				$( '#save-these-institutions-confirmation' ).modal( 'hide' );
        			}, 1200 );

        		} else {
        			console.error(response.data);
        		}

         	},
            error: function ( xmlHttpRequest, status, error ) {
            	$( '#search-results-loader' ).remove();
            	console.error(xmlHttpRequest.responseText);
            	console.error(xmlHttpRequest);
            	console.error(status);
            	console.error(error);
            },
            complete: function ( xmlHttpRequest, textStatus ) {
            }
        });

	});

    /*
     * Load saved institutions from db
     */
    $( 'body' ).on( 'click', '#load-saved-institutions', function( event ) {
        event.preventDefault();

        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: VuFind.getPath() + '/AJAX/JSON?method=getSavedInstitutions',
            beforeSend: function() {
            },
            success: function( response ) {
                console.log( 'Save these institutions: ' );
                console.log( response );
                if (response.status == 'OK') {
                    $('#facet_institution').jstree(true).deselect_all();

                    let arrayInstitutions = response.data.savedInstitutions;

                    $.each( arrayInstitutions, function( index, value ){
                        var institution = '~local_region_institution_facet_str_mv:"' + value + '"';
                        $('#facet_institution').jstree(true).select_node(institution);

                    });

                    $( "input[name='page']" ).val( '1' );

                    //remove all institutions
                    var allInstitutions = $('#facet_institution').jstree(true).get_json('#', {flat:true});
                    $.each( allInstitutions, function( index, value ){
                        ADVSEARCH.removeFacetFilter( value['id'], false );
                    });

                    //add selected institutions
                    var selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
                    $.each( selectedInstitutions, function( index, value ){
                        ADVSEARCH.addFacetFilter( value, false );
                    });
                    ADVSEARCH.updateSearchResults( undefined, undefined );

                } else {
                    console.error(response.data);
                }

            },
            error: function ( xmlHttpRequest, status, error ) {
                $( '#search-results-loader' ).remove();
                console.error(xmlHttpRequest.responseText);
                console.error(xmlHttpRequest);
                console.error(status);
                console.error(error);
            },
            complete: function ( xmlHttpRequest, textStatus ) {
            }
        });

    });
    
    /*
     * Load my institutions from HTML container
     */
    $( 'body' ).on( 'click', '#load-my-institutions', function( event ) {
        event.preventDefault();
        
        var data = $( '#my-libraries-container' ).text();
        console.log('Loading my libraries: ');
        console.log( data  );

        $('#facet_institution').jstree(true).deselect_all();

        var arrayInstitutions = data.split(";");

        $.each( arrayInstitutions, function( index, value ){
            var institution = '~local_region_institution_facet_str_mv:"' + value + '"';
            $('#facet_institution').jstree(true).select_node(institution);

        });

        $( "input[name='page']" ).val( '1' );

        //remove all institutions
        var allInstitutions = $('#facet_institution').jstree(true).get_json('#', {flat:true});
        $.each( allInstitutions, function( index, value ){
            ADVSEARCH.removeFacetFilter( value['id'], false );
        });

        //add selected institutions
        var selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
        $.each( selectedInstitutions, function( index, value ){
            ADVSEARCH.addFacetFilter( value, false );
        });
        ADVSEARCH.updateSearchResults( undefined, undefined );

    });
    
    /*
     * Load nearest institutions from HTML container
     */
    $( 'body' ).on( 'click', '#load-nearest-institutions', function( event ) {
        event.preventDefault();
        
        GEO.getPositionForLoadingInstitutions();
    });
    
    /*
     * Shake button on institution facet change
     */
    $( 'body' ).on( 'click', '#facet_institution .jstree-anchor', function( event ) {
        if (localStorage['facetsApplied']) {
            localStorage.setItem("facetsApplied", parseInt(parseInt(localStorage.getItem("facetsApplied")) + 1));
        }
        if ( $( '.institution-facet-filter-button' ).visible( true ) ) {
            $('.institution-facet-filter-button').parent().effect('shake', {
                times: 3,
                distance: 3,
                direction: 'right'
            }, 200);
        } else {
            if ($(".bootstrap-growl").length === 0) {
                var isMobile = false; //initiate as false
                // device detection
                if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
                    || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) isMobile = true;

                if (isMobile == false) {
                    $.bootstrapGrowl(VuFind.translate('Do not forget to update search results'), // Messages
                        { // options
                            type: "info", // info, success, warning and danger
                            ele: "body", // parent container
                            offset: {
                                from: "bottom",
                                amount: 40
                            },
                            align: "left", // right, left or center
                            width: 300,
                            delay: 4000,
                            allow_dismiss: false, // add a close button to the message
                            stackup_spacing: 10
                        });
                }
            }
        }
    });
    
    FACETS = {
    			
		reloadInstitutionsByGeolocation: function( coords ) {
			console.log( 'Loading position... Coords: ' );
			console.log( coords );
			
			$.ajax({
	            type: 'POST',
	            cache: false,
	            dataType: 'json',
	            data: coords,
	            url: VuFind.getPath() + '/AJAX/JSON?method=getTownsByRegion',
	            beforeSend: function() {
	            },
	            success: function( response ) {

	                if (response.status == 'OK') {
		                console.log( 'STATUS: OK ' );
		                //console.log( response );
		                console.log( 'My region is:' );
		                console.log( response.data.region );
		                
		                $('#facet_institution').jstree(true).deselect_all();
		                
		                $.each( response.data.towns, function( key, value ) {
		                var townFacet = '~local_region_institution_facet_str_mv:"1/Library/' + value.town.toLowerCase() + '/"';
		                	$('#facet_institution').jstree(true).select_node(townFacet);
	                	});
		                
		                $( "input[name='page']" ).val( '1' );

		                //remove all institutions
		                var allInstitutions = $('#facet_institution').jstree(true).get_json('#', {flat:true});
		                $.each( allInstitutions, function( index, value ){
		                    ADVSEARCH.removeFacetFilter( value['id'], false );
		                });

		                //add selected institutions
		                var selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
		                $.each( selectedInstitutions, function( index, value ){
		                    ADVSEARCH.addFacetFilter( value, false );
		                });
		                ADVSEARCH.updateSearchResults( undefined, undefined );
		                
	                } else {
	                    console.error(response.data);
	                }

	            },
	            error: function ( xmlHttpRequest, status, error ) {
	                $( '#search-results-loader' ).remove();
	                console.error(xmlHttpRequest.responseText);
	                console.error(xmlHttpRequest);
	                console.error(status);
	                console.error(error);
	            },
	            complete: function ( xmlHttpRequest, textStatus ) {
	            }
	        });
		}
		
	};
    	

});

var replaceAll = function ( str, find, replace ) {
	  return str.replace( new RegExp( (find+'').replace(/[.?*+^$[\]\\(){}|-]/g, "\\$&") , 'g' ), replace );
};

/**
 * This functions is used like standard php's urlencode,
 * but insted of double encode, this creates url friedly string for
 * base64 encoding/decoding.
 *
 * @param   {string } input
 *
 * @return  {string}
 */
var specialUrlEncode = function( input ) {
	if ( typeof input[0] == 'undefined' || input[0] == null || !input ) {
		return '';
	}
	var output = replaceAll( input, '+', '-' );
	output = replaceAll( output, '/', '_' );
	output = replaceAll( output, '=', '.' );
	return output;
};

/**
 * This functions is used like standard php's urldecode,
 * but insted of double decode, this creates url friedly string for
 * base64 encoding/decoding.
 *
 * @param   {string } input
 *
 * @return  {string}
 */
var specialUrlDecode = function( input ) {
	if ( typeof input[0] == 'undefined' || input[0] == null || !input ) {
		return '';
	}
	var output = replaceAll( input, '-', '+' );
	output = replaceAll( output, '_', '/' );
	output = replaceAll( output, '.', '=' );
	return output;
};
