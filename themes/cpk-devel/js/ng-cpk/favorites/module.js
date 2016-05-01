/**
 * 
 * Basic concept:
 * 
 * This angularJS based app is written in order to let the not logged in user
 * have access to favorites. To be capable of creating them, deleting them &
 * sharing them.
 * 
 * What do we need to achieve this goal and what problems are we facing in this
 * implementation?
 * 
 * First we need to realize that some not logged in user should be able to save,
 * delete & share all his favorites. If we have stored his favorites on server,
 * we would have to deal with the problem of "When we should delete the stored
 * favorites ?" By a cron job? That's not the way appaerently .. So that's why
 * we are going to use user's browser's local storage. That's why we have to use
 * javascript to achieve this goal. It also seems to be an "ecological" solution
 * by preventing some amount of data to flow through the internet.
 * 
 * Also, it's an complex task to complete, so I've decided to use AngularJS'
 * MVC.
 * 
 * 1) Privacy:
 * 
 * Not logged user should retain his privacy so that's why need to delete all
 * the favorites as soon, as the browser closes. This can be done using
 * sessionStorage.
 * 
 * 2) Transferring favorites from not logged in user to logged in user:
 * 
 * After PHP detects user has just logged in, it will create a global JS function
 * "sendMeFavs" returning true. The broadcaster service will detect that & will
 * send all the user favorites to the PHP
 * 
 * 3) Creating favorites
 * 
 * This is solved by:
 * 	I.   Parse the record being added
 * 	II.  Convert the parsed data to a Favorite class
 * 	III. Store the Favorite class instance of the record into the
 * 		sessionStorage
 * 
 * 4) Deleting favorites
 * 
 * Simply purge the Favorite from the sessionStorage, identified by timestamp.
 * 
 * 5) Notifying user about having favorites & not being logged in yet
 * 
 * User should know the risks of having his favorites within an "offline"
 * account, I mean to let him know about the fact that he could lose those
 * favorites very easily, so he should log in in order to retain his favorites
 * on the server, not the browser.
 * 
 * Note that the Favorites app is being written with respect to Angular Style
 * Guide you can look at here:
 * https://github.com/johnpapa/angular-styleguide#angular-style-guide
 * 
 * 6) Print, export or email favorites
 * 
 * There are used VuFind built-in JS functions to reach this goal 
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('favorites', []);
})();