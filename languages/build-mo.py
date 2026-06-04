#!/usr/bin/env python3
"""
Qaiyo Featured Image Bulk Replacer – .po + .mo builder (Qaiyo i18n standard).

Regenerates the .pot template and every supported locale's .po + .mo file in one
run. No msgfmt dependency (self-contained binary writer).

Run:  python3 build-mo.py
"""

import os
import struct
from collections import OrderedDict

DOMAIN = "qaiyo-featured-image-bulk-replacer"
PLUGIN_VERSION = "1.2.0"
HERE = os.path.dirname(os.path.abspath(__file__))

# ---------------------------------------------------------------------------
# Source strings (English msgid)
# ---------------------------------------------------------------------------

SOURCE = [
    "Qaiyo Plugins",
    # Menu
    "Featured Image Bulk Replacer",
    "Featured Images",
    # Page chrome / form
    "You do not have permission to access this page.",
    "Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.",
    "Replacement settings",
    "New featured image",
    "Choose from Media Library",
    "Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.",
    "Target scope",
    "All matching posts",
    "Only posts without a featured image",
    "Post type",
    "Category",
    "All categories",
    "Category filter applies to standard posts.",
    "After date (optional)",
    "Only posts published on or after this date are affected. Leave empty for no lower bound.",
    "Before date",
    "Only posts published strictly before this date are affected (the date itself is excluded).",
    "Alt / title text (optional)",
    "If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.",
    "Preview",
    "Test run (max %d posts)",
    "Live run (all matching posts)",
    "Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.",
    # Restore + log
    "Restore originals",
    "Restores the original featured image of every post the plugin previously changed.",
    "Restorable posts:",
    "Restore original featured images",
    "Nothing to restore right now.",
    "Last run",
    "No runs yet.",
    "Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.",
    "test",
    "live",
    "Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.",
    "Qaiyo Featured Image Bulk Replacer v%s — by",
    # Errors / messages
    "You do not have permission to perform this action.",
    "Please enter a valid “before” date.",
    "Please choose an image from the Media Library or paste an image URL.",
    "No Media Library image was found for that URL. Make sure the image is already uploaded.",
    "The job expired. Please start again.",
    # Preview table
    "skip (already correct)",
    "replace",
    "Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.",
    "No posts match these criteria.",
    "Showing the first %1$d of %2$d matching posts.",
    "ID",
    "Image",
    "Title",
    "Date",
    "Action",
    # JS (localized)
    "Select the new featured image",
    "Use this image",
    "Loading preview…",
    "Could not load the preview.",
    "Please choose an image and a date first.",
    "Preparing…",
    "Processing…",
    "Nothing to process.",
    "Something went wrong. Please try again.",
    "Run the TEST replacement on the first few matching posts?",
    "Run the LIVE bulk featured-image replacement on ALL matching posts?",
    "Restore the original featured image of every affected post?",
    # Pro teaser
    "Pro features",
    "Background processing & scheduling",
    "Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.",
    "Automatic featured image",
    "Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.",
    "Run history & per-run restore",
    "Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.",
    "Unlock in Pro →",
    "Qaiyo Featured Image Bulk Replacer Pro",
    "Get Pro",
]

PLURALS = []

# ---------------------------------------------------------------------------
# Translations
# ---------------------------------------------------------------------------

