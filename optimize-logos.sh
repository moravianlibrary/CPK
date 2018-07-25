#!/usr/bin/env bash

ext='_small.png'

cd themes/bootstrap3/images/institutions/logos/
# loop through all folders with logos, convert and resize it
for dir in */; do
  cd ${dir}
  for file in *; do
    if  [[ ${file} != *_*.png ]]; then
      ids=${file%%.*}
      convert ${file} -strip -resize 80x60 ${ids}${ext}
    fi
  done
  cd ..
done
