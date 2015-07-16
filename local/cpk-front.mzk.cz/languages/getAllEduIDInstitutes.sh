#/bin/sh
curl https://metadata.eduid.cz/entities/eduid+idp | grep -E "Scope|DisplayName xml:lang=\"cs\"" | cut -d ">" -f 2 | awk -F"<" '{print $1 }' | awk '!a[$0]++' | grep -vE "v.v.i.|v. v. i." | sed 's_\(.*[.]cz\)_source\_\1 = _g' | sed 'N;s/=\(.*\)\n/= /g'

