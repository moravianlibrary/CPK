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

    /**
     * Need to have very verbose console output while debugging?
     * 
     * @boolean veryVerbose
     */
    var veryVerbose = false;

    angular.module('favorites').factory('favsBroadcaster', favsBroadcaster);

    favsBroadcaster.$inject = [ '$log', 'storage', 'Favorite', 'favsNotifications' ];

    function favsBroadcaster($log, storage, Favorite, favsNotifications) {

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
	 * ID of current tab to identify requests & responses
	 */
	var tabId;

	/**
	 * ID of last tab which placed a request
	 */
	var lastKnownTabId;

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

	    if (isNewTab()) {

		handleNewTab();
	    } else {

		handleOldTab();
	    }

	    // Listen for master changes in order if this tab was chosen ..
	    window.addEventListener('storage', handleStorageEvent);

	    sendFavsIfNeccessarry();
	}

	function handleStorageEvent(event) {

	    if (veryVerbose)
		$log.debug('Recieved an broadcast: ', event);

	    // New masterTab ?
	    if (event.key === 'favoritesMasterTab') {
		// yes !

		// Should this tab be masterTab ?
		if (parseInt(event.newValue) === tabId || event.newValue === 'rand')
		    becomeMaster();

	    } else if (event.key === 'favAdded' && event.newValue) {

		handleFavAdded(event);

	    } else if (event.key === 'favRemoved' && event.newValue) {

		handleFavRemoved(event);
	    } else if (event.key === 'purgeAllTabs' && event.newValue) {

		storage.removeAllFavorites();
	    }
	}

	function handleFavAdded(event) {

	    // Parse it
	    var favObj = JSON.parse(event.newValue);

	    // Create the Favorite class from it
	    var newFav = new Favorite().fromObject(favObj);

	    // Add it to the storage
	    storage.addFavorite(newFav);

	    // Tell the controllers ..
	    if (typeof window.__favChanged === 'function') {

		if (veryVerbose)
		    $log.debug('Calling window.__favChanged with ', newFav);

		window.__favChanged(true, newFav);
	    }
	}

	function handleFavRemoved(event) {

	    // Parse it
	    var favObj = JSON.parse(event.newValue);

	    // Create the Favorite class from it
	    var oldFav = new Favorite().fromObject(favObj);

	    // Remove it from the storage
	    storage.removeFavorite(oldFav.created());

	    // Tell the controllers ..
	    if (typeof window.__favChanged === 'function') {

		if (veryVerbose)
		    $log.debug('Calling window.__favChanged with ', oldFav);

		window.__favChanged(false, oldFav);
	    }
	}

	/**
	 * Returns boolean whether is this tab a new tab or not.
	 */
	function isNewTab() {
	    return typeof sessionStorage.tabId === 'undefined';
	}

	/**
	 * Handles the logic right after we found out this is brand new tab
	 * without any useful sessionStorage data.
	 * 
	 * It basically prompts another tabs for existing favorites & stores
	 * them inside sessionStorage.
	 */
	function handleNewTab() {

	    // Generate tabId ..
	    tabId = Date.now();

	    // Store it within the sessionStorage
	    sessionStorage.tabId = tabId;

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

		    // Let the controller know ..
		    if (typeof window.__favChanged === 'function') {

			var favs = JSON.parse(event.newValue);

			var favorites = favs.map(function(fav) {
			    return new Favorite().fromObject(fav);
			});

			favorites.forEach(function(favorite) {
			    window.__favChanged(true, favorite);
			});

			if (favs.length) {
			    favsNotifications.favAdded();
			}
		    }

		    // Don't listen with this func anymore ..
		    window.removeEventListener('storage', onGotFavorites);
		}
	    }

	    window.addEventListener('storage', onGotFavorites);

	    // Wait 1500 ms for response, then suppose this is the first tab
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

	/**
	 * Handles the logic right after we found out this is an old tab.
	 * 
	 * It basically prompts reassigns important persistent variables.
	 */
	function handleOldTab() {

	    // Assign the tabId the first tabId generated by this tab
	    tabId = sessionStorage.tabId;

	    var masterTabId = localStorage.favoritesMasterTab;

	    if (masterTabId === tabId || masterTabId === 'rand') {

		becomeMaster(true);

	    } else if (veryVerbose)
		$log.debug('Being a slaveTab is nice!');

	}

	/**
	 * Becoming master tab
	 * 
	 * @param boolean
	 *                actively
	 * 
	 * Determines if this tab is becoming master tab actively or passively
	 * (if it was told it to do so, or it decied itself by reaching the
	 * timeout when no master returns response on init request)
	 */
	function becomeMaster(active) {

	    if (veryVerbose) {
		(typeof active === 'undefined') ? $log.debug('Passively becoming masterTab!') : $log.debug('Actively becoming masterTab!');
	    }

	    // Overwrite it to inform other tabs about becoming masterTab..
	    localStorage.setItem('favoritesMasterTab', tabId);

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
		localStorage.setItem('favoritesMasterTab', newMaster);
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

	/**
	 * If VuFind detected user have just logged in, it'll create
	 * 'sendMeFavs' function returning true.
	 * 
	 * The point is to send sessionStorage's contents into the PHP app so
	 * that it stores the data in more persistent way.
	 * 
	 * The broadcaster also broadcasts an event to clear favorites stored
	 * within all tabs inside sessionStorage to prevent them being present
	 * after logout.
	 */
	function sendFavsIfNeccessarry() {
	    if (typeof sendMeFavs === 'function' && sendMeFavs() === true) {

		storage.getFavorites().then(function(favs) {

		    // Do not send empty favorites ..
		    if (favs.length === 0) {
			return;
		    }

		    var data = {
			favs : favs.map(function(fav) {
			    return fav.toObject();
			})
		    };

		    if (veryVerbose)
			$log.debug('Pushing favorites on prompt ..', data);

		    $.post('/AJAX/JSON?method=pushFavorites', data).always(function() {
			
			// Broadcast all tabs removal
			purgeAllTabs();
			
			// Reload the page to see newly created favorites list
			location.reload();
			
			// TODO do not reload whole page, but only the menu with angular ..
		    });
		    		    
		});
	    }
	}

	/**
	 * Sends an broadcast to purge all favorites
	 */
	function purgeAllTabs() {

	    broadcast('purgeAllTabs');

	    storage.removeAllFavorites();
	}
    }
})();