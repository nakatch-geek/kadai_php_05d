<?php
require_once __DIR__ . '/../../app/config2.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plandy - AI旅行計画アプリ</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="app">
        <header class="app-header">
            <h1>Plandy <span class="subtitle">AI旅行計画アプリ</span></h1>
            <p>AI旅行コンシェルジュ『Plandy』のコンセプトを体験してください。</p>
        </header>
        <main class="main-container">
            <!-- 左パネル -->
            <section id="controls-panel" class="panel">
                <div class="panel-sticky-content">
                    <h2 class="panel-title">1. 旅の計画をはじめる</h2>
                    <p class="panel-description">まず、あなたの旅の基本情報を入力してください。</p>
                    <div class="form-group">
                        <div><label for="trip-name">旅行名</label><input type="text" id="trip-name" value="金沢満喫の旅"></div>
                        <div><label for="destination">目的地</label><input type="text" id="destination" value="金沢市"></div>
                        <div class="date-group">
                            <div><label for="start-date">出発日</label><input type="date" id="start-date" value="2025-08-22"></div>
                            <div><label for="end-date">帰着日</label><input type="date" id="end-date" value="2025-08-23"></div>
                        </div>
                    </div>
                    <div class="share-section">
                        <h3>共有</h3>
                        <p class="panel-description">URLをコピーして友達を招待しよう！</p>
                        <button id="share-button" class="button-secondary">🔗 共有リンクをコピー</button>
                        <div id="share-feedback" class="feedback-text"></div>
                    </div>
                </div>
            </section>
            <!-- 中央パネル -->
            <section id="discovery-panel" class="panel">
                <h2 class="panel-title">2. スポットを探す</h2>
                <p class="panel-description">地図とリストから行きたい場所を見つけて、「行きたいリスト」に追加しましょう。</p>
                <div id="map-container"></div>
                <div class="category-buttons">
                    <button class="category-btn" data-category="restaurant">食事 🍽️</button>
                    <button class="category-btn" data-category="tourist_attraction">観光 🏯</button>
                    <button class="category-btn" data-category="cafe">カフェ ☕</button>
                </div>
                <div id="dining-genre-buttons" class="genre-buttons-container hidden">
                    <button class="genre-btn" data-genre="和食">和食</button>
                    <button class="genre-btn" data-genre="寿司">寿司</button>
                    <button class="genre-btn" data-genre="ラーメン">ラーメン</button>
                    <button class="genre-btn" data-genre="居酒屋">居酒屋</button>
                </div>
                <div id="spots-list" class="spots-list-container"><div class="loader"></div></div>
            </section>
            <!-- 右パネル -->
            <section id="planning-panel" class="panel">
                <h2 class="panel-title">3. 旅程を組み立てる</h2>
                <p class="panel-description">AIに旅程の作成を任せたり、手動で調整したりできます。</p>
                <div class="tab-container">
                    <button id="wishlist-tab-btn" class="tab-btn active">行きたいリスト</button>
                    <button id="itinerary-tab-btn" class="tab-btn">旅程</button>
                </div>
                <div class="tab-content-wrapper">
                    <div id="wishlist-view" class="tab-content active"><div id="wishlist-items" class="list-container"><p class="empty-message">まだスポットが追加されていません。</p></div></div>
                    <div id="itinerary-view" class="tab-content"><div id="itinerary-days" class="list-container"><p class="empty-message">AIによる旅程がここに表示されます。</p></div></div>
                </div>
                <div class="action-buttons-container">
                     <button id="clear-wishlist-btn" class="button-danger hidden">🗑️ リストを空にする</button>
                     <button id="generate-itinerary-btn" class="button-primary" disabled>
                        <span id="generate-btn-text">🤖 AIに旅程を作成してもらう</span>
                        <div id="loading-spinner" class="spinner hidden"></div>
                    </button>
                    <button id="open-gmaps-btn" class="button-tertiary hidden">🗺️ Googleマップでルートを開く</button>
                </div>
            </section>
        </main>
    </div>
    
    <div id="toast-container"></div>
    <div id="ai-message-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="ai-modal-title">AIからのメッセージ</h3>
            <p id="ai-modal-body"></p>
            <button id="ai-modal-close-btn" class="button-primary">旅を始める！</button>
        </div>
    </div>

    <script>const projectBasePath = '<?php echo str_replace(basename($_SERVER["SCRIPT_NAME"]), "", $_SERVER["SCRIPT_NAME"]); ?>';</script>
    <script>const GEMINI_API_KEY = '<?php echo GEMINI_API_KEY; ?>';</script>
    <script src="js/main.js" defer></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GEMINI_API_KEY; ?>&callback=initMap" defer></script>
</body>
</html>
