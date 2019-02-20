# ES

> ES Response Manager

```json
{
  "query": {
    "bool": {
      "must": [
        {
          "term": {
            "requestId.keyword": "r1902191910115c6be4133372a250373"
          }
        }
      ],
      "must_not": [],
      "should": []
    }
  },
  "from": 0,
  "size": 100,
  "sort": {
    "time.keyword": {
      "order": "desc"
    }
  },
  "aggs": {}
}
```


```javascript
var res = function(root, prev){
    var tmp = {
        f: 0.0, 
        l: 0.0
    };
    var formatter = function(log){
        var t = log._source.time,
        d = new Date(t), 
        m = t.match(/\.(\d+)$/), 
        diffTime = 0.0;
        if (m.length == 2){
            tmp.f = parseFloat(parseInt(d.getTime()/1000) + '.' + m[1]);
            if (tmp.l > 0.0){
                diffTime = parseInt(1000000 * (tmp.f - tmp.l))/1000;
            }
            tmp.l = tmp.f;
        }
        var serverAddr = log._source.serverAddr;
        if (serverAddr === ""){
            serverAddr = "  "
        }
        var requestId = log._source.requestId;
        if (requestId === ""){
            requestId = "  "
        }
        var duration = parseInt(log._source.duration * 1000000)/1000;
        if (duration <= 0.0){
            duration = " ";
        }
        var moduleName = log._source.module;
        if (log._source.requestUrl !== ""){
            if (moduleName !== ''){
               moduleName += ":/"
            }
            moduleName += log._source.requestUrl;
        }
        if (moduleName === ""){
            moduleName = "  "
        }
        if (diffTime <= 0.0){
            diffTime = " "
        }
        return {
            "上报机器": serverAddr, 
            "请求标识": requestId, 
            "工作项目": moduleName, 
            "记时": duration, 
            "记录时间": log._source.time, 
            "时差": diffTime, 
            "级别": log._source.level, 
            "日志描述" : log._source.content
        };
    };
    for (var i = 0; i < root.hits.hits.length; i++){
        root.hits.hits[i] = formatter(root.hits.hits[i]);
    }
    return root;
}
```
