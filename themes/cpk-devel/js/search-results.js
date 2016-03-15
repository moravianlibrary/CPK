jQuery( document ).ready( function( $ ) {
	
	ADVSEARCH = {
		updateGroupsDOMState: function( formSelector ) {
			var groupsCount = $( formSelector ).find( '.group' ).length;
			if ( groupsCount == 1 ) {
				$( '.remove-advanced-search-group' ).parent().addClass( 'hidden' );
				$( '#group-join-type' ).parent().parent().addClass( 'hidden' );
			} else {
				$( '.remove-advanced-search-group' ).parent().removeClass( 'hidden' );
				$( '#group-join-type' ).parent().parent().removeClass( 'hidden' );
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
		 */
		updateSearchResults: function( dataFromWindowHistory ) {
			
			var data = {};
			if ( dataFromWindowHistory !== undefined) { // history.back or forward action was porformed
				
				data = dataFromWindowHistory;
				
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
			
			/**************** Start modifications control ****************/
			/*
			var warnings = 0;
			TODO: If the form input fields were modified, ask to reflect 
			a new search queries or to use original ones
			Is jQuery.each.lookFor[] == object.foreach.lookFor[] ? ++warnigns;
			*/
			/**************** End of modifications control ****************/
				
			$.ajax({
	        	type: 'POST',
	        	dataType: 'json',
	        	url: VuFind.getPath() + '/AJAX/JSON?method=updateSearchResults',
	        	data: data,
	        	beforeSend: function() {
	        		var loader = "<div id='search-results-loader' class='text-center'><i class='fa fa-2x fa-refresh fa-spin'></i></div>";
	        		$( '#result-list-placeholder' ).hide( 'blind', {}, 200, function() {
	        			$( '#result-list-placeholder' ).before( loader );
	        		});
	        		$( '#pagination-placeholder' ).hide( 'blind', {}, 200 );
	        		
	        	},
	        	success: function( response ) {
	        		if (response.status == 'OK') {
	        			$( '#search-results-loader' ).remove();
	        			$( '#result-list-placeholder, #pagination-placeholder' ).css( 'display', 'none' );
	        			$( '#result-list-placeholder' ).html( response.data.resultsHtml );
	        			$( '#pagination-placeholder' ).html( response.data.paginationHtml );
		        		$( '#result-list-placeholder, #pagination-placeholder' ).show( 'blind', {}, 500 );
		        		ADVSEARCH.updateUrl( data );
	        		} else {
	        			console.error(response.data);
	        		}
	         	},
	            error: function (request, status, error) {
	            	console.error(request.responseText);
	            }
	        });
		},
			
		addOrRemoveFacetFilter: function( value ) {
			var actionPerformed = 0;
			$( '#hiddenFacetFilters input' ).each( function( index, element ) {
				if( $( element ).val() == value) {
					$( this ).remove();
					++actionPerformed;
					return false; // javascript equivalent to php's break;
				}
			});
			
			if (actionPerformed == 0) { /* This filter not applied yet, apply it now */
				var html = "<input type='hidden' class='hidden-filter' name='filter[]' value='" + value + "'>";
				$( '#hiddenFacetFilters' ).append(html);
			}
			
			/*
			 * @TODO: UPDATE URL async here!
			 */
			
			ADVSEARCH.updateSearchResults();
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
		ADVSEARCH.updateSearchResults( currentState );
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
				ADVSEARCH.addOrRemoveFacetFilter( $( element ).attr( 'href' ) );
			});
		} else {
			ADVSEARCH.addOrRemoveFacetFilter( $( this ).attr( 'href' ) );
		}
	});
	
	$( '#editable-advanced-search-form' ).on( 'click', '#submit-edited-advanced-search', function( event ) {
		event.preventDefault();
		ADVSEARCH.updateSearchResults();
	});
});