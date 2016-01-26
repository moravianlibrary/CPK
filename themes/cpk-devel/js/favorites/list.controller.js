/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('ListController', ListController);

    ListController.$inject = [ '$log', 'storage', 'FavoritesFactory' ];

    function ListController($log, storage, FavoritesFactory) {

	var vm = this;

	vm.paginationStart = 0;
	vm.paginationLength = 0;

	vm.listId = undefined;
	vm.listDescription = undefined;
	vm.listTitle = "Your Favorites";	
	vm.listLength = 0;

	vm.editList = editList;
	vm.deleteList = deleteList;
	vm.submitBulk = submitBulk;
	
	vm.deleteFavorite = storage.deleteFavorite;
	
	////////////////////////////

	function editList(id) {
	    alert("editing list " + id)
	}

	function deleteList(id) {
	    alert("deleting list " + id)
	}

	function submitBulk() {
	    
	}
	
    }
})();