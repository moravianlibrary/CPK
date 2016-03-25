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
		 * @param {JSON} 	originalQueryJson 
		 * @param {JSON} 	dataFromAutocomplete
		 * @param {boolean}	switchToAdvancedSearch
		 * 
		 * @return {undefined}
		 */
		updateSearchResults: function( dataFromWindowHistory, dataFromAutocomplete, switchToAdvancedSearch ) {
			
			var data = {};
			
			if ( dataFromWindowHistory !== undefined ) { // history.back or forward action was porformed
				
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
			
			if ( dataFromWindowHistory == undefined) {
				/* Set default values if not provided before (for basic search) */
				if ( (! data.hasOwnProperty( 'bool0' )) || (! data.bool0) ) {
					data['bool0'] = [];
					data['bool0'].push( 'OR' );
				}
				
				if ( (! data.hasOwnProperty( 'join' ) ) || ( ! data.join ) ) {
					data['join'] = 'OR';
				}
				
				if ( (! data.hasOwnProperty( 'page' )) || (! data.page) ) {
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
			
			/* Live update url */
    		if ( dataFromWindowHistory == undefined ) {
    			ADVSEARCH.updateUrl( data );
    		} else { // from history
    			ADVSEARCH.replaceUrl( data );
    		}
    		
    		if ( switchToAdvancedSearch ) {
    			var  toUrl = window.location.href;
    			window.location.href = toUrl;
    		}
			
    		/* Search */	
			$.ajax({
	        	type: 'POST',
	        	cache: false,
	        	dataType: 'json',
	        	url: VuFind.getPath() + '/AJAX/JSON?method=updateSearchResults',
	        	data: data,
	        	beforeSend: function() {
	        		
	        		smoothScrollToElement( '#result-list-placeholder' );
	        		var loader = "<div id='search-results-loader' class='text-center'></div>";
	        		$( '#result-list-placeholder' ).hide( 'blind', {}, 200, function() {
	        			$( '#result-list-placeholder' ).before( loader );
	        		});
	        		$( '#results-amount-info-placeholder' ).html( "<i class='fa fa-2x fa-refresh fa-spin'></i>" );
	        		//$( '#side-facets-placeholder' ).hide( 'fade', {}, 200 );
	        		$( '#pagination-placeholder' ).hide( 'blind', {}, 200 );
	        		
	        		// Disabled submit button until ajax finishes
	        		$( '#submit-edited-advanced-search', '.ajax-update-limit', '.ajax-update-sort' ).attr( 'disabled', true );
	        	},
	        	success: function( response ) {
	        		if (response.status == 'OK') {
	        			
	        			var responseData = response.data;
	        			var resultsHtml = JSON.parse(responseData.resultsHtml);
	        			var facetsHtml = JSON.parse(responseData.sideFacets);
	        			var resultsAmountInfoHtml = JSON.parse(responseData.resultsAmountInfoHtml);
	        			var paginationHtml = JSON.parse(responseData.paginationHtml);	
	        			
	        			/* Ux content replacement */
	        			$( '#search-results-loader' ).remove();
	        			$( '#result-list-placeholder, #pagination-placeholder' ).css( 'display', 'none' );
	        			$( '#result-list-placeholder' ).html( decodeHtml(resultsHtml.html) );
	        			$( '#pagination-placeholder' ).html( paginationHtml.html );
	        			$( '#results-amount-info-placeholder' ).html( resultsAmountInfoHtml.html );
	        			$( '#side-facets-placeholder' ).html( facetsHtml.html );
		        		$( '#result-list-placeholder, #pagination-placeholder, #results-amount-info-placeholder' ).show( 'blind', {}, 500 );
		        		//$( '#side-facets-placeholder' ).show( 'fade', {}, 200 );
		        		
		        		/* Update search identificators */
		        		$( '#rss-link' ).attr( 'href', window.location.href + '&view=rss' );
		        		$( '.mail-record-link' ).attr( 'id', 'mailSearch' + responseData.searchId );
		        		$( '#add-to-saved-searches' ).attr( 'href', 'MyResearch/SaveSearch?save=' + responseData.searchId );
		        		$( '#remove-from-saved-searches' ).attr( 'href', 'MyResearch/SaveSearch?delete=' + responseData.searchId );
		        		
	        		} else {
	        			console.error(response.data);
	        		}
	        		$( '#submit-edited-advanced-search', '.ajax-update-limit', '.ajax-update-sort' ).removeAttr( 'selected' );
	        		
	        		/** 
	        		 * Opdate sort and limit selects, when moving in history back or forward. 
	        		 * We need to use this f****** stupid robust solution to prevent 
	        		 * incompatibility and bad displaying of options that are 
	        		 * in real selected 
	        		 */
	        		$( '.ajax-update-limit option' ).prop( 'selected', false);
	        		$( '.ajax-update-limit' ).val( [] );
	        		$( '.ajax-update-limit option' ).removeAttr( 'selected' );
	        		
	        		$( '.ajax-update-sort option' ).prop( 'selected', false );
	        		$( '.ajax-update-sort' ).val( [] );
	        		$( '.ajax-update-sort option').removeAttr( 'selected');
	        		
	        		$( '.ajax-update-limit' ).val( data.limit );
	        		$( '.ajax-update-limit option[value=' + data.limit + ']' ).attr( 'selected', 'selected' );
	        		$( '.ajax-update-limit option[value=' + data.limit + ']' ).attr( 'selected', true );
	        		
	        		$( '.ajax-update-sort' ).val( data.sort );
        			$( '.ajax-update-sort option[value="' + data.sort + '"]' ).attr( 'selected', 'selected' );
        			$( '.ajax-update-sort option[value="' + data.sort + '"]' ).attr( 'selected', true );
	        		
	         	},
	            error: function (xmlHttpRequest, status, error) {
	            	$( '#search-results-loader' ).remove();
	            	console.error(xmlHttpRequest.responseText);
	            	console.log(xmlHttpRequest);
	            	console.error(status);
	            	console.error(error);
	            	console.log( 'Sent data: ' );
	            	console.log( data );
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
			window.history.pushState( stateObject, title, url );
			console.log( 'Pushing and replacing state: ' );
			console.log( stateObject );
			window.history.replaceState( stateObject, title, url );
		},
		
		replaceUrl: function( data ) {
			var stateObject = data;
			var title = 'New search query';
			var url = '/Search/Results/?' + jQuery.param( data )
			window.history.replaceState( stateObject, title, url );
			console.log( 'Replacing state: ' );
			console.log( stateObject );
		},
	}
	
	/**
	 * Load correct content on history back or forward
	 */
	$( window ).bind( 'popstate', function() {
		var currentState = history.state;
		console.log( 'POPing state: ' );
		console.log( currentState );
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
		clone.find( 'select.group-operator' ).attr( 'name', 'bool' + nextGroupNumber + '[]' );
		clone.find( 'select.query-type' ).attr( 'name', 'type' + nextGroupNumber + '[]' );
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
	
	$( '.searchForm' ).on( 'click', '#edit-as-advanced-search-link', function( event ) {
		event.preventDefault();
		ADVSEARCH.updateSearchResults( undefined, undefined, true );
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
  	
  	/**
  	 * Convert html entities to chars 
  	 * 
  	 * @param	{string}	html
  	 * @return	{string}
  	 */
  	var decodeHtml = function( html ) {
  	    var txt = document.createElement( 'textarea' );
  	    txt.innerHTML = html;
  	    return txt.value;
  	}
});