# Muutosloki – Jäsenrekisteri

## 4.6.5 (2026-10-15)

### Uutta: äänioikeusluettelo vuosikokoukseen
- Vuosiraportin painikkeiden (*Tulosta*, *Vie Exceliin*) jatkona on painike **🗳 Äänioikeusluettelo**. Se avaa tulostettavan A4-luettelon voimassa olevista jäsenistä aakkosjärjestyksessä. Sarakkeet: numero, nimi, lisätunniste (esim. jäsennumero), jäsenluokka, jäsenmaksun tila, äänioikeus (✓) ja allekirjoitus.
- **Äänioikeus:** jäsenmaksu valitulta laskulta on maksettu, tai jäsenluokan jäsenmaksu on 0 € (esim. kunniajäsen). Muiden tilaksi näytetään *Maksamatta* tai *Ei laskulla*.
- Luettelon yläpalkissa valitaan kokouksen nimi (oletuksena ”Vuosikokous” ja raporttia seuraava vuosi), päivä ja lasku (oletuksena uusin). Valinnalla *Vain äänioikeutetut* luettelo näyttää vain äänioikeutetut. Lopussa on äänioikeutettujen määrä.
- Monisivuinen luettelo toistaa otsikkorivin joka sivulla. Luettelon avaaminen kirjataan muutoslokiin, koska se sisältää henkilötietoja.

### Uutta: merkkipäivät
- Lisäkenttiin tuli kaksi uutta tyyppiä: **Päivämäärä** ja **Syntymäaika (merkkipäivät)**. Jäsenen lomakkeella ne ovat päivämääräkenttiä. CSV-viennissä ne ovat muodossa p.k.vvvv, ja tuonnissa kelpaavat sekä p.k.vvvv että vvvv-kk-pp.
- Asetuksissa on uusi kohta **Merkkipäivät**: *Täytettävät vuodet* (oletuksena 50, 60, 70, 75, 80, 85, 90, 95 ja 100) ja *Jäsenyysvuodet* (oletuksena 10, 20, 25, 30, 40, 50 ja 60). Vuodet voi muuttaa vapaasti, ja tyhjä kenttä poistaa ne käytöstä.
- **Vuosiraportissa** on osio *Merkkipäivät* (päivä, jäsen, merkkipäivä, jäsenluokka), ja se on myös Excel-raportissa omana välilehtenään.
- **Jäsenlistassa** on kortti, joka näyttää seuraavan 60 päivän merkkipäivät ja ensimmäisen niistä, myös vuodenvaihteen yli. Kortista pääsee vuosiraportin merkkipäiviin.
- Karkauspäivänä syntyneen merkkipäivä on muina vuosina 28.2. Mukana ovat vain voimassa olevat jäsenet.
- **Merkkipäivät voi kytkeä päälle ja pois** asetuksista (*Asetukset → Merkkipäivät → Merkkipäivät käytössä*, oletuksena päällä). Pois päältä merkkipäiviä ei näytetä jäsenlistan kortissa, vuosiraportissa eikä Excel-raportissa, ja asetetut vuodet säilyvät.

### Muut muutokset
- Jäsenyyden ehdot yhtenäistetty: jäseniksi lasketaan kaikkialla vain voimassa olevat jäsenet (aiemmin ”ei eronnut”). Tämä vaikuttaa jäsenmäärien, tiedotteiden, asetusten ja joukkotoimintojen ehtoihin.
- Lisäosa toimii edelleen vain WordPressin hallintapaneelissa: sivustolle ei tule lomakkeita eikä lyhytkoodeja. Kokeiluversion 4.7.0 liittymislomake jätettiin pois (liittymiseen kannattaa käyttää esim. Contact Form 7:ää).
- Jos 4.7.0-kokeiluversiolla ehti tulla liittymishakemuksia, ne näkyvät jäsenlistan välilehdellä *Eronneet* merkinnällä *liittymishakemus*. Niitä ei laskuteta eikä lasketa jäseniksi. Hakemuksen voi palauttaa jäseneksi (joukkotoiminto *Palauta jäseneksi* tai jäsenen lomakkeella) tai poistaa.

## 4.6.4 (2026-10-14)

### Korjaus: tyhjä toinen sivu paperilaskun PDF:ssä
- Versiossa 4.6.3 lasku mahtui jo yhdelle sivulle, mutta joillakin koneilla PDF:n perään tuli edelleen tyhjä sivu. Syy: maksuosa työnnettiin sivun alalaitaan. Kun selaimen tulostusalue oli pienempi (oletusreunukset tai Letter-paperikoko), maksuosa tai sivun tyhjä alalaita valui toiselle sivulle.
- Tulosteessa sivu on nyt vain sisällön korkuinen, ja maksuosa tulee heti erittelyn perään. Sivunvaihto tehdään vain laskujen väliin, ei koskaan viimeisen perään.
- Tiiviimpi maksuosa: QR-koodi 25 mm, pankkiviivakoodin korkeus 11 mm, pienemmät välit ja ohjetekstit, tulosteen fonttikoko 9,5 pt. Pitkälläkin saatetekstillä lasku on noin 250 mm korkea.
- Testattu Chromen PDF-tulostuksella viidellä yhdistelmällä: A4 ilman reunuksia, A4 10 mm ja 15 mm reunuksilla sekä Letter 10 mm ja 15 mm reunuksilla. Jokaisessa yksi lasku vie yhden sivun, ja QR-koodi ja pankkiviivakoodi lukeutuvat tulosteesta.
- Postimaksun selitysrivin ja saatteen väliin lisättiin pieni väli.

## 4.6.3 (2026-10-14)

### Korjaus: paperilasku tulostui kahdelle sivulle
- Tulostettaessa laskun perään tuli tyhjä toinen sivu. Syy: sivun korkeus oli tasan A4 (297 mm), ja kun selain käyttää omia tulostusreunuksiaan (Chromessa ”Reunukset: Oletus”), sivu ei mahtunut arkille. Nyt tulostettava sivu on enintään 270 mm korkea, joten se mahtuu yhdelle arkille reunusasetuksesta riippumatta. Jos saateteksti on poikkeuksellisen pitkä, maksuosa siirtyy kokonaisena seuraavalle sivulle eikä leikkaudu pois.
- Testattu Chromen PDF-tulostuksella sekä ilman reunuksia että 10 mm reunuksilla: yksi lasku = yksi sivu, ja QR-koodi ja pankkiviivakoodi lukeutuvat tulosteesta.

