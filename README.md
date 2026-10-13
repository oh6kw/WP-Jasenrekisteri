# Jäsenrekisteri – WordPress-lisäosa yhdistyksille

Jäsenrekisteri ja jäsenmaksulaskutus WordPressin hallintapaneeliin. Lisäosa on tehty radiokerhon tarpeisiin, mutta sopii mille tahansa suomalaiselle yhdistykselle.

Lähetä koko jäsenistölle jäsenmaksulaskut sähköpostilla yhdellä painalluksella. Jokainen lasku saa oman suomalaisen viitenumeron, QR-koodin ja pankkiviivakoodin. Tuo maksut pankin tiedostosta, ja ne kohdistuvat oikeille jäsenille viitteen perusteella, vaikka maksaja olisi puoliso tai vanhempi.

[![Versio](https://img.shields.io/github/v/release/oh6kw/WP-Jasenrekisteri?label=versio)](https://github.com/oh6kw/WP-Jasenrekisteri/releases/latest)
![WordPress 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
[![Lisenssi: GPL v2+](https://img.shields.io/badge/lisenssi-GPL--2.0--or--later-blue)](LICENSE)

![Jäsenlista](docs/kuvat/01-jasenlista.png)

> Kuvissa on keksitty esimerkkikerho. Nimet, kutsutunnukset, osoitteet ja puhelinnumerot ovat kuvitteellisia, ja tilinumero on pankkien yleisesti käyttämä esimerkkinumero.

## Ominaisuudet

**Jäsenet**
- Omat jäsenluokat ja jäsenmaksut, esim. Jäsen 25 €, Nuorisojäsen 10 €, Perhejäsen 10 € ja Kunniajäsen 0 €. Luokat näkyvät jäsenlistassa välilehtinä.
- 0 €:n luokan jäsenille ei tehdä laskua, mutta he saavat tiedotteet.
- Haku, lajittelu, joukkotoiminnot (aseta luokka, merkitse eronneeksi) sekä jokaisen jäsenen lasku- ja maksuhistoria.
- Vapaasti nimettävä lisäkenttä, esim. *Kutsutunnus* tai *Jäsennumero*.
- Tuonti ja vienti CSV-muodossa (Excel-yhteensopiva). Sarakkeet tunnistetaan otsikoista, ja puhelinnumeroiden etunollat säilyvät.
- Varoitus mahdollisesta tuplakirjauksesta.

**Laskutus**
- Lasku koko jäsenistölle tai valituille jäsenille. Summa määräytyy jäsenluokan mukaan, ja sitä voi muuttaa laskukohtaisesti.
- Yksilöllinen viitenumero (7-3-1-tarkiste), josta näkee suoraan laskun ja jäsenen.
- Siisti HTML-lasku, jossa on EPC QR -koodi, pankkiviivakoodi (versio 4) ja virtuaaliviivakoodi tekstinä. Sähköpostissa on mukana myös tekstiversio.
- Ennen lähetystä voi esikatsella laskun ja lähettää testilaskun valittuun osoitteeseen.
- Lähetys tehdään pienissä erissä edistymispalkin kanssa, joten suurikaan jäsenmäärä ei aiheuta aikakatkaisuja. Jo lähetettyjä laskuja ei lähetetä uudelleen.
- Maksumuistutukset käsin tai automaattisesti, esim. 14 päivää eräpäivän jälkeen ja enintään 2 kertaa. Rahastonhoitaja saa halutessaan yhteenvedon lähetetyistä muistutuksista.

**Maksujen kirjaus**
- **Maksut voi tuoda pankista:** KTL-viitesiirtoaineisto, camt.054 / camt.053 (XML) tai tiliote CSV-muodossa.
- Maksut kohdistetaan viitteen perusteella, myös RF-muotoisesta viitteestä. Ennen tallennusta näytetään esikatselu, josta näkee täsmäävät maksut, poikkeavat summat, jo kirjatut maksut ja tuntemattomat viitteet.
- Samaa maksua ei kirjata kahdesti, koska pankin arkistointitunnus tallennetaan.
- Merkintä onnistuu myös käsin, yksittäin tai joukkona. Hakukenttä löytää laskun tiliotteen viitteellä.

**Tiedotteet**
- Sähköposti kaikille jäsenille, yhdelle jäsenluokalle tai viimeisimmän laskun maksamattomille.
- Viestissä voi käyttää paikkamerkkejä, kuten `{etunimi}` ja `{jasenluokka}`. Jokainen saa oman viestinsä, eivätkä osoitteet näy muille.

## Kuvat

| | |
|---|---|
| ![Laskun lähetys ja maksutilanne](docs/kuvat/04-lasku.png) **Lasku:** lähetys, muistutukset ja maksutilanne. Maksajan nimi ja poikkeava summa näkyvät. | ![Lasku sähköpostissa](docs/kuvat/06-lasku-sahkoposti.png) **Jäsenen saama lasku:** jäsenluokka, viite, QR-koodi ja pankkiviivakoodi. |
| ![Uusi lasku](docs/kuvat/05-uusi-lasku.png) **Uusi lasku:** summat jäsenluokittain. Kunniajäsenet (0 €) jätetään pois. | ![Maksujen tuonti](docs/kuvat/07-maksujen-tuonti.png) **Maksujen tuonti pankista:** esikatselu ennen kirjausta. |
| ![Jäsenen tiedot](docs/kuvat/02-jasen.png) **Jäsenen tiedot** sekä kaikki laskut ja maksut. | ![Asetukset](docs/kuvat/08-asetukset.png) **Asetukset:** jäsenluokat ja automaattinen muistutus. |
| ![Tiedote](docs/kuvat/09-tiedote.png) **Tiedote** koko jäsenistölle tai yhdelle luokalle. | ![Laskut](docs/kuvat/03-laskut.png) **Laskut:** maksukertymä ja maksujen tuonti. |

## Asennus

1. Lataa uusin `jasenrekisteri.zip` [julkaisuista (Releases)](https://github.com/oh6kw/WP-Jasenrekisteri/releases/latest).
2. Valitse WordPressissä **Lisäosat → Lisää uusi → Lataa lisäosa** ja anna zip-tiedosto.
3. Ota lisäosa käyttöön. Valikkoon ilmestyy **Jäsenrekisteri**.

> Käytä julkaisun `jasenrekisteri.zip`-tiedostoa, älä GitHubin vihreän *Code*-painikkeen zip-latausta. Julkaisun zipissä lisäosan kansion nimi on `jasenrekisteri`, jolloin myöhemmät päivitykset korvaavat vanhan version oikein.

**Päivitys:** lataa uusi zip samalla tavalla. WordPress tarjoaa nykyisen version korvaamista, ja tietokanta päivittyy automaattisesti. Ota varmuuskopio ennen päivitystä.

## Käyttöönotto

1. **Asetukset:** yhdistyksen nimi, tilinumero (IBAN, ohjelma tarkistaa sen), lähettäjän sähköposti ja vastausosoite (esim. rahastonhoitaja).
2. **Jäsenluokat:** lisää luokat ja niiden jäsenmaksut. Kunniajäsenille jäsenmaksuksi 0 €.
3. **Jäsenet:** tuo CSV-tiedostosta (Jäsenet → Tuo CSV, malli ladattavissa) tai lisää käsin. Jos jäseniä jää ilman luokkaa, aseta luokka joukkona välilehdellä *Ilman jäsenluokkaa*.
4. **SMTP:** asenna esim. [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) tai [FluentSMTP](https://wordpress.org/plugins/fluent-smtp/), jotta viestit lähtevät yhdistyksen oikean sähköpostitilin kautta eivätkä päädy roskapostiin.
5. **Kokeile:** tee lasku ja lähetä testilasku itsellesi. Skannaa QR-koodi pankkisovelluksella, mutta älä hyväksy maksua.

## Jäsenmaksukierros käytännössä

1. **Laskutus → Uusi lasku**, esim. ”Jäsenmaksu 2027”. Tarkista summat ja eräpäivä.
2. Esikatsele ja lähetä testilasku itsellesi, sitten **Lähetä laskut**.
3. Lataa verkkopankista viitesiirtoaineisto tai camt.054 ja tuo se kohdasta **Laskutus → Tuo maksut pankista**. Toista tarvittaessa – samaa maksua ei kirjata kahdesti.
4. Eräpäivän jälkeen maksamattomat saavat muistutuksen automaattisesti tai yhdellä painalluksella.
5. Jäsenet, joilla ei ole sähköpostia, näkyvät laskulla omana ryhmänään. Heidän viitteensä ja summansa ovat taulukossa, joten laskun voi toimittaa muuta kautta.

## Pankkitiedostot

| Muoto | Mistä saa | Huomioita |
|---|---|---|
| KTL-viitesiirtoaineisto (`.txt`) | Verkkopankin *viitesiirto* tai *saapuvat viitemaksut* | Perinteinen suomalainen muoto, kaikki pankit |
| camt.054 (`.xml`) | Verkkopankin *viiteaineisto XML* / *Saapuvat maksut* | ISO 20022, maksajan nimi skandeineen |
| camt.053 (`.xml`) | Verkkopankin *XML-tiliote* | Vain saapuvat maksut käsitellään |
| Tiliote CSV | Verkkopankin tilitapahtumat CSV-muodossa | Sarakkeet tunnistetaan otsikoista (viite ja summa pakollisia) |

Jos oman pankkisi tiedosto ei aukea, avaa [issue](https://github.com/oh6kw/WP-Jasenrekisteri/issues) ja liitä mukaan esimerkki, josta henkilötiedot on poistettu.

## Maksukoodit

- **EPC QR -koodi** (European Payments Council, EPC069-12): moni suomalainen pankkisovellus lukee sen kamerasta, jolloin saaja, tilinumero, summa ja viite täyttyvät valmiiksi. QR-koodissa viite on RF-muodossa (ISO 11649), ja pankit kohdistavat sen samaan viitteeseen.
- **Pankkiviivakoodi**, versio 4 (Finanssiala ry:n opas): Code 128 C -viivakoodi kuvana.
- **Virtuaaliviivakoodi** tekstinä: sen voi kopioida pankkisovellukseen.

Asetuksista voi valita, näytetäänkö QR-koodi, viivakoodi, molemmat vai ei kumpaakaan. Kuvat upotetaan sähköpostiin, joten ne näkyvät ilman ulkoisia latauksia. Kuvien tekemiseen ei tarvita PHP:n GD-laajennusta.

## Tietosuoja ja tietoturva

Jäsenrekisteri sisältää henkilötietoja. Rekisterinpitäjä on yhdistys, ja sen tulee laatia jäsenrekisteristä tietosuojaseloste.

- Lisäosaa voivat käyttää vain ylläpitäjät (`manage_options`).
- Tallentavat toiminnot on suojattu CSRF-tunnisteilla (nonce). Tietokantakyselyt ovat valmisteltuja, ja kaikki tulostettava tieto escapataan.
- CSV-vienti estää kaavojen suorittamisen taulukkolaskennassa.
- Tiedot pysyvät omalla palvelimellasi. Lisäosa ei lähetä tietoja ulkopuolisille palveluille.
- **Lisäosan poistaminen:** kun lisäosa poistetaan käytöstä, kysytään, säilytetäänkö tiedot vai poistetaanko kaikki taulut ja asetukset, jos lisäosa myöhemmin poistetaan kokonaan. Valinnan voi vaihtaa myös asetuksista. Oletuksena tiedot säilyvät.

![Poiston kysely](docs/kuvat/10-poisto.png)

## Vaatimukset

- WordPress 5.8 tai uudempi
- PHP 7.4 tai uudempi
- MySQL / MariaDB
- Toimiva sähköpostin lähetys (SMTP-lisäosa suositeltava)
- Automaattiset muistutukset käyttävät WP-Cronia, joka käynnistyy sivuston käyntien yhteydessä. Hiljaisella sivustolla voi ottaa käyttöön palvelimen cronin.

## Rakenne

```
jasenrekisteri.php          Lisäosan pääsivu, valikot ja kytkennät
uninstall.php               Tietojen poisto lisäosan poiston yhteydessä (jos valittu)
includes/
  install.php               Tietokantataulut ja versiopäivitykset
  helpers.php               Asetukset, viitenumero, IBAN, virtuaaliviivakoodi, CSV, sähköposti
  members.php               Jäsenlista, jäsenlomake, joukkotoiminnot
  import-members.php        Jäsenten CSV-tuonti
  invoices.php              Laskut, lähetys ja maksumerkinnät
  import-payments.php       Pankkitiedostojen (KTL, camt, CSV) tuonti ja kohdistus
  paycodes.php              EPC QR -koodi, pankkiviivakoodi, RF-viite
  email-templates.php       Sähköpostin ja laskun HTML-pohja
  mailer.php                Tiedotteet
  cron.php                  Automaattiset muistutukset
  settings.php              Asetussivu
  lib/jr-qrcode.php         QR-koodikirjasto (MIT)
assets/                     Hallinnan CSS ja JavaScript
```

## Muutokset

Katso [CHANGELOG.md](CHANGELOG.md).

## Lisenssi

[GPL-2.0-or-later](LICENSE) © Jarno Jaskari OH6KW

QR-koodien tekoon käytetään Kazuhiko Arasen [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator)-kirjastoa (MIT-lisenssi, [includes/lib/LICENSE-qrcode.txt](includes/lib/LICENSE-qrcode.txt)).

---

## In English

*Jäsenrekisteri* is a WordPress membership register and dues-invoicing plugin for Finnish associations. It manages members and membership classes, emails individual invoices with Finnish reference numbers, EPC QR codes and Finnish bank barcodes, imports payments from bank files (KTL, camt.054/053, CSV) and matches them by reference, and sends reminders and newsletters. The user interface is in Finnish. License: GPL-2.0-or-later.
