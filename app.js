const gameArea = document.getElementById('game-area');
const voteButtons = document.getElementById('vote-buttons');
const statsArea = document.getElementById('stats-area');
const influencerImage = document.getElementById('influencer-image');
const nextButton = document.getElementById('next-btn');
const voteBtnElements = document.querySelectorAll('.vote-btn');
const disclaimerPopup = document.getElementById('disclaimer-popup');
const disclaimerOkButton = document.getElementById('disclaimer-ok-btn');
const influencerNameElement = document.getElementById('influencer-name');
const instructionTextElement = document.getElementById('instruction-text');
const questionElement = document.getElementById('question');

const SEEN_INFLUENCERS_KEY = 'gejoguessr_seen';
let currentInfluencerId = null;
window.currentStats = null;
let firstInfluencerPromise = null;

function getSeenInfluencers() {
    const history = localStorage.getItem(SEEN_INFLUENCERS_KEY);
    console.log("Reading seen IDs:", history ? JSON.parse(history) : []);
    return history ? JSON.parse(history) : [];
}

function addInfluencerToSeen(newId) {
    let seenIds = getSeenInfluencers();
    if (!seenIds.includes(newId)) {
        seenIds.push(newId);
        localStorage.setItem(SEEN_INFLUENCERS_KEY, JSON.stringify(seenIds));
        console.log(`Added ID ${newId}. New seen count: ${seenIds.length}`);
    } else {
        console.warn(`ID ${newId} was already seen.`);
    }
}

function clearSeenHistory() {
    localStorage.removeItem(SEEN_INFLUENCERS_KEY);
    console.log("Seen influencers history cleared.");
}

async function fetchInfluencer() {
    const excludedIds = getSeenInfluencers();
    console.log(`Workspaceing: Excluding ${excludedIds.length} IDs`);
    try {
        const response = await fetch('/api/influencer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ exclude_ids: excludedIds })
        });

        if (!response.ok) {
            let errorData = { error: `HTTP error! status: ${response.status}` };
            try {
                errorData = await response.json();
            } catch (e) {
                const errorText = await response.text().catch(() => 'Could not read error text');
                console.error(`Influencer API Error Response (Status: ${response.status}):`, errorText);
            }
            const errorMessage = errorData.error === 'No available influencers found.' ? 'no_more_influencers' : (errorData.error || `HTTP error! status: ${response.status}`);
            throw new Error(errorMessage);
        }

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            console.error("Failed to parse JSON response from influencer API:", parseError);
            const responseText = await response.text().catch(() => 'Could not read response text');
            console.error("Influencer API Raw Response Text:", responseText);
            throw new Error("Invalid JSON response received from server.");
        }

        if (data.error) {
            console.error("Influencer API returned an error:", data.error);
            throw new Error(data.error);
        }
        console.log("FETCHED successfully:", data);
        return data;
    } catch (error) {
        console.error("FETCH FAILED:", error);
        throw error;
    }
}

async function submitVote(id, guess) {
    let response;
    try {
        response = await fetch('/api/vote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ influencer_id: id, guess: guess }),
        });

        if (!response.ok) {
            const errorText = await response.text().catch(() => 'Could not read error text');
            console.error(`Vote API Error Response (Status: ${response.status}):`, errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            console.error("Failed to parse JSON response from vote API:", parseError);
            const responseText = await response.text().catch(() => 'Could not read response text');
            console.error("Vote API Raw Response Text:", responseText);
            throw new Error("Invalid JSON response received from server.");
        }


        if (data.error) {
            console.error("Vote API returned an error in JSON:", data.error);
            throw new Error(data.error);
        }
        return data;

    } catch (error) {
        console.error("Error submitting vote:", error.message);
        alert("Failed to submit vote. Check console for details.");
        return null;
    }
}


function displayStats(stats) {
    window.currentStats = stats;
    const totalVotes = stats.votes_gay + stats.votes_straight;
    let percentGay = 0;
    let percentStraight = 0;

    if (totalVotes > 0) {
        percentGay = Math.round((stats.votes_gay / totalVotes) * 100);
        percentStraight = 100 - percentGay;
    }

    const gayBar = document.querySelector('.gay-bar');
    const straightBar = document.querySelector('.straight-bar');
    const gayPercLabel = document.querySelector('.gay-perc');
    const straightPercLabel = document.querySelector('.straight-perc');

    if (gayBar && straightBar && gayPercLabel && straightPercLabel) {
        gayBar.style.width = percentGay + '%';
        straightBar.style.width = percentStraight + '%';
        gayPercLabel.textContent = percentGay + '%';
        straightPercLabel.textContent = percentStraight + '%';
    }

    const gayLabel = document.querySelector('.chart-label[data-translate="button_gay"]');
    const straightLabel = document.querySelector('.chart-label[data-translate="button_straight"]');
    if (gayLabel && typeof getTranslation === 'function') gayLabel.textContent = getTranslation('button_gay');
    if (straightLabel && typeof getTranslation === 'function') straightLabel.textContent = getTranslation('button_straight');

    if (voteButtons) voteButtons.style.display = 'none';
    if (statsArea) statsArea.style.display = 'block';
    if (questionElement) questionElement.style.display = 'none';
    if (instructionTextElement) instructionTextElement.style.display = 'none';
}


