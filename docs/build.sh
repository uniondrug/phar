#!/bin/sh

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

cd ..
path="$(pwd)"
tag=$(date '+%y%m%d')
tagPath=~/Documents/phars/${tag}
mkdir -p ${tagPath}

for name in ${apps}; do
    cd "${path}/${name}" && \
    echo "${name} : ${path}" && \
    echo "    remove " && rm -rf *.phar &>/dev/null && \
    echo "    git pull " && git pull &>/dev/null && \
    echo "    composer update " && composer update &>/dev/null && \
    echo "    building... " && php console phar -e production --tag=${tag} &>/dev/null && \
    echo "    completed " && mv *.phar ${tagPath} &>/dev/null
done
