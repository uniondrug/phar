#!/bin/sh


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


for name in ${apps}; do
    path="/data/apps/${name}"
    if [[ -d ${path} ]]; then
        echo "${path}" && cd ${path} && \
        echo "    force kill" && php server stop --force-kill &>/dev/null && \
        echo "    sync consul kv" && php server kv --consul udsdk.uniondrug.net &>/dev/null && \
        echo "    remote logs" && rm -rf log/2019-02/*.log && echo '' > log/server.log &>/dev/null && \
        echo "    start server" && php server start -e release --log-level=DEBUG --consul-register 172.16.0.67:8500 -d &>/dev/null
    fi
done

ps aux | grep 'master' | grep 'r\.'