HU = {
    "Qaiyo Plugins": "Qaiyo pluginok",
    "Featured Image Bulk Replacer": "Kiemelt kép tömeges csere",
    "Featured Images": "Kiemelt képek",
    "You do not have permission to access this page.": "Nincs jogosultságod az oldal megnyitásához.",
    "Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.":
        "Cseréld le közzétett bejegyzések kiemelt képét egy a Médiatárban már meglévő képre. Szűrj dátumra, hatókörre és kategóriára, nézd meg az előnézetet, majd futtasd biztonságos kötegekben — és bármikor visszaállíthatod az eredetit.",
    "Replacement settings": "Csere beállításai",
    "New featured image": "Új kiemelt kép",
    "Choose from Media Library": "Választás a Médiatárból",
    "Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.":
        "Válassz egy képet a Médiatárból, vagy illeszd be egy már feltöltött kép URL-jét. A plugin semmit nem tölt le.",
    "Target scope": "Hatókör",
    "All matching posts": "Minden érintett bejegyzés",
    "Only posts without a featured image": "Csak a kiemelt kép nélküli bejegyzések",
    "Post type": "Bejegyzéstípus",
    "Category": "Kategória",
    "All categories": "Összes kategória",
    "Category filter applies to standard posts.": "A kategóriaszűrő a normál bejegyzésekre vonatkozik.",
    "After date (optional)": "Ettől a dátumtól (opcionális)",
    "Only posts published on or after this date are affected. Leave empty for no lower bound.":
        "Csak az e dátumon vagy utána közzétett bejegyzések érintettek. Hagyd üresen, ha nincs alsó határ.",
    "Before date": "Eddig a dátumig",
    "Only posts published strictly before this date are affected (the date itself is excluded).":
        "Csak az e dátumnál szigorúan korábban közzétett bejegyzések érintettek (maga a nap nem tartozik bele).",
    "Alt / title text (optional)": "Alt / title szöveg (opcionális)",
    "If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.":
        "Ha kitöltöd, a kiválasztott médiatári kép alt és title attribútuma egyszer erre a szövegre lesz állítva. Hagyd üresen, ha nem akarod megváltoztatni.",
    "Preview": "Előnézet",
    "Test run (max %d posts)": "Teszt futtatás (max %d bejegyzés)",
    "Live run (all matching posts)": "Éles futtatás (összes érintett bejegyzés)",
    "Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.":
        "Tipp: éles futtatás előtt készíts adatbázis-mentést. A bejegyzések kis kötegekben dolgozódnak fel, így a nagy oldalak sem futnak timeoutba. Minden módosított bejegyzés eredeti kiemelt képe eltárolódik, így lent visszaállítható.",
    "Restore originals": "Eredetiek visszaállítása",
    "Restores the original featured image of every post the plugin previously changed.":
        "Visszaállítja minden olyan bejegyzés eredeti kiemelt képét, amelyet a plugin korábban módosított.",
    "Restorable posts:": "Visszaállítható bejegyzések:",
    "Restore original featured images": "Eredeti kiemelt képek visszaállítása",
    "Nothing to restore right now.": "Jelenleg nincs mit visszaállítani.",
    "Last run": "Utolsó futtatás",
    "No runs yet.": "Még nem volt futtatás.",
    "Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.":
        "Visszaállítás (%1$s) — visszaállítva: %2$d, kihagyva: %3$d, hiba: %4$d.",
    "test": "teszt",
    "live": "éles",
    "Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.":
        "Csere (%1$s) — %2$s — találat: %3$d, lecserélve: %4$d, kihagyva: %5$d, hiba: %6$d.",
    "Qaiyo Featured Image Bulk Replacer v%s — by": "Qaiyo Featured Image Bulk Replacer v%s — készítette:",
    "You do not have permission to perform this action.": "Nincs jogosultságod a művelet végrehajtásához.",
    "Please enter a valid “before” date.": "Adj meg egy érvényes „eddig” dátumot.",
    "Please choose an image from the Media Library or paste an image URL.":
        "Válassz egy képet a Médiatárból, vagy illessz be egy kép URL-t.",
    "No Media Library image was found for that URL. Make sure the image is already uploaded.":
        "Nem található médiatári kép ehhez az URL-hez. Győződj meg róla, hogy a kép már fel van töltve.",
    "The job expired. Please start again.": "A feladat lejárt. Kezdd elölről.",
    "skip (already correct)": "kihagyva (már megfelelő)",
    "replace": "csere",
    "Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.":
        "Médiatári csatolmány ID: %1$d. Találat: %2$d bejegyzés — cserélendő: %3$d, kihagyandó: %4$d.",
    "No posts match these criteria.": "Nincs a feltételeknek megfelelő bejegyzés.",
    "Showing the first %1$d of %2$d matching posts.": "A %2$d találatból az első %1$d látható.",
    "ID": "ID",
    "Image": "Kép",
    "Title": "Cím",
    "Date": "Dátum",
    "Action": "Művelet",
    "Select the new featured image": "Válaszd ki az új kiemelt képet",
    "Use this image": "Ezt a képet használom",
    "Loading preview…": "Előnézet betöltése…",
    "Could not load the preview.": "Az előnézet betöltése nem sikerült.",
    "Please choose an image and a date first.": "Először válassz egy képet és egy dátumot.",
    "Preparing…": "Előkészítés…",
    "Processing…": "Feldolgozás…",
    "Nothing to process.": "Nincs mit feldolgozni.",
    "Something went wrong. Please try again.": "Valami hiba történt. Próbáld újra.",
    "Run the TEST replacement on the first few matching posts?":
        "Lefuttatod a TESZT cserét az első néhány érintett bejegyzésen?",
    "Run the LIVE bulk featured-image replacement on ALL matching posts?":
        "Lefuttatod az ÉLES tömeges kiemelt kép cserét AZ ÖSSZES érintett bejegyzésen?",
    "Restore the original featured image of every affected post?":
        "Visszaállítod minden érintett bejegyzés eredeti kiemelt képét?",
    "Pro features": "Pro funkciók",
    "Background processing & scheduling": "Háttér-feldolgozás és ütemezés",
    "Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.":
        "Nagy futtatások átadása egy szerverkímélő háttér-sornak adaptív kötegmérettel, lassítással és csúcsidőn kívüli ablakkal — vagy ütemezés későbbre. Nem kell nyitva tartani a böngészőt.",
    "Automatic featured image": "Automatikus kiemelt kép",
    "Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.":
        "Automatikusan beállít egy tartalék kiemelt képet, amikor egy bejegyzés kép nélkül jelenik meg — bejegyzéstípus és kategória szerint, opcionálisan a tartalom első képét használva.",
    "Run history & per-run restore": "Futtatás-előzmény és futtatásonkénti visszaállítás",
    "Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.":
        "Megőrzi minden futtatás előzményét, és bármelyik futtatás külön visszavonható, plusz e-mail riport, amikor egy háttérfeladat befejeződik.",
    "Unlock in Pro →": "Feloldás a Pro-ban →",
    "Qaiyo Featured Image Bulk Replacer Pro": "Qaiyo Featured Image Bulk Replacer Pro",
    "Get Pro": "Pro beszerzése",
}

