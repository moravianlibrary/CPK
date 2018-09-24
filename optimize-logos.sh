#!/usr/bin/env bash

function print_usage {
    cat <<EOF

USAGE: optimize-logos.sh [--all|-a] [--help|-h]

  --all|-a          Optimize all files, do not skip already optimized files
  --help|-h         Print usage

EOF
}

optimize_all="false"
ext='_small.png'

while true ; do
    case "$1" in
         --all|-a)
            optimize_all="true"
            shift
            ;;
         --help|-h)
            print_usage;
            exit 0;;
        *) break ;;
    esac
done

cd themes/bootstrap3/images/institutions/logos/
# loop through all folders with logos, convert and resize it
for dir in */; do
  cd ${dir}
  for file in *; do
    if [[ ${file} != *_*.png ]]; then
      file_name=${file%%.*}
      if [[ ${optimize_all} == 'true' || (${optimize_all} == 'false' && ! -f ./${file_name}${ext}) ]]; then
        convert ${file} -strip -resize 80x60 ${file_name}${ext}
      fi
    fi
  done
  cd ..
done
