/**
 * Storage service for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    angular.module('favorites').factory('storage', storageService);

    storageService.$inject = [ '$log', 'Favorite' ];

    function storageService($log, Favorite) {

	var storage = {
	    addFavorite : addFavorite,
	    deleteFavorite : deleteFavorite,
	    getFavorite : getFavorite,
	    getFavorites : getFavorites,
	    name : '_fav'
	};

	var favorites = [];
	
	var initialize = {
		done : false,
		buffer : []
	};

	initialize();

	return storage;

	// Public methods

	function addFavorite(favorite) {
	    return new Promise(function(resolve, reject) {

		if (favorite instanceof Favorite) {
		    
		    saveFavorite(favorite).then(resolve).catch(reject);
		    
		} else {
		    reject('storage.addFavorite(Favorite favorite) - instanceof Favorite must be provided !');
		}
	    });
	}

	function deleteFavorite(id) {
	    return new Promise(function(resolve, reject) {
		
		if (typeof id === "number" && id >= 0) {
		    
		    if (typeof favorites[id] === "undefined") {
			reject('Favorite does not exist with this ID');
		    } else {
			
			// Remove that Favorite
			favorites.splice(id, 1);
			
			// Save those Favorites
			saveFavorites().then(resolve).catch(reject);
		    };
		} else {
		    reject('Wrong id provided to delete an Favorite from sessionStorage');
		}
	    });
	}
	
	function getFavorite(id) {
	    return new Promise(function(resolve, reject) {
		
		if (typeof id === "number" && id >= 0) {
		    
		    var theJob = function() {
			resolve(favorite(id));
		    };

		    executeAfterInitialized(theJob);
		    
		} else {
		    reject('Cannoct get favorite not having an index');
		};
	    });
	}

	function getFavorites() {
	    return new Promise(function(resolve, reject) {
		
		var theJob = function() {
		    resolve(favorites);
		};
		
		executeAfterInitialized(theJob);
	    });
	}

	// Private methods

	function saveFavorite(favorite) {
	    return new Promise(function(resolve, reject) {
		
		    favorites.push(favorite);
		    
		    saveFavorites().then(resolve).catch(reject);
	    });
	}
	
	function saveFavorites() {
	    return new Promise(function(resolve, reject) {
		
		var theJob = function() {
		    localforage.setItem(storage.name, favorites).then(resolve).catch(reject);
		};
		
		executeAfterInitialized(theJob);
	    });
	}
	
	function executeAfterInitialized(func) {
	    if (typeof func === "function")
		if (initialize.done) {
		    func.call();
		
		} else {
		    // Execute after an initialization
	    	    initialize.buffer.push(func);
		}
	}

	function initialize() {

	    localforage.getSessionItem(storage.name).then(function(val) {
		
		if (val)
		    favorites = val;
		
		initialize.done = true;
		
		initialize.buffer.forEach(function(func) {
		    func.call();
		});
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	};

    };
})();