### Tiiviimpi lasku
- Oikean yläkulman tietolaatikko (päiväys, tunnus, viite, eräpäivä, jäsenmaksu, postimaksu, maksettava) on tiiviimpi: rivivälit ovat pienemmät, summat on tasattu oikealle ja *Maksettava*-rivin yläpuolella on viiva.
- Yläosan, saatteen, erittelyn ja maksettava-laatikon välit ovat pienemmät, ja tulosteen fonttikoko on 10 pt. Osoite osuu edelleen ikkunakuoren ikkunaan.

## 4.6.2 (2026-10-13)

### Korjaus: postimaksun erittely paperilaskussa
- Paperilaskussa luki saatteessa virheellisesti, että jäsenmaksu on 9,00 €, vaikka jäsenmaksu oli 5,00 € ja postimaksu 4,00 €. Syy: paikkamerkki `{summa}` tarkoitti maksettavaa kokonaissummaa, ja saatteissa sitä käytetään yleensä jäsenmaksun kohdalla.
- **`{summa}` on nyt jäsenmaksu ilman postimaksua**, kuten saatteissa on tapana kirjoittaa (”Jäsenmaksu tässä luokassa: {summa}”). Sähköpostilaskuissa ei ole postimaksua, joten niissä mikään ei muutu.
- Uusi paikkamerkki **`{maksettava}`**: maksettava yhteensä (paperilaskussa jäsenmaksu + postimaksu). `{postimaksu}` toimii edelleen. Vuoden 4.6.1 `{jasenmaksu}` toimii edelleen samana kuin `{summa}`.
- Paperilaskun **yläosan tiedoissa** näkyvät nyt erikseen *Jäsenmaksu*, *Postimaksu* ja *Maksettava* (aiemmin vain ”Summa”).
- Jos saate ei mainitse postimaksua (`{postimaksu}` tai `{maksettava}`), saatteen perään lisätään automaattisesti lihavoitu rivi: ”Paperilaskun postimaksu 4,00 € on lisätty jäsenmaksuun 5,00 €. Maksettava yhteensä 9,00 €.”
- Ikkunakuoren ikkunassa ei enää toistu yhdistyksen nimi kahteen kertaan. Asetuksiin (*Paperilaskut*) tuli kenttä **Yhdistyksen postiosoite**, joka tulostetaan pienellä palautusosoitteeksi vastaanottajan yläpuolelle. Tyhjänä palautusriviä ei tulosteta.

