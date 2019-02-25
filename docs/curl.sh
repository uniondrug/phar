#!/bin/sh

path=/data/apps

apps="customer \
data \
equity \
insure \
product \
project \
stagnation \
user \
wx"


for name in ${apps}; do
    cd "${path}/module.${name}" && \
    echo "[${name}] : ${path}/module.${name}"
    echo "    -> sync kv" && php server kv --consul udsdk.uniondrug.net &>/dev/null && \
    echo "    -> force kill" && php server stop --force-kill &>/dev/null && \
    echo "    -> clear logs" && rm -rf log/*/*.log &>/dev/null && \
    echo "    -> starting server" && php server start -e release -d --consul-register 172.16.0.67:8500 &>/dev/null && \
    echo "    -> completed"
done


#php server kv --consul udsdk.uniondrug.net && php server stop --force-kill && rm -rf log/2019* && php server start -e release -d --consul-register 172.16.0.67:8500

# display
for name in $(ls /data/apps); do tree /data/apps/${name} | grep '/data/phar'; done
for name in $(ls /data/apps); do tree /data/apps/log/2019; done

# release-02
rm -rf /data/apps/backend.notify/server && ln -s /data/phar/notify.backend-190222.phar /data/apps/backend.notify/server

# release-03
rm -rf /data/apps/backend.stagnation/server && ln -s /data/phar/stagnation.backend-190222.phar /data/apps/backend.stagnation/server && \
rm -rf /data/apps/backend.wx/server && ln -s /data/phar/wx.backend-190222.phar /data/apps/backend.wx/server

# release-04
rm -rf /data/apps/module.rule/server && ln -s /data/phar/rule.module-190222.phar /data/apps/module.rule/server

# release-05
rm -rf /data/apps/module.customer/server && ln -s /data/phar/customer.module-190222.phar /data/apps/module.customer/server && \
rm -rf /data/apps/module.data/server && ln -s /data/phar/data.module-190222.phar /data/apps/module.data/server && \
rm -rf /data/apps/module.equity/server && ln -s /data/phar/equity.module-190222.phar /data/apps/module.equity/server && \
rm -rf /data/apps/module.insure/server && ln -s /data/phar/insure.module-190222.phar /data/apps/module.insure/server && \
rm -rf /data/apps/module.product/server && ln -s /data/phar/product.module-190222.phar /data/apps/module.product/server && \
rm -rf /data/apps/module.project/server && ln -s /data/phar/project.module-190222.phar /data/apps/module.project/server && \
rm -rf /data/apps/module.stagnation/server && ln -s /data/phar/stagnation.module-190222.phar /data/apps/module.stagnation/server && \
rm -rf /data/apps/module.user/server && ln -s /data/phar/user.module-190222.phar /data/apps/module.user/server && \
rm -rf /data/apps/module.wx/server && ln -s /data/phar/wx.module-190222.phar /data/apps/module.wx/server

# restart
for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "restart ${name}" && cd /data/apps/${name} && \
            php server kv --consul udsdk.uniondrug.net &>/dev/null && \
            php server stop --force-kill &>/dev/null && \
            php server start -e release --log-level=DEBUG --consul-register 172.16.0.67:8500 -d &>/dev/null && \
            tree /data/apps/${name};
    fi \
done


# delete log
for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "remove logs ${name}" && cd /data/apps/${name} && \
            \rm -rf /data/apps/${name}/log/2019-02/*.log && \
            echo '' > /data/apps/${name}/log/server.log && \
            tree /data/apps/${name}/log;
    fi \
done


# show log
for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "show logs ${name}" && cd /data/apps/${name} && \
            ll -h /data/apps/${name}/log/2019-02;
    fi \
done


# show server log
for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "show logs ${name}" && \
            echo '' > /data/apps/${name}/log/server.log;
    fi \
done



for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "build log directory ${name}" && \
            ll /data/apps/${name}/tmp; \
    fi \
done

# make dir
for name in $(ls /data/apps); do \
    count=$(ll /data/apps/${name} | grep '/data/phar/' | grep server | wc -l); \
    if [ ${count} -eq 1 ]; then \
        echo "build log directory ${name}" && \
            mkdir -p /data/apps/${name}/log/2019-02; \
    fi \
done




# consul health
curl -s http://172.16.0.67:8086/consul.health | awk -F ',' '{print "8086-customer.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8087/consul.health | awk -F ',' '{print "8087-project.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8088/consul.health | awk -F ',' '{print "8088-product.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8089/consul.health | awk -F ',' '{print "8089-user.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8090/consul.health | awk -F ',' '{print "8090-equity.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8093/consul.health | awk -F ',' '{print "8093-data.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.66:8095/consul.health | awk -F ',' '{print "8095-rule.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8118/consul.health | awk -F ',' '{print "8118-insure.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8119/consul.health | awk -F ',' '{print "8119-wx.module{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.65:8120/consul.health | awk -F ',' '{print "8120-stagnation.backend{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.64:8210/consul.health | awk -F ',' '{print "8210-notify.backend{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.65:8213/consul.health | awk -F ',' '{print "8213-wx.backend{"$5", "$8", "$9", "$10", "$11", "$12}' && \
curl -s http://172.16.0.67:8215/consul.health | awk -F ',' '{print "8215-stagnation.module{"$5", "$8", "$9", "$10", "$11", "$12}'

# table health
echo '{' && \
curl -s http://172.16.0.67:8086/table.health && \
curl -s http://172.16.0.67:8087/table.health && \
curl -s http://172.16.0.67:8088/table.health && \
curl -s http://172.16.0.67:8089/table.health && \
curl -s http://172.16.0.67:8090/table.health && \
curl -s http://172.16.0.67:8093/table.health && \
curl -s http://172.16.0.66:8095/table.health && \
curl -s http://172.16.0.67:8118/table.health && \
curl -s http://172.16.0.67:8119/table.health && \
curl -s http://172.16.0.65:8120/table.health && \
curl -s http://172.16.0.64:8210/table.health && \
curl -s http://172.16.0.65:8213/table.health && \
curl -s http://172.16.0.67:8215/table.health &&
echo '}'

