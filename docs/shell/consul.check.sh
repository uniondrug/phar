#!/bin/bash

#
# Consul健康检查脚本
# date: 2019-05-14
# arguments: command arguments
#            $1 项目所在的绝对路径, 如: /data/apps/module.wx
#            $2 服务鉴听的端口号, 如: 8123
#            $3 项目所在的机器IP, 如: 172.16.0.67
#            $4 项目鉴听IP地址, 如: 127.0.0.1
#

logger="/tmp/consul.check.$(date '+%Y-%m-%d').log"
status=$(curl -isS "http://${3}:${2}/consul.health" | grep HTTP | awk '{print $2}')

# successful
if [ "${status}" == "200" ]; then
    echo "[$(date '+%H:%M:%S')][INFO][${3}:${2}] running - ${1}" >> ${logger}
    exit 0
fi

# restart application
# 1. login the server
# 2. restart application
echo "[$(date '+%H:%M:%S')][ERROR][${3}:${2}] mistake - ${1}" >> ${logger}
