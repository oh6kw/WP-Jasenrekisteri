# Jäsenrekisteri – WordPress-lisäosa yhdistyksille

Jäsenrekisteri ja jäsenmaksulaskutus WordPressin hallintapaneeliin suomalaisille yhdistyksille.

Lähetä koko jäsenistölle jäsenmaksulaskut sähköpostilla yhdellä painalluksella. Jokainen lasku saa oman suomalaisen viitenumeron, QR-koodin ja pankkiviivakoodin. Tuo maksut pankin tiedostosta, ja ne kohdistuvat oikeille jäsenille viitteen perusteella, vaikka maksaja olisi puoliso tai vanhempi.

[![Versio](https://img.shields.io/github/v/release/oh6kw/WP-Jasenrekisteri?label=versio)](https://github.com/oh6kw/WP-Jasenrekisteri/releases/latest)
![WordPress 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
[![Lisenssi: AGPL v3](https://img.shields.io/badge/lisenssi-AGPL--3.0-blue)](LICENSE)
[![Ko-fi](https://img.shields.io/badge/Ko--fi-tarjoa%20kahvit-72a4f2?logo=ko-fi&logoColor=white)](https://ko-fi.com/oh6kw)

![Jäsenlista](docs/kuvat/01-jasenlista.png)

> Kuvissa on keksitty esimerkkiyhdistys. Nimet, jäsennumerot, osoitteet ja puhelinnumerot ovat kuvitteellisia, ja tilinumero on pankkien yleisesti käyttämä esimerkkinumero.

## Ominaisuudet

**Jäsenet**
- Omat jäsenluokat ja jäsenmaksut, esim. Jäsen 25 €, Nuorisojäsen 10 €, Perhejäsen 10 € ja Kunniajäsen 0 €. Luokat näkyvät jäsenlistassa välilehtinä.
- 0 €:n luokan jäsenille ei tehdä laskua, mutta he saavat tiedotteet.
- Haku, lajittelu, joukkotoiminnot (aseta luokka, merkitse eronneeksi) sekä jokaisen jäsenen lasku- ja maksuhistoria.
- Vapaasti nimettävä lisäkenttä, esim. *Jäsennumero*.
- Tuonti ja vienti CSV-muodossa (Excel-yhteensopiva). Sarakkeet tunnistetaan otsikoista, ja puhelinnumeroiden etunollat säilyvät.
- Varoitus mahdollisesta tuplakirjauksesta.
- **Kesken kauden liittyvä jäsen** lisätään jäsenlomakkeesta suoraan jo lähetetylle laskulle. Hän saa oman viitteen ja eräpäivän, ja laskun voi lähettää heti.

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
- **Excel-raportti:** yhdellä painalluksella kaikki laskut yhteen Excel-tiedostoon: yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään (kuka on maksanut, kuka ei, maksupäivät ja maksajat).

**Tiedotteet**
- Sähköposti kaikille jäsenille, yhdelle jäsenluokalle tai viimeisimmän laskun maksamattomille.
- Viestissä voi käyttää paikkamerkkejä, kuten `{etunimi}` ja `{jasenluokka}`. Ne lisätään tekstiin klikkaamalla. Jokainen saa oman viestinsä, eivätkä osoitteet näy muille.

## Kuvat

| | |
|---|---|
| ![Laskun lähetys ja maksutilanne](docs/kuvat/04-lasku.png) **Lasku:** lähetys, muistutukset ja maksutilanne. Maksajan nimi ja poikkeava summa näkyvät. | ![Lasku sähköpostissa](docs/kuvat/06-lasku-sahkoposti.png) **Jäsenen saama lasku:** jäsenluokka, viite, QR-koodi ja pankkiviivakoodi. |
| ![Uusi lasku](docs/kuvat/05-uusi-lasku.png) **Uusi lasku:** summat jäsenluokittain. Luokat, joiden jäsenmaksu on 0 €, jätetään laskutuksesta pois. | ![Maksujen tuonti](docs/kuvat/07-maksujen-tuonti.png) **Maksujen tuonti pankista:** esikatselu ennen kirjausta. |
| ![Jäsenen tiedot](docs/kuvat/02-jasen.png) **Jäsenen tiedot** sekä kaikki laskut ja maksut. | ![Asetukset](docs/kuvat/08-asetukset.png) **Asetukset:** jäsenluokat ja automaattinen muistutus. |
| ![Tiedote](docs/kuvat/09-tiedote.png) **Tiedote** koko jäsenistölle tai yhdelle luokalle. | ![Laskut](docs/kuvat/03-laskut.png) **Laskut:** maksukertymä ja maksujen tuonti. |
| ![Uusi jäsen kesken kauden](docs/kuvat/11-uusi-jasen-laskulle.png) **Kesken kauden liittyvä jäsen** lisätään jo lähetetylle laskulle, ja hän saa oman eräpäivän. | ![Tietoja ja palaute](docs/kuvat/12-tietoja.png) **Tietoja ja palaute:** palautelomake ja Ko-fi. |

## Asennus

1. Lataa uusin `jasenrekisteri.zip` [julkaisuista (Releases)](https://github.com/oh6kw/WP-Jasenrekisteri/releases/latest).
2. Valitse WordPressissä **Lisäosat → Lisää uusi → Lataa lisäosa** ja anna zip-tiedosto.
3. Ota lisäosa käyttöön. Valikkoon ilmestyy **Jäsenrekisteri**.

> Käytä julkaisun `jasenrekisteri.zip`-tiedostoa, älä GitHubin vihreän *Code*-painikkeen zip-latausta. Julkaisun zipissä lisäosan kansion nimi on `jasenrekisteri`, jolloin myöhemmät päivitykset korvaavat vanhan version oikein.

**Päivitys:** lataa uusi zip samalla tavalla. WordPress tarjoaa nykyisen version korvaamista, ja tietokanta päivittyy automaattisesti. Ota varmuuskopio ennen päivitystä.

## Käyttöönotto

1. **Asetukset:** yhdistyksen nimi, tilinumero (IBAN, ohjelma tarkistaa sen), lähettäjän sähköposti ja vastausosoite (esim. rahastonhoitaja).
2. **Jäsenluokat:** lisää luokat ja niiden jäsenmaksut. Jos jonkin luokan jäseniltä ei peritä maksua, aseta sen jäsenmaksuksi 0 €.
3. **Jäsenet:** tuo CSV-tiedostosta (Jäsenet → Tuo CSV, malli ladattavissa) tai lisää käsin. Jos jäseniä jää ilman luokkaa, aseta luokka joukkona välilehdellä *Ilman jäsenluokkaa*.
4. **SMTP:** asenna esim. [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) tai [FluentSMTP](https://wordpress.org/plugins/fluent-smtp/), jotta viestit lähtevät yhdistyksen oikean sähköpostitilin kautta eivätkä päädy roskapostiin.
5. **Kokeile:** tee lasku ja lähetä testilasku itsellesi. Skannaa QR-koodi pankkisovelluksella, mutta älä hyväksy maksua.

## Jäsenmaksukierros käytännössä

1. **Laskutus → Uusi lasku**, esim. ”Jäsenmaksu 2027”. Tarkista summat ja eräpäivä.
2. Esikatsele ja lähetä testilasku itsellesi, sitten **Lähetä laskut**.
3. Lataa verkkopankista viitesiirtoaineisto tai camt.054 ja tuo se kohdasta **Laskutus → Tuo maksut pankista**. Toista tarvittaessa – samaa maksua ei kirjata kahdesti.
4. Eräpäivän jälkeen maksamattomat saavat muistutuksen automaattisesti tai yhdellä painalluksella.
5. Jäsenet, joilla ei ole sähköpostia, näkyvät laskulla omana ryhmänään. Heidän viitteensä ja summansa ovat taulukossa, joten laskun voi toimittaa muuta kautta.
6. **Kesken kauden liittyvä jäsen:** kun lisäät uuden jäsenen, lomakkeessa on kohta *Jäsenmaksu jo tehdyltä laskulta*. Valitse lasku, tarkista summa (osan kaudesta voi laskuttaa pienemmällä summalla) ja tallenna. Jäsen saa oman viitteen ja vähintään kolmen viikon eräpäivän, ja lasku lähtee heti sähköpostilla. Saman voi tehdä myöhemmin jäsenen muokkaussivulta. Laskun sivun painike *Lisää laskulle uudet jäsenet* lisää kerralla kaikki laskulta puuttuvat maksavat jäsenet.

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
- Tiedot pysyvät omalla palvelimellasi. Lisäosa ei lähetä tietoja ulkopuolisille palveluille. Ainoa poikkeus on palautelomake: se lähettää vain itse kirjoittamasi viestin ja halutessasi versiotiedot.
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
  export-xlsx.php           Laskujen Excel-raportti
  xlsx.php                  Excel-kirjoitin (ei ulkoisia kirjastoja)
  about.php                 Tietoja ja palaute -sivu
  lib/jr-qrcode.php         QR-koodikirjasto (MIT)
assets/                     Hallinnan CSS ja JavaScript
```

## Palaute ja kehitysehdotukset

Kehitysehdotukset ja vikailmoitukset ovat tervetulleita:
- lisäosan oma **Tietoja ja palaute** -sivu (lomake lähettää viestin kehittäjälle)
- sähköposti: [jarno.jaskari@gmail.com](mailto:jarno.jaskari@gmail.com)
- [GitHub-issues](https://github.com/oh6kw/WP-Jasenrekisteri/issues)

### Tarjoa kahvit

Jos lisäosasta on hyötyä yhdistyksellesi, voit tarjota kehittäjälle kahvit: **[ko-fi.com/oh6kw](https://ko-fi.com/oh6kw)** ☕ Kiitos!

## Tekijät

- **Jarno Jaskari, OH6KW:** alkuperäinen lisäosa (versio 2.6), ideointi, vaatimukset, testaus ja ylläpito
- **Claude (Anthropic):** jatkokehitys yhdessä Jarnon kanssa versiosta 3.0 alkaen

## Muutokset

Katso [CHANGELOG.md](CHANGELOG.md).

## Lisenssi

[GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-or-later) © Jarno Jaskari OH6KW

Saat käyttää, muokata ja jakaa lisäosaa vapaasti. Jos jaat muokatun version tai tarjoat sitä palveluna verkon yli, myös muokattu lähdekoodi on annettava saataville saman lisenssin ehdoin.

QR-koodien tekoon käytetään Kazuhiko Arasen [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator)-kirjastoa (MIT-lisenssi, [includes/lib/LICENSE-qrcode.txt](includes/lib/LICENSE-qrcode.txt)).

---

## In English

*Jäsenrekisteri* is a WordPress membership register and dues-invoicing plugin for Finnish associations. It manages members and membership classes, emails individual invoices with Finnish reference numbers, EPC QR codes and Finnish bank barcodes, imports payments from bank files (KTL, camt.054/053, CSV) and matches them by reference, and sends reminders and newsletters. The user interface is in Finnish. License: AGPL-3.0-or-later. Feedback: jarno.jaskari@gmail.com · Support: [ko-fi.com/oh6kw](https://ko-fi.com/oh6kw)
