<?php

// 检查是否通过命令行运行  
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n 这个脚本必须从命令行运行。\n");
}

// 函数：从命令行参数中解析选项  
function parseArgs($argv): array
{
    $options = [];
    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            // 跳过脚本名  
            continue;
        }
        if (strpos($arg, '-') === 0) {
            // 解析选项
            $arr = explode('=', substr($arg, 1));
            $value = 1;
            if (isset($arr[1])) {
                $value = $arr[1];
            }
            $options[$arr[0]] = $value;
        }
    }
    return $options;
}

// 函数：输出带有颜色的文本（仅支持支持 ANSI 的终端）  
function outputMsg(string $text, int $colorCode): void
{
    echo "\033[{$colorCode}m{$text}\033[0m\n";
}

// 解析命令行参数  
$options = parseArgs($argv);

// is_debug
$debug = "release"; // 发行名
$debug_micro = "micro"; // 发行编译文件
if (isset($options["debug"])) {
    $debug = "debug";
    $debug_micro = "micro-debug";
}

// 文件名
$file_name = "webview";
if (isset($options["name"])) {
    if (trim($options["name"]) == "") {
        throw new Exception("参数 -name 必须是有效的，例如：-name=webview\n");
        exit;
    }
    $file_name = $options["name"];
}

/**
 * 复制 function
 *
 * @param string $source
 * @param string $destination
 * @return void
 */
function copyDir(string $source, string $destination): void
{
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    $dir = opendir($source);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
                copyDir($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
            } else {
                copy($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
            }
        }
    }
    closedir($dir);
}

/**
 * 删除 function
 *
 * @param string $dir 文件夹
 * @return void
 */
function delDir(string $dir): void
{
    if (!is_dir($dir)) {
        throw new InvalidArgumentException("$dir must be a directory");
    }
    if (substr($dir, strlen($dir) - 1, 1) != '/') {
        $dir .= '/';
    }
    $files = glob($dir . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            delDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

// 路径
$dirPhar = __DIR__ . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $debug;
if (is_dir($dirPhar)) {
    delDir($dirPhar);
}
mkdir($dirPhar, 0777, true);
// webview.phar文件
$webviewPhar = $dirPhar . DIRECTORY_SEPARATOR . "$file_name.phar";

// 检查文件是否存在  
if (file_exists($webviewPhar)) {
    // 文件存在，尝试删除  
    if (!unlink($webviewPhar)) {
        echo "执行删除文件 {$webviewPhar} 时出错。\n";
        exit;
    }
}

try {
    //产生一个webview.phar文件
    $phar = new Phar($webviewPhar, 0, $file_name . '.phar');
    // 添加src里面的所有文件到webview.phar归档文件
    $phar->buildFromDirectory(dirname(__FILE__) . '/src');
    //设置执行时的入口文件，第一个用于命令行，第二个用于浏览器访问，这里都设置为index.php
    $phar->setDefaultStub('index.php');
} catch (Exception $e) {
    throw new Exception("生成 Phar 文件时出错：" . $e->getMessage()) . "\n";
    exit;
}

copyDir(__DIR__ . DIRECTORY_SEPARATOR . "os", $dirPhar . DIRECTORY_SEPARATOR . "os");

// windows 执行
$command = "copy /b " . __DIR__ . "\\php\\windows\\{$debug_micro}.sfx + {$dirPhar}\\{$file_name}.phar {$dirPhar}\\{$file_name}.exe";
echo $command . "\n";
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "命令执行成功！\n";
} else {
    echo "命令执行失败，返回码：" . $returnVar . PHP_EOL . "\n";
    if (!empty($output)) {
        echo "输出信息：" . PHP_EOL . "\n";
        print_r($output);
    }
    exit;
}

outputMsg("编译成功！！！", 33);

outputMsg("编译路径:$dirPhar", 33);

outputMsg("二进制文件必须和os文件夹一起分发!", 31);
