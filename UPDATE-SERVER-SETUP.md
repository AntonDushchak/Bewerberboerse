# Update-Server-Einrichtung für das Bewerberbörse-Plugin

## Was wurde hinzugefügt

1. **Update-Klasse** (`includes/class-updater.php`) - prüft automatisch auf neue Versionen
2. **Einstellungen im Admin-Bereich** - Feld zur Angabe der Update-Server-URL
3. **Update-Prüfbutton** - manuelle Prüfung auf Updates in den Plugin-Einstellungen

## Wie es funktioniert

Das Plugin prüft automatisch über die konfigurierte Server-URL auf Updates. Wenn eine neue Version gefunden wird, erscheint sie in der WordPress-Plugin-Liste mit der Möglichkeit zur Aktualisierung.

## Update-Server-Einrichtung

Sie müssen einen Server erstellen (oder einen bestehenden verwenden), der zwei JSON-Dateien bereitstellt:

### 1. Versionsdatei (`version.json`)

URL: `https://ihr-server.com/updates/bewerberboerse/version.json`

Beispielinhalt:
```json
{
  "version": "1.5.4"
}
```

### 2. Plugin-Infodatei (`info.json`)

URL: `https://ihr-server.com/updates/bewerberboerse/info.json`

Beispielinhalt:
```json
{
  "name": "Bewerberbörse",
  "version": "1.5.4",
  "author": "EMA",
  "requires": "5.0",
  "tested": "6.4",
  "last_updated": "2024-01-15",
  "homepage": "https://example.com/bewerberboerse",
  "download_link": "https://ihr-server.com/updates/bewerberboerse/download/bewerberboerse-1.5.4.zip",
  "description": "Plugin zur Anzeige von Stellenanzeigen und Bewerbungen von Arbeitssuchenden",
  "changelog": "## Version 1.5.4\n- Fehler behoben\n- Neue Funktionen hinzugefügt"
}
```

### 3. ZIP-Datei zum Download

URL: `https://ihr-server.com/updates/bewerberboerse/download/bewerberboerse-1.5.4.zip`

Dies sollte ein ZIP-Archiv des Plugins sein (Ordner `bewerberboerse` mit allen Dateien darin).

## Alternative Optionen

### Verwendung von GitHub Releases (EMPFOHLEN - KOSTENLOS!)

**GitHub Releases sind vollständig kostenlos für öffentliche Repositorys!** Dies ist der einfachste Weg, automatische Updates einzurichten.

#### Schnelle Einrichtung für das Repository AntonDushchak/Bewerberboerse:

1. **In WordPress-Einstellungen**:
   - Gehen Sie zu **Einstellungen → Bewerberbörse**
   - Geben Sie im Feld "GitHub Repository" ein: `AntonDushchak/Bewerberboerse`
   - Speichern Sie die Einstellungen

2. **Erstellen Sie ein Release auf GitHub**:
   - Gehen Sie zu https://github.com/AntonDushchak/Bewerberboerse
   - Klicken Sie auf "Releases" → "Create a new release"
   - Geben Sie einen Versions-Tag ein (z.B.: `v1.5.4` oder einfach `1.5.4`)
   - Fügen Sie eine Release-Beschreibung hinzu (Changelog)
   - **WICHTIG**: Fügen Sie eine ZIP-Datei des Plugins im Bereich "Attach binaries" hinzu
     - Die ZIP-Datei muss den Ordner `bewerberboerse` mit allen Plugin-Dateien enthalten
   - Klicken Sie auf "Publish release"

3. **Das Plugin findet das Update automatisch!**

#### So erstellen Sie eine ZIP-Datei für das Release:

1. Erstellen Sie ein Archiv des Ordners `bewerberboerse` (alle Plugin-Dateien müssen darin enthalten sein)
2. Oder verwenden Sie den Befehl im Terminal:
   ```bash
   cd wp-content/plugins
   zip -r bewerberboerse-1.5.4.zip bewerberboerse/
   ```

#### Versionsformat:

- Sie können das Format verwenden: `1.5.4`, `v1.5.4`, `1.5.4-beta` usw.
- Das Plugin entfernt automatisch das Präfix `v`, falls vorhanden

### Verwendung von WordPress-Filtern

Sie können die URL über einen Filter in der `functions.php` Ihres Themes überschreiben:

```php
add_filter('bewerberboerse_update_api_url', function($url) {
    return 'https://ihre-custom-url.com/updates';
});
```

## Konfiguration in WordPress

### Option 1: GitHub Releases (empfohlen)

1. Gehen Sie zu **Einstellungen → Bewerberbörse**
2. Geben Sie im Feld "GitHub Repository" ein: `AntonDushchak/Bewerberboerse` (oder Ihr Repository)
3. Speichern Sie die Einstellungen
4. Erstellen Sie ein Release auf GitHub mit einer ZIP-Datei des Plugins
5. Das Plugin beginnt automatisch mit der Update-Prüfung

### Option 2: Eigener Server

1. Gehen Sie zu **Einstellungen → Bewerberbörse**
2. Lassen Sie das Feld "GitHub Repository" leer
3. Geben Sie die URL Ihres Update-Servers im Feld "Update Server URL" ein
4. Speichern Sie die Einstellungen
5. Das Plugin beginnt automatisch mit der Update-Prüfung

## Update-Prüfung

- **Automatisch**: WordPress prüft alle 12 Stunden auf Updates
- **Manuell**: Klicken Sie auf die Schaltfläche "Updates prüfen" in den Plugin-Einstellungen
- **Über das Admin-Menü**: Gehen Sie zu **Plugins** - dort wird eine Benachrichtigung angezeigt, wenn ein Update verfügbar ist

## Beispiel-Serverstruktur

```
https://ihr-server.com/updates/bewerberboerse/
├── version.json
├── info.json
└── download/
    ├── bewerberboerse-1.5.3.zip
    ├── bewerberboerse-1.5.4.zip
    └── bewerberboerse-1.6.0.zip
```

