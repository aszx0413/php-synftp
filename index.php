<?php

date_default_timezone_set('Etc/GMT-8');
// error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
error_reporting(E_ALL);

if ($argc < 2) {
    die('Usage: php index.php <PROJECT_NAME>'."\n");
} elseif (!file_exists(dirname(__FILE__).'/projects/'.$argv[1].'.php')) {
    die('☹️  项目 '.$argv[1]." 不存在！\n");
}
$project = include_once 'projects/'.$argv[1].'.php';
$cfgFileContent = file_get_contents(dirname(__FILE__).'/projects/'.$argv[1].'.php');
$tmp = preg_match('/\/\/\s{1}Updated:\s{1}[0-9\-:\s]{19}/', $cfgFileContent, $result);
if (!$tmp) {
    die('项目 '.$argv[1]." 更新时间未设置！\n");
}
$lastUpdatedTs = str_ireplace('// Updated: ', '', $result[0]);

$newCfgFileContent = str_ireplace($lastUpdatedTs, date('Y-m-d H:i:s'), $cfgFileContent);

$doFtp = true;

/*
 * 如果带参数-t，
 * 则表示只列出当前需要更新的文件列表，
 * 不进行FTP上传
 */
if (isset($argv[2])) {
    if ($argv[2] == '-t') {
        $doFtp = false;
    }
}

//
// 需要忽略的文件
// 如果不带“/”则表示根目录下的所有该文件均被忽略
$ignored = $project['ignored'];

define('ROOT_DIR', $project['root_dir']);
define('FTP_ROOT_DIR', $project['ftp']['root_dir']);

$lastUpdatedTs = strtotime($lastUpdatedTs); // 项目上次提交时间戳

$ftpFiles = []; //需要FTP更新的文件

function tree($directory)
{
    global $ftpFiles, $lastUpdatedTs, $ignored;
    $files = [
        'type' => 'd',
        'name' => basename($directory),
        'ls'   => [],
    ];
    $mydir = dir($directory);
    while ($file = $mydir->read()) {
        if ($file != '.' && $file != '..') {
            if (is_dir("$directory/$file")) {
                if (!in_array("/$file", $ignored) && !in_array(str_replace(ROOT_DIR, '', $directory)."/$file", $ignored)) {
                    $files['ls'][] = tree("$directory/$file");
                }
            } else {
                if (!in_array($file, $ignored) && !in_array(str_replace(ROOT_DIR, '', $directory)."/$file", $ignored)) {
                    $ft = filemtime("$directory/$file");
                    if ($ft < filectime("$directory/$file")) {
                        $ft = filectime("$directory/$file");
                    }
                    $f = ['path' => str_replace(ROOT_DIR, '', $directory), 'name' => $file, 'mtime' => $ft];
                    if ($ft > $lastUpdatedTs) {
                        $ftpFiles[] = $f;
                    }
                    $files['ls'][] = $f;
                }
            }
        }
    }
    $mydir->close();

    return $files;
}

/**
 * 创建 FTP 服务器端的目录
 */
function ftpMkdirs($ftp, $dirs)
{
    if (@ftp_chdir($ftp, $dirs)) {
        return true;
    }

    $dirArr = explode('/', $dirs);
    $fullDir = '';
    foreach ($dirArr as $dir) {
        $fullDir .= '/' . $dir;
        if (!@ftp_chdir($ftp, $fullDir)) {
            @ftp_mkdir($ftp, $fullDir);
        }
    }
}

$files = tree(ROOT_DIR); //获取本地项目所有文件

if ($doFtp) {
    $ftp = ftp_connect($project['ftp']['host']);
    // $ftp = ftp_ssl_connect($project['ftp']['host']);
    if (!$ftp) {
        die('ftp cannot connect'."\n");
    }

    $ftpLogin = ftp_login($ftp, $project['ftp']['username'], $project['ftp']['password']);
    if (!$ftpLogin) {
        die('ftp cannot login'."\n");
    }

    // ftp_pasv($ftp, true);
}

$cnt = 0;
$success = 0;

$lastDir = '';
foreach ($ftpFiles as $v) {
    echo $v['path'].'/'.$v['name']."\n";

    if ($doFtp) {
        if (FTP_ROOT_DIR.$v['path'] != $lastDir) {

            // for ($i = 0; $i < 3; $i++) {
            //     if (@ftp_chdir($ftp, FTP_ROOT_DIR.$v['path'])) {
            //         $inDir = true;
            //         break;
            //     } else {
            //         echo "- Can not ftp_chdir to ".FTP_ROOT_DIR.$v['path']."\n";
            //     }
            // }

            if (@ftp_chdir($ftp, FTP_ROOT_DIR.$v['path'])) {
                $inDir = true;
            } else {
                ftpMkdirs($ftp, FTP_ROOT_DIR.$v['path']);
            }

            if (!$inDir) {
                if (!ftp_mkdir($ftp, FTP_ROOT_DIR.$v['path'])) {
                    die('☹️  - ftp_mkdir failed: '.FTP_ROOT_DIR.$v['path']."\n");
                }
                @ftp_chdir($ftp, FTP_ROOT_DIR.$v['path']);
            }
            $inDir = false;
            $lastDir = FTP_ROOT_DIR.$v['path'];
        }

        // 传输模式有 FTP_ASCII/FTP_BINARY
        $ret = ftp_put($ftp, $v['name'], ROOT_DIR.$v['path'].'/'.$v['name'], FTP_BINARY);

        if ($ret) {
            $success++;
        } else {
            var_dump($ret);
            echo "☹️  - Can't ftp_put ".FTP_ROOT_DIR.$v['path'].'/'.$v['name']."\n";
        }
        $cnt++;
    }
}

if ($doFtp) {
    ftp_close($ftp);

    echo '🎉  - Total: '.$cnt.", Success: {$success}\n";

    if ($cnt == $success) {
        file_put_contents(dirname(__FILE__).'/projects/'.$argv[1].'.php', $newCfgFileContent);
    }
}

die;
