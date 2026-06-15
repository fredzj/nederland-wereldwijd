# Nederland Wereldwijd – open data importer

Haalt **landen**, **reisadviezen** en **nood-informatie** op bij de open data API
van [Nederland Wereldwijd](https://www.nederlandwereldwijd.nl/open-data) en slaat
deze op in een MariaDB-database.

- **PHP** 8.4
- **MariaDB** 10.6.20
- Geen externe PHP-afhankelijkheden (gebruikt `curl`, `pdo_mysql` en `json`)

## Bron

- Open data: <https://www.nederlandwereldwijd.nl/open-data>
- API-details: <https://apis.developer.overheid.nl/apis/ziOaBu6HR>
- Basis-URL: `https://opendata.nederlandwereldwijd.nl/v2`

Gebruikte endpoints:

| Gegevens        | Endpoint                                                                  |
| --------------- | ------------------------------------------------------------------------- |
| Landen          | `/sources/nederlandwereldwijd/infotypes/countries`                        |
| Reisadviezen    | `/sources/nederlandwereldwijd/infotypes/traveladvice` (+ detail per land) |
| Nood-informatie | `/sources/nederlandwereldwijd/infotypes/hulp-bij-nood` (+ detail per id)  |

## Projectstructuur

```
.
├── bin/import.php              # CLI-startpunt
├── config/config.php           # Configuratie (leest .env)
├── database/schema.sql         # Databaseschema
├── src/
│   ├── Api/                     # HTTP-client en API-service
│   ├── Database/               # PDO-verbinding
│   ├── Importer/               # Import-logica per gegevenssoort
│   ├── Repository/             # Upsert naar de database
│   └── Support/                # Env-loader, logger, hulpfuncties
├── .env.example
└── composer.json
```

## Installatie

1. **Database aanmaken** (schema en tabellen):

   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. **Configuratie instellen**:

   ```bash
   cp .env.example .env
   ```

   Pas in `.env` de databasegegevens aan (`DB_HOST`, `DB_NAME`, `DB_USER`,
   `DB_PASSWORD`, …).

3. **Autoloader** (optioneel). Het project draait ook zonder Composer dankzij een
   ingebouwde PSR-4 fallback. Met Composer:

   ```bash
   composer install
   ```

## Gebruik

Alles importeren:

```bash
php bin/import.php all
```

Of per onderdeel:

```bash
php bin/import.php countries
php bin/import.php traveladvice
php bin/import.php emergency
```

Via Composer-scripts:

```bash
composer run import
composer run import:countries
composer run import:traveladvice
composer run import:emergency
```

De import is **idempotent**: bestaande records worden bijgewerkt (upsert op de
primaire sleutel), nieuwe records worden toegevoegd. Je kunt de import dus veilig
periodiek (bijv. via cron) opnieuw draaien om de gegevens actueel te houden.

## Databasetabellen

- **`countries`** – landen met ISO-code, naam en bron-URL's.
- **`travel_advice`** – reisadviezen met inhoud, kleurcodering (`classification`)
  en bijbehorende bestanden/kaarten (als JSON).
- **`emergency_infos`** – nood-informatie ("wat te doen bij nood") met inhoud,
  locaties en talen (als JSON).

Geneste API-structuren (zoals `content`, `files`, `locations`) worden als JSON in
`LONGTEXT`-kolommen opgeslagen.

## Configuratie-opties (`.env`)

| Variabele         | Standaard                                       | Omschrijving                          |
| ----------------- | ----------------------------------------------- | ------------------------------------- |
| `DB_HOST`         | `127.0.0.1`                                     | Databasehost                          |
| `DB_PORT`         | `3306`                                          | Databasepoort                         |
| `DB_NAME`         | `nederland_wereldwijd`                          | Databasenaam                          |
| `DB_USER`         | `root`                                          | Gebruikersnaam                        |
| `DB_PASSWORD`     | _(leeg)_                                         | Wachtwoord                            |
| `API_BASE_URL`    | `https://opendata.nederlandwereldwijd.nl/v2`    | Basis-URL van de API                  |
| `API_LANG`        | `nl`                                            | Taal (`nl` of `en`)                   |
| `HTTP_TIMEOUT`    | `30`                                            | Time-out per verzoek (seconden)       |
| `HTTP_RETRIES`    | `3`                                             | Aantal nieuwe pogingen bij fouten     |
| `HTTP_THROTTLE_MS`| `150`                                           | Pauze tussen verzoeken (milliseconden)|

## Licentie

De opgehaalde gegevens zijn open data van het ministerie van Buitenlandse Zaken.
De code in dit project staat onder de MIT-licentie.
