<?php
/**
 * Plandy API Logic
 * このファイルは直接実行されることを想定していません。
 */

// デバッグが完了したら、この3行はコメントアウトしてください。
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config2.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'createProject':
            handleCreateProject($pdo, $input);
            break;
        case 'loadProject':
            handleLoadProject($pdo, $input);
            break;
        case 'updateProject':
            handleUpdateProject($pdo, $input);
            break;
        case 'addSpotToWishlist':
            handleAddSpotToWishlist($pdo, $input);
            break;
        case 'removeSpotFromWishlist':
            handleRemoveSpotFromWishlist($pdo, $input);
            break;
        case 'clearWishlist':
            handleClearWishlist($pdo, $input);
            break;
        case 'searchSpots':
            handleSearchSpotsNew($input);
            break;
        case 'generateItinerary':
            handleGenerateItinerary($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => '不明なアクションです。']);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['error' => 'サーバーでエラーが発生しました。']);
    exit;
}

function handleSearchSpotsNew($input) {
    if (!isset($input['query'])) { http_response_code(400); echo json_encode(['error' => '検索クエリが必要です。']); exit; }
    $query = $input['query'];
    $url = "https://places.googleapis.com/v1/places:searchText";
    $postData = ['textQuery' => $query, 'languageCode' => 'ja', 'maxResultCount' => 10];
    // ★★★ 更新箇所 ★★★ 写真と概要を取得するためのフィールドを追加
    $headers = ['Content-Type: application/json', 'X-Goog-Api-Key: ' . GEMINI_API_KEY, 'X-Goog-FieldMask: places.id,places.displayName,places.rating,places.location,places.photos,places.editorialSummary'];
    $response = sendCurlRequest($url, $postData, $headers);
    $apiResponse = json_decode($response, true);

    if ($apiResponse && isset($apiResponse['places'])) {
        $spots = array_map(function($place) {
            // ★★★ 更新箇所 ★★★ 写真と概要の情報をレスポンスに追加
            return [
                'id' => $place['id'], 
                'name' => $place['displayName']['text'] ?? '名前不明', 
                'rating' => $place['rating'] ?? null, 
                'location' => $place['location'] ?? null,
                'photo' => $place['photos'][0]['name'] ?? null,
                'summary' => $place['editorialSummary']['text'] ?? null,
            ];
        }, $apiResponse['places']);
        echo json_encode(['spots' => $spots]);
    } else {
        error_log('Places API (New) Error: ' . $response);
        echo json_encode(['spots' => [], 'error_details' => $apiResponse]);
    }
}

