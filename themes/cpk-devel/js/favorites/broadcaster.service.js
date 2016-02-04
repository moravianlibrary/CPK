/**
 * Rules of handling inter-tab sessionStorage with respect to the security (yes,
 * user's favorites does have a need to be handled securely)
 * 
 * There are broadcasted these events: FavoriteAdded FavoriteRemoved
 * 
 * Also there exists one master tab which always returns desired favorites on
 * prompt.
 * 
 * Note that although this service is called broadcaster, it is also supposed to
 * handle the backend of recieving an broadcasted event. The 'broadcaster' name
 * is intended to simplify the implementation within controllers.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    
    var veryVerbose = true;
    
    angular.module('favorites').factory('broadcaster', broadcaster);

    broadcaster.$inject = [ '$log', 'storage', 'Favorite' ];

    function broadcaster($log, storage, Favorite) {

	var service = {
	    broadcastAdded : broadcastAdded,
	    broadcastRemoved : broadcastRemoved
	};

	init();

	return service;

	/**
	 * Broadcasts event called 'favAdded' across all tabs listening on
	 * storage event so they can update themselves
	 */
	function broadcastAdded(favorite) {

	    var favObj = favorite.toObject();

	    broadcast('favAdded', JSON.stringify(favObj));
	}

	/**
	 * Broadcasts event called 'favRemoved' across all tabs listening on
	 * storage event so they can update themselves
	 */
	function broadcastRemoved(favId) {
	    broadcast('favRemoved', favId);
	}

	// Private

	/**
	 * Just broadcast a message using localStorage's event
	 */
	function broadcast(key, value) {
	    localStorage.setItem(key, value);
	    localStorage.removeItem(key);
	    
	    if (veryVerbose)
		$log.debug('Emitted broadcast with key & value', key, value);
	}

	/**
	 * Create localStorage event listener to have ability of fetching data
	 * from another tab.
	 * 
	 * Also prompt for newest sessionStorage data if this is new tab
	 * created.
	 * 
	 * Also share current sessionStorage with another tabs if is this master
	 * tab. Note that only master tab can share current sessionStorage to
	 * prevent spamming from many tabs opened willing to share their
	 * sessionStorage
	 */
	function init() {

	    var favs = sessionStorage.getItem(storage.name);

	    var tabId = Date.now();

	    if (favs === null) {

		function onGotFavorites(event) {
		    if (parseInt(event.key) === tabId) {
			
			if (event.newValue === 'null') {
			    sessionStorage.setItem(storage.name, '[]');
			    return;
			}

			// We got response, so there is already a master tab
			window.clearTimeout(mastershipRetrieval);

			// Set the sessionStorage
			sessionStorage.setItem(storage.name, event.newValue);

			// Don't listen with this func anymore ..
			window.removeEventListener('storage', onGotFavorites);
		    }
		}

		window.addEventListener('storage', onGotFavorites);

		// Wait 1500 ms for response, then suppose this it's first tab
		var mastershipRetrieval = window.setTimeout(function() {

		    // Stop waiting for data ..
		    window.removeEventListener('storage', onGotFavorites);
		    
		    // Create empty array
		    sessionStorage.setItem(storage.name, '[]');
		    
		    becomeMaster(true);

		}, 1500);

		// Ask other tabs for favorites ..
		broadcast('giveMeFavorites', tabId);
	    }

	    // Listen for master changes in order if this tab was chosen ..
	    window.addEventListener('storage', function(event) {
		
		if (veryVerbose)
		    $log.debug('Recieved an broadcast: ', event);

		// New master ? .. this tab ?
		if (event.key === 'favoritesMasterTab' && ( parseInt(event.newValue) === tabId || event.newValue === 'rand' )) {
		    // yes !

		    if (veryVerbose)
			$log.debug('Recieved an order to become a master .. yeah!', event);
		    
		    becomeMaster();

		} else if (event.key === 'favAdded' && event.newValue) { // Favorite added
		    
		    // Parse it
		    var favObj = JSON.parse(event.newValue);

		    // Create the Favorite class from it
		    var newFav = new Favorite().fromObject(favObj);

		    // Add it to the storage
		    storage.addFavorite(newFav);
		    
		    // Tell the controllers ..
		    if (typeof window.__isFavCallback === 'function') {
			
			if (veryVerbose)
			    $log.debug('Calling window.__isFavCallback with ', newFav);
			
			window.__isFavCallback(true, newFav);
		    }

		} else if (event.key === 'favRemoved' && event.newValue) { // Favorite removed

		    // Parse it
		    var favObj = JSON.parse(event.newValue);

		    // Create the Favorite class from it
		    var oldFav = new Favorite().fromObject(favObj);

		    // Remove it from the storage
		    storage.removeFavorite(oldFav.created());

		    // Tell the controllers ..
		    if (typeof window.__isFavCallback === 'function') {
			
			if (veryVerbose)
			    $log.debug('Calling window.__isFavCallback with ', oldFav);
			
			window.__isFavCallback(false, oldFav);
		    }
		}
	    });

	    var lastKnownTabId = undefined;

	    /**
	     * Becoming master tab
	     * 
	     * @param boolean
	     *                actively
	     * 
	     * Determines if this tab is becoming master tab actively or
	     * passively (if it was told it to do so, or it decied itself by
	     * reaching the timeout when no master returns response on init
	     * request)
	     */
	    function becomeMaster(active) {

		if (typeof active !== 'undefined') {
		    
		    if (veryVerbose)
			$log.debug('Actively becoming mastertab!');

		    localStorage.setItem('favoritesMasterTab', tabId);
		}

		/**
		 * Give up mastership on tab close
		 */
		window.onbeforeunload = function() {
		    giveUpMastership();
		}

		/**
		 * Create event listener to play master role
		 */
		window.addEventListener('storage', masterJob);

		/**
		 * Giving up the mastership
		 */
		function giveUpMastership() {

		    window.removeEventListener('storage', masterJob);

		    var newMaster = lastKnownTabId ? lastKnownTabId : 'rand';

		    // Create persistent info
		    localStorage.setItem('favoritesMastertab', newMaster);
		}

		/**
		 * Playing master role
		 */
		function masterJob(event) {
		    if (event.key == 'giveMeFavorites' && event.newValue) {
			// Some tab asked for the sessionStorage -> send it

			lastKnownTabId = event.newValue;

			broadcast(event.newValue, sessionStorage.getItem(storage.name));
		    }
		}
	    }
	} // init func end
    }
})();