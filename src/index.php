<?php

declare(strict_types=1);

require "vendor/autoload.php";

use KingBes\PhpWebview\WebView;
use KingBes\PhpWebview\Dialog;

use KingBes\PhpWebview\Toast;


// webview实例
$webview = new WebView('Php WebView', 640, 480, true);
// 获取html
$html = <<<EOF
<button onclick="onMsg('hello php',2)">弹出</button>
<script>
    function onMsg(str,num){
        openMsg(str,num).then(function (data,a){
            console.log(data)
        })
    }
</script>

EOF;
// 设置HTML
$webview->setHTML($html);

// 任务栏标题
$webview->icon_title('php WeView');
// 任务栏菜单
$arr = [
    ["name" => "显示", "fn" => function () use ($webview) {
        // 显示窗口
        $webview->show_win();
    }],
    ["name" => "退出", "fn" => function () use ($webview) {
        // 退出窗口
        $webview->destroy_win();
    }]
];
$webview->icon_menu($arr);
// 绑定
$dialog = new Dialog();
$webview->bind('openMsg', function ($seq, $req, $context) use ($dialog) {
    // 弹出消息窗口
    $msg = $dialog->msg($req[0], $req[1]);
    return ["code" => 0, "msg" => $msg];
});

// 运行
$webview->run();
// 销毁
$webview->destroy();
