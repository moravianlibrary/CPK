(function() {
    angular.module('history').controller('CheckedOutHistoryController', CheckedOutHistoryController).directive('ngHistoryUsername', ngHistoryUsernameDirective)
    	.directive('ngHistoryItem', ngHistoryItemDirective).directive('ngPagination', ngPaginationDirective).directive('ngPagesCount', ngPagesCountDirective)
    	.filter('urlencode', function() {
    		return function(input) {
    			return encodeURI(input);
			}
		});
    
    CheckedOutHistoryController.$inject = [ '$q', '$log', '$http', '$scope' ];
    
    var onHistoryUsernameLinked = function() {};
    
    var pagesCountDOM = {};
    
    function CheckedOutHistoryController($q, $log, $http, $scope) {

	// Private
	var username = undefined;
	var loaderDiv = undefined;
	    
	var currentPage = 1;
	
	var pagesCache = [];
	
	onHistoryUsernameLinked = onHistoryUsernameDirectiveLinked;
	
	// Public
	var vm = this;
	
	vm.historyPage = [];
	vm.pageSelected = pageSelected;
	
	vm.perPage = '10';
	vm.perPageUpdated = perPageUpdated;
	
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
		    
		}).then($scope.$applyAsync).then(downloadCovers(result['obalky']));
	    }
	}
	
	/**
	 * Is called when an perPage limit is chosen
	 */
	function perPageUpdated() {
	    
	    // Behave like we just reloaded the page
	    new Promise(function(resolve) {

		// Hide paginator
		$scope.paginator.lastPage = 1;
		
		// Reset currentPage
		currentPage = 1;
		
		// Hide previous results
		vm.historyPage = [];
		
		// Show loader
		loaderDiv.removeAttribute('hidden');
		
		// Apply the view before prompting for new data
		resolve();
	    }).then($scope.$applyAsync);
		
	    // Clear the cache
	    pagesCache = [];
	    
	    onHistoryUsernameDirectiveLinked(loaderDiv, username);
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
			page : currentPage,
			perPage : vm.perPage
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
			
			if (typeof response.data.html !== 'undefined' && typeof response.data.source !== 'undefined') {
			    $('div#' + response.data.source).html(response.data.html);
			    return;
            }

			return resolve(response.data);
		    } else {
			return reject(response.data);
		    }
		};
	    });
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
		loaderDiv.innerHTML = '<span class="label label-danger">' + err.message + '</span>';
	    });
	    
	    function onGotMyHistoryPage(result) {
		
		var historyPage = result.historyPage;
		var totalPages = result.totalPages;
		
		if (totalPages > 0)
		    pagesCountDOM[username].className = pagesCountDOM[username].className.replace('hidden','');
		
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
			
		    loaderDiv.setAttribute('hidden', 'hidden');
			
		    vm.historyPage = historyPage;
		    
		    resolve();
		    
		}).then($scope.$applyAsync).then(downloadCovers(result['obalky']));
	    }
	}
	
	function downloadCovers(covers) {
	    if (typeof covers !== 'undefined') {
		for ( var id in covers) {
		    if (covers.hasOwnProperty(id)) {
			var cover = covers[id];

			obalky.fetchImage(id, cover.bibInfo, cover.advert, 'icon');
		    }
		}
	    }
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
		    
		    var showThisPage = true;
		    
		    if (i < 0) {
			maxPage = maxPage - i;
			
			if (maxPage > lastPage)
			    maxPage = lastPage;
			
			i = 0;
		    }
		    
		    var showThisPage = !((willShowLast && i === (lastPage - 1)) || (willShowFirst && i === 0));
		    
		    if (showThisPage)
			newVisiblePages[j] = newPages[i];
		    else
			--j;
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
	
	function scrollToLoader() {
	    
	}
    }
    
    /**
     * Directive for creating an improvized "onload" event when angular links
     * the loader div with the username in it to call the
     * onHistoryUsernameLinked function
     */
    function ngHistoryUsernameDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    onHistoryUsernameLinked(elements.context, attrs.ngHistoryUsername);
	}
    }
    
    /**
     * Directive for filling a list of items within a history page with provided
     * template.
     */
    function ngHistoryItemDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/bootstrap3/js/ng-cpk/history/history-item.html'
	};
    }
    
    /**
     * Directive for importing the paginator
     */
    function ngPaginationDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/bootstrap3/js/ng-cpk/pagination.html',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    var source = attrs['ngPagination'];
	    scope.source = source;
	}
    }
    
    function ngPagesCountDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    pagesCountDOM[attrs['ngPagesCount']] = elements.context;
	}
    }
})();
