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

	return storage;

	// Public methods

	function addFavorite(favorite) {
	    return new Promise(function(resolve, reject) {

		if (favorite instanceof Favorite) {

		    resolve();
		} else {
		    $log.error('storage.addFavorite(Favorite favorite) - instanceof Favorite must be provided !');
		    reject();
		}
	    });
	}

	function deleteFavorite(id) {
	    $log('Favorite with id ' + id + ' removed !');
	}

	function getFavorite() {

	}

	function getFavorites() {

	}

	// Private methods

	function saveObject(obj) {
	    if (typeof obj === "object") {
		localforage.setDriver(localforage.SESSIONSTORAGE);

		localforage.getItem(storage.name).then(save(obj)); // ?? ... TODO

		localforage.setLastDriver(); // See
						// js/vendor/localforage-customizations.js

		function save(obj) {
		    return function(favorites) {
			if (typeof favorites === "object") {
			    if (typeof favorites.totalCount === "number") {
				++favorites.totalCount;
			    } else {
				favorites.totalCount = 1;
			    }
			}
		    }
		}
	    }
	}

    }
})();