#!/bin/sh


echo "---- ---- ---- ---- build php archive package ---- ---- ---- ----"


# applications definer
# col.1 directory name
# col.2 branch name
# col.3 package name
# col.4 is uniondrug or not
apps="\
    tm-service phar tm-service yes \
    tm-appbackend phar tm-appbackend yes \
    uniondrug phar tm-uniondrug yes \
"

for s in ${apps}; do

done

