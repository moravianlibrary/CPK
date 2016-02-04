/**
 * Record Controller for favorites AngularJS app.
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

    angular.module('favorites').controller('RecordFavController', RecordController).directive('addRemove', RecordDirective);

    RecordController.$inject = [ '$log', 'storage', 'favoritesFactory', 'Favorite', 'broadcaster' ];

    /**
     * Private variables to let the addRemove directive handle desired elements
     * to the RecordController ..
     */
    var pubElements = {
	    remFavBtn : undefined,
	    addFavBtn : undefined
    };

    function RecordController($log, storage, favoritesFactory, Favorite, broadcaster) {

	var fav = undefined,
	recordIsFav = false;
	
	var vm = this;

	vm.addOrRemoveFavorite = addOrRemoveFavorite;
	vm.isFavorite = isFavorite;
	
	isFavorite().then(function(favorite) {
	    fav = favorite;
	    
	    switchAddRemoveSpanVisibility();
	});
	
	// Public method __isFavCallback
	window.__isFavCallback = function(tf, newFav) {
	    
	    if (newFav instanceof Favorite)

		if (tf === true && recordIsFav === false && getRecordId(newFav.titleLink()) === getRecordId()) {
			
			/*
			 * Was created new & this ctrl doesnt know it & we are
			 * talking about current rec
			 */
		    
		    switchAddRemoveSpanVisibility();
		} else if (tf === false && recordIsFav === true && newFav.created() === fav.created()) {

		    	/*
			 * Was removed old & this ctrl doesnt know it & we are
			 * talking about current rec
			 */
		    switchAddRemoveSpanVisibility();		
		}
	}

	return;
	//

	/**
	 * Dispatches the user's click based on the logic implemented ..
	 */
	function addOrRemoveFavorite() {
	    if (! recordIsFav) {
		addFavorite();
	    } else {
		removeFavorite();
	    }
	};
	
	/**
	 * Prompts the storage to add the current record to the favorites.
	 */
	function addFavorite() {

	    fav = favoritesFactory.createFromCurrentRecord();
	    
	    storage.addFavorite(fav).then(function() {
		
		switchAddRemoveSpanVisibility();
		
		// Broadcast this event across all tabs
		broadcaster.broadcastAdded(fav);
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	};
	
	/**
	 * Prompts the storage to remove the current favorite.
	 */
	function removeFavorite() {
	    
	    var id = fav.created();
	    
	    storage.removeFavorite(id).then(function() {
		
		switchAddRemoveSpanVisibility();
		
		// Broadcast this event across all tabs
		broadcaster.broadcastRemoved(id);
		
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
	function isFavorite() {
	    return new Promise(function(resolve, reject) {
		
		var recordId = getRecordId();
		
		storage.hasFavorite(recordId).then(function(favorite) {
		    resolve(favorite);
		}).catch(reject);
	    });
	};
	
	/**
	 * Gets the record id of current record page
	 */
	function getRecordId(fromThis) {
	    
	    var fromWhat = (typeof fromThis === "undefined") ? location.pathname : fromThis;
	    
	    return fromWhat.split('/')[2];
	}
	
	/**
	 * It switches the visibility of two spans with "addRemove" attribute.
	 * 
	 * It should be called with caution only when you're certain the
	 * visibility change is desired !
	 */
	function switchAddRemoveSpanVisibility() {
	    	    
	    // Switch their roles ..
	    pubElements.remFavBtn.hidden = ! pubElements.remFavBtn.hidden;
	    pubElements.addFavBtn.hidden = ! pubElements.addFavBtn.hidden;
	    
	    // record is favorite boolean is now being inverted ..
	    recordIsFav = ! recordIsFav;
	};
    };
    
    function RecordDirective() {
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
	    
	    // Add favorite will be shown by default & remove hidden by def
	    if (elements.context.getAttribute('add-remove') === "add") {

		// Store the pointer to this element
	    	pubElements.addFavBtn = elements.context;

		// Set it to shown
	    	elements.context.hidden = false;
	    	    
	    } else if (elements.context.getAttribute('add-remove') === "rem") {
		    
		// Store the pointer to this element
		pubElements.remFavBtn = elements.context;
		    
		// Set it to hidden
		elements.context.hidden = true;
		    
	    } 
	    
	};
    };
})();