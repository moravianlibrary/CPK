(function() {
    angular.module('history').controller('CheckedOutHistoryController', CheckedOutHistoryController).directive('ngHistoryUsername', ngHistoryUsernameDirective);
    
    CheckedOutHistoryController.$inject = [ '$q', '$log', '$http' ];
    
    var onElementLinked = function() {};
    
    function CheckedOutHistoryController($q, $log, $http) {
	var vm = this;
	
	window.ngLoadMore = loadMore;
	
	onElementLinked = function(domElement, username) {
	    
	    // Execute non-blocking Q
	    $q.resolve(getMyHistory(username)).then(onGotMyHistory).catch(function(err) {
		$log.error(err);
	    });
	    
	    function onGotMyHistory(history) {
		
		var parent = domElement.parentElement;
		
		domElement.remove();
		
		var div = document.createElement('div');
		
		parent.appendChild(div);
		
		div.outerHTML = history;
	    }
	}
	
	return vm;
	
	function loadMore($event) {
	    var target = $event.target;
	    
	    // Unhide the loader
	    var loader = target.nextElementSibling;
	    
	    target.remove();	    
	    loader.removeAttribute('hidden');
	    
	    onElementLinked(loader /* (loader , username) ???? - přepsat vypisování html do template a naplňovat ji - vznikne taky ušetření loadu*/);
	}
	
	function getMyHistory(username) {
	    return new Promise(function(resolve, reject) {
		
		var data = {
			cat_username : username
		};
			
		var options = {
			headers: {
			    'Content-Type': 'application/x-www-form-urlencoded'
			}
		};
		    
		$http.post('/AJAX/JSON?method=getMyHistory', $.param(data), options).then(function(response) {
		    response = response.data;
		    
		    if (typeof response.php_errors !== 'undefined') {
			$log.error(response.php_errors);
		    }
		    
		    if (response.status === 'OK') {
			resolve(response.data.html);
		    } else {
			reject(response.data);
		    }
		});
	    });
	}
    }    
    
    function ngHistoryUsernameDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    onElementLinked(elements.context, attrs.ngHistoryUsername);
	}
    }
})();