function handleCreateProject($pdo, $input) {
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $stmt = $pdo->prepare("INSERT INTO projects (project_uuid, name, destination, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$uuid, $input['tripName'] ?? '新しい旅', $input['destination'] ?? '未定', $input['startDate'] ?? date('Y-m-d'), $input['endDate'] ?? date('Y-m-d')]);
    echo json_encode(['project_uuid' => $uuid]);
}

function handleLoadProject($pdo, $input) {
    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("SELECT p.*, ip.plan_json FROM projects p LEFT JOIN itinerary_plans ip ON p.id = ip.project_id WHERE p.project_uuid = ?");
    $stmt->execute([$uuid]);
    $project = $stmt->fetch();

    if (!$project) { http_response_code(404); echo json_encode(['error' => 'プロジェクトが見つかりません。']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM wishlist_spots WHERE project_id = ?");
    $stmt->execute([$project['id']]);
    $wishlist = $stmt->fetchAll();
    echo json_encode(['project' => $project, 'wishlist' => $wishlist]);
}

function handleUpdateProject($pdo, $input) {
    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("UPDATE projects SET name = ?, destination = ?, start_date = ?, end_date = ? WHERE project_uuid = ?");
    $stmt->execute([$input['tripName'], $input['destination'], $input['startDate'], $input['endDate'], $uuid]);
    echo json_encode(['success' => true]);
}

function handleAddSpotToWishlist($pdo, $input) {
    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE project_uuid = ?");
    $stmt->execute([$uuid]);
    $project = $stmt->fetch();
    if (!$project) { exit; }
    $stmt = $pdo->prepare("INSERT INTO wishlist_spots (project_id, spot_id, spot_name, spot_rating, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$project['id'], $input['spot']['id'], $input['spot']['name'], $input['spot']['rating'], $input['spot']['category']]);
    echo json_encode(['success' => true]);
}

function handleRemoveSpotFromWishlist($pdo, $input) {
    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE project_uuid = ?");
    $stmt->execute([$uuid]);
    $project = $stmt->fetch();
    if (!$project) { exit; }
    $stmt = $pdo->prepare("DELETE FROM wishlist_spots WHERE project_id = ? AND spot_id = ?");
    $stmt->execute([$project['id'], $input['spot_id']]);
    echo json_encode(['success' => true]);
}

function handleClearWishlist($pdo, $input) {
    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE project_uuid = ?");
    $stmt->execute([$uuid]);
    $project = $stmt->fetch();
    if (!$project) { http_response_code(404); echo json_encode(['error' => 'プロジェクトが見つかりません。']); exit; }
    $stmt = $pdo->prepare("DELETE FROM wishlist_spots WHERE project_id = ?");
    $stmt->execute([$project['id']]);
    echo json_encode(['success' => true]);
}

function handleGenerateItinerary($pdo, $input) {
    if (!isset($input['wishlist']) || empty($input['wishlist'])) { http_response_code(400); echo json_encode(['error' => '行きたいリストが空です。']); exit; }

    $uuid = $input['project_uuid'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE project_uuid = ?");
    $stmt->execute([$uuid]);
    $project = $stmt->fetch();
    if (!$project) { http_response_code(404); echo json_encode(['error' => 'プロジェクトが見つかりません。']); exit; }
    $project_id = $project['id'];

    $startDateStr = $project['start_date'];
    $endDateStr = $project['end_date'];
    $destination = $project['destination'];

    try {
        $startDate = new DateTime($startDateStr);
        $endDate = new DateTime($endDateStr);
        if ($startDate > $endDate) { list($startDate, $endDate) = [$endDate, $startDate]; }
        $interval = $startDate->diff($endDate);
        $days = $interval->days + 1;
        $duration_text = $days > 1 ? ($days - 1) . "泊{$days}日" : "{$days}日間";
    } catch (Exception $e) { $duration_text = "1泊2日"; }

    $wishlist = $input['wishlist'];
    $dining_spots = array_column(array_filter($wishlist, fn($s) => in_array($s['category'], ['restaurant', 'cafe'])), 'name');
    $other_spots = array_column(array_filter($wishlist, fn($s) => !in_array($s['category'], ['restaurant', 'cafe'])), 'name');
    $dining_spot_names = !empty($dining_spots) ? implode('、', $dining_spots) : 'なし';
    $other_spot_names = !empty($other_spots) ? implode('、', $other_spots) : 'なし';

    // ★★★ 更新箇所 ★★★ AIへの指示をより感情的に
    $prompt = "あなたは旅を愛する、情熱的な旅行プランナー兼ナレーターです。日本の{$destination}を巡る{$duration_text}の旅行プランを、以下の条件で、まるで旅番組のようにわくわくする紹介文を添えて作成してください。\n\n"
            . "# 訪問先リスト\n- 観光スポット: {$other_spot_names}\n- 食事・カフェ: {$dining_spot_names}\n\n"
            . "# 条件\n- 上記の訪問先をすべて含めてください。\n- 食事場所は昼食(12:00〜14:00)か夕食(18:00〜20:00)の時間帯に割り振ってください。\n"
            . "- 現実的なタイムスケジュールを組んでください。\n- 各プランの説明は、期待感が高まるような、少し詩的で魅力的な文章にしてください。\n"
            . "- 最初に、この旅全体を表すキャッチーな「旅のテーマ」を考えてください。\n- 出力は必ず指定のJSON形式に従ってください。\n\n"
            . "# JSON形式の例\n{\"theme\": \"美食と歴史に触れる、金沢うるわし紀行\", \"days\": [{\"date\": \"1日目\", \"plan\": [{\"time\": \"12:00 - 13:30\", \"spotName\": \"近江町市場\", \"description\": \"金沢の台所で、新鮮な海の幸があなたを待っています。\"}]}]}";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . GEMINI_API_KEY;
    $postData = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['response_mime_type' => 'application/json']];
    $headers = ['Content-Type: application/json'];
    $response = sendCurlRequest($url, $postData, $headers);
    $apiResponse = json_decode($response, true);

    if ($apiResponse && isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $planJson = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
        $stmt = $pdo->prepare("INSERT INTO itinerary_plans (project_id, plan_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE plan_json = VALUES(plan_json)");
        $stmt->execute([$project_id, $planJson]);
        echo $planJson;
    } else {
        http_response_code(500);
        error_log('Gemini API Error: ' . $response);
        echo json_encode(['error' => 'AIによる旅程生成に失敗しました。', 'details' => $apiResponse]);
    }
}

function sendCurlRequest($url, $postData = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