DE = {
    "Qaiyo Plugins": "Qaiyo Plugins",
    "Featured Image Bulk Replacer": "Beitragsbild-Massenersetzung",
    "Featured Images": "Beitragsbilder",
    "You do not have permission to access this page.": "Du hast keine Berechtigung, diese Seite aufzurufen.",
    "Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.":
        "Ersetze das Beitragsbild veröffentlichter Beiträge durch ein Bild, das bereits in deiner Mediathek vorhanden ist. Filtere nach Datum, Umfang und Kategorie, sieh dir die Vorschau an und führe es dann in sicheren Stapeln aus — die Originale lassen sich jederzeit wiederherstellen.",
    "Replacement settings": "Ersetzungseinstellungen",
    "New featured image": "Neues Beitragsbild",
    "Choose from Media Library": "Aus Mediathek wählen",
    "Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.":
        "Wähle ein Bild aus der Mediathek oder füge die URL eines bereits hochgeladenen Bilds ein. Es wird nichts heruntergeladen.",
    "Target scope": "Geltungsbereich",
    "All matching posts": "Alle passenden Beiträge",
    "Only posts without a featured image": "Nur Beiträge ohne Beitragsbild",
    "Post type": "Beitragstyp",
    "Category": "Kategorie",
    "All categories": "Alle Kategorien",
    "Category filter applies to standard posts.": "Der Kategoriefilter gilt für Standardbeiträge.",
    "After date (optional)": "Ab Datum (optional)",
    "Only posts published on or after this date are affected. Leave empty for no lower bound.":
        "Betroffen sind nur Beiträge, die an oder nach diesem Datum veröffentlicht wurden. Leer lassen für keine Untergrenze.",
    "Before date": "Vor Datum",
    "Only posts published strictly before this date are affected (the date itself is excluded).":
        "Betroffen sind nur Beiträge, die strikt vor diesem Datum veröffentlicht wurden (das Datum selbst ist ausgenommen).",
    "Alt / title text (optional)": "Alt-/Titeltext (optional)",
    "If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.":
        "Wenn ausgefüllt, werden Alt-Text und Titel des gewählten Mediathek-Bilds einmalig auf diesen Text gesetzt. Leer lassen, um sie unverändert zu lassen.",
    "Preview": "Vorschau",
    "Test run (max %d posts)": "Testlauf (max. %d Beiträge)",
    "Live run (all matching posts)": "Live-Lauf (alle passenden Beiträge)",
    "Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.":
        "Tipp: Sichere deine Datenbank vor einem Live-Lauf. Beiträge werden in kleinen Stapeln verarbeitet, damit große Websites kein Timeout erleiden. Das ursprüngliche Beitragsbild jedes geänderten Beitrags wird gespeichert und kann unten wiederhergestellt werden.",
    "Restore originals": "Originale wiederherstellen",
    "Restores the original featured image of every post the plugin previously changed.":
        "Stellt das ursprüngliche Beitragsbild jedes zuvor geänderten Beitrags wieder her.",
    "Restorable posts:": "Wiederherstellbare Beiträge:",
    "Restore original featured images": "Ursprüngliche Beitragsbilder wiederherstellen",
    "Nothing to restore right now.": "Derzeit gibt es nichts wiederherzustellen.",
    "Last run": "Letzter Lauf",
    "No runs yet.": "Noch keine Läufe.",
    "Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.":
        "Wiederherstellung (%1$s) — wiederhergestellt: %2$d, übersprungen: %3$d, Fehler: %4$d.",
    "test": "Test",
    "live": "Live",
    "Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.":
        "Ersetzung (%1$s) — %2$s — Treffer: %3$d, ersetzt: %4$d, übersprungen: %5$d, Fehler: %6$d.",
    "Qaiyo Featured Image Bulk Replacer v%s — by": "Qaiyo Featured Image Bulk Replacer v%s — von",
    "You do not have permission to perform this action.": "Du hast keine Berechtigung für diese Aktion.",
    "Please enter a valid “before” date.": "Bitte gib ein gültiges „Vor“-Datum ein.",
    "Please choose an image from the Media Library or paste an image URL.":
        "Bitte wähle ein Bild aus der Mediathek oder füge eine Bild-URL ein.",
    "No Media Library image was found for that URL. Make sure the image is already uploaded.":
        "Für diese URL wurde kein Mediathek-Bild gefunden. Stelle sicher, dass das Bild bereits hochgeladen ist.",
    "The job expired. Please start again.": "Der Vorgang ist abgelaufen. Bitte erneut starten.",
    "skip (already correct)": "überspringen (bereits korrekt)",
    "replace": "ersetzen",
    "Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.":
        "Mediathek-Anhang-ID: %1$d. Treffer: %2$d Beiträge — zu ersetzen: %3$d, zu überspringen: %4$d.",
    "No posts match these criteria.": "Keine Beiträge entsprechen diesen Kriterien.",
    "Showing the first %1$d of %2$d matching posts.": "Es werden die ersten %1$d von %2$d Treffern angezeigt.",
    "ID": "ID",
    "Image": "Bild",
    "Title": "Titel",
    "Date": "Datum",
    "Action": "Aktion",
    "Select the new featured image": "Neues Beitragsbild auswählen",
    "Use this image": "Dieses Bild verwenden",
    "Loading preview…": "Vorschau wird geladen…",
    "Could not load the preview.": "Die Vorschau konnte nicht geladen werden.",
    "Please choose an image and a date first.": "Bitte wähle zuerst ein Bild und ein Datum.",
    "Preparing…": "Vorbereitung…",
    "Processing…": "Verarbeitung…",
    "Nothing to process.": "Nichts zu verarbeiten.",
    "Something went wrong. Please try again.": "Etwas ist schiefgelaufen. Bitte versuche es erneut.",
    "Run the TEST replacement on the first few matching posts?":
        "Die TEST-Ersetzung für die ersten passenden Beiträge ausführen?",
    "Run the LIVE bulk featured-image replacement on ALL matching posts?":
        "Die LIVE-Massenersetzung der Beitragsbilder für ALLE passenden Beiträge ausführen?",
    "Restore the original featured image of every affected post?":
        "Das ursprüngliche Beitragsbild jedes betroffenen Beitrags wiederherstellen?",
    "Pro features": "Pro-Funktionen",
    "Background processing & scheduling": "Hintergrundverarbeitung & Zeitplanung",
    "Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.":
        "Lagere große Läufe in eine serverfreundliche Hintergrund-Warteschlange mit adaptiver Stapelgröße, Drosselung und Nebenzeitenfenster aus — oder plane einen Lauf für später. Der Browser muss nicht geöffnet bleiben.",
    "Automatic featured image": "Automatisches Beitragsbild",
    "Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.":
        "Setzt automatisch ein Ersatz-Beitragsbild, wenn ein Beitrag ohne Bild veröffentlicht wird — nach Beitragstyp und Kategorie, optional anhand des ersten Bilds im Inhalt.",
    "Run history & per-run restore": "Laufverlauf & Wiederherstellung pro Lauf",
    "Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.":
        "Führt einen Verlauf jedes Laufs und macht jeden einzelnen Lauf unabhängig rückgängig, plus E-Mail-Bericht, wenn ein Hintergrundjob fertig ist.",
    "Unlock in Pro →": "In Pro freischalten →",
    "Qaiyo Featured Image Bulk Replacer Pro": "Qaiyo Featured Image Bulk Replacer Pro",
    "Get Pro": "Pro holen",
}

