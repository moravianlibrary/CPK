#!/usr/bin/env bash

# loop through all folders with logos, convert and resize it
cd themes/bootstrap3/images/institutions/logos/
for dir in */; do
  cd ${dir}
  for file in *; do
    if  [[ ${file} != *_*.png ]]; then
      ids=${file%%.*}
      ext='_small.png'
      convert ${file} -strip -resize 80x60 ${ids}${ext}
    fi
  done
  cd ..
done