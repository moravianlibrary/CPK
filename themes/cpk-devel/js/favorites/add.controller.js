/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('AddController', AddController);

    AddController.$inject = [ '$log', 'storage', 'favoritesFactory' ];

    function AddController($log, storage, favoritesFactory) {

	var vm = this;

	vm.addFavorite = addFavorite;
	
	////////////////////////////

	function addFavorite() {
	    
	    var favorite = favoritesFactory.createFromCurrentRecord();
	    
	    storage.addFavorite(favorite).then(function() {
		$log.info('Promise fullfilled! :)');
	    });
	}

    }
})();