<?php
/**
 * Plandy API Public Gateway
 * このファイルがAPIリクエストの唯一の入り口となります。
 * 実際の処理は、サーバー内部の api_logic.php を呼び出して実行します。
 */

// レスポンスのContent-TypeをJSONに設定
header('Content-Type: application/json');

// 3階層上のappフォルダにあるロジックファイルを読み込む
require_once __DIR__ . '/../../../app/api_logic.php';

?>
