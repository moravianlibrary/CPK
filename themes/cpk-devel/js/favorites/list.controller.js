/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('ListController', ListController).directive('favoritesListItem', favoritesListDirective);

    ListController.$inject = [ '$q', '$log', 'storage' ];

    function ListController($q, $log, storage) {

	var vm = this;
	
	var maxPaginationLength = 10;

	vm.paginationStart = 0;
	vm.paginationLength = 0;

	vm.listLength = 0;
	
	vm.canSort = canSort;

	vm.editList = editList;
	vm.deleteList = deleteList;
	
	vm.editModeActive = false;
	
	vm.favorites = [];
	vm.listEmpty = true;

	$q.resolve(storage.getFavorites()).then(onGetFavorites).catch(function(reason) {
	    
	    $log.error(reason);
	});
	
	// //////////////////////////
	
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
	
	function listEmpty() {
	    return 
	}

	function editList(id) {
	    alert("editing list " + id)
	}

	function deleteList(id) {
	    alert("deleting list " + id)
	}
    }
    
    function favoritesListDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/favorites/list-item.html'
	};
    }
    
})();