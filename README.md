# PHP-SynFTP

实现本地项目修改与FTP项目同步

## 使用方法

#### 建立项目配置文件

在 `~/projects` 目录下建立项目所属的配置文件，文件名格式：`pm.php`，其中 `pm` 表示项目标识，该标识将用于运行时的参数。

`pm.php` 文件代码格式如下：

```
<?php
// 测试
// Updated: 2016-07-10 23:43:34
$cfg = array(
	'root_dir' => '/Users/michael/www1/test/demo',
	'ftp' => array(
		'host' 		=> '10.11.12.13',
		'username' 	=> 'www',
		'password' 	=> 'pwd',
		'root_dir' 	=> '/test/demo'
	),
	'ignored' => array(
		'.DS_Store',
		'.gitignore',
		'.project',
		'.buildpath',

		'/.git',
		'/.settings',
	),
);
return $cfg;

```
其中，第一行为项目描述，第二行表示项目更新时间基准，格式应严格按照

```// Updated: YYYY-MM-DD HH:ii:ss```

填写，**每次运行更新后更新的时间会记录至此**。

配置项 `$cfg` 的各属性分别表示：

| 属性 | 描述 |
| ------------- | ------------- |
| root_dir | 本地项目根目录，绝对路径，不以“/”结束 |
| ftp | ftp连接配置信息，其中 `root_dir` 表示FTP上项目的根目录，需要与本地目录root_dir对应 |
| ignored | 项目忽略文件 |

至此，配置完毕。

#### 运行同步

 - CLI

`$ index.php pm [-t]`

#### 参数说明

`t` 测试模式，只检查需要上传更新的文件，不做FTP上传