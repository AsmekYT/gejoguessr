const translations = {
    en: {
        title: "Gejoguessr",
        question: "Is this person...",
        button_gay: "Gay",
        button_straight: "Straight",
        stats_title: "Results:",
        stats_result: "{percentGay}% voted Gay, {percentStraight}% voted Straight.",
        button_next: "Next",
        loading: "Loading...",
        loading_name: "Loading name...",
        loading_error: "Error loading image.",
        no_more_influencers: "You've seen everyone! Reset or try again later.", // Zaktualizowany tekst
        instruction: "Rate if they are gay and see what others think!",
        disclaimer_title: "Disclaimer",
        disclaimer_text: "This game is intended for humorous purposes only and is not meant to offend or attack the individuals depicted or any social group. Sexual orientation is a private matter for each individual.",
        disclaimer_button: "I Understand"
    },
    pl: {
        title: "Gejoguessr",
        question: "Czy ta osoba jest...",
        button_gay: "Gejem",
        button_straight: "Straight",
        stats_title: "Wyniki:",
        stats_result: "{percentGay}% zagłosowało Gej, {percentStraight}% zagłosowało Straight.",
        button_next: "Następny",
        loading: "Ładowanie...",
        loading_name: "Ładowanie imienia...",
        loading_error: "Błąd ładowania obrazka.",
        no_more_influencers: "Widziałeś już wszystkich! Zresetuj lub spróbuj później.", // Zaktualizowany tekst
        instruction: "oceń czy jest gejem i sprawdź co sądzą inni!",
        disclaimer_title: "Uwaga",
        disclaimer_text: "Ta gra ma charakter wyłącznie humorystyczny i nie ma na celu obrażania ani atakowania przedstawionych osób ani żadnej grupy społecznej. Orientacja seksualna jest prywatną sprawą każdej osoby.",
        disclaimer_button: "Rozumiem"
    }
};

let currentLang = localStorage.getItem('lang') || 'pl';

function setLanguage(lang) {
    if (!translations[lang]) {
        console.warn(`Language ${lang} not supported, defaulting to pl.`);
        lang = 'pl';
    }

    currentLang = lang;
    localStorage.setItem('lang', lang);
    document.documentElement.lang = lang;

    document.querySelectorAll('[data-translate]').forEach(el => {
        const key = el.getAttribute('data-translate');
        const translation = translations[lang]?.[key];
        if (translation) {
            if (!el.classList.contains('lang-flag')) {
                el.textContent = translation;
            }
        } else if (!el.classList.contains('lang-flag')) {
            console.warn(`Missing translation for key: ${key} in language: ${lang}`);
        }
        const disclaimerPopup = document.getElementById('disclaimer-popup');
        if (disclaimerPopup && disclaimerPopup.style.display !== 'none' && disclaimerPopup.contains(el) && translation && !el.classList.contains('lang-flag')) {
            el.textContent = translation;
        }
    });

    document.querySelectorAll('.lang-flag').forEach(flag => {
        flag.classList.toggle('active', flag.dataset.lang === lang);
    });


    if (typeof displayStats === 'function' && document.getElementById('stats-area')?.style.display !== 'none' && window.currentStats) {
        displayStats(window.currentStats);
    }
}


function getTranslation(key, replacements = {}) {
    let text = translations[currentLang]?.[key] || key;
    for (const placeholder in replacements) {
        text = text.replace(`{${placeholder}}`, replacements[placeholder]);
    }
    return text;
}