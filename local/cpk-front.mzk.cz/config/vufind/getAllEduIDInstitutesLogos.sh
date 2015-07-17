#/bin/sh
curl https://metadata.eduid.cz/entities/eduid+idp | grep -E "Logo |Scope" | cut -d">" -f2 | cut -d"<" -f1 | sed -r "s_\s+__g" | sed "N;s_\([.]cz\)\([\n\s]*\)http_\1 = http_g" | sed "N;s_\([.]cz\)\([\n\s]*\)http_\1 = http_g" | sed "N;s_\([.]cz\)\([\n\s]*\)http_\1 = http_g" | grep "="