FR = {
    "Qaiyo Plugins": "Plugins Qaiyo",
    "Featured Image Bulk Replacer": "Remplacement groupé des images mises en avant",
    "Featured Images": "Images mises en avant",
    "You do not have permission to access this page.": "Vous n'avez pas l'autorisation d'accéder à cette page.",
    "Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.":
        "Remplacez l'image mise en avant des articles publiés par une image déjà présente dans votre médiathèque. Filtrez par date, portée et catégorie, prévisualisez le résultat, puis lancez l'opération par lots sûrs — et restaurez les originaux à tout moment.",
    "Replacement settings": "Réglages du remplacement",
    "New featured image": "Nouvelle image mise en avant",
    "Choose from Media Library": "Choisir dans la médiathèque",
    "Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.":
        "Choisissez une image dans la médiathèque ou collez l'URL d'une image déjà importée. Rien n'est téléchargé.",
    "Target scope": "Portée",
    "All matching posts": "Tous les articles concernés",
    "Only posts without a featured image": "Uniquement les articles sans image mise en avant",
    "Post type": "Type de contenu",
    "Category": "Catégorie",
    "All categories": "Toutes les catégories",
    "Category filter applies to standard posts.": "Le filtre de catégorie s'applique aux articles standards.",
    "After date (optional)": "À partir de la date (facultatif)",
    "Only posts published on or after this date are affected. Leave empty for no lower bound.":
        "Seuls les articles publiés à cette date ou après sont concernés. Laissez vide pour aucune limite inférieure.",
    "Before date": "Avant la date",
    "Only posts published strictly before this date are affected (the date itself is excluded).":
        "Seuls les articles publiés strictement avant cette date sont concernés (la date elle-même est exclue).",
    "Alt / title text (optional)": "Texte alt / titre (facultatif)",
    "If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.":
        "Si rempli, le texte alternatif et le titre de l'image choisie sont définis une fois sur ce texte. Laissez vide pour ne pas les modifier.",
    "Preview": "Aperçu",
    "Test run (max %d posts)": "Test (max %d articles)",
    "Live run (all matching posts)": "Exécution réelle (tous les articles concernés)",
    "Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.":
        "Astuce : sauvegardez votre base de données avant une exécution réelle. Les articles sont traités par petits lots afin que les grands sites n'atteignent pas le délai d'expiration. L'image d'origine de chaque article modifié est conservée pour la restaurer ci-dessous.",
    "Restore originals": "Restaurer les originaux",
    "Restores the original featured image of every post the plugin previously changed.":
        "Restaure l'image mise en avant d'origine de chaque article précédemment modifié par l'extension.",
    "Restorable posts:": "Articles restaurables :",
    "Restore original featured images": "Restaurer les images d'origine",
    "Nothing to restore right now.": "Rien à restaurer pour le moment.",
    "Last run": "Dernière exécution",
    "No runs yet.": "Aucune exécution pour l'instant.",
    "Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.":
        "Restauration (%1$s) — restaurés : %2$d, ignorés : %3$d, erreurs : %4$d.",
    "test": "test",
    "live": "réel",
    "Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.":
        "Remplacement (%1$s) — %2$s — correspondances : %3$d, remplacés : %4$d, ignorés : %5$d, erreurs : %6$d.",
    "Qaiyo Featured Image Bulk Replacer v%s — by": "Qaiyo Featured Image Bulk Replacer v%s — par",
    "You do not have permission to perform this action.": "Vous n'avez pas l'autorisation d'effectuer cette action.",
    "Please enter a valid “before” date.": "Veuillez saisir une date « avant » valide.",
    "Please choose an image from the Media Library or paste an image URL.":
        "Veuillez choisir une image dans la médiathèque ou coller une URL d'image.",
    "No Media Library image was found for that URL. Make sure the image is already uploaded.":
        "Aucune image de la médiathèque n'a été trouvée pour cette URL. Vérifiez que l'image est déjà importée.",
    "The job expired. Please start again.": "La tâche a expiré. Veuillez recommencer.",
    "skip (already correct)": "ignorer (déjà correct)",
    "replace": "remplacer",
    "Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.":
        "ID du fichier joint : %1$d. Correspondances : %2$d articles — à remplacer : %3$d, à ignorer : %4$d.",
    "No posts match these criteria.": "Aucun article ne correspond à ces critères.",
    "Showing the first %1$d of %2$d matching posts.": "Affichage des %1$d premiers articles sur %2$d.",
    "ID": "ID",
    "Image": "Image",
    "Title": "Titre",
    "Date": "Date",
    "Action": "Action",
    "Select the new featured image": "Sélectionner la nouvelle image mise en avant",
    "Use this image": "Utiliser cette image",
    "Loading preview…": "Chargement de l'aperçu…",
    "Could not load the preview.": "Impossible de charger l'aperçu.",
    "Please choose an image and a date first.": "Veuillez d'abord choisir une image et une date.",
    "Preparing…": "Préparation…",
    "Processing…": "Traitement…",
    "Nothing to process.": "Rien à traiter.",
    "Something went wrong. Please try again.": "Une erreur s'est produite. Veuillez réessayer.",
    "Run the TEST replacement on the first few matching posts?":
        "Lancer le remplacement de TEST sur les premiers articles concernés ?",
    "Run the LIVE bulk featured-image replacement on ALL matching posts?":
        "Lancer le remplacement groupé RÉEL des images mises en avant sur TOUS les articles concernés ?",
    "Restore the original featured image of every affected post?":
        "Restaurer l'image mise en avant d'origine de chaque article concerné ?",
    "Pro features": "Fonctionnalités Pro",
    "Background processing & scheduling": "Traitement en arrière-plan et planification",
    "Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.":
        "Déléguez les grandes exécutions à une file d'attente en arrière-plan respectueuse du serveur, avec taille de lot adaptative, limitation et plage creuse — ou planifiez une exécution pour plus tard. Inutile de garder le navigateur ouvert.",
    "Automatic featured image": "Image mise en avant automatique",
    "Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.":
        "Définit automatiquement une image mise en avant de secours lorsqu'un article est publié sans image — par type de contenu et catégorie, en utilisant éventuellement la première image du contenu.",
    "Run history & per-run restore": "Historique des exécutions et restauration par exécution",
    "Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.":
        "Conserve l'historique de chaque exécution et permet d'annuler indépendamment n'importe laquelle, avec un rapport par e-mail à la fin d'une tâche en arrière-plan.",
    "Unlock in Pro →": "Débloquer dans Pro →",
    "Qaiyo Featured Image Bulk Replacer Pro": "Qaiyo Featured Image Bulk Replacer Pro",
    "Get Pro": "Obtenir Pro",
}

