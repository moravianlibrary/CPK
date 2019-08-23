#!/bin/bash
# looks for translation strings in PHP, PHTML and JS files and marks unused ones in language.ini file
# example ./checkTermUsage.sh inputFile.ini ../projectRootDir [outputFile.txt]
# creates two more files for propper user examination and mistake correction
# terms_found.txt with results of grep command
# terms_refused.txt with all terms not found
# outputFile.txt (variable name), containing all terms with line comment on those not found in any file
# CHECK THE RESULTS MANUALLY

# $1 is input file
[ -z "$1" -o ! -f "$1" ] && echo "$1 není soubor" && exit 1;
# $2 is base directory path
[ -z "$2" -o ! -d "$2" ] && echo "$2 musí být adresář" && exit 1;
# output file name. If empty, no file is created, only output is in STDOUT
outfile=''
[ ! -z "$3" ] && outfile="$3"
# process terms line by line, remove comment lines
cat $1 | while IFS= read -r line; do
  output="${line}"
  # preserve comments and blank lines
  if [ ! -z "${line}" -a ! "${line:0:1}" = ";" ]; then
      # get term from the line content
      term=`printf '%s' "$line" | cut -d'=' -f1`
      # trim whitespaces
      term="$(sed -e 's/[[:space:]]*$//' <<<${term})"
      # if term contains one of prefixes, don't look for it in source codes
      if [[ ! "$term" =~ ^status_.*|^availability_.*|^sigla_.*|^doctypes_.* ]]; then
          # find term usage if nothing found, mark as suspicious with ;
          found=`egrep -r --include=\*.{php,phtml,js} --exclude-dir={languages,local} "[\"']${term}[\"']" "${2}" -C 3`
          if [ ! -z "${found}" ]; then
            # log accepted terms grep output for propper user examination
            printf '%s:\n%s\n' "$term" "$found" >> terms_found.txt
          else
            output=";unused: ${line}"
            # log removed terms
            printf '%s\n' "$term" >> terms_refused.txt
          fi;
      fi;
  fi;
  # print visible output
  printf '%s\n' "$output"
  # print result at the end of file, if filename was set
  [ ! -z "$outfile" ] && printf '%s\n' "$output" >> $outfile
done;
