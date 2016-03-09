#!/bin/bash

deps="

favorites/module.js
favorites/translate.filter.js
favorites/favsNotifications.service.js
favorites/favorite.class.js
favorites/favorites.factory.js
favorites/storage.service.js
favorites/broadcaster.service.js
favorites/list.controller.js
favorites/record.controller.js

federative-login/module.js
federative-login/login.controller.js

notifications/module.js
notifications/notif.controller.js

cpk.ng-app.js
"

if [ ! $(which curl) ]; then
	echo "You have not installed curl, please perform an installation using:"
	echo "sudo apt-get install curl"
	exit 1
fi

args=""
for i in $deps; do
	args+=" --data-urlencode js_code@${i}"
done

dest="ng-apps.min.js"

echo "Requesting google closure compiler to concatenate & compress & compile all the ng-apps together .."
curl -Ls $args -d output_info=compiled_code https://closure-compiler.appspot.com/compile > "$dest"

if [ "$(grep "ERROR:" "$dest")" ]; then
	echo " ERROR !"
	echo "An error occured ! Please see the file \"$dest\""
else
	echo "All done!"
fi