function displayInfluencer(influencer) {
    if (!influencer || !influencer.id) {
        console.error("displayInfluencer called with invalid data:", influencer);
        handleFetchError(new Error("Received invalid influencer data"));
        return;
    }

    console.log(`Displaying influencer ID: ${influencer.id}`);

    currentInfluencerId = influencer.id;
    const nameToShow = influencer.name || 'Influencer';
    if (influencerNameElement) {
        influencerNameElement.textContent = nameToShow;
        influencerNameElement.removeAttribute('data-translate');
    }
    if (influencerImage) {
        influencerImage.src = influencer.image_url;
        influencerImage.alt = `Image of ${nameToShow}`;
        influencerImage.onerror = () => {
            console.error(`Failed to load image: ${influencer.image_url}`);
            influencerImage.alt = typeof getTranslation === 'function' ? getTranslation('loading_error') : 'Error loading image.';
        };
    }
    addInfluencerToSeen(currentInfluencerId);


    if (statsArea) statsArea.style.display = 'none';
    if (voteButtons) voteButtons.style.display = 'block';
    if (questionElement) questionElement.style.display = 'block';
    if (instructionTextElement) instructionTextElement.style.display = 'block';
    voteBtnElements.forEach(btn => btn.disabled = false);
    if (nextButton) nextButton.disabled = true;
}


function handleFetchError(error) {
    console.error("Handling fetch error:", error.message);
    if (influencerNameElement) influencerNameElement.textContent = "";
    if (instructionTextElement) instructionTextElement.style.display = 'none';
    if (questionElement) questionElement.style.display = 'none';
    if (influencerImage) {
        const errorMessageKey = error.message === 'no_more_influencers' ? 'no_more_influencers' : 'loading_error';
        influencerImage.alt = typeof getTranslation === 'function' ? getTranslation(errorMessageKey) : errorMessageKey;
        influencerImage.src = "";
        influencerImage.onerror = null; // Remove error handler if fetch failed
    }
    voteBtnElements.forEach(btn => btn.disabled = true);
    if (voteButtons) voteButtons.style.display = 'none';
    if (nextButton) nextButton.disabled = true;
}


async function loadNextInfluencer() {
    console.log("loadNextInfluencer called");

    if (statsArea) statsArea.style.display = 'none';
    if (voteButtons) voteButtons.style.display = 'block';
    if (questionElement) questionElement.style.display = 'block';
    if (instructionTextElement) {
        instructionTextElement.style.display = 'block';
        if(typeof getTranslation === 'function') instructionTextElement.textContent = getTranslation('instruction');
    }
    if (nextButton) nextButton.disabled = true;

    if (influencerNameElement) influencerNameElement.textContent = typeof getTranslation === 'function' ? getTranslation('loading_name') : 'Loading name...';
    if (influencerImage) {
        influencerImage.src = "";
        influencerImage.alt = typeof getTranslation === 'function' ? getTranslation('loading') : 'Loading...';
        influencerImage.onerror = null;
    }
    voteBtnElements.forEach(btn => btn.disabled = true);

    try {
        const influencer = await fetchInfluencer();
        displayInfluencer(influencer);
    } catch (error) {
        handleFetchError(error);
    } finally {
        window.currentStats = null;
    }
}


voteBtnElements.forEach(button => {
    button.addEventListener('click', async (event) => {
        const guess = event.target.getAttribute('data-guess');
        if (currentInfluencerId && !button.disabled) {
            voteBtnElements.forEach(btn => btn.disabled = true);
            const stats = await submitVote(currentInfluencerId, guess);
            if (stats) {
                displayStats(stats);
                if (nextButton) nextButton.disabled = false;
            } else {
                voteBtnElements.forEach(btn => btn.disabled = false);
                if (nextButton) nextButton.disabled = true;
            }
        }
    });
});


if(nextButton) {
    nextButton.addEventListener('click', () => {
        if (nextButton) nextButton.disabled = true;
        loadNextInfluencer();
    });
}


if(disclaimerOkButton) {
    disclaimerOkButton.addEventListener('click', async () => {
        console.log("Disclaimer OK clicked");
        if (!firstInfluencerPromise) {
            console.error("First influencer promise not available! Loading manually.");
            if(nextButton) nextButton.disabled = true;
            loadNextInfluencer(); // Attempt to load directly
            if(disclaimerPopup) disclaimerPopup.style.display = 'none';
            return;
        }

        if(disclaimerPopup) disclaimerPopup.style.display = 'none';

        if (influencerNameElement) influencerNameElement.textContent = typeof getTranslation === 'function' ? getTranslation('loading_name') : 'Loading name...';
        if (influencerImage) influencerImage.alt = typeof getTranslation === 'function' ? getTranslation('loading') : 'Loading...';
        voteBtnElements.forEach(btn => btn.disabled = true);
        if(nextButton) nextButton.disabled = true;

        try {
            console.log("Awaiting first influencer promise...");
            const influencer = await firstInfluencerPromise;
            console.log("First influencer promise resolved:", influencer);
            displayInfluencer(influencer);
        } catch (error) {
            console.error("Error handling first influencer promise:", error);
            handleFetchError(error);
        }
    });
}


document.body.addEventListener('click', function(event) {
    if (event.target.classList.contains('lang-flag')) {
        const selectedLang = event.target.dataset.lang;
        if (typeof setLanguage === 'function' && typeof translations !== 'undefined' && translations[selectedLang] && selectedLang !== currentLang) {
            setLanguage(selectedLang);
        }
    }
});


document.addEventListener('DOMContentLoaded', () => {
    console.log("DOMContentLoaded event");
    if (typeof setLanguage === 'function') {
        setLanguage(currentLang);
    } else {
        console.error("setLanguage function not found. Make sure lang.js is loaded before app.js");
    }

    if(disclaimerPopup) {
        disclaimerPopup.style.display = 'flex';
        console.log("Disclaimer shown. Initiating background fetch...");
        firstInfluencerPromise = fetchInfluencer().catch(error => {
            console.warn("Background pre-fetch failed:", error.message);
            return null;
        });

    } else {
        console.warn("Disclaimer popup not found, loading influencer directly.");
        if(nextButton) nextButton.disabled = true;
        loadNextInfluencer();
    }
});