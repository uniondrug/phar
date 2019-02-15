# Application

> 基于PHAR构建项目的目录结构

```text
/ data/apps/name-tag.phar
  ├─ app
  │  ├─ Commands
  │  ├─ Controllers
  │  ├─ Errors
  │  ├─ Logics
  │  ├─ Models
  │  ├─ Providers
  │  ├─ Servers
  │  │  ├─ Crons
  │  │  │  └─ ExampleCron.php
  │  │  ├─ Processes
  │  │  │  └─ ExampleProcess.php
  │  │  ├─ Tables
  │  │  │  └─ ExampleTable.php
  │  │  ├─ Tasks
  │  │  │  └─ ExampleTask.php
  │  │  └─ Http.php
  │  ├─ Services
  │  └─ Structs
  ├─ config
  └─ vendor
     └─ uniondrug
        └─ phar
           └─ src
              ├─ exec
              └─ server.php

```
