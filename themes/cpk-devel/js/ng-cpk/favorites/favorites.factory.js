/**
 * Storage service for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    angular.module('favorites').factory('favoritesFactory', favoritesFactory);

    favoritesFactory.$inject = [ '$log', 'Favorite' ];

    function favoritesFactory($log, Favorite) {

	var factory = this;

	factory.create = function() {
	    return new Favorite();
	};

	factory.createFromCurrentRecord = function() {
	    return factory.create().parseCurrentRecord();
	};

	return factory;
    }

})();