### WordPress.orgin lisäosahakemistoa varten
- **Englanninkielinen `readme.txt`** WordPress.orgin muodossa: kuvaus, asennus, usein kysytyt kysymykset, kuvakaappaukset ja muutokset. Readme on vaatinut englantia heinäkuusta 2025.
- **Yksilöllinen etuliite `jasrek_`** kaikille funktioille, vakioille (`JASREK_`), luokille, asetuksille, välimuistille, ajastuksille, lomakkeiden toiminnoille ja CSS-luokille (aiemmin `jr_`). **Asetukset siirtyvät automaattisesti**: vanhat `jr_settings` ja `jr_db_version` kopioidaan uusille nimille ja poistetaan, ja vanha muistutusajastus korvataan uudella. Hallintasivujen osoitteet muuttuivat (esim. `page=jasrek-laskutus`), joten vanhat kirjanmerkit pitää päivittää.
- **”Update URI” poistettu** lisäosan otsakkeesta. WordPress.orgin lisäosat päivittyvät vain WordPress.orgin kautta.
- **Vähimmäisversio WordPress 6.2** (aiemmin 5.8). Tarvitaan SQL-tunnisteiden turvalliseen sitomiseen (`%i`). ”Tested up to: 7.1”.
- **[Plugin Check](https://wordpress.org/plugins/plugin-check/) menee läpi ilman virheitä ja varoituksia.** Ennen korjauksia löydöksiä oli 191 virhettä ja 421 varoitusta. Korjaukset:
  - Kaikki tulostettava tieto escapataan (`esc_html`, `wp_kses_post`).
  - Kaikki SQL-kyselyt kulkevat `$wpdb->prepare()`-kutsun kautta. Taulujen nimet luetaan `$wpdb`-olion ominaisuuksista (kuten WordPressin omat taulut), IN-listat sidotaan paikkamerkeillä, ja dynaamiset WHERE-ehdot kootaan SQL-literaaleista, joiden arvot sidotaan erikseen. Laskurivikyselyt kulkevat yhden apufunktion (`jasrek_get_rivit`) kautta.
  - Pyyntöparametrit (`$_GET`, `$_POST`, `$_FILES`) luetaan keskitetyillä apufunktioilla, jotka sanitoivat arvot. Lomakkeiden nonce-tarkistus tehdään ennen tietojen käsittelyä.
  - `date()` → `gmdate()`. `utf8_encode()` (vanhentunut PHP 8.2:ssa) korvattu `mb_convert_encoding`/`iconv`-muunnoksella.
  - QR-koodikirjastosta poistettu käyttämättömät kuva-, HTML- ja SVG-tulostusfunktiot. Virheet nostetaan poikkeuksina, ja laskussa on aina vähintään virtuaaliviivakoodi.
- **Hakemiston kuvat** kansiossa `.wordpress-org`: ikoni, banneri ja 8 kuvakaappausta.

## 4.6.1 (2026-10-13)

### Uutta: postimaksu paperilaskuihin
- Asetuksissa on uusi kohta **Paperilaskut → Postimaksu (€)**. Postimaksu lisätään jäsenmaksuun **vain paperilaskun saajille** eli jäsenille, jotka haluavat laskun paperilla tai joilla ei ole sähköpostia. Sähköpostilaskuihin sitä ei lisätä. Oletus on 0 € (ei postimaksua).
- Uuden laskun lomakkeessa on kenttä *Postimaksu paperilaskuihin*. Se esitäytetään asetuksista, ja sitä voi muuttaa laskukohtaisesti. Lomake kertoo, moneenko laskuun postimaksu tulee.
- Laskussa (tuloste ja sähköpostiversio) postimaksu näkyy **omana rivinään**: jäsenmaksu, postimaksu ja maksettava yhteensä. QR-koodi, pankkiviivakoodi ja virtuaaliviivakoodi sisältävät kokonaissumman, joten pankkitiedoston tuonti kohdistaa maksun oikein.
- Laskun sivulla summan alla lukee ”sis. postimaksun 2,50 €”. Laskun kortissa ja laskulistassa näytetään jäsenmaksut ilman postimaksua.
- Jos jäsen vaihtaa paperilaskuun laskun teon jälkeen, laskun Paperilaskut-osio huomauttaa puuttuvasta postimaksusta, ja painike **Lisää postimaksu** lisää sen kaikkiin postittamattomiin ja maksamattomiin paperilaskuihin.
- Laskurivien joukkotoimintoihin tuli **Lisää postimaksu** ja **Poista postimaksu** (vain maksamattomat). Jokainen muutos kirjataan muutoslokiin uuden summan kanssa.
- Myöhemmin laskulle lisätty jäsen (jäsenlomake tai *Lisää laskulle uudet jäsenet*) saa laskun postimaksun, jos hän saa paperilaskun.
- **Uudet paikkamerkit:** `{jasenmaksu}` (jäsenmaksu ilman postimaksua) ja `{postimaksu}`. `{summa}` on maksettava kokonaissumma. Jos saatteessa lukee esimerkiksi ”Jäsenmaksu tässä luokassa: {summa}”, vaihda se muotoon `{jasenmaksu}`, jotta paperilaskun saaja näkee jäsenmaksun ja postimaksun erikseen.
- **Vuosiraportti** erittelee jäsenmaksukertymästä postimaksut ja jäsenmaksut omiin sarakkeisiinsa (myös Excelissä), jos postimaksuja on kertynyt.
- Tietokantaversio 9: sarake `postimaksu` laskuille ja laskuriveille. Päivitys tapahtuu automaattisesti, ja aiemmilla laskuilla postimaksu on 0.

## 4.6.0 (2026-10-13)

### Uutta: paperilaskut
- Jäsenen tiedoissa on valinta **”Haluaa laskun paperilla”**. Laskua ei silloin lähetetä sähköpostilla, vaan se tulostetaan. Jäsenet, joilla ei ole sähköpostia, saavat laskun paperilla aina.
- Laskun sivulla on oma osio **Paperilaskut**: jäsen, postiosoite, viite, summa, postitustila ja maksutilanne. Jokaisella rivillä on painikkeet *Näytä lasku* ja *Tulosta*, ja ylhäällä *Tulosta postittamattomat*, *Tulosta muistutukset erääntyneille* ja *Näytä kaikki paperilaskut*.
- **Tulostettava lasku** on A4-sivu, joka sopii ikkunakuoreen (C5 tai DL, ikkuna vasemmalla). Nimi ja osoite osuvat ikkunaan, ja reunassa on taittomerkit. Laskussa ovat saateteksti, erittely, maksutiedot, EPC QR -koodi, pankkiviivakoodi ja virtuaaliviivakoodi. Osoite jaetaan riveiksi pilkkujen kohdalta. Jos osoite puuttuu, siitä varoitetaan.
- Tulostussivulla on painike **Merkitse postitetuksi**. Postituspäivä näkyy laskun rivillä kohdassa ”Postitettu”. Paperiset muistutukset kirjataan muistutuksiksi kuten sähköpostimuistutukset.
- Sähköpostilähetys, muistutukset (käsin ja automaattiset) ja kesken kauden liittyvän jäsenen ”lähetä heti” ohittavat paperilaskun valinneet. Laskun taulukossa on suodatin *Paperilaskut*, ja uuden laskun sivu kertoo, moniko saa laskun paperilla.
- Jäsenlistassa on merkki *paperilasku* ja kortti ”Saa laskun paperilla”. Valinnan voi asettaa myös joukkotoiminnolla, ja se kulkee CSV-viennissä ja -tuonnissa (sarake *Paperilasku*).

### Uutta: omat lisäkentät
- Asetuksissa voi luoda jäsenille omia kenttiä, esim. **Avain** (kerhotalon avaimen numero, kuten 8.59) tai **Talkoolistalla**.
- Kenttä on joko **teksti** (tyhjä = ei arvoa) tai **valintaruutu** (kyllä/ei). Kentän voi näyttää jäsenlistassa omana sarakkeenaan.
- Kentät näkyvät jäsenlomakkeella, ovat mukana CSV-viennissä ja tunnistetaan CSV-tuonnissa sarakeotsikosta.
- Jäsenlistan haku löytää kentän nimellä (esim. ”avain”) kaikki, joilla kenttä on täytetty, ja tekstikentän arvolla (esim. ”8.59”) oikean jäsenen.
- CSV-viennissä arvot kuten 8.12 suojataan, jottei Excel muuta niitä päivämääriksi.

### Uutta: muutosloki
- Uusi sivu **Jäsenrekisteri → Muutosloki**: kuka muutti mitä ja milloin.
- Kirjataan:
  - jäsenen lisäys, muutokset (jokainen muuttunut kenttä vanha → uusi) ja poisto
  - joukkomuutokset ja CSV-tuonnit
  - maksumerkinnät ja niiden peruminen (käsin, joukkona tai pankkitiedostosta)
  - laskun lähetys ja muistutus, paperilaskun postitus ja laskulta poisto
  - laskun luonti, muokkaus ja poisto, sekä uusien jäsenten lisäys laskulle
  - sähköpostilähetykset (yksi merkintä per lähetysajo vastaanottajineen) ja automaattiset muistutukset
  - asetusmuutokset, tiedotteet sekä tuonnit ja viennit (CSV ja Excel)
- Suodatus aiheen mukaan, haku nimellä, käyttäjällä tai muutoksella, sekä sivutus.
- Jäsenen sivulla on oma **Muutoshistoria**, jossa ovat hänen viimeisimmät merkintänsä, ja linkki kaikkiin merkintöihin.
- Asetuksissa valitaan **säilytysaika** (1–10 vuotta tai pysyvästi, oletus 2 vuotta). Vanhemmat merkinnät poistetaan automaattisesti, koska loki sisältää henkilötietoja.

### Uutta: vuosiraportti vuosikokoukseen
- Uusi sivu **Jäsenrekisteri → Vuosiraportti** vuosivalinnalla:
  - **Jäsenmäärä jäsenluokittain:** jäseniä edellisen vuoden lopussa, liittyneet, eronneet, jäseniä vuoden lopussa (kuluvana vuonna tänään) ja muutos.
  - **Jäsenmaksut:** vuoden laskut (laskuja, maksettu kpl, laskutettu, maksettu €, avoinna, josta erääntynyt) sekä sama jäsenluokittain.
  - **Jäsenmaksukertymä:** vuoden aikana maksupäivän mukaan kirjatut maksut, myös aiempien vuosien laskuista. Luku on vertailukelpoinen kirjanpidon kanssa.
- Painikkeet **Tulosta** (sivu tulostuu ilman WordPressin valikoita) ja **Vie Exceliin** (neljä välilehteä, yhteensä-rivit kaavoina).
- Raportti kertoo, jos eronneilta puuttuu eroamispäivä tai jäseniltä liittymispäivä.

### Muut muutokset
- **Eroamispäivä:** kun jäsen merkitään eronneeksi (lomakkeella, joukkona tai CSV-tuonnissa), eroamispäivä tallentuu. Lomakkeella päivää voi muuttaa, ja se tyhjenee, jos jäsen palautetaan. CSV-vienti ja -tuonti tuntevat sarakkeen *Eronnut*.
- **Jäsenluokka laskutushetkellä:** laskuriville tallennetaan jäsenen luokka laskua tehtäessä. Laskussa, Excel-raportissa ja vuosiraportissa näkyy siksi luokka, jonka mukaan maksu on laskutettu, vaikka jäsenen luokka myöhemmin vaihtuisi. Vanhoille riveille päivitys asettaa nykyisen luokan.
- Laskun muokkaus ei enää nollaa laskun automaattimuistutusvalintaa, jos automaattimuistutus on asetuksissa pois päältä.
- Jäsen-CSV:n mallitiedostossa on uudet sarakkeet (Eronnut, Paperilasku ja omat lisäkentät).
- Tietokantaversio 8: uudet sarakkeet `paperilasku`, `lisakentat` ja `eronnut_pvm` jäsenille, `jasenlaji` laskuriveille ja uusi taulu `jasenrekisteri_loki`. Päivitys tapahtuu automaattisesti, ja lisäosan poisto (jos tietojen poisto on valittu) poistaa myös lokitaulun.

## 4.5.0 (2026-10-13)

### Uutta: laskut Exceliin
- Laskutus-sivulla on painike **Vie kaikki laskut Exceliin**. Se tekee yhden .xlsx-tiedoston, jossa on:
  - **Yhteenveto:** jokaisesta laskusta laskujen, maksettujen, avoimien ja erääntyneiden määrä sekä laskutettu, maksettu ja avoinna oleva summa. Yhteensä-rivillä on kaavat.
  - **Avoimet maksut:** kaikki maksamattomat kaikilta laskuilta yhdellä välilehdellä yhteystietoineen, muistutusmäärineen ja eräpäivineen.
  - **Jokainen lasku omalla välilehdellään:** jäsen, jäsenluokka, viite, summa, eräpäivä, tila (Maksettu / Avoin / Erääntynyt / Maksettu eri summalla, värikoodattuina), maksupäivä, maksettu summa, maksaja, lähetyspäivä ja muistutukset.
- Otsikkorivit on kiinnitetty, sarakkeissa on suodattimet, ja päivämäärät ja eurot ovat oikeassa muodossa, joten niillä voi laskea. Tulostettaessa taulukko sovitetaan sivun leveyteen.
- Yksittäisen laskun sivulla on lisäksi painike **Vie Exceliin**. CSV-vienti on edelleen käytettävissä.
- Excel-tiedosto tehdään ilman ulkoisia kirjastoja tai PHP:n zip-laajennusta, joten vienti toimii kaikilla palvelimilla.

### Yleiskäyttöisyys
- Radioamatööriviittaukset poistettu lisäosasta, README:stä ja kuvista. Esimerkkinä on nyt kuvitteellinen Esimerkkiyhdistys ry jäsennumeroineen.
- Jäsentuonti tunnistaa sarakkeen ”Jäsentunnus”. Tuonti löytää edelleen sarakkeen, jonka nimi on sama kuin asetuksissa annettu lisätunnisteen nimi.

### Korjaukset (koodikatselmointi)
1. **Viitenumeroiden törmäys suurilla jäsen-ID:illä:** kun jäsenten ID:t ylittävät 9999, kahdelle eri laskuriville saattoi syntyä sama viite (esim. lasku 1 + jäsen 12345 ja lasku 11 + jäsen 2345). Tällöin jäsenelle ei syntynyt laskua. Nyt viite tarkistetaan, ja törmäyksessä perään lisätään juokseva numero ennen tarkistetta. Tavallisissa tapauksissa viitteet ovat ennallaan.
2. **Pankin CSV:n summat:** muoto 1,234.50 (pilkku tuhaterottimena) tulkittiin väärin. Desimaalierotin tunnistetaan nyt viimeisestä erottimesta, ja €-merkki ohitetaan.
3. **Tietosuoja:** keskeneräisen jäsen- tai maksutuonnin tiedot (henkilötietoja) poistetaan tietokannasta heti, kun tuonti perutaan. Aiemmin ne vanhenivat tunnin kuluttua.


## 4.4.0 (2026-10-13)

### Uutta: klikattavat paikkamerkit
- Tekstikenttien alla olevat paikkamerkit, kuten `{etunimi}`, `{jasenluokka}` ja `{summa}`, ovat nyt **painikkeita**. Klikkaus lisää merkin tekstiin kohdistimen kohdalle tai valitun tekstin tilalle, ja kohdistin siirtyy merkin perään.
- Käytössä kaikissa viestikentissä: uuden laskun saateteksti, laskun muokkaus, tiedote sekä asetusten oletussaate ja muistutusteksti. Oletussaatteella on nyt oma paikkamerkkirivi, eikä se enää jaa riviä muistutustekstin kanssa.
- Painikkeen päälle vietäessä näytetään selite (esim. ”Summa, esim. 25,00 €”). Paikkamerkit näkyvät nyt myös laskun muokkauskentässä (Muokkaa laskun tietoja).
- Lisätunnisteen paikkamerkki `{tunnus}` näytetään vain, jos lisätunniste on käytössä asetuksissa.


## 4.3.0 (2026-10-13)

### Uutta: kesken kauden liittyvä jäsen
- Jäsenlomakkeessa on uusi kohta **”Jäsenmaksu jo tehdyltä laskulta”**. Uuden tai olemassa olevan jäsenen voi lisätä jo lähetetylle laskulle, esim. ”Jäsenmaksu 2027”. Summa tulee jäsenluokasta ja päivittyy, kun luokka vaihdetaan. Summaa voi muuttaa, esim. osakauden jäsenmaksuksi.
- **Jäsenkohtainen eräpäivä:** laskulle myöhemmin lisätty jäsen saa eräpäivän vähintään kolmen viikon päähän, jottei lasku ole erääntynyt jo lähtiessään. Eräpäivä näkyy laskussa, QR- ja viivakoodeissa, laskun rivillä (”eräp. …”), erääntymisissä ja automaattisissa muistutuksissa.
- Valinta **”Lähetä lasku heti sähköpostilla”**: lasku lähtee tallennettaessa.
- Laskun sivun painike *Lisää laskulle uudet jäsenet* antaa myöhemmin lisätyille jäsenille saman jäsenkohtaisen eräpäivän, jos laskun eräpäivä on jo lähellä tai mennyt.
- Laskun kortissa näkyy ”Avoinna, josta erääntynyt …”, ja laskulistassa erääntyneiden määrä jäsenkohtaisten eräpäivien mukaan.

### Uutta: Tietoja ja palaute
- Uusi sivu **Jäsenrekisteri → Tietoja ja palaute**: tekijät, yhteystieto (jarno.jaskari@gmail.com), linkit GitHubiin, muutoslokiin ja vikailmoituksiin.
- **Palautelomake:** kehitysehdotus, virheilmoitus tai kysymys lähtee kehittäjälle sähköpostilla. Vastausosoitteena on lähettäjän oma osoite, ja versiotiedot liitetään mukaan halutessa. Jäsenrekisterin tietoja ei lähetetä.
- **Ko-fi:** ”Tarjoa kahvit” -linkki Tietoja-sivulla, Lisäosat-listassa ja GitHubissa (Sponsor-painike).
- Lisäosat-listaan linkit: GitHub, Palaute ja kehitysehdotukset, Tarjoa kahvit.

### Lisenssi ja tekijät
- Lisenssiksi vaihdettu **GNU Affero General Public License v3.0** (AGPL-3.0-or-later).
- Tekijätiedot: Jarno Jaskari OH6KW (alkuperäinen lisäosa ja ylläpito) ja Claude (Anthropic) jatkokehityksessä.

### Muuta
- Laskun CSV-vientiin lisätty Eräpäivä-sarake.
- README: 0 €:n jäsenluokkia ei yleistetä kunniajäseniin.
- Tietokantaversio 7: laskuriveille sarake `erapaiva`.


## 4.2.0 – ensimmäinen julkinen versio GitHubissa (2026-10-13)
- Julkaistu GitHubissa: README ja kuvakaappaukset.
- Lisäosan otsakkeeseen lisätty Plugin URI, lisenssitiedot ja `Update URI`, jottei WordPress.orgin samanniminen lisäosa voi koskaan korvata tätä päivityksenä.
- Julkaisun zip rakennetaan automaattisesti (GitHub Actions), ja kansion nimi on aina `jasenrekisteri`.


## 4.2 (2026-10-13)

### Uutta: tietojen poisto lisäosan poiston yhteydessä
- Kun lisäosa poistetaan käytöstä Lisäosat-sivulla, avautuu kysely: **säilytetäänkö vai poistetaanko** jäsenrekisterin tiedot, jos lisäosa myöhemmin poistetaan kokonaan. Poistovalinnan kohdalla näytetään varoitus ja linkki CSV-vientiin.
- Sama valinta löytyy myös kohdasta **Asetukset → Lisäosan poistaminen**. Oletuksena tiedot säilyvät.
- Uusi `uninstall.php`: jos poisto on valittu, poistetaan kaikki kolme taulua, asetukset ja väliaikaiset tiedot. Toimii myös monisivustoasennuksessa. Automaattisten muistutusten ajastus poistetaan aina.
- Kysely näytetään käytöstä poistettaessa, koska WordPress ei lataa käytöstä poistettua lisäosaa enää varsinaisessa poistovaiheessa.

### Korjaukset
1. **Puhelinnumeron etunolla katosi**, kun vientitiedosto avattiin Excelissä (0401234567 → 401234567), ja +358-numerot muuttuivat luvuiksi. Vienti merkitsee nyt etunollalliset ja +-alkuiset numerot tekstiksi, joten ne näkyvät oikein Excelissä ja LibreOfficessa.
2. **Tuonti korjaa rikkoutuneet numerot:** 401234567 → 0401234567 ja 358401234567 → +358401234567. Tuonti tunnistaa myös oman vientitiedoston tekstimerkinnät, joten vienti → tuonti säilyttää numerot ennallaan.
3. CSV-viennin kaavasuojaus kattaa nyt myös + ja - -merkillä alkavat arvot.

### Parannukset
- Laskussa näkyy oma rivi **Jäsenluokka** (esim. ”Nuorisojäsen”), kun jäsenellä on luokka.
- Paikkamerkki `{jasenluokka}` näyttää ”Jäsen”, jos jäsenellä ei ole luokkaa, joten tekstiin ei jää tyhjää kohtaa.
- `{jasenluokka}` toimii myös tiedotteissa.


## 4.1 (2026-10-13)

### Jäsenluokat välilehdiksi
- Jäsenlistan välilehdet muodostuvat nyt **omista jäsenluokista** (esim. Kaikki jäsenet · Jäsen · Nuorisojäsen · Kunniajäsen · Eronneet). Vanhat välilehdet ”Jäsenet / Maksuvapaat jäsenet / Kaikki” poistuivat.
- ”Ilman jäsenluokkaa” -välilehti ja vinkki näkyvät vain, jos jollakin jäsenellä ei ole luokkaa.
- Jos luokkia ei ole vielä määritelty, listassa näytetään vinkki ja linkki asetuksiin.
- ”Jäsenlaji” on nimetty kaikkialla **”Jäsenluokaksi”**.

### Jäsenmaksu 0 € korvaa maksuvapaan tilan
- Jäsenluokan jäsenmaksuksi voi asettaa **0 €**. Silloin luokan jäsenille ei tehdä laskua eikä heitä muistuteta, mutta he saavat tiedotteet. Nollahintaiset luokat näkyvät violettina merkkinä, ja jäsenmaksusarakkeessa lukee ”ei jäsenmaksua”.
- Erillinen jäsenyyden tila ”maksuvapaa” poistettiin. Jäsenyys on nyt joko **Voimassa** tai **Eronnut**.
- **Automaattinen muunnos:** jos rekisterissä oli maksuvapaiksi merkittyjä, heidät siirretään luokkaan ”Kunniajäsen” (0 €). Luokka luodaan, jos 0 €:n luokkaa ei vielä ole.

### Muut parannukset
- **Joukkotoiminnot jäsenlistassa:** valitse jäsenet ruuduista ja aseta heille jäsenluokka, merkitse eronneiksi tai palauta jäseniksi kerralla.
- Jäsenlomakkeessa jäsenluokka on ensimmäisenä, ja hinta tai ”ei jäsenmaksua” näkyy valinnassa. Kun uusi jäsen lisätään luokan välilehdeltä, se luokka on valmiiksi valittuna.
- **Tiedotteen voi lähettää vain yhdelle jäsenluokalle** (esim. vain nuorisojäsenille).
- Uuden laskun lomakkeessa näkyy luokittain jäsenmäärä ja merkintä ”ei laskua” 0 €:n luokille. Rivi ”Ilman jäsenluokkaa” näkyy vain tarvittaessa.
- CSV-tuonti tunnistaa sarakkeet ”Jäsenluokka” ja ”Luokka”. Jos Jäsenyys-sarakkeessa on luokan nimi (esim. ”Kunniajäsen”), jäsen asetetaan siihen luokkaan. Malli-CSV päivitetty.
- Uusi paikkamerkki `{jasenluokka}` (vanha `{jasenlaji}` toimii edelleen).

### Korjaukset
1. Laskun ”Lisää laskulle uudet jäsenet” -painike laski mukaan myös jäsenet, joille ei kuulu laskua. Nyt mukana ovat vain ne, joiden jäsenmaksu on yli 0 €.
2. Jos jäsenluokka lisättiin laskun luomisen jälkeen, uusille jäsenille käytettiin laskun oletussummaa. Nyt käytetään luokan hintaa.

### Tietokanta
- Tietokantaversio 6 (vain tietojen muunnos, ei uusia sarakkeita).


## 4.0 (2026-10-12)

### Uutta: maksujen tuonti pankista
- **Laskutus → Tuo maksut pankista**: lataa verkkopankista maksutiedosto, ja maksut kohdistetaan laskuihin viitteen perusteella. Maksajan nimellä ei ole väliä, joten esim. puolison maksama lasku kohdistuu oikein.
- Tuetut muodot: **KTL-viitesiirtoaineisto** (.txt), **camt.054** ja **camt.053** (XML), sekä **pankkien CSV-tiliotteet** (sarakkeet tunnistetaan otsikoista, esim. OP ja Nordea). Merkistö tunnistetaan automaattisesti.
- Ennen tallennusta näytetään esikatselu. Täsmäävät maksut on valittu valmiiksi. Jos summa poikkeaa (esim. osamaksu), rivi pitää valita itse.
- Lisäksi tunnistetaan: jo maksetut, tiedostossa toistuvat viitteet, oikaisut ja veloitukset sekä viitteet, joiden tarkiste ei täsmää (näppäilyvirhe).
- **Tuplakirjauksen esto:** pankin arkistointitunnus tallennetaan, joten saman tiedoston voi ladata uudelleen ilman, että mitään kirjataan kahdesti.
- RF-viitteet (kansainvälinen viite) kohdistetaan samaan laskuun kuin kotimainen viite.
- Laskulle tallennetaan maksupäivä, maksaja ja maksettu summa. Laskunäkymässä näkyy ”Maksaja: …”, ja poikkeava summa näytetään oranssilla.

### Uutta: jäsenlajit
- Asetuksissa voi määritellä vapaasti **jäsenlajeja omilla jäsenmaksuillaan** (esim. varsinainen jäsen, opiskelija, perhejäsen). Lajeja voi lisätä, nimetä uudelleen ja poistaa.
- Jäsenelle valitaan laji jäsenlomakkeessa. Laji näkyy jäsenlistassa merkkinä, ja sillä voi hakea.
- Uutta laskua tehdessä summat näytetään lajeittain jäsenmäärineen, ja niitä voi muuttaa kyseiselle laskulle. Jos summa on 0, sen ryhmän jäsenille ei tehdä laskua.
- Laskulistassa ja laskunäkymässä näytetään summaväli (esim. 12,50–25,00 €).
- Uusi paikkamerkki `{jasenlaji}` saatteisiin.

### Uutta: jäsenten tuonti CSV-tiedostosta
- **Jäsenet → Tuo CSV**: sarakkeet tunnistetaan otsikoista (esim. Nimi, Kutsutunnus, Sähköposti, Postinro, Liittymisvuosi, Laji, Jäsenyys), ja ne voi tarkistaa ja vaihtaa ennen tuontia.
- Tukee koko nimeä yhdessä sarakkeessa (Etunimi Sukunimi tai Sukunimi Etunimi), osoitteen yhdistämistä postinumerosta ja -toimipaikasta, pelkkää liittymisvuotta sekä Excelin Windows-merkistöä.
- Jäsenyys tunnistetaan tekstistä (esim. ”Kunniajäsen” → maksuvapaa, ”Eronnut” → eronnut).
- Jos jäsen on jo rekisterissä, hänet voi joko ohittaa tai päivittää. Tyhjät kentät eivät korvaa nykyisiä tietoja. Tunnistus tehdään ensin nimen ja sähköpostin perusteella yhdessä, sitten nimen ja lopuksi sähköpostin perusteella. Pariskunnan yhteinen sähköpostiosoite ei sekoita henkilöitä.
- Malli-CSV ladattavissa. Jäsenrekisterin oma CSV-vienti kelpaa tuontiin sellaisenaan.

### Uutta: automaattinen maksumuistutus (valinnainen)
- Asetuksista päälle ja pois. Muistutus lähtee valitun päivämäärän kuluttua eräpäivästä (oletus 14 päivää), ja niitä lähetetään enintään valittu määrä (oletus 1). Seuraava muistutus lähtee aina saman välin jälkeen.
- Automaattisen muistutuksen voi kytkeä pois myös yksittäiseltä laskulta (Muokkaa laskun tietoja).
- Muistutuksen saavat vain jäsenet, joille lasku on lähetetty ja joilla on sähköposti. Maksuvapaiksi tai eronneiksi merkityt eivät saa muistutusta.
- Rahastonhoitajalle lähetetään valinnaisesti yhteenveto lähetetyistä muistutuksista.
- Laskunäkymässä näkyy, milloin ensimmäinen automaattinen muistutus lähtee.

### Uutta: QR-koodi ja pankkiviivakoodi laskuun
- **EPC QR -koodi** (Euroopan maksuneuvoston standardi): pankkisovellus täyttää saajan, tilinumeron, summan ja viitteen. Viite on QR-koodissa RF-muodossa, jonka pankit kohdistavat samaan viitteeseen.
- **Pankkiviivakoodi** (Code 128 C, versio 4) kuvana.
- Asetuksista valitaan: QR ja viivakoodi, vain QR, vain viivakoodi tai ei kuvaa. Virtuaaliviivakoodi tekstinä on laskussa aina.
- Kuvat upotetaan sähköpostiin liitteinä (cid), joten ne näkyvät ilman ulkoisia latauksia. Kuvat tehdään puhtaalla PHP:llä, eikä GD-laajennusta tarvita.
- Koodit on tarkistettu koneellisesti lukemalla ne sekä tavallisella että tarkalla näytöllä.

### Korjaukset
1. Jos oletustekstin (saate tai muistutus) tallensi tyhjänä, lasku lähti ilman saatetta. Nyt tyhjän tilalla käytetään oletustekstiä.
2. Maksumerkinnän peruminen (”Peru maksu”) tyhjentää nyt myös maksajan, maksetun summan ja arkistointitunnuksen.

### Tekninen
- Tietokantaversio 5: jäsenille `jasenlaji`, laskuille `hinnat` ja `auto_muistutus`, laskuriveille `maksettu_summa`, `maksaja` ja `arkistotunnus`. Päivitys tapahtuu automaattisesti.
- QR-kirjasto: kazuhikoarase/qrcode-generator (MIT-lisenssi, `includes/lib/LICENSE-qrcode.txt`). Luokkien nimet on etuliitetty, jotta ne eivät törmää muiden lisäosien kanssa.
- Lisäosan poistaminen käytöstä pysäyttää automaattisten muistutusten ajastuksen.


## 3.3 (2026-10-12) – katselmointi

### Korjaukset
1. **Tiedotteen lähetys saattoi jäädä alkamatta.** Lähetyksen tunnuksessa oli isoja kirjaimia, mutta WordPress muuttaa sen pieniksi luettaessa. Toimi vain, jos tietokanta vertaa kirjainkoosta välittämättä. Nyt tunnus on aina pienillä kirjaimilla.
2. **Viitehaku ei löytänyt tiliotteen etunollilla täytettyä viitettä** (esim. `00000000000000100010`). Nyt etunollat ohitetaan.
3. **Eräpäivä saattoi olla ennen laskun päiväystä.** Nyt se estetään, ja summan yläraja on tarkistettu (virtuaaliviivakoodin maksimi 999 999,99 €).
4. Jos jollekin jäsenelle ei voitu muodostaa laskua (viitenumero jo käytössä), asiasta ei kerrottu. Nyt näytetään varoitus ja määrä.
5. Tiedotetta ei voi enää ”lähettää” joukolle, jossa ei ole yhtään sähköpostiosoitetta. Näytetään ilmoitus.
6. Laskun CSV-viennissä tunnisteen sarake nimetään asetusten mukaan.

### Parannukset
- **Sähköposteihin lisätty tekstiversio** HTML:n rinnalle (multipart/alternative). Pelkkä HTML-viesti päätyy herkemmin roskapostiin, ja tekstiversio näkyy myös tekstipohjaisissa sähköpostiohjelmissa.
- **Jäsenen sivulla näkyy kaikki hänen laskunsa ja maksunsa**: viite, summa, eräpäivä, maksupäivä ja muistutukset, sekä linkki laskuun.
- **Tuplakirjauksen varoitus:** jos rekisterissä on jo jäsen samalla nimellä tai sähköpostiosoitteella, tallennuksen jälkeen näytetään varoitus.
- Hakemistoihin lisätty tyhjät `index.php`-tiedostot (estää hakemistolistauksen palvelimilla, joissa se on päällä).

### Tarkistettu – kunnossa
- Kaikki tietokantakyselyt käyttävät valmisteltuja lauseita tai kokonaislukuja. Lajittelusarakkeet ovat sallittujen listalla.
- Kaikki tallentavat toiminnot vaativat ylläpitäjän oikeudet ja nonce-tunnisteen (CSRF-suojaus).
- Kaikki tulostettava tieto escapataan (XSS-suojaus), myös JavaScriptissä.
- Sähköpostin otsakkeisiin ei voi syöttää rivinvaihtoja (otsakeinjektio).
- CSV-vienti estää kaavojen suorittamisen Excelissä.
- Tiedostoja ei voi avata suoraan selaimella (ABSPATH-tarkistus).


## 3.2 (2026-10-12)

### Muistutukset
- Muistutukset lasketaan. Laskun rivillä näkyy **oranssi merkki** ”Muistutettu 12.10.2026”, ja useamman muistutuksen jälkeen ”Muistutettu 2× · viimeksi 12.10.2026”.
- Samana päivänä jo muistutetulle ei lähetetä toista muistutusta. Tämä estää vahingossa tehdyn tuplapainalluksen. Kun kaikki maksamattomat on muistutettu, painikkeen tilalla lukee ”Maksamattomia muistutettu tänään”.
- Ennen muistutuksen lähettämistä varmistuskysymys kertoo, moniko vastaanottajista on jo muistutettu aiemmin.
- CSV-viennissä muistutuksista kerrotaan määrä ja viimeisin päivä.

### Lisätunniste
- Uusissa asennuksissa kentän oletusnimi on **Jäsennumero**, eikä isoiksi kirjaimiksi muuntaminen ole oletuksena päällä. Nimen voi vaihtaa asetuksista, esim. ”Tunnus”.
- Asetuksista on poistettu radioamatööriviittaus (”esim. OH6ABC”).
- Päivityksessä olemassa olevat asennukset säilyttävät nimen ”Tunnus” ja isot kirjaimet, ellei asetusta ole jo tallennettu.

### Korjaukset
1. **”73” näkyi yhä oletusteksteissä.** Teksti oli tallentunut tietokantaan asetusten tallennuksen yhteydessä, ja lisäksi jo luodulla laskulla on oma saateteksti. Päivitys vaihtaa allekirjoituksen ”73, {yhdistys}” muotoon ”Ystävällisin terveisin {yhdistys}” sekä asetuksissa että jo luoduissa laskuissa. Muuhun tekstiin ei kosketa.
2. Välilehden nimi ”Maksuvapaat” muutettu muotoon **”Maksuvapaat jäsenet”**.

### Tietokanta
- Laskuriveille on lisätty sarake `muistutuksia` (tietokantaversio 4). Aiemmin lähetetyt muistutukset lasketaan yhdeksi.

## 3.1 (2026-10-12)

### Uutta
- **Kolme jäsentilaa:** *Jäsen*, *Jäsen, ei jäsenmaksua (esim. kunniajäsen)* ja *Eronnut*. Maksuvapaille ei tehdä laskua, mutta he saavat tiedotteet kuten muut jäsenet. Jäsenlistassa on violetti ”maksuvapaa”-merkki ja oma välilehti.
- **Lisätunniste asetuksiin:** ”Tunnus”-kentän nimen voi vaihtaa (esim. Kutsutunnus, Jäsennumero), muunnon isoiksi kirjaimiksi voi kytkeä pois, ja kentän voi piilottaa kokonaan. Plugin sopii näin muillekin yhdistyksille kuin radiokerhoille.
- **Testiviestin osoite näkyy ja on muokattavissa** sekä laskuissa että tiedotteissa. Oletuksena on kirjautuneen WP-käyttäjän osoite, ja käyttäjän nimi näytetään. Laskusta voi testata myös muistutuksen.
- Ilmoitus kertoo, mihin osoitteeseen testiviesti lähti.

### Muutokset
- Oletussaatteissa radioamatöörien ”73” on vaihdettu muotoon ”Ystävällisin terveisin” (yleiskäyttöisyys). Jo tallennetut tekstit eivät muutu.
- Laskun luonnissa kerrotaan, montako maksuvapaata jäsentä jätetään pois.
- Tiedotteen vastaanottajavalinta ”Jäsenet” sisältää myös maksuvapaat.
- Jäsenlistan välilehdillä näkyvät lukumäärät. Haku löytää nimen kummassakin järjestyksessä (etunimi sukunimi / sukunimi etunimi).
- Laskun rivien hakukentän ohjeteksti mainitsee viitteen ensimmäisenä: viitteellä löytyy oikea jäsen, vaikka maksajan nimi olisi eri.
- CSV-viennissä tunnisteen sarake on nimetty asetusten mukaan, ja jäsenyyden tila on kirjoitettu auki.
- Pluginin kuvaus muutettu yleiseksi (”Yhdistyksen jäsenrekisteri”).

## 3.0 (2026-10-12)

### Uutta: jäsenmaksulaskutus
- Uusi **Laskutus**-sivu. Ylläpitäjä syöttää laskun nimen (esim. ”Jäsenmaksu 2027”), summan, laskun päiväyksen, eräpäivän ja saatetekstin.
- Ohjelma luo jokaiselle aktiiviselle jäsenelle (tai valituille jäsenille) oman laskun **yksilöllisellä suomalaisella viitenumerolla** (laskun nro + jäsenen ID + 7-3-1-tarkiste).
- Laskut lähetetään sähköpostilla siistinä HTML-laskuna: saaja, IBAN, BIC, viite, eräpäivä, summa ja **virtuaaliviivakoodi** (pankkiviivakoodi v4), jonka jäsen voi liittää pankkisovellukseen.
- Lähetys tapahtuu pienissä erissä edistymispalkin kanssa – ei aikakatkaisuja. Jo lähetettyjä ei lähetetä uudelleen, vaikka lähetys keskeytyisi.
- Testilasku omaan sähköpostiin ja laskun esikatselu selaimessa ennen lähetystä.
- Maksujen seuranta: merkitse maksetuksi / peru, joukkomerkintä valitulla päivämäärällä, haku nimellä tai viitteellä (tiliotteen täsmäytys).
- **Maksumuistutus** maksamattomille yhdellä painikkeella (oma muistutusteksti asetuksissa).
- Yhteenvetokortit: lähetetty, maksettu, kertynyt ja avoinna oleva summa; erääntyneet merkitään punaisella.
- Laskulle voi jälkikäteen lisätä uudet jäsenet tai poistaa jäsenen (esim. kunniajäsen).
- Laskun nimeä, eräpäivää ja saatetekstiä voi muokata.
- Laskun rivit CSV-tiedostona (Excel-yhteensopiva).
- Jäsentaulukossa näkyy viimeisimmän laskun maksutilanne jokaiselle jäsenelle.

### Uutta: asetukset
- Yhdistyksen nimi, IBAN (tarkistus), BIC, Y-tunnus, viestien alalaidan yhteystiedot.
- Lähettäjän nimi ja osoite, vastausosoite (Reply-To).
- Oletussaateteksti ja muistutusteksti paikkamerkeillä ({etunimi}, {tunnus}, {viite}, {summa}, {erapaiva} …).

### Parannukset: jäsenet
- Uudet kentät: **liittymispäivä**, **jäsenyyden tila** (aktiivinen / eronnut) ja **lisätiedot**. Eronneita ei laskuteta.
- Jäsenlomake ja lista omissa näkymissään; lisäys- ja muokkauslomake ei enää vie tilaa listan yläpuolelta.
- Pikahaku (nimi, tunnus, sähköposti, puhelin), välilehdet aktiiviset / eronneet / kaikki.
- Lajittelu nuolilla, oletuksena sukunimen mukaan.
- Yhteenvetokortit: aktiiviset, eronneet, ilman sähköpostia olevat.
- Jäsenrekisterin vienti CSV-tiedostoon.
- Kutsutunnus muutetaan automaattisesti isoiksi kirjaimiksi.
- Poisto kysyy varmistuksen.

### Parannukset: sähköposti
- Viestit lähtevät yhtenäisellä, puhelimellakin toimivalla HTML-pohjalla.
- Rivinvaihdot säilyvät (aiemmin koko viesti tuli yhtenä rivinä).
- Paikkamerkit {etunimi}, {sukunimi}, {tunnus} – jokainen saa henkilökohtaisen viestin.
- Vastaanottajiksi aktiiviset, kaikki tai viimeisimmän laskun maksamattomat.
- Testiviesti itselle, erälähetys edistymispalkilla, tyhjät osoitteet ohitetaan.

### Korjaukset
1. **SQL-injektio** lajittelussa (`order_by` meni suoraan SQL-lauseeseen) – nyt vain sallitut sarakkeet.
2. **CSRF-suojaus puuttui** kaikista lomakkeista – lisätty nonce-tarkistukset ja oikeustarkistukset.
3. **Jäsenen poisto numeroi kaikkien jäsenten ID:t uudelleen.** Tämä olisi rikkonut viitenumerot ja maksuhistorian – poistettu. ID:t pysyvät pysyvinä.
4. **Sähköpostin lähetyksen jälkeen jäsenlista näkyi tyhjänä**, koska lähetyskoodi korvasi `$members`-muuttujan. Korjattu.
5. Sähköposti lähetettiin HTML-muodossa mutta tekstinä, jolloin rivinvaihdot katosivat. Korjattu.
6. Sähköpostia yritettiin lähettää myös jäsenille, joilla ei ole osoitetta. Korjattu.
7. Sivun päivitys (F5) lähetti lomakkeen uudelleen (esim. tuplajäsenet tai tuplasähköpostit) – nyt käytetään Post/Redirect/Get-mallia.
8. Tietokannan rakenne ei päivittynyt pluginia päivitettäessä (vain aktivoinnissa) – nyt päivitys tapahtuu automaattisesti.
9. Lomakkeiden `label for` -viittaukset eivät osuneet kenttiin (saavutettavuus) – korjattu.
10. Tunnus-kenttä pidennetty 10 → 20 merkkiin.

### Yhteensopivuus
- Olemassa oleva jäsentaulu (`wp_jasenrekisteri`) ja sen tiedot säilyvät; uudet sarakkeet lisätään automaattisesti ja nykyiset jäsenet merkitään aktiivisiksi.
- Vaatii WordPress 5.8+ ja PHP 7.4+.

## 2.6
- Alkuperäinen versio: jäsenten lisäys, muokkaus, poisto ja sähköposti kaikille.
