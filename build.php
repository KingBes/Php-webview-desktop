<?php

declare(strict_types=1);

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
$dirPhar = __DIR__ . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'debug';
if (is_dir($dirPhar)) {
    delDir(__DIR__ . DIRECTORY_SEPARATOR . 'build');
}
mkdir($dirPhar, 0777, true);

// 入口文件
$srcPath = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "index.php";

if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . "favicon.ico")) {
    throw new Exception("没有文件：" . __DIR__ . DIRECTORY_SEPARATOR . "favicon.ico");
    exit;
}

copy(__DIR__ . DIRECTORY_SEPARATOR . "favicon.ico", $dirPhar . DIRECTORY_SEPARATOR . "favicon.ico");
copyDir(__DIR__ . DIRECTORY_SEPARATOR . "src", $dirPhar . DIRECTORY_SEPARATOR);

// debug 执行
$command = "copy /b " . __DIR__ . "\\php\\windows\\micro-debug.sfx + {$srcPath} {$dirPhar}\\debug_{$file_name}.exe";
echo $command . "\n";
exec($command, $output, $returnVar);

// release 执行
$command = "copy /b " . __DIR__ . "\\php\\windows\\micro.sfx + {$srcPath} {$dirPhar}\\{$file_name}.exe";
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

if (isset($options["debug"])) {
    outputMsg("编译成功！！！", 33);
    outputMsg("编译debug路径:$dirPhar", 33);
    outputMsg("运行文件exe~", 33);
    exit;
}

// release 编译
$dirRelease = __DIR__ . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release';
if (is_dir($dirRelease)) {
    delDir($dirRelease);
}
mkdir($dirRelease, 0777, true);

// 排除的文件夹或文件夹
$exclude = [DIRECTORY_SEPARATOR . "debug_{$file_name}.exe"];
if (isset($options["exclude"])) {
    $exclude = array_merge($exclude, explode(",", $options["exclude"]));
}

/**
 * 批量匹配最后的字符 function
 *
 * @param string $string
 * @param array $target
 * @return boolean
 */
function endsWith(string $string, array $target): bool
{
    $res = true;
    foreach ($target as $k => $v) {
        if (strpos($string, $v) !== false) {
            if ($k != 0) {
                $newStr = str_replace("debug", "release", $string);
                if (is_file($string)) {
                    copy($string, $newStr);
                } else {
                    copyDir($string, $newStr);
                }
            }
            $res = false;
            break;
        } else {
            continue;
        }
    }
    return $res;
}

// 编译文件
$InputFile = $dirPhar . DIRECTORY_SEPARATOR . $file_name . ".exe";
// 编译后
$OutputFile = $dirRelease . DIRECTORY_SEPARATOR . $file_name . ".exe";

function filesStr(string $source, array $exclude): string
{
    $dir = opendir($source);
    $str = "";
    while (false !== ($file = readdir($dir))) {
        $endsWith = endsWith($source . DIRECTORY_SEPARATOR . $file, $exclude);
        if (($file != '.') && ($file != '..') && $endsWith) {
            $str .= "<File>";
            $Name = "<Name>" . basename($source . DIRECTORY_SEPARATOR . $file) . "</Name>";
            if (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
                $Type = "<Type>3</Type>";
                $File = "";
                $Files = "<Files>" . filesStr($source . DIRECTORY_SEPARATOR . $file, $exclude) . "</Files>";
            } else {
                $str .= "<ActiveX>False</ActiveX>
                <ActiveXInstall>False</ActiveXInstall>
                <PassCommandLine>False</PassCommandLine>";
                $Type = "<Type>2</Type>";
                $File = "<File>" . $source . DIRECTORY_SEPARATOR . $file . "</File>";
                $Files = "";
            }
            $str .= "$Type $Name <OverwriteDateTime>False</OverwriteDateTime>
            <OverwriteAttributes>False</OverwriteAttributes>
            <HideFromDialogs>0</HideFromDialogs> $File $Files";
            $str .= "</File>";
        } else {
            continue;
        }
    }
    closedir($dir);
    return $str;
}

