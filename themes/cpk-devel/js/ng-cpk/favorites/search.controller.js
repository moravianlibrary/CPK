/**
 * Search Controller for favorites AngularJS app.
 * 
 * It communicates with the storage module & the favoritesFactory.
 * 
 * The FavoritesFactory provides an method parsing current record page into the
 * Favorite class, which is then handled to the storage module handling the
 * session storage ..
 * 
 * This controller is basically responsible for determining if the record being
 * now viewed is alredy in user's favorites & to let user add or remove it from
 * those favorites ..
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('SearchFavController', SearchController).directive('addRemove', SearchDirective);

    SearchController.$inject = [ '$window', '$scope', '$compile', '$log', 'storage', 'favoritesFactory', 'Favorite', 'favsBroadcaster' ];

    /**
     * Private variables to let the addRemove directive handle desired elements
     * to the SearchController ..
     */
    var pubElements = [], pubElementsLinked = [];

    function SearchController($window, $scope, $compile, $log, storage, favoritesFactory, Favorite, favsBroadcaster) {

	var favs = [],
	recordIsFav = [];
	
	var vm = this;

	vm.addOrRemoveFavorite = addOrRemoveFavorite;
	vm.isFavorite = isFavorite;
	
	var rankedItemsLength, rankedItems;
	init(true);
	
	$window.addEventListener('searchResultsLoaded', function() {
	    $scope.$apply(init());
	});
	
	$window.addEventListener('searchResultsLoading', function() {
	    $scope.$apply(reset());
	});

	/**
	 * Public function about to be called from the favsBroadcaster when an event
	 * happens (meaning adding / removal of the favorite)
	 */
	window.__favChanged = function(isNew, newFav) {
	    
	    if (newFav instanceof Favorite)

		if (isNew === true) {
			
			/*
			 * This ctrl doesnt know it & we are talking about
			 * current rec
			 */
		    for (var rank = 0; rank < rankedItemsLength; ++rank) {
			if (recordIsFav[rank] === false && getRecordId(newFav.titleLink()) === getRecordId(undefined, rank)) {
			    favs[rank] = newFav;
		    	    switchAddRemoveSpanVisibility(rank);
		    	    break;
			}
		    }
		    
		} else if (isNew === false) {

		    	/*
			 * Was removed old & this ctrl doesnt know it & we are
			 * talking about current rec
			 */
		    for (var rank = 0; rank < rankedItemsLength; ++rank) {
			if (recordIsFav[rank] === true && newFav.created() === favs[rank].created()) {
			    favs[rank] = newFav;
		    	    switchAddRemoveSpanVisibility(rank);
		    	    break;
			}
		    }
		}
	}

	return;
	//
	
	function init(directly) {
	    rankedItems = document.querySelectorAll('div#result-list-placeholder div[id]');
	    
	    if (typeof directly === 'undefined')
		$compile(rankedItems)($scope);
	    
	    rankedItemsLength = rankedItems.length;
	    for (var rank = 0; rank < rankedItemsLength; ++rank) {
		isFavorite(rank).then(function(result) {
		    
		    rank = result.rank;
		    favorite = result.favorite;
		    
		    favs[rank] = favorite;

	    	    switchAddRemoveSpanVisibility(rank);
		});
	    }
	}
	
	function reset() {
	    favs = [];
	    recordIsFav = [];
	    pubElements = [];
	    pubElementsLinked = [];
	}

	/**
	 * Dispatches the user's click based on the logic implemented ..
	 */
	function addOrRemoveFavorite(rank) {
	    if (! recordIsFav[rank]) {
		addFavorite(rank);
	    } else {
		removeFavorite(rank);
	    }
	};
	
	/**
	 * Prompts the storage to add the current record to the favorites.
	 */
	function addFavorite(rank) {
	    
	    favs[rank] = favoritesFactory.createFromCurrentSearch(rank);
	    
	    storage.addFavorite(favs[rank]).then(function() {
		
		switchAddRemoveSpanVisibility(rank);
		
		// Broadcast this event across all tabs
		favsBroadcaster.broadcastAdded(favs[rank]);
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	};
	
	/**
	 * Prompts the storage to remove the current favorite.
	 */
	function removeFavorite(rank) {
	    var id = favs[rank].created();
	    
	    storage.removeFavorite(id).then(function() {
		
		switchAddRemoveSpanVisibility(rank);
		
		// Broadcast this event across all tabs
		favsBroadcaster.broadcastRemoved(favs[rank]);
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	}
	
	/**
	 * Prompts storage module to see if there already is favorite with
	 * current recordId ..
	 * 
	 * It returns Promise, which will resolve the favorite as a Favorite
	 * class if found ..
	 * 
	 * If it doesn't find anything, it fires the reject method only.
	 */
	function isFavorite(rank) {
	    return new Promise(function(resolve, reject) {
		
		var recordId = getRecordId(undefined, rank);
		
		storage.hasFavorite(recordId).then(function(favorite) {
		    resolve({
			rank: rank,
			favorite: favorite
		    });
		}).catch(reject);
	    });
	};
	
	/**
	 * Gets the record id of a ranked record 
	 */
	function getRecordId(fromThis, rank) {
	    
	    if (typeof rank !== 'undefined')
		return rankedItems[rank].querySelector('a.title').getAttribute('href').match(/Record\/(.*)\?/)[1];
	    
	    var fromWhat = (typeof fromThis === "undefined") ? location.pathname : fromThis;
	    
	    return fromWhat.split('/')[2];
	}
	
	/**
	 * It switches the visibility of two spans with "addRemove" attribute.
	 * 
	 * It should be called with caution only when you're certain the
	 * visibility change is desired !
	 */
	function switchAddRemoveSpanVisibility(rank) {
	    	    
	    if (typeof pubElementsLinked[rank] === 'undefined' || ! pubElementsLinked[rank]) {
		pubElementsLinked[rank] = function() {
		    switchAddRemoveSpanVisibility(rank);
		};
		
		return;
	    }
	    // Switch their roles ..
	    pubElements[rank].remFavBtn.hidden = ! pubElements[rank].remFavBtn.hidden;
	    pubElements[rank].addFavBtn.hidden = ! pubElements[rank].addFavBtn.hidden;
	    
	    // record is favorite boolean is now being inverted ..
	    recordIsFav[rank] = ! recordIsFav[rank];
	};
    };
    
    function SearchDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	/**
	 * Links the spans saying add favorite & remove favorite to private
	 * variables which are about to be handled by the controller.
	 * 
	 * @param scope
	 * @param elements
	 * @param attrs
	 */
	function linker(scope, elements, attrs) {
	    
	    var attr = elements.context.getAttribute('add-remove');
	    
	    var action, rank, attrSplitted = attr.split(':');
	    
	    action = attrSplitted[0];
	    rank = parseInt(attrSplitted[1]);
	    
	    if (Number.isNaN(rank))
		return console.error('No rank provided to a SearchDirective!');
	    
	    if (typeof pubElements[rank] === 'undefined')
		pubElements[rank] = {
		    addFavBtn: undefined,
		    remFavBtn: undefined
	    	}
	    
	    // Add favorite will be shown by default & remove hidden by def
	    if (action === "add") {

		// Store the pointer to this element
	    	pubElements[rank].addFavBtn = elements.context;

		// Set it to shown
	    	elements.context.hidden = false;
	    	    
	    } else if (action === "rem") {
		    
		// Store the pointer to this element
		pubElements[rank].remFavBtn = elements.context;
		    
		// Set it to hidden
		elements.context.hidden = true;
		    
	    }
	    if (typeof pubElementsLinked[rank] === 'function') {
		pubElementsLinked[rank].call();
	    } else
		pubElementsLinked[rank] = true;
	};
    };
})();