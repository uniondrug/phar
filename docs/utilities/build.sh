#!/bin/sh

#
# 入参
# url: git@e.coding.net:uniondrug/module.project.git
# branch: master
#

url="${1}"
branch="master"
source="/tmp"
target="/data/apps"

url="git@e.coding.net:uniondrug/module.project.git"

for name in "url branch source target"; do

    #if [ ! -n $name ]; then
        echo $name" ... "
    #fi

done


echo $url | awk -F '/' '{print $0}'

