# 📁 Private Media Access (PMA)

**Private Media Access** è un plugin WordPress che ti permette di gestire file pubblici e privati nella Libreria Media, con spostamento automatico, URL protetto e thumbnail riservata.

---

## ✨ Funzionalità principali

- **Impostazione rapida della visibilità**: per ogni file puoi scegliere se renderlo pubblico o privato tramite la Libreria Media.
- **Spostamento automatico**:
  - File **privati** spostati nella cartella `/wp-content/protected-media/` (fuori dalla cartella `uploads` pubblica).
  - File **pubblici** spostati di nuovo nella cartella `uploads`.
- **URL sicuro** per i file privati (tramite `download.php`).
- **Thumbnail riservata** per i file privati, per non mostrare l’anteprima originale.
- **Log amministratore** con tutte le operazioni eseguite.
- **Creazione automatica** della cartella `protected-media` e del relativo `.htaccess` (se mancanti).
- **Compatibile** con la Media Modal di WordPress (aggiorna automaticamente URL e immagine nella sidebar).

---

## 🚀 Installazione

### Da utente semplice
Scarica il file .zip che trovi qui sopra e caricalo come plugin qui: `https://www.tua-scuola.edu.it/wp-admin/plugin-install.php`

### Da sviluppatore
1. Scarica o clona questo repository nella cartella `wp-content/plugins/` del tuo sito.
2. Attiva il plugin da **Bacheca → Plugin** in WordPress.
3. Il plugin crea automaticamente:
   - `wp-content/protected-media/` (con sottocartelle `YYYY/MM` quando necessario)
   - `wp-content/protected-media/.htaccess` con regole di blocco base (se non presente)

> Dopo l’attivazione non sono richieste altre configurazioni minime: puoi subito impostare i media come **Pubblici** o **Privati**.

---

## 🔒 Protezione file privati

Per impedire l’accesso diretto ai file nella cartella `protected-media`, assicurati che nella cartella esista un file `.htaccess` con, ad esempio:

```apache
# Impedisce l'accesso diretto ai file nella cartella protetta
Order Deny,Allow
Deny from all
```

Puoi personalizzare la protezione (ad es. consentire l’accesso solo ad utenti autenticati) modificando `download.php` o usando regole di rewrite più complesse.

---

## 🖼 Come funziona

### Quando imposti **Privato**
- Il file originale (e le sue varianti/miniature) viene spostato da `uploads/YYYY/MM/` a `protected-media/YYYY/MM/`.
- L’URL nella Libreria Media diventa:

```
https://tuosito.it/wp-content/plugins/private-media-access/download.php?file=NOMEFILE&year=YYYY&month=MM
```

- L’anteprima nella Media Modal mostra `reserved-thumbnail.png`.

### Quando imposti **Pubblico**
- Il file viene riportato in `uploads/YYYY/MM/`.
- L’URL torna quello pubblico standard di WordPress.
- L’anteprima viene aggiornata (forzando il refresh se necessario).

---

## 🧰 Log

Vai su **Bacheca → Log PMA** per:
- Visualizzare lo storico delle operazioni (successi/errori).
- Filtrare il log tramite barra di ricerca.
- **Azzera Log** o **Aggiorna Log** con gli appositi pulsanti.

I log sono salvati in `wp-content/plugins/private-media-access/pma-log.txt`.

---

## ❓ FAQ / Troubleshooting

- **Non vedo la thumbnail riservata quando rendo privato un file.**  
  Il plugin forza l’anteprima in `reserved-thumbnail.png`. Se persiste la vecchia immagine, aggiorna la pagina o svuota la cache del browser.

- **L’URL nella sidebar non cambia subito.**  
  Il plugin prova ad aggiornare in tempo reale. In alcune installazioni può essere necessario un refresh della pagina della Libreria Media.

- **La cartella `protected-media` non è stata creata.**  
  Verifica i permessi di scrittura della cartella `wp-content/`. Alla prima operazione di “Privato” il plugin proverà a crearla automaticamente.

- **Accesso negato ai file privati anche da loggato.**  
  Controlla le regole del `.htaccess` in `protected-media` o la logica di `download.php` per gestire l’autorizzazione.

---

## 🧩 Compatibilità

- Testato con WordPress ≥ 6.x.
- Funziona con il tema “Scuola”/PA (mitiga il bug storico sulla protezione degli URL media).
- PHP 7.4+ consigliato.

---

## 🔐 Sicurezza

- Gli URL dei file privati non sono diretti: passano tramite `download.php` (dove puoi aggiungere controlli su capability/ruolo utente).
- I file vengono fisicamente spostati in una directory non pubblica (`protected-media`) con `.htaccess` restrittivo.

---

## 👨‍💻 Autore

Sviluppato da **Michele Minno** con il supporto di ChatGPT.  
Se questo plugin ti è utile, lascia una ⭐ su GitHub e condividilo con altre scuole.

---
