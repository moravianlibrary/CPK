/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('ListController', ListController)
    	.directive('favoritesListItem', favoritesListDirective)
    	.directive('listNotEmpty', listNotEmptyDirective);

    ListController.$inject = [ '$q', '$http', '$log', '$scope', 'storage', 'favsBroadcaster', 'Favorite' ];
    
    // helper private variables
    var divsAsFavs = {};
    var listEmptyDiv = undefined;
    var listNotEmptyDiv = undefined;

    function ListController($q, $http, $log, $scope, storage, favsBroadcaster, Favorite) {
	
	var activeSorting = 'recent';
	
	var modal = $('#modal'); // FIXME don't grab it by ID, it's slow

	var vm = this;

	vm.favSelected = {};
	vm.favorites = [];

	vm.sortVal = sortVal;
	
	vm.allSelected = false;

	vm.listStart = 1;
	vm.listLength = 1;

	vm.selectAll = selectAll;

	vm.removeFavorite = removeFavorite;
	
	vm.removeSelected = removeSelected;
	vm.emailSelected = emailSelected;
	vm.exportSelected = exportSelected;
	vm.printSelected = printSelected;
	
	$q.resolve(storage.getFavorites()).then(onGetFavorites).catch(function(reason) {
	    
	    $log.error(reason);
	});
	
	/**
	 * Public function about to be called from the favsBroadcaster when an
	 * event happens (meaning adding / removal of the favorite)
	 */
	window.__favChanged = function(isNew, favorite) {
	    
	    if (favorite instanceof Favorite)
		
		if (isNew === true) { // being added ..
		    
		    addFavorite(favorite);
		
		} else if (isNew === false) { // being deleted ..
		    
		    // We need to refresh the view with async job .. use Promise
		    new Promise(function(resolve, reject) {
			var id = favorite.created();

			var el = divsAsFavs[id];
			    
			if (window.usesIE) {
			    el.parentElement.removeChild(el);
			} else
			    el.remove();
			
			delete divsAsFavs[id];
			
			--vm.listLength;
			
			if (Object.keys(divsAsFavs).length === 0)
			    changeVisibleDiv();
			    
			// Delete it from the vm.favorites list ..
			vm.favorites = vm.favorites.filter(function(fav) {
			    return fav.created() !== favorite.created();
			});
			
			resolve();
			
		    }).then($scope.$applyAsync);
		}
	}; 
	
	return; //
	
	function onGetFavorites(favs) {
	    
	    // Default sorting is by most recent, so flip the order ..
	    favs = favs.reverse();
	    
	    vm.favorites = favs;
	    
	    var length = favs.length;

	    if (length) {
		changeVisibleDiv();
			
		vm.listLength = length;			
	    }
	}
	
	function removeFavorite(id) {
	    
	    // We need to refresh the view with async job .. use Promise
	    new Promise(function(resolve, reject) {
	    
		storage.removeFavorite(id).then(function() {
		
		    var el = divsAsFavs[id];
		    
		    if (window.usesIE) {
			el.parentElement.removeChild(el);
		    } else
			el.remove();
		    
		    delete divsAsFavs[id];
		
		    --vm.listLength;
		
		    if (Object.keys(divsAsFavs).length === 0)
			changeVisibleDiv();
		    
		    var idAsInt = parseInt(id);
		    
		    // Delete it from the vm.favorites list ..
		    vm.favorites = vm.favorites.filter(function(favorite) {
			
			if (favorite.created() !== idAsInt)
			    return true;
			
			
			// And also broadcast it's removal
			favsBroadcaster.broadcastRemoved(favorite);
			
			return false;
		    });
		
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
	
	function exportSelected() {
	    
	    Lightbox.titleSet = false;
	    
	    useLightboxWithSelected('export');
	}
	
	function emailSelected(event) {
	    
	    modal.find('.modal-title').html(event.target.value);
	    Lightbox.titleSet = true;
	    
	    useLightboxWithSelected('email');
	}
	
	/**
	 * Redirects user to /Records/Home action if selected something
	 */
	function printSelected() {
	    
	    var selectedIds = getSelectedIds();
	    
	    if (selectedIds.length === 0)
		return;
	    
	    var printLocation = '/Records/Home?print=1';
	    
	    selectedIds.forEach(function(selectedId){
		printLocation += '&id[]=Solr|' + selectedId;
	    });
	    
	    // Open in new tab
	    window.open(printLocation, '_blank').focus();
	}
	
	function selectAll() {
	    vm.favorites.forEach(function(favorite) {
		vm.favSelected[favorite.created()] = vm.allSelected;
	    });
	}
	
	/**
	 * Returns current sorting if no argument supplied.
	 * 
	 * Else sets the sorting provided & updates the view.
	 */
	function sortVal(val) {
	    return arguments.length ? setSorting(val) : getSorting(); 
	}
	
	// Private
	
	function addFavorite(favorite) {
	    
	    // We need to refresh the view with async job .. use Promise
	    new Promise(function(resolve, reject) {
		
		if (! favorite instanceof Favorite) {
		    reject();
		    return;
		}
		
		vm.favorites.push(favorite);
		
		// Apply current sorting
		setSorting(getSorting());
		
		if (vm.favorites.length === 1) {
		    changeVisibleDiv();
		    
		    vm.listLength = 1;
		}
		
		resolve();
		
	    }).then($scope.$applyAsync);
	}
	
	function changeVisibleDiv() {
	    listEmptyDiv.hidden = ! listEmptyDiv.hidden;
	    listNotEmptyDiv.hidden = ! listNotEmptyDiv.hidden;
	}
	
	function getSelectedIds() {

	    var selectedIds = [];
	    
	    Object.keys(vm.favSelected).forEach(function(key) {
		if (vm.favSelected[key] === true) {

		    var id = getFavoriteId(key);
		    
		    selectedIds.push(id);
		}
	    });
	    
	    return selectedIds;
	}
	
	function useLightboxWithSelected(type) {
	    
	    var selectedIds = getSelectedIds();
	    
	    if (selectedIds.length === 0)
		return;
	    
	    // Append "Solr|" string to all the ids selected
	    for (var i = 0; i < selectedIds.length; ++i) {
		selectedIds[i] = 'Solr|' + selectedIds[i];
	    }	   

	    var data = {
		ids : selectedIds,
	    };
	    
	    data[type] = true;

	    var options = {
		headers : {
		    'Content-Type' : 'application/x-www-form-urlencoded'
		}
	    };
	    
	    function setModalContent(response) {
		Lightbox.changeContent(response.data);
	    }

	    $http.post('/AJAX/JSON?method=getLightbox&submodule=Cart&subaction=MyResearchBulk', $.param(data), options).then(setModalContent);

	    modal.modal('show');
	}
	
	function setSorting(val) {
	    
	    // We need to refresh the view with async job .. use Promise
	    new Promise(function(resolve, reject) {
		
		var validSorting = true;
		
		switch(val) {
		
		case 'alphabetical':
		    
		    vm.favorites.sort(function(a, b) {
			return a.title() > b.title();
		    });
		    break;
		    
		case 'author':
		    
		    vm.favorites.sort(function(a, b) {
			return a.author() > b.author();
		    });
		    break;
		    
		case 'recent':
		    
		    vm.favorites.sort(function(a, b) {
			return a.created() < b.created();
		    });
		    break;
		    
		default:
		    validSorting = false;
		    $log.error('Invalid sorting provided');
		}
		
		if (validSorting)
		    activeSorting = val;
		
	    }).then($scope.$applyAsync);
	    
	}
	
	function getSorting() {
	    return activeSorting;
	}
	
	/**
	 * Returns Solr ID of favorite identified by timestamp created
	 * 
	 * @param key
	 */
	function getFavoriteId(key) {
	    var fav = vm.favorites.find(function(favorite) {
		return favorite.created() === parseInt(key);
	    });
	    
	    if (typeof fav === 'undefined')
		return;
	    
	    return fav.titleLink().replace('/Record/', '');
	}
    }
    
    function favoritesListDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/bootstrap3/js/ng-cpk/favorites/list-item.html',
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
