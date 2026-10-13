# Muutosloki – Jäsenrekisteri

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
