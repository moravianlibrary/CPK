#!/bin/bash

if [ ! $(which curl) ]; then
	echo "You have not installed curl, please perform an installation using:"
	echo "sudo apt-get install curl"
	exit 1
fi

dest="$(dirname $0)/localforage-bundle.min.js"

localforage_url="https://github.com/mozilla/localForage/raw/master/dist/localforage.min.js"

echo "Downloading latest localforage.min.js .."
curl -Ls "$localforage_url" > "$dest"

#localforage_sessionstoragewrapper="https://github.com/thgreasi/localForage-sessionStorageWrapper/raw/master/src/localforage-sessionstoragewrapper.js"
localforage_customizations="localforage-customizations.js"

echo "Requesting google closure compiler to concatenate & compress & compile latest sessionstorage driver & our customizations together .."
#curl -Ls --data-urlencode "code_url=${localforage_sessionstoragewrapper}" --data-urlencode js_code@${localforage_customizations} -d output_info=compiled_code https://closure-compiler.appspot.com/compile >> "$dest"
curl -Ls --data-urlencode js_code@${localforage_customizations} -d output_info=compiled_code https://closure-compiler.appspot.com/compile >> "$dest"

if [ "$(grep "ERROR:" "$dest")" ]; then
	echo " ERROR !"
	echo "An error occured ! Please see the file \"$dest\""
else
	echo "All done!"
fi
