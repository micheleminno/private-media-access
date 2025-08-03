# 📁 Private Media Access for WordPress

Gestione avanzata dei file privati e pubblici nella Media Library di WordPress, con spostamento fisico dei file, URL sicuri e interfaccia utente personalizzata.

---

## ✨ Caratteristiche principali

✅ Aggiunge un'opzione *"Pubblico / Privato"* nella scheda di ogni file nella libreria media
✅ Sposta fisicamente il file nella cartella `/protected-media` se reso privato
✅ Genera un URL protetto con accesso controllato per i file privati
✅ Sostituisce la **thumbnail** del file con un'icona riservata
✅ Log completo delle operazioni effettuate
✅ Interfaccia admin con strumenti per visualizzare e azzerare il log
✅ Compatibile con tutti i tipi di file: PDF, immagini, audio, video ecc.

---

## 🧠 Perché questo plugin?

In WordPress i media caricati nella libreria sono **sempre accessibili pubblicamente** via URL diretto, anche se la pagina che li incorpora è privata.
Questo plugin nasce per:

* garantire la **riservatezza effettiva** dei file sensibili (ad es. documenti scolastici, relazioni, verbali…)
* offrire un **controllo semplice e trasparente** su ogni file, direttamente dalla Media Library
* permettere di **disabilitare l'accesso pubblico**, con pochi clic

---

## 🔧 Come installare

1. Clona o scarica il plugin nella cartella `wp-content/plugins`:

```bash
cd wp-content/plugins
git clone https://github.com/micheleminno/private-media-access.git
```

2. Attiva il plugin da **Bacheca → Plugin**
3. Carica o modifica qualsiasi file nella Libreria Media: vedrai comparire la nuova sezione **Visibilità**

---

## 🔐 Come proteggere i file riservati

### 1. Crea la cartella `protected-media`

Il plugin userà `wp-content/protected-media/YYYY/MM` per spostare i file riservati. Se non esiste, la creerà automaticamente. Assicurati che la cartella `wp-content/protected-media` sia **scrivibile** da WordPress.

### 2. Proteggi l’accesso via `.htaccess`

Per evitare che i file nella cartella siano accessibili direttamente, crea un file:

```
wp-content/protected-media/.htaccess
```

con questo contenuto:

```apache
# Nega l'accesso diretto ai file nella cartella
<Files "*">
  Order deny,allow
  Deny from all
</Files>
```

Con questa regola, i file riservati potranno essere serviti **solo tramite PHP**, e mai direttamente.

---

## 📤 Come funziona lo spostamento

Quando un file viene marcato come **privato**:

* viene fisicamente spostato in `/protected-media/YYYY/MM/`
* l’URL diretto viene sostituito con uno del tipo:

```
https://www.tuosito.it/wp-content/plugins/private-media-access/download.php?file=nome.pdf&year=2025&month=07
```

Quando viene reso **pubblico**, torna nella cartella `uploads/YYYY/MM`.

---

## 👨‍💼 Logging delle operazioni

Tutte le operazioni vengono registrate in:

```
wp-content/plugins/private-media-access/pma-log.txt
```

Dal menu **Log PMA** nel backend puoi:

* consultare il log
* azzerarlo con un clic
* forzare il refresh

---

## 📸 Thumbnail riservate

Per i file privati, la thumbnail viene sostituita con l’immagine `reserved-thumbnail.png` inclusa nel plugin. Puoi personalizzarla sostituendo quel file con un tuo PNG.

---

## ⚠️ Avvertenze

* Il plugin non implementa una gestione avanzata dei permessi utenti. L’URL sicuro è offuscato ma **chiunque lo conosca può accedere**, a meno che non aggiungi un controllo lato PHP in `download.php`.
* Assicurati di **fare il backup dei media** se modifichi manualmente la struttura delle cartelle.

---

## 📁 Struttura del plugin

```
private-media-access/
├── admin.js                  // Comportamento UI
├── pm-accessibility-admin.css
├── reserved-thumbnail.png
├── pma-logs.php              // Visualizzazione log
├── download.php              // Accesso controllato ai file riservati
├── pma-log.txt               // Log testuale
└── private-media-access.php  // Plugin principale
```

---

## 🦭 To-do futuri

* [ ] Controllo utente nel download (es. solo utenti loggati)
* [ ] Protezione dei file anche per estensioni non previste
* [ ] Supporto multisito
* [ ] Interfaccia con ruoli utente personalizzati

---

## 👨‍💼 Autori

**Michele Minno** (docente e sviluppatore)
Con supporto tecnico e refactoring di ChatGPT (OpenAI)

---