$files = filesStr($dirPhar, $exclude);

$evbFile = <<<EOF
<?xml version="1.0" encoding="windows-1252"?>
<>
    <InputFile>$InputFile</InputFile>
    <OutputFile>$OutputFile</OutputFile>
    <Files>
        <Enabled>True</Enabled>
        <DeleteExtractedOnExit>False</DeleteExtractedOnExit>
        <CompressFiles>True</CompressFiles>
        <Files>
            <File>
                <Type>3</Type>
                <Name>%DEFAULT FOLDER%</Name>
                <Action>0</Action>
                <OverwriteDateTime>False</OverwriteDateTime>
                <OverwriteAttributes>False</OverwriteAttributes>
                <HideFromDialogs>0</HideFromDialogs>
                <Files>
                $files
                </Files>
            </File>
        </Files>
    </Files>
    <Registries>
  <Enabled>False</Enabled>
  <Registries>
    <Registry>
      <Type>1</Type>
      <Virtual>True</Virtual>
      <Name>Classes</Name>
      <ValueType>0</ValueType>
      <Value />
      <Registries />
    </Registry>
    <Registry>
      <Type>1</Type>
      <Virtual>True</Virtual>
      <Name>User</Name>
      <ValueType>0</ValueType>
      <Value />
      <Registries />
    </Registry>
    <Registry>
      <Type>1</Type>
      <Virtual>True</Virtual>
      <Name>Machine</Name>
      <ValueType>0</ValueType>
      <Value />
      <Registries />
    </Registry>
    <Registry>
      <Type>1</Type>
      <Virtual>True</Virtual>
      <Name>Users</Name>
      <ValueType>0</ValueType>
      <Value />
      <Registries />
    </Registry>
    <Registry>
      <Type>1</Type>
      <Virtual>True</Virtual>
      <Name>Config</Name>
      <ValueType>0</ValueType>
      <Value />
      <Registries />
    </Registry>
  </Registries>
</Registries>
<Packaging>
  <Enabled>False</Enabled>
</Packaging>
<Options>
  <ShareVirtualSystem>False</ShareVirtualSystem>
  <MapExecutableWithTemporaryFile>True</MapExecutableWithTemporaryFile>
  <TemporaryFileMask />
  <AllowRunningOfVirtualExeFiles>True</AllowRunningOfVirtualExeFiles>
  <ProcessesOfAnyPlatforms>False</ProcessesOfAnyPlatforms>
</Options>
<Storage>
  <Files>
    <Enabled>False</Enabled>
    <Folder>%DEFAULT FOLDER%\</Folder>
    <RandomFileNames>False</RandomFileNames>
    <EncryptContent>False</EncryptContent>
  </Files>
</Storage>
</>
</>
EOF;

$file_evb = __DIR__ . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'build.evb';

if (is_file($file_evb)) {
    unlink($file_evb);
}

$fileing =  file_put_contents($file_evb, $evbFile);

if ($file_name) {
    $compiler = __DIR__ . DIRECTORY_SEPARATOR . 'compiler.exe';
    if (is_file($compiler)) {
        echo "release执行：" . $compiler . " " . $file_evb . " -input $InputFile -output $OutputFile\n";
        $handle = popen($compiler . " " . $file_evb . " -input $InputFile -output $OutputFile", 'r');
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                echo $buffer;
                flush();
            }
            outputMsg("编译成功！！！", 33);
            outputMsg("debug路径:$dirPhar", 33);
            outputMsg("release路径:$dirRelease", 33);
        } else {
            outputMsg("release:编译失败~", 31);
        }
    } else {
        throw new Exception("编译失败，缺少编译器文件compiler.exe~");
        exit;
    }
} else {
    throw new Exception("编译失败，缺少编译配置文件~");
    exit;
}

/* outputMsg("编译成功！！！", 33);

outputMsg("编译debug路径:$dirPhar", 33);

outputMsg("二进制文件必须和os文件夹一起分发!", 31); */
