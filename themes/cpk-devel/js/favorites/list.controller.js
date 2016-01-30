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

	vm.paginationStart = 0;
	vm.paginationLength = 0;

	vm.listLength = 0;

	vm.editList = editList;
	vm.deleteList = deleteList;
	
	vm.editModeActive = false;
	
	vm.favorites = [];

	$q.resolve(storage.getFavorites()).then(function(favs) {
	    
	    vm.favorites = favs;
	    
	}).catch(function(reason) {
	    
	    $log.error(reason);
	});
	
	// //////////////////////////

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