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

    if (facetName == "local_institution_facet_str_mv" ) {
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
    
      if (facetName == "local_statuses_facet_str_mv" || facetName == "conspectus_str_mv" || facetName == "cpk_detected_format_facet_str_mv") {
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

function initFacetTree(treeNode, inSidebar)
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
        treeNode.jstree({
          'core': {
            'data': results
          }
        });
      }
    }
  );
}

function initFacetOrTree(treeNode, inSidebar)
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
                treeNode.jstree({
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
            }
        }
    );
}


function initInstitutionsTree(treeNode, inSidebar)
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
        treeNode.jstree({
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

                    var csvInstitutions = response.data.savedInstitutions;

                    var arrayInstitutions = csvInstitutions.split(";");


                    $.each( arrayInstitutions, function( index, value ){
                        var institution = '~local_institution_facet_str_mv:"' + value + '"';
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
            var institution = '~local_institution_facet_str_mv:"' + value + '"';
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
    $( 'body' ).on( 'click', '.jstree-checkbox, .jstree-anchor', function( event ) {
    	$( '.institution-facet-filter-button' ).parent().effect( 'shake', {times:3, distance: 3, direction: 'right'}, 200 );
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
		                	var townFacet = '~local_institution_facet_str_mv:"1/Library/'+value.town.toLowerCase()+'/"';
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
