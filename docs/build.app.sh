#!/bin/sh

# cd ~/SourceCode/uniondrug-2018/backend.wx
# /bin/sh vendor/uniondrug/phar/docs/build.sh

# 1. tag name
tag=${1}
if [ -z ${tag} ]; then
    tag=$(date '+%y%m%d')
fi

# 2. branch name
branch=${2}
if [ -z ${branch} ]; then
    branch="master"
fi



# 3. applications
# backend.app app.backend console \
apps="uniondrug uniondrug pails \
tm-service service pails \
tm-appbackend appbackend pails"

# 4. path
cd ..
path="$(pwd)"
tagPath=~/Documents/phars/${tag}
mkdir -p ${tagPath}

# 5. loop

i=0
n=3
x=0

_cmd=""
_phar=""
_folder=""

for name in ${apps}; do
    let 'i++,x=i%n'
    if [ 1 -eq ${x} ]; then
        _folder="${name}"
    elif [ 2 -eq ${x} ]; then
        _phar="${name}"
    elif [ 0 -eq ${x} ]; then
        _cmd="${name}"
        cd ${path}/${_folder} && \
        rm -rf *.phar && \
        php ${_cmd} phar --tag=${tag} --env=production && \
        rscp *.phar
    fi
done


#for name in ${apps}; do
#    cd "${path}/${name}" && \
#    echo "${name} : ${path}" && \
#    echo "    remove " && rm -rf *.phar && rm -rf vendor/uniondrug/phar &>/dev/null && \
#    echo "    composer update " && ln -s ~/SourceCodes/cn.uniondrug/com.github/phar vendor/uniondrug/phar &>/dev/null && \
#    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
#    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
#done

#for name in ${apps}; do
#    cd "${path}/${name}" && \
#    echo "${name} : ${path}" && \
#    echo "    remove " && rm -rf *.phar &>/dev/null && \
#    echo "    composer update " && rm -rf vendor/uniondrug/phar && ln -s ~/SourceCodes/cn.uniondrug/com.github/phar vendor/uniondrug/phar &>/dev/null && \
#    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
#    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
#done
