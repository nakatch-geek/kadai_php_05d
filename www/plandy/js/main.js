/**
 * Plandy アプリケーション フロントエンドスクリプト (最終修正版)
 */

let map;
let markers = [];
let currentItinerary = null;

function initMap() {
    map = new google.maps.Map(document.getElementById("map-container"), {
        center: { lat: 35.681236, lng: 139.767125 },
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const appState = {
        project_uuid: null,
        wishlist: [],
        currentCategory: 'tourist_attraction',
        currentGenre: null,
    };

    const elements = {
        tripNameInput: document.getElementById('trip-name'),
        destinationInput: document.getElementById('destination'),
        startDateInput: document.getElementById('start-date'),
        endDateInput: document.getElementById('end-date'),
        spotsList: document.getElementById('spots-list'),
        wishlistItems: document.getElementById('wishlist-items'),
        generateBtn: document.getElementById('generate-itinerary-btn'),
        generateBtnText: document.getElementById('generate-btn-text'),
        loadingSpinner: document.getElementById('loading-spinner'),
        wishlistTabBtn: document.getElementById('wishlist-tab-btn'),
        itineraryTabBtn: document.getElementById('itinerary-tab-btn'),
        wishlistView: document.getElementById('wishlist-view'),
        itineraryView: document.getElementById('itinerary-view'),
        itineraryDays: document.getElementById('itinerary-days'),
        categoryButtons: document.querySelectorAll('.category-btn'),
        diningGenreButtonsContainer: document.getElementById('dining-genre-buttons'),
        genreButtons: document.querySelectorAll('.genre-btn'),
        shareButton: document.getElementById('share-button'),
        shareFeedback: document.getElementById('share-feedback'),
        openGmapsBtn: document.getElementById('open-gmaps-btn'),
        clearWishlistBtn: document.getElementById('clear-wishlist-btn'),
        toastContainer: document.getElementById('toast-container'),
        aiMessageModal: document.getElementById('ai-message-modal'),
        aiModalTitle: document.getElementById('ai-modal-title'),
        aiModalBody: document.getElementById('ai-modal-body'),
        aiModalCloseBtn: document.getElementById('ai-modal-close-btn'),
    };

    async function apiRequest(action, data = {}) {
        try {
            const apiUrl = `${projectBasePath}api/gateway.php`;
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, project_uuid: appState.project_uuid, ...data }),
            });
            const responseText = await response.text();
            let jsonData;
            try { jsonData = JSON.parse(responseText); } catch (e) {
                console.error("サーバーからの応答がJSON形式ではありません:", responseText);
                throw new Error('サーバーから予期せぬ応答がありました。PHPのエラーログを確認してください。');
            }
            if (!response.ok) { throw new Error(jsonData.error || '不明なAPIエラーが発生しました。'); }
            return jsonData;
        } catch (error) {
            console.error('API Error:', error);
            alert(`エラーが発生しました: ${error.message}`);
            return null;
        }
    }

    async function fetchAndRenderSpots() {
        elements.spotsList.innerHTML = '<div class="loader"></div>';
        clearMarkers();
        const destination = elements.destinationInput.value;
        const searchQuery = appState.currentGenre ? `${destination}の${appState.currentGenre}` : `${destination}の${appState.currentCategory}`;
        const response = await apiRequest('searchSpots', { query: searchQuery });

        elements.spotsList.innerHTML = '';
        if (response && response.spots && response.spots.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            response.spots.forEach(spot => {
                const isAdded = appState.wishlist.some(w => w.spot_id === spot.id);
                const spotEl = document.createElement('div');
                spotEl.className = 'spot-item';
                const photoUrl = spot.photo ? `https://places.googleapis.com/v1/${spot.photo}/media?maxHeightPx=400&key=${GEMINI_API_KEY}` : 'https://placehold.co/400x200/e2e8f0/64748b?text=No+Image';
                spotEl.innerHTML = `
                    <img src="${photoUrl}" alt="${spot.name}" class="spot-item-image" onerror="this.src='https://placehold.co/400x200/e2e8f0/64748b?text=No+Image'">
                    <div class="spot-item-content">
                        <div class="spot-item-info">
                            <h4>${spot.name}</h4>
                            <p>⭐️ ${spot.rating || 'N/A'}</p>
                        </div>
                        <button data-spot-id='${spot.id}' data-spot-name='${spot.name}' data-spot-rating='${spot.rating || 'N/A'}' class="add-to-wishlist-btn" ${isAdded ? 'disabled' : ''}>${isAdded ? '✓' : '+'}</button>
                    </div>
                    ${spot.summary ? `<p class="spot-item-summary">${spot.summary}</p>` : ''}
                `;
                elements.spotsList.appendChild(spotEl);
                if (spot.location) {
                    const position = { lat: spot.location.latitude, lng: spot.location.longitude };
                    const marker = new google.maps.Marker({ position, map, title: spot.name });
                    markers.push(marker);
                    bounds.extend(position);
                }
            });
            map.fitBounds(bounds);
        } else {
            elements.spotsList.innerHTML = '<p class="empty-message">スポットが見つかりませんでした。</p>';
        }
    }
    
    function clearMarkers() { markers.forEach(m => m.setMap(null)); markers = []; }

    function renderWishlist() {
        elements.wishlistItems.innerHTML = '';
        if (appState.wishlist.length === 0) {
            elements.wishlistItems.innerHTML = '<p class="empty-message">まだスポットが追加されていません。</p>';
            elements.generateBtn.disabled = true;
            elements.clearWishlistBtn.classList.add('hidden');
            return;
        }
        appState.wishlist.forEach(spot => {
            const itemEl = document.createElement('div');
            itemEl.className = 'wishlist-item draggable';
            itemEl.setAttribute('draggable', 'true');
            itemEl.dataset.spotId = spot.spot_id;
            itemEl.innerHTML = `<div class="wishlist-item-info"><span class="drag-handle">⠿</span><div><h4>${spot.spot_name}</h4><p>⭐️ ${spot.spot_rating}</p></div></div><button data-spot-id="${spot.spot_id}" class="remove-from-wishlist-btn">×</button>`;
            elements.wishlistItems.appendChild(itemEl);
        });
        elements.generateBtn.disabled = false;
        elements.clearWishlistBtn.classList.remove('hidden');
    }

    function renderItinerary(itineraryData) {
        currentItinerary = itineraryData;
        elements.itineraryDays.innerHTML = '';
        if (!itineraryData || !itineraryData.days) {
            elements.itineraryDays.innerHTML = '<p class="empty-message">旅程の生成に失敗しました。</p>';
            elements.openGmapsBtn.classList.add('hidden');
            return;
        }
        itineraryData.days.forEach((day, dayIndex) => {
            const dayEl = document.createElement('div');
            dayEl.className = 'itinerary-day';
            let itemsHtml = '';
            day.plan.forEach((item, itemIndex) => {
                itemsHtml += `<div class="itinerary-item draggable" draggable="true" data-day-index="${dayIndex}" data-item-index="${itemIndex}"><div class="itinerary-item-header"><p class="itinerary-item-time">${item.time}</p><div class="itinerary-item-controls"><span class="drag-handle">⠿</span><button class="remove-from-itinerary-btn" data-day-index="${dayIndex}" data-item-index="${itemIndex}">×</button></div></div><h4>${item.spotName}</h4><p>${item.description}</p></div>`;
            });
            dayEl.innerHTML = `<h3>${day.date}</h3><div class="timeline" data-day-index="${dayIndex}">${itemsHtml}</div>`;
            elements.itineraryDays.appendChild(dayEl);
        });
        elements.openGmapsBtn.classList.remove('hidden');
        addItineraryDragAndDrop();
    }
    
    function switchTab(tabName) {
        const isWishlist = tabName === 'wishlist';
        elements.wishlistTabBtn.classList.toggle('active', isWishlist);
        elements.itineraryTabBtn.classList.toggle('active', !isWishlist);
        elements.wishlistView.classList.toggle('active', isWishlist);
        elements.itineraryView.classList.toggle('active', !isWishlist);
        elements.generateBtnText.textContent = `🤖 AIに旅程を${isWishlist ? '作成' : '再作成'}してもらう`;
        elements.openGmapsBtn.classList.toggle('hidden', isWishlist || !currentItinerary);
        elements.clearWishlistBtn.classList.toggle('hidden', !isWishlist || appState.wishlist.length === 0);
    }
    
    function updateProjectDetails(project) {
        elements.tripNameInput.value = project.name;
        elements.destinationInput.value = project.destination;
        elements.startDateInput.value = project.start_date;
        elements.endDateInput.value = project.end_date;
    }

    elements.spotsList.addEventListener('click', async e => {
        if (e.target.classList.contains('add-to-wishlist-btn')) {
            const button = e.target;
            const spot = { id: button.dataset.spotId, name: button.dataset.spotName, rating: button.dataset.spotRating, category: appState.currentCategory };
            if (!appState.wishlist.some(w => w.spot_id === spot.id)) {
                const response = await apiRequest('addSpotToWishlist', { spot });
                if (response && response.success) {
                    appState.wishlist.push({ spot_id: spot.id, spot_name: spot.name, spot_rating: spot.rating, category: spot.category });
                    renderWishlist();
                    button.textContent = '✓';
                    button.disabled = true;
                    showToast(`「${spot.name}」を追加しました！`);
                }
            }
        }
    });

    elements.wishlistItems.addEventListener('click', async e => {
        if (e.target.classList.contains('remove-from-wishlist-btn')) {
            const spotId = e.target.dataset.spotId;
            const response = await apiRequest('removeSpotFromWishlist', { spot_id: spotId });
            if (response && response.success) {
                appState.wishlist = appState.wishlist.filter(s => s.spot_id !== spotId);
                renderWishlist();
                fetchAndRenderSpots();
            }
        }
    });

    elements.generateBtn.addEventListener('click', async () => {
        elements.generateBtn.disabled = true;
        elements.generateBtnText.textContent = 'AIが考え中...';
        elements.loadingSpinner.classList.remove('hidden');
        await handleProjectDetailsChange();
        const dbWishlist = appState.wishlist.map(s => ({name: s.spot_name, category: s.category}));
        const response = await apiRequest('generateItinerary', { wishlist: dbWishlist });
        if (response) {
            renderItinerary(response);
            switchTab('itinerary');
            showAiWelcomeMessage(response.theme);
        }
        elements.generateBtn.disabled = false;
    });

    elements.categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            elements.categoryButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            appState.currentCategory = button.dataset.category;
            appState.currentGenre = null;
            elements.genreButtons.forEach(btn => btn.classList.remove('active'));
            elements.diningGenreButtonsContainer.classList.toggle('hidden', appState.currentCategory !== 'restaurant');
            fetchAndRenderSpots();
        });
    });

    elements.genreButtons.forEach(button => {
        button.addEventListener('click', () => {
            elements.genreButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            appState.currentGenre = button.dataset.genre;
            fetchAndRenderSpots();
        });
    });

    elements.shareButton.addEventListener('click', () => {
        const url = `${window.location.origin}${projectBasePath}?project=${appState.project_uuid}`;
        navigator.clipboard.writeText(url).then(() => {
            elements.shareFeedback.textContent = 'コピーしました！';
            setTimeout(() => { elements.shareFeedback.textContent = ''; }, 2000);
        });
    });
    
    elements.openGmapsBtn.addEventListener('click', () => {
        if (!currentItinerary || !currentItinerary.days) return;
        const waypoints = currentItinerary.days.flatMap(day => day.plan.map(item => encodeURIComponent(item.spotName)));
        if (waypoints.length > 1) {
            const origin = waypoints.shift();
            const destination = waypoints.pop();
            const waypointsStr = waypoints.join('/');
            const url = `https://www.google.com/maps/dir/${origin}/${waypointsStr}/${destination}`;
            window.open(url, '_blank');
        } else if (waypoints.length === 1) {
            const url = `https://www.google.com/maps/search/?api=1&query=${waypoints[0]}`;
            window.open(url, '_blank');
        }
    });
    
    elements.clearWishlistBtn.addEventListener('click', async () => {
        const response = await apiRequest('clearWishlist');
        if (response && response.success) {
            appState.wishlist = [];
            renderWishlist();
            fetchAndRenderSpots();
        }
    });

    elements.itineraryDays.addEventListener('click', e => {
        if (e.target.classList.contains('remove-from-itinerary-btn')) {
            const dayIndex = e.target.dataset.dayIndex;
            const itemIndex = e.target.dataset.itemIndex;
            currentItinerary.days[dayIndex].plan.splice(itemIndex, 1);
            renderItinerary(currentItinerary);
        }
    });

    function addItineraryDragAndDrop() { /* 省略: この部分は変更なし */ }
    function getDragAfterElement(container, y) { /* 省略: この部分は変更なし */ }

    async function handleProjectDetailsChange() {
        await apiRequest('updateProject', {
            tripName: elements.tripNameInput.value,
            destination: elements.destinationInput.value,
            startDate: elements.startDateInput.value,
            endDate: elements.endDateInput.value,
        });
    }

    async function init() {
        elements.wishlistTabBtn.addEventListener('click', () => switchTab('wishlist'));
        elements.itineraryTabBtn.addEventListener('click', () => switchTab('itinerary'));
        elements.tripNameInput.addEventListener('change', handleProjectDetailsChange);
        elements.destinationInput.addEventListener('change', async () => {
            await createNewProject();
            appState.wishlist = [];
            currentItinerary = null;
            renderWishlist();
            renderItinerary(null);
            switchTab('wishlist');
            await fetchAndRenderSpots();
        });
        elements.startDateInput.addEventListener('change', handleProjectDetailsChange);
        elements.endDateInput.addEventListener('change', handleProjectDetailsChange);
        elements.aiModalCloseBtn.addEventListener('click', () => elements.aiMessageModal.classList.add('hidden'));

        const urlParams = new URLSearchParams(window.location.search);
        const projectUuid = urlParams.get('project');

        if (projectUuid) {
            const data = await apiRequest('loadProject', { project_uuid: projectUuid });
            if (data) {
                appState.project_uuid = projectUuid;
                appState.wishlist = data.wishlist;
                updateProjectDetails(data.project);
                if (data.project.plan_json) {
                    renderItinerary(JSON.parse(data.project.plan_json));
                    switchTab('itinerary');
                }
            } else { await createNewProject(); }
        } else { await createNewProject(); }

        document.querySelector('.category-btn[data-category="tourist_attraction"]').classList.add('active');
        fetchAndRenderSpots();
        renderWishlist();
    }
    
    async function createNewProject() {
        const data = await apiRequest('createProject', {
            tripName: elements.tripNameInput.value,
            destination: elements.destinationInput.value,
            startDate: elements.startDateInput.value,
            endDate: elements.endDateInput.value,
        });
        if (data && data.project_uuid) {
            appState.project_uuid = data.project_uuid;
            const newUrl = `${window.location.origin}${projectBasePath}?project=${data.project_uuid}`;
            history.pushState({ path: newUrl }, '', newUrl);
        }
    }
    
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        elements.toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    function showAiWelcomeMessage(theme) {
        if (!theme) return;
        elements.aiModalTitle.textContent = `今回の旅のテーマは...`;
        elements.aiModalBody.textContent = `「${theme}」`;
        elements.aiMessageModal.classList.remove('hidden');
    }

    init();
});