ES = {
    "Qaiyo Plugins": "Plugins Qaiyo",
    "Featured Image Bulk Replacer": "Reemplazo masivo de imagen destacada",
    "Featured Images": "Imágenes destacadas",
    "You do not have permission to access this page.": "No tienes permiso para acceder a esta página.",
    "Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.":
        "Reemplaza la imagen destacada de las entradas publicadas por una imagen que ya existe en tu biblioteca de medios. Filtra por fecha, alcance y categoría, previsualiza el resultado y ejecútalo en lotes seguros — y restaura los originales cuando quieras.",
    "Replacement settings": "Ajustes del reemplazo",
    "New featured image": "Nueva imagen destacada",
    "Choose from Media Library": "Elegir de la biblioteca de medios",
    "Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.":
        "Elige una imagen de la biblioteca de medios o pega la URL de una imagen ya subida. No se descarga nada.",
    "Target scope": "Alcance",
    "All matching posts": "Todas las entradas coincidentes",
    "Only posts without a featured image": "Solo entradas sin imagen destacada",
    "Post type": "Tipo de contenido",
    "Category": "Categoría",
    "All categories": "Todas las categorías",
    "Category filter applies to standard posts.": "El filtro de categoría se aplica a las entradas estándar.",
    "After date (optional)": "Desde la fecha (opcional)",
    "Only posts published on or after this date are affected. Leave empty for no lower bound.":
        "Solo se ven afectadas las entradas publicadas en esta fecha o después. Déjalo vacío para no poner límite inferior.",
    "Before date": "Antes de la fecha",
    "Only posts published strictly before this date are affected (the date itself is excluded).":
        "Solo se ven afectadas las entradas publicadas estrictamente antes de esta fecha (la fecha en sí queda excluida).",
    "Alt / title text (optional)": "Texto alt / título (opcional)",
    "If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.":
        "Si se rellena, el texto alternativo y el título de la imagen elegida se establecen una vez con este texto. Déjalo vacío para no modificarlos.",
    "Preview": "Vista previa",
    "Test run (max %d posts)": "Prueba (máx. %d entradas)",
    "Live run (all matching posts)": "Ejecución real (todas las entradas coincidentes)",
    "Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.":
        "Consejo: haz una copia de seguridad de la base de datos antes de una ejecución real. Las entradas se procesan en lotes pequeños para que los sitios grandes no agoten el tiempo de espera. La imagen destacada original de cada entrada modificada se guarda para poder restaurarla abajo.",
    "Restore originals": "Restaurar originales",
    "Restores the original featured image of every post the plugin previously changed.":
        "Restaura la imagen destacada original de cada entrada que el plugin haya modificado anteriormente.",
    "Restorable posts:": "Entradas restaurables:",
    "Restore original featured images": "Restaurar imágenes destacadas originales",
    "Nothing to restore right now.": "Ahora mismo no hay nada que restaurar.",
    "Last run": "Última ejecución",
    "No runs yet.": "Aún no hay ejecuciones.",
    "Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.":
        "Restauración (%1$s) — restauradas: %2$d, omitidas: %3$d, errores: %4$d.",
    "test": "prueba",
    "live": "real",
    "Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.":
        "Reemplazo (%1$s) — %2$s — coincidencias: %3$d, reemplazadas: %4$d, omitidas: %5$d, errores: %6$d.",
    "Qaiyo Featured Image Bulk Replacer v%s — by": "Qaiyo Featured Image Bulk Replacer v%s — por",
    "You do not have permission to perform this action.": "No tienes permiso para realizar esta acción.",
    "Please enter a valid “before” date.": "Introduce una fecha «antes de» válida.",
    "Please choose an image from the Media Library or paste an image URL.":
        "Elige una imagen de la biblioteca de medios o pega una URL de imagen.",
    "No Media Library image was found for that URL. Make sure the image is already uploaded.":
        "No se encontró ninguna imagen de la biblioteca de medios para esa URL. Asegúrate de que la imagen ya esté subida.",
    "The job expired. Please start again.": "La tarea ha caducado. Empieza de nuevo.",
    "skip (already correct)": "omitir (ya correcta)",
    "replace": "reemplazar",
    "Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.":
        "ID de adjunto de la biblioteca: %1$d. Coincidencias: %2$d entradas — a reemplazar: %3$d, a omitir: %4$d.",
    "No posts match these criteria.": "Ninguna entrada coincide con estos criterios.",
    "Showing the first %1$d of %2$d matching posts.": "Mostrando las primeras %1$d de %2$d entradas coincidentes.",
    "ID": "ID",
    "Image": "Imagen",
    "Title": "Título",
    "Date": "Fecha",
    "Action": "Acción",
    "Select the new featured image": "Selecciona la nueva imagen destacada",
    "Use this image": "Usar esta imagen",
    "Loading preview…": "Cargando vista previa…",
    "Could not load the preview.": "No se pudo cargar la vista previa.",
    "Please choose an image and a date first.": "Primero elige una imagen y una fecha.",
    "Preparing…": "Preparando…",
    "Processing…": "Procesando…",
    "Nothing to process.": "Nada que procesar.",
    "Something went wrong. Please try again.": "Algo salió mal. Inténtalo de nuevo.",
    "Run the TEST replacement on the first few matching posts?":
        "¿Ejecutar el reemplazo de PRUEBA en las primeras entradas coincidentes?",
    "Run the LIVE bulk featured-image replacement on ALL matching posts?":
        "¿Ejecutar el reemplazo masivo REAL de imágenes destacadas en TODAS las entradas coincidentes?",
    "Restore the original featured image of every affected post?":
        "¿Restaurar la imagen destacada original de cada entrada afectada?",
    "Pro features": "Funciones Pro",
    "Background processing & scheduling": "Procesamiento en segundo plano y programación",
    "Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.":
        "Delega las ejecuciones grandes a una cola en segundo plano respetuosa con el servidor, con tamaño de lote adaptativo, limitación y ventana de horas valle — o programa una ejecución para más tarde. No hace falta mantener el navegador abierto.",
    "Automatic featured image": "Imagen destacada automática",
    "Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.":
        "Establece automáticamente una imagen destacada de reserva cuando se publica una entrada sin ella — por tipo de contenido y categoría, opcionalmente usando la primera imagen del contenido.",
    "Run history & per-run restore": "Historial de ejecuciones y restauración por ejecución",
    "Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.":
        "Guarda un historial de cada ejecución y permite revertir cualquiera de forma independiente, además de un informe por correo cuando termina una tarea en segundo plano.",
    "Unlock in Pro →": "Desbloquear en Pro →",
    "Qaiyo Featured Image Bulk Replacer Pro": "Qaiyo Featured Image Bulk Replacer Pro",
    "Get Pro": "Obtener Pro",
}

