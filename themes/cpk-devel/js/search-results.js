/**
 * Async search-results.js v 0.1
 * @Author Martin Kravec <martin.kravec@mzk.cz>
 */
jQuery( document ).ready( function( $ ) {
	
	ADVSEARCH = {
		updateGroupsDOMState: function( formSelector ) {
			var groupsCount = $( formSelector ).find( '.group' ).length;
			if ( groupsCount == 1 ) {
				$( '.remove-advanced-search-group' ).parent().addClass( 'hidden' );
				$( '#group-join-type-row' ).addClass( 'hidden' );
			} else {
				$( '.remove-advanced-search-group' ).parent().removeClass( 'hidden' );
				$( '#group-join-type-row' ).removeClass( 'hidden' );
			}
		},

		updateQueriesDOMState: function( groupSelector ) {
			var queriesCount = $( groupSelector + ' .queries').length;
			if ( queriesCount == 1 ) {
				$( groupSelector ).find( '.remove-advanced-search-query' ).parent().addClass( 'hidden' );
			} else {
				$( groupSelector ).find( '.remove-advanced-search-query' ).parent().removeClass( 'hidden' );
			}
		},
		
		/**
		 * Return and display new seaerch results
		 * 
		 * @param {JSON} originalQueryJson 
		 * @param {JSON} dataFromAutocomplete
		 * 
		 * @return {undefined}
		 */
		updateSearchResults: function( dataFromWindowHistory, dataFromAutocomplete ) {
			
			var data = {};
			
			if ( dataFromWindowHistory !== undefined) { // history.back or forward action was porformed
				
				data = dataFromWindowHistory;
				
			} else if ( dataFromAutocomplete ) {
				
				data = queryStringToJson( dataFromAutocomplete.queryString );
				
				if ( data.lookfor ) {
					data['lookfor0'] = data.lookfor;
					delete data.lookfor;
				}
				
				if ( data.type ) {
					data['type0'] = data.type;
					delete data.type;
				}
				
			} else { // harvest form's fields and form's hidden facetFilters
				
				var filters = [];
				$( '.hidden-filter' ).each( function( index, element ) {
					filters.push( $( element ).val() );
				});
				data['filter'] = filters;
				
				$( '.query-type, .query-string, .group-operator' ).each( function( index, element ) {
					var key = $( element ).attr( 'name' ).slice( 0, -2 );
					if (! data.hasOwnProperty( key )) {
						data[key] = [];
					}
					data[key].push( $( element ).val() );
				});
				
				var allGroupsOperator = $( 'input[name="join"]' ).val();
				data['join'] = allGroupsOperator;
			}
			
			/* Set default values if not provided before (for basic search) */
			if (! data.hasOwnProperty( 'bool0' )) {
				data['bool0'] = [];
				data['bool0'].push( 'OR' );
			}
			
			if ( (! data.hasOwnProperty( 'join' ) ) || ( ! data.join ) ) {
				data['join'] = 'OR';
			}
			
			if (! data.hasOwnProperty( 'page' )) {
				var page = $( "input[name='page']" ).val();
				data['page'] = page;
			}
			
			/* Set search term and type from Autocomplete when provding async results loading in basic search */
			if (! data.hasOwnProperty( 'lookfor0' )) {
				var lookfor0 = $( "input[name='last_searched_lookfor0']" ).val();
				data['lookfor0'] = [];
				data['lookfor0'].push( lookfor0 );
			}
			
			if (! data.hasOwnProperty( 'type0' )) {
				var type = $( "input[name='last_searched_type0']" ).val();
				data['type0'] = [];
				data['type0'].push( type );
			}
			
			if (! data.hasOwnProperty( 'limit' )) {
				var limit = $( "input[name='limit']" ).val();
				data['limit'] = limit;
			}
			
			if (! data.hasOwnProperty( 'sort' )) {
				var sort = $( "input[name='sort']" ).val();
				data['sort'] = sort;
			}
			
			/**************** Start modifications control ****************/
			/*
			var warnings = 0;
			TODO: If the form input fields were modified, ask to reflect 
			a new search queries or to use original ones
			Is jQuery.each.lookFor[] == object.foreach.lookFor[] ? ++warnigns;
			*/
			/**************** End of modifications control ****************/
			
			/* Set last search */
			var lastSearchedLookFor0 = data['lookfor0'][0];
			$( "input[name='last_searched_lookfor0']" ).val( lastSearchedLookFor0 );
			
			var lastSearchedType0 = data['type0'][0];
			$( "input[name='last_searched_type0']" ).val( lastSearchedType0 );
			
    		/* Search */	
			$.ajax({
	        	type: 'POST',
	        	dataType: 'json',
	        	url: VuFind.getPath() + '/AJAX/JSON?method=updateSearchResults',
	        	data: data,
	        	beforeSend: function() {
	        		
	        		/* Live update url */
	        		ADVSEARCH.updateUrl( data );
	        		
	        		smoothScrollToElement( '#result-list-placeholder' );
	        		var loader = "<div id='search-results-loader' class='text-center'><i class='fa fa-2x fa-refresh fa-spin'></i></div>";
	        		$( '#result-list-placeholder' ).hide( 'blind', {}, 200, function() {
	        			$( '#result-list-placeholder' ).before( loader );
	        		});
	        		$( '#results-amount-info-placeholder' ).html( "<i class='fa fa-refresh fa-spin'></i>" );
	        		$( '#side-facets-placeholder' ).html( "<i class='fa fa-refresh fa-spin'></i>" );
	        		$( '#pagination-placeholder' ).hide( 'blind', {}, 200 );
	        		
	        	},
	        	success: function( response ) {
	        		if (response.status == 'OK') {
	        			
	        			/* Ux content replacement */
	        			$( '#search-results-loader' ).remove();
	        			$( '#result-list-placeholder, #pagination-placeholder' ).css( 'display', 'none' );
	        			$( '#result-list-placeholder' ).html( response.data.resultsHtml );
	        			$( '#pagination-placeholder' ).html( response.data.paginationHtml );
	        			$( '#results-amount-info-placeholder' ).html( response.data.resultsAmountInfoHtml );
	        			$( '#side-facets-placeholder' ).html( response.data.sideFacets );
		        		$( '#result-list-placeholder, #pagination-placeholder, #results-amount-info-placeholder' ).show( 'blind', {}, 500 );
		        		
		        		/* Update search identificators */
		        		$( '#rss-link' ).attr( 'href', window.location.href + '&view=rss' );
		        		$( '.mail-record-link' ).attr( 'id', 'mailSearch' + response.data.searchId );
		        		$( '#add-to-saved-searches' ).attr( 'href', 'MyResearch/SaveSearch?save=' + response.data.searchId );
		        		$( '#remove-from-saved-searches' ).attr( 'href', 'MyResearch/SaveSearch?delete=' + response.data.searchId );
		        		
	        		} else {
	        			console.error(response);
	        		}
	         	},
	            error: function (request, status, error) {
	            	console.error(request.responseText);
	            }
	        });
		},
			
		addOrRemoveFacetFilter: function( value ) {
			var actionPerformed = 0;
			$( '#hiddenFacetFilters input, #hiddenFacetFiltersForBasicSearch input' ).each( function( index, element ) {
				if( $( element ).val() == value) {
					$( this ).remove();
					++actionPerformed;
					return false; // javascript equivalent to php's break;
				}
			});
			
			if (actionPerformed == 0) { /* This filter not applied yet, apply it now */
				var html = "<input type='hidden' class='hidden-filter' name='filter[]' value='" + value + "'>";
				$( '#hiddenFacetFilters, #hiddenFacetFiltersForBasicSearch' ).append( html );
			}
			
			/*
			 * @TODO: UPDATE URL async here!
			 */
			
			ADVSEARCH.updateSearchResults( undefined, undefined );
		},
		
		updateUrl: function( data ) {
			var stateObject = data;
			var title = 'New search query';
			var url = '/Search/Results/?' + jQuery.param( data )
			window.history.replaceState( stateObject, title, url );
		},
	}
	
	/**
	 * Load correct content on history back or forward
	 */
	$( window ).bind( 'popstate', function() {
		var currentState = history.state;
		ADVSEARCH.updateSearchResults( currentState, undefined );
	});
	
	/* Update DOM state on page load */
	ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
	$( '#editable-advanced-search-form .group' ).each( function(){
		ADVSEARCH.updateQueriesDOMState( '#' + $( this ).attr( 'id' ) );
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '.add-search-group', function( event ) {
		event.preventDefault();
		var parentDiv = $( this ).parent().parent();
		var last = parentDiv.find( '.group' ).last();
		var clone = last.clone();
		var nextGroupNumber = parseInt( clone.attr( 'id' ).match( /\d+/ ) ) + 1;
		clone.attr( 'id', 'group_' + nextGroupNumber);
		clone.find( 'select' ).prop( 'selected', false );
		clone.find( 'select' ).attr( 'name', 'type' + nextGroupNumber + '[]' );
		clone.find( '.queries:not(:first)').remove();
		clone.find( 'input:text' ).val( '' );
		clone.find( 'input:text' ).attr( 'name', 'lookfor' + nextGroupNumber + '[]' );
		clone.find( '.remove-advanced-search-query' ).addClass( 'hidden' );
		clone.css( 'display', 'none' )
		last.after( clone );
		clone.show( 'blind', {}, 400, function() {
			ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
		})
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '.add-search-query', function( event ) {
		event.preventDefault();
		var parentDiv = $( this ).parent().parent();
		var last = parentDiv.find( '.queries' ).last();
		var clone = last.clone();
		var nextQueryNumber = parseInt( clone.attr( 'id' ).match( /\d+/ ) ) + 1;
		var thisGroupElement = parentDiv.parent();
		var thisGroupNumber = parseInt( thisGroupElement.attr( 'id' ).match( /\d+/ ) );
		clone.attr( 'id', 'query_' + nextQueryNumber);
		clone.find( 'select' ).prop( 'selected', false );
		clone.find( 'select' ).attr( 'name', 'type' + thisGroupNumber + '[]' );
		clone.find( 'input:text' ).val( '' );
		clone.find( 'input:text' ).attr( 'name', 'lookfor' + thisGroupNumber + '[]' );
		clone.css( 'display', 'none' );
		last.after( clone );
		clone.show( 'blind', {}, 400, function() {
			var thisGroupSelector = $( this ).parent().parent().parent().parent();
			ADVSEARCH.updateQueriesDOMState( '#' + thisGroupElement.attr( 'id' ) );
			thisGroupElement.find( '.remove-advanced-search-query' ).removeClass( 'hidden' );
		})
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '.remove-advanced-search-group', function( event ) {
		event.preventDefault();
		$( this ).parent().parent().hide( 'blind', {}, 400, function() {
			$( this ).remove();
			ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
		});
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '.remove-advanced-search-query', function( event ) {
		event.preventDefault();
		var thisElement = $( this );
		var queryRow = thisElement.parent().parent();
		var thisGroupSelector = queryRow.parent().parent();
		queryRow.hide( 'blind', {},  400, function() {
			queryRow.remove();
			ADVSEARCH.updateQueriesDOMState( '#' + thisGroupSelector.attr( 'id' ) );
		});
	});
	
	$( 'body' ).on( 'click', '.facet-filter', function( event ) {
		event.preventDefault();
		if ( $( this ).hasClass( 'institution-facet-filter-button' ) ) {
			$( 'institution-facet-filter.facet-filter-checked' ).each( function ( index, element ) {
				ADVSEARCH.addOrRemoveFacetFilter( $( element ).attr( 'data-facet' ) );
			});
		} else {
			ADVSEARCH.addOrRemoveFacetFilter( $( this ).attr( 'data-facet' ) );
		}
	});
	
	$( 'body' ).on( 'click', '.ajax-update-page', function( event ) {
		event.preventDefault();
		var page = $( this ).attr( 'href' );
		$( "input[name='page']" ).val( page );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	$( 'body' ).on( 'change', '.ajax-update-sort', function( event ) {
		event.preventDefault();
		var sort = $( this ).val();
		$( "input[name='sort']" ).val( sort );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	$( 'body' ).on( 'change', '.ajax-update-limit', function( event ) {
		event.preventDefault();
		var limit = $( this ).val();
		$( "input[name='limit']" ).val( limit );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '#submit-edited-advanced-search', function( event ) {
		event.preventDefault();
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/**
	 * Get param from url
	 * 
	 * This function doesn't handle parameters that aren't followed by equals sign
	 * This function also doesn't handle multi-valued keys
	 * 
	 * @param	{string}	name	Param name
	 * @param	{string}	url		Url
	 * 
	 * @return	{string}
	 */
	var getParameterByName = function( name, url ) {
	    var url = url.toLowerCase(); // avoid case sensitiveness  
	    var name = name.replace( /[\[\]]/g, "\\$&" ).toLowerCase(); // avoid case sensitiveness
	    
	    var regex = new RegExp( "[?&]" + name + "(=([^&#]*)|&|#|$)" ),
	        results = regex.exec( url );
	    
	    if ( ! results ) return null;
	    if ( ! results[2] ) return '';
	    
	    return decodeURIComponent( results[2].replace( /\+/g, " " ) );
	};
	
	/**
	 * Smooth scroll to the top of the element
	 * 
	 * @param	{string}	elementId
	 * @return	{undefined}
	 */
	var smoothScrollToElement = function( elementId ) {
		$( 'body' ).animate( {
	        scrollTop: $( elementId ).offset().top
	    }, 1000);
	};
	
	/**
  	 * Returns JSON from query string
  	 * Function supports multi-valued keys
  	 * 
  	 * @param	{string}	queryString	?param=value&param2=value2
  	 * @return	{JSON}
  	 */
  	var queryStringToJson = function ( queryString ) {            
  	    var pairs = queryString.slice( 1 ).split( '&' );
  	    
  	    var results = {};
  	    pairs.forEach( function( pair ) {
  	        var pair = pair.split('=');
  	        var key = pair[0];
  	        var value = decodeURIComponent(pair[1] || '');
  	        
  	        if (! results.hasOwnProperty( key )) {
  	        	results[key] = [];
  			}
  	        results[key].push( value );
  	    });

  	    return JSON.parse( JSON.stringify( results ) );
  	};
});