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
apps="backend.stagnation \
backend.wx \
module.customer \
module.data \
module.equity \
module.insure \
module.product \
module.project \
module.rule \
module.user \
module.wx \
module.stagnation"

# 4. path
cd ..
path="$(pwd)"
tagPath=~/Documents/phars/${tag}
mkdir -p ${tagPath}

# 5. loop
for name in ${apps}; do
    cd "${path}/${name}" && \
    echo "${name} : ${path}" && \
    echo "    remove " && rm -rf *.phar &>/dev/null && \
    echo "    git checkout ${branch}" && git checkout ${branch} &>/dev/null && \
    echo "    git pull " && git pull &>/dev/null && \
    echo "    composer update " && composer update &>/dev/null && \
    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
done
