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
apps="backend.notify \
backend.stagnation \
backend.wx \
module.customer \
module.data \
module.equity \
module.insure \
module.mbx \
module.product \
module.project \
module.rule \
module.stagnation \
module.user \
module.wx"

# 4. path
cd ..
path="$(pwd)"
tagPath=~/Documents/phars/${tag}
mkdir -p ${tagPath}

# 5. loop

#for name in ${apps}; do
#    cd "${path}/${name}" && \
#    echo "${name} : ${path}" && \
#    echo "    remove " && rm -rf *.phar && rm -rf vendor/uniondrug/phar &>/dev/null && \
#    echo "    composer update " && ln -s ~/SourceCodes/cn.uniondrug/com.github/phar vendor/uniondrug/phar &>/dev/null && \
#    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
#    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
#done

for name in ${apps}; do
    cd "${path}/${name}" && \
    echo "${name} : ${path}" && \
    echo "    remove " && rm -rf *.phar &>/dev/null && \
    echo "    composer update " && rm -rf vendor/uniondrug/phar && ln -s ~/SourceCodes/cn.uniondrug/com.github/phar vendor/uniondrug/phar &>/dev/null && \
    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
done
