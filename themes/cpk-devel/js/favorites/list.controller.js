/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
var hack;
(function() {

    angular.module('favorites').controller('ListController', ListController)
    	.directive('favoritesListItem', favoritesListDirective)
    	.directive('listNotEmpty', listNotEmptyDirective);

    ListController.$inject = [ '$q', '$log', '$scope', 'storage' ];
    
    // helper private variables
    var divsAsFavs = {};
    var listEmptyDiv = undefined;    
    var listNotEmptyDiv = undefined;

    function ListController($q, $log, $scope, storage) {

	var vm = this;
	
	hack = this;
	vm.scope = $scope;

	vm.favSelected = {};
	vm.favorites = [];

	vm.allSelected = false;

	vm.listStart = 1;
	vm.listLength = 1;

	vm.selectAll = selectAll;
	
	vm.canSort = canSort;
	vm.removeFavorite = removeFavorite;
	
	vm.removeSelected = removeSelected;
	
	$q.resolve(storage.getFavorites()).then(onGetFavorites).catch(function(reason) {
	    
	    $log.error(reason);
	});
	
	return vm;
	
	//
	
	function onGetFavorites(favs) {
	    vm.favorites = favs;
	    
	    var length = favs.length;

	    if (length) {
		changeVisibleDiv();
			
		vm.listLength = length;			
	    }
	}
	
	function canSort(type) {
	    return true; // TODO ..
	}
	
	function removeFavorite(id) {
	    
	    // We need to refresh the view with async job .. use Promise
	    new Promise(function(resolve, reject) {
	    
		storage.removeFavorite(id).then(function() {
		
		    divsAsFavs[id].remove();
		    delete divsAsFavs[id];
		
		    --vm.listLength;
		
		    if (Object.keys(divsAsFavs).length === 0)
			changeVisibleDiv();
		
		}).catch(function(reason) {
		
		    $log.error(reason);
		});
		
		resolve();
	    
		// Then refresh the scope
	    }).then($scope.$applyAsync);
	}
	
	function removeSelected() {
	    Object.keys(vm.favSelected).forEach(function(key) {
		if (vm.favSelected[key] === true) {

		    removeFavorite(parseInt(key));

		    delete vm.favSelected[key];
		}
	    });
	}
	
	function selectAll() {
	    vm.favorites.forEach(function(favorite) {
		vm.favSelected[favorite.created()] = vm.allSelected;
	    });
	}
	
	function changeVisibleDiv() {
	    listEmptyDiv.hidden = ! listEmptyDiv.hidden;
	    listNotEmptyDiv.hidden = ! listNotEmptyDiv.hidden;
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
    
    function listNotEmptyDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    if (attrs.listNotEmpty === "true") {
		
		listNotEmptyDiv = elements.context;
		listNotEmptyDiv.hidden = true;
	    } else if (attrs.listNotEmpty === "false") {
		
		listEmptyDiv = elements.context;
		listEmptyDiv.hidden = false;
	    }
	}
    }
    
})();