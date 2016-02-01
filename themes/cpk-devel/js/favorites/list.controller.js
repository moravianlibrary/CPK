/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('ListController', ListController).directive('favoritesListItem', favoritesListDirective);

    ListController.$inject = [ '$q', '$log', 'storage' ];
    
    var divsAsFavs = {};

    function ListController($q, $log, storage) {

	var maxPaginationLength = 10;
	
	var vm = this;
	
	vm.favsSelected = {};
	vm.favorites = [];

	vm.paginationStart = 0;
	vm.paginationLength = 0;
	vm.listLength = 0;
	vm.listEmpty = true;
	
	vm.canSort = canSort;
	vm.removeFavorite = removeFavorite;
	
	vm.deleteList = deleteList;

	$q.resolve(storage.getFavorites()).then(onGetFavorites).catch(function(reason) {
	    
	    $log.error(reason);
	});
	
	return vm;
	
	//
	
	function onGetFavorites(favs) {
	    vm.favorites = favs;
	    
	    var length = favs.length;

	    if (length) {
		vm.listEmpty = false;
			
		vm.listLength = length;
			
		vm.paginationStart = 1;
		vm.paginationLength = maxPaginationLength < length ? maxPaginationLength : length;
			
	    }
	}
	
	function canSort(type) {
	    return true; // TODO ..
	}
	
	function removeFavorite(id) {
	    
	    storage.removeFavorite(id).then(function() {
		
		divsAsFavs[id].remove();
		delete divsAsFavs[id];
		
		if (Object.keys(divsAsFavs).length === 0)
		    vm.listEmpty = true;
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	}
	
	function deleteList() {
	    alert('deleting all favorites !');
	}
    }
    
    function favoritesListDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/favorites/list-item.html',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    // Assing the divs to an object with fav ID
	    divsAsFavs[scope.fav.created()] = elements.context;
	}
    }
    
})();