LOCALES = {
    "hu_HU": ("hu", "nplurals=2; plural=(n != 1);", HU),
    "de_DE": ("de", "nplurals=2; plural=(n != 1);", DE),
    "fr_FR": ("fr", "nplurals=2; plural=(n > 1);", FR),
    "es_ES": ("es", "nplurals=2; plural=(n != 1);", ES),
}

# ---------------------------------------------------------------------------
# Writers
# ---------------------------------------------------------------------------

def po_escape(s):
    return s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n")


def write_po(path, lang_code, plural_forms, translations):
    lines = []
    lines.append('msgid ""')
    lines.append('msgstr ""')
    lines.append('"Project-Id-Version: ' + DOMAIN + ' ' + PLUGIN_VERSION + '\\n"')
    lines.append('"Report-Msgid-Bugs-To: https://qaiyo-plugins.com\\n"')
    lines.append('"Language: ' + lang_code + '\\n"')
    lines.append('"MIME-Version: 1.0\\n"')
    lines.append('"Content-Type: text/plain; charset=UTF-8\\n"')
    lines.append('"Content-Transfer-Encoding: 8bit\\n"')
    lines.append('"Plural-Forms: ' + plural_forms + '\\n"')
    lines.append('"X-Generator: Qaiyo build-mo.py\\n"')
    lines.append('')

    seen = set()
    for src in SOURCE:
        if src in seen:
            continue
        seen.add(src)
        trans = "" if translations is None else translations.get(src, "")
        lines.append('msgid "' + po_escape(src) + '"')
        lines.append('msgstr "' + po_escape(trans) + '"')
        lines.append('')

    with open(path, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def write_mo(path, lang_code, plural_forms, translations):
    """Minimal .mo writer (GNU format)."""
    entries = OrderedDict()

    meta = (
        "Project-Id-Version: " + DOMAIN + " " + PLUGIN_VERSION + "\n"
        "Language: " + lang_code + "\n"
        "MIME-Version: 1.0\n"
        "Content-Type: text/plain; charset=UTF-8\n"
        "Content-Transfer-Encoding: 8bit\n"
        "Plural-Forms: " + plural_forms + "\n"
    )
    entries[""] = meta

    seen = set()
    for src in SOURCE:
        if src in seen:
            continue
        seen.add(src)
        t = translations.get(src) if translations else None
        if t:
            entries[src] = t

    keys = list(entries.keys())
    keys.sort()

    encoded_keys = [k.encode("utf-8") for k in keys]
    encoded_vals = [entries[k].encode("utf-8") for k in keys]

    n = len(keys)
    pos = 7 * 4  # header size
    keys_table_offset = pos
    pos += n * 8
    vals_table_offset = pos
    pos += n * 8

    key_offsets = []
    val_offsets = []
    for k in encoded_keys:
        key_offsets.append((len(k), pos))
        pos += len(k) + 1
    for v in encoded_vals:
        val_offsets.append((len(v), pos))
        pos += len(v) + 1

    output = b""
    output += struct.pack("Iiiiiii", 0x950412DE, 0, n, keys_table_offset,
                          vals_table_offset, 0, 0)
    for length, offset in key_offsets:
        output += struct.pack("ii", length, offset)
    for length, offset in val_offsets:
        output += struct.pack("ii", length, offset)
    for k in encoded_keys:
        output += k + b"\x00"
    for v in encoded_vals:
        output += v + b"\x00"

    with open(path, "wb") as f:
        f.write(output)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    pot_path = os.path.join(HERE, DOMAIN + ".pot")
    write_po(pot_path, "", "nplurals=2; plural=(n != 1);", None)
    print("OK " + pot_path)

    for locale, (lang_code, plurals, trans) in LOCALES.items():
        po_path = os.path.join(HERE, DOMAIN + "-" + locale + ".po")
        mo_path = os.path.join(HERE, DOMAIN + "-" + locale + ".mo")
        write_po(po_path, lang_code, plurals, trans)
        write_mo(mo_path, lang_code, plurals, trans)
        print("OK " + po_path)
        print("OK " + mo_path)


if __name__ == "__main__":
    main()
