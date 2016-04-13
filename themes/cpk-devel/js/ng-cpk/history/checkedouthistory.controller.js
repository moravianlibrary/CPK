(function() {
    angular.module('history').controller('CheckedOutHistoryController', CheckedOutHistoryController).directive('ngHistoryUsername', ngHistoryUsernameDirective)
    	.directive('ngHistoryItem', ngHistoryItemDirective).directive('ngPagination', ngPaginationDirective);
    
    CheckedOutHistoryController.$inject = [ '$q', '$log', '$http', '$scope' ];
    
    var onElementLinked = function() {};    
    
    function CheckedOutHistoryController($q, $log, $http, $scope) {

	// Private
	var username = undefined;
	var loaderDiv = undefined;
	    
	var currentPage = 1;
	
	var pagesCache = [];
	
	onElementLinked = onHistoryUsernameDirectiveLinked;
	
	// Public
	var vm = this;
	
	vm.historyPage = [];
	vm.pageSelected = pageSelected;
	
	return vm;
	// Public
	
	function pageSelected(page) {
	    
	    // Show loader
	    loaderDiv.removeAttribute('hidden');
	    
	    // Clear the page
	    vm.historyPage = [];
	    
	    currentPage = page;
	    
	    updatePaginatorActivePage();
	    
	    $q.resolve(getMyHistoryPage()).then(onGotMyHistoryPage).catch(function(err) {
		$log.error(err);
	    });
	    
	    function onGotMyHistoryPage(result) {
		
		var historyPage = result.historyPage;
		
		// We need to refresh the view with async job .. use Promise
		new Promise(function(resolve) {
		    
		    loaderDiv.setAttribute('hidden', 'hidden');
			
		    vm.historyPage = historyPage;
		    
		    return resolve();
		    
		}).then($scope.$applyAsync);
	    }
	}	
	
	// Private
	
	/**
	 * Method for fetching the history page specified by currentPage
	 * variable
	 */
	function getMyHistoryPage() {
	    return new Promise(function(resolve, reject) {
		
		var cacheIndex = currentPage - 1;
		
		// Search cache for existing page
		if (typeof pagesCache[cacheIndex] !== 'undefined') {
		    return resolve(pagesCache[cacheIndex]);
		}
		
		var data = {
			cat_username : username,
			page : currentPage
		};
			
		var options = {
			headers: {
			    'Content-Type': 'application/x-www-form-urlencoded'
			}
		};
		    
		$http.post('/AJAX/JSON?method=getMyHistoryPage', $.param(data), options).then(onGotResponse);
		
		function onGotResponse(response) {
		    response = response.data;
		    
		    if (typeof response.php_errors !== 'undefined') {
			$log.error(response.php_errors);
		    }
		    
		    if (response.status === 'OK') {
			
			// Cache the answer ..
			pagesCache[cacheIndex] = response.data;
			
			return resolve(response.data);
		    } else {
			return reject(response.data);
		    }
		};
	    });
	}
	
	/**
	 * This function initializes paginator for history by setting the
	 * totalPages to the paginator object within a $scope & sets the active
	 * page
	 */
	function initializePaginator() {
	    var visiblePagesList = [];
	    var pagesList = [];
	    var totalPages = pagesCache.length;
	    
	    // Initialize pagesList
	    for (var i = 0; i < totalPages;) {
		pagesList[i++] = {
		    number : i,
		    clazz : (i === 1) ? 'active' : ''
		};
		
		if (i <= 5) {
		    visiblePagesList[i - 1] = pagesList[i - 1];
		}
	    }
	    
	    // Now create the paginator object within the scope
	    $scope.paginator = {
		lastPage : totalPages,
		visiblePages : visiblePagesList,
		pages : pagesList,
		activePage : 1,
		showFirst : false,
		showLast : totalPages > 5,
		goToPage : pageSelected
	    }
	}
	
	/**
	 * Updates paginator active page
	 */
	function updatePaginatorActivePage() {
	    
	    new Promise(function(resolve) {
		
		var lastActivePage = $scope.paginator.activePage;
		
		var newPages = $scope.paginator.pages;
		
		// Update active element
		newPages[lastActivePage - 1].clazz = '';
		newPages[currentPage - 1].clazz = 'active';
		
		var lastPage = $scope.paginator.lastPage;
		
		// Determine whether to show the short to first or last page
		var willShowFirst = currentPage > 3;
		var willShowLast = currentPage < ( lastPage - 2 );
		
		var newVisiblePages = [];
		
		// Update the regular visible pages
		var maxPage = currentPage + 2;

		var tolerance = 3;
		
		if (maxPage > lastPage) {
		    tolerance = maxPage - lastPage + tolerance;
		    maxPage = lastPage;
		}
		
		for (var i = currentPage - tolerance, j = 0; i < maxPage; ++j, ++i) {
		    if (i < 0) {
			maxPage = maxPage - i;
			
			if (maxPage > lastPage)
			    maxPage = lastPage;
			
			i = 0;
		    }
		    newVisiblePages[j] = newPages[i];
		}

		$scope.paginator = {
			lastPage : lastPage,
			visiblePages : newVisiblePages,
			pages : newPages,
			activePage : currentPage,
			showFirst : willShowFirst,
			showLast : willShowLast,
			goToPage : pageSelected
		}

		resolve();
	    }).then($scope.$applyAsync);
	}
	
	/**
	 * Handles the call of an improvized "onload" event when angular links
	 * the loader div with the username in it
	 */
	function onHistoryUsernameDirectiveLinked(domElement, parsedUsername) {
	    
	    loaderDiv = domElement;
	    
	    // Store the username value
	    username = parsedUsername;
	    
	    // Execute non-blocking Q
	    $q.resolve(getMyHistoryPage()).then(onGotMyHistoryPage).catch(function(err) {
		$log.error(err);
	    });
	    
	    function onGotMyHistoryPage(result) {
		
		var historyPage = result.historyPage;
		var totalPages = result.totalPages;
		
		// Initialize the cache length
		pagesCache = new Array(totalPages);
		
		/*
		 * Cache this result as it was removed from the cache in
		 * previous commnand
		 */
		pagesCache[0] = result;
		
		initializePaginator();
		
		// We need to refresh the view with async job .. use Promise
		new Promise(function(resolve) {
		    var parent = loaderDiv.parentElement;
			
		    loaderDiv.setAttribute('hidden', 'hidden');
			
		    vm.historyPage = historyPage;
		    
		    resolve();
		    
		}).then($scope.$applyAsync);
	    }
	};
    }
    
    /**
     * Directive for creating an improvized "onload" event when angular links
     * the loader div with the username in it to call the onElementLinked
     * function
     */
    function ngHistoryUsernameDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    onElementLinked(elements.context, attrs.ngHistoryUsername);
	}
    }
    
    /**
     * Directive for filling a list of items within a history page with provided
     * template.
     */
    function ngHistoryItemDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/ng-cpk/history/history-item.html'
	};
    }
    
    /**
     * Directive for importing the paginator
     */
    function ngPaginationDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/ng-cpk/pagination.html'
	};
    }
})();