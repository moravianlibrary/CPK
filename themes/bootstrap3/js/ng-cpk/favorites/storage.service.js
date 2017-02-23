/**
 * Storage service for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    
    if (! (sessionStorage && ('setItem' in sessionStorage))) {
        console.error('This browser does not support sessionStorage, no favorites for not logged user can be provided!');
        return;
    }
    
    angular.module('favorites').factory('storage', storageService);

    storageService.$inject = [ '$log', 'Favorite', 'favsNotifications' ];

    function storageService($log, Favorite, favsNotifications) {

	var storage = {
	    addFavorite : addFavorite,
	    removeFavorite : removeFavorite,
	    removeAllFavorites : removeAllFavorites,
	    hasFavorite : hasFavorite,
	    getFavorite : getFavorite,
	    getFavorites : getFavorites,
	    name : '_favs'
	};

	var favorites = [];
	
	var initializer = {
		done : false,
		buffer : []
	};

	init();

	return storage;

	// Public methods

	function addFavorite(favorite) {
	    return new Promise(function(resolve, reject) {

		if (favorite instanceof Favorite) {

		    var theJob = function() {
			saveFavorite(favorite).then(resolve).catch(reject);
		    }
		    
		    call(theJob);
		    
		    favsNotifications.favAdded();

		} else {
		    reject('storage.addFavorite(favorite) needs favorite instanceof Favorite !');
		}
	    });
	}

	function removeFavorite(id) {
	    return new Promise(function(resolve, reject) {
		
		if (typeof id !== "number") {
		    reject('Invalid ID provided');
		    return;
		}

		var theJob = function() {
		    
		    var count = favorites.length, tmp = [], removed = false;
		    
		    // Let's slice out those whose title link doesn't match
		    for (var i = 0; i < count; ++i) {
			if (favorites[i].created === id) {
			    removed = true;
			} else {
			    tmp.push(favorites[i]);
			}
		    }
		    
		    if (removed === false) {
			reject('Invalid ID provided');
			return;
		    }
		    
		    favorites = tmp;
			
		    // Save those Favorites
		    saveFavorites().then(resolve).catch(reject);
		    
		    if (favorites.length === 0) {
			favsNotifications.allFavsRemoved();
		    }
		}

		call(theJob);

	    });
	}
	
	function removeAllFavorites() {
	    
	    return new Promise(function(resolve, reject) {

		    favorites = [];
		    
		    favsNotifications.allFavsRemoved();
		    
		    saveFavorites().then(resolve).catch(reject);
	    });
	}
	
	function hasFavorite(recordId) {
	    return new Promise(function(resolve, reject) {
		
		var theJob = function() {
		    
		    var regexp = new RegExp("\/" + recordId.replace(/\./,"\\."));
		    
		    if (!favorites) {
			reject();
			return;
		    }
		    
		    var found = favorites.find(function(fav) {
			
			return !!fav.title.link.match(regexp);
		    });
		    
		    if (typeof found === "undefined") {
			reject();
		    } else {
			resolve(new Favorite().fromObject(found));
		    }
		};
		
		call(theJob);
	    });
	}
	
	function getFavorite(id) {
	    return new Promise(function(resolve, reject) {
		
		if (typeof id === "number" && id >= 0) {
		    
		    var theJob = function() {
			
			var favObj = favorite[id];
			
			// It is basically added by this ..
			favsNotifications.favAdded();
			
			resolve(new Favorite().fromObject(favObj));
		    };

		    call(theJob);
		    
		} else {
		    reject('Cannot get favorite without an index');
		}
	    });
	}

	function getFavorites() {
	    return new Promise(function(resolve, reject) {
		
		var theJob = function() {
		    
		    resolve(favorites.map(function(fav) {
			
			// It is basically added by this ..
			favsNotifications.favAdded();
			
			return new Favorite().fromObject(fav);
		    }));
		};
		
		call(theJob);
	    });
	}

	// Private methods

	function saveFavorite(favorite) {
	    return new Promise(function(resolve, reject) {

		favorites.push(favorite.toObject());

		saveFavorites().then(resolve).catch(reject);
	    });
	}

	function saveFavorites() {
	    return new Promise(function(resolve, reject) {

		var theJob = function() {
		    
		    var retVal = undefined;
		    
		    // Delete the object from memory if empty
		    if (favorites.length)
			retVal = sessionStorage.setItem(storage.name, JSON.stringify(favorites));
		    else
			sessionStorage.removeItem(storage.name);

		    resolve(retVal);
		};

		// Create an async call
		setTimeout(theJob, 0);
	    });
	}
	
	function call(func) {
	    if (typeof func === "function")
		if (initializer.done) {
		    func.call();

		} else {
		    // Execute after an initialization
	    	    initializer.buffer.push(func);
		}	    
	}

	/**
	 * Retrieve the favorites from sessionStorage. Then call all functions
	 * within a buffer (it is possible they were called)
	 */
	function init() {
	    
	    var theJob = function() {
		
		var favs = sessionStorage.getItem(storage.name);

		if (favs !== null)
		    favorites = JSON.parse(favs);
		
		initializer.done = true;
		
		initializer.buffer.forEach(function(func) {
		    func.call();
		});

	    }
	    
	    // Don't wait for it to finish ..
	    setTimeout(theJob, 0);
	};
    };
})();