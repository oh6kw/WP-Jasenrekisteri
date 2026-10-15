<?php
if (!defined('ABSPATH')) exit;

/** Yhteinen sähköpostipohja (inline-tyylit, toimii myös Outlookissa ja puhelimella). */
function jasrek_email_layout($title, $inner) {
    $s = jasrek_settings();
    $footer = $s['yhteystiedot'] !== '' ? nl2br(esc_html($s['yhteystiedot'])) : esc_html($s['yhdistys']);
    if ($s['ytunnus'] !== '') $footer .= '<br>Y-tunnus ' . esc_html($s['ytunnus']);

    return '<!DOCTYPE html><html lang="fi"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#eef1f5;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#1f2933;font-size:15px;line-height:1.55;">'
        . '<tr><td style="background:#1d3557;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:bold;">' . esc_html($s['yhdistys']) . '</td></tr>'
        . '<tr><td style="padding:28px;">' . $inner . '</td></tr>'
        . '<tr><td style="background:#f6f8fa;color:#6b7785;padding:16px 28px;font-size:12px;line-height:1.5;">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Laskurivin henkilötiedot: ensisijaisesti jäsenrekisterin nykyiset tiedot, muuten laskulle tallennetut. */
function jasrek_rivi_person($r) {
    $etu  = isset($r->etunimi) && $r->etunimi !== null ? $r->etunimi : strtok((string) $r->nimi, ' ');
    $suku = isset($r->sukunimi) && $r->sukunimi !== null ? $r->sukunimi : trim(substr((string) $r->nimi, strlen((string) $etu)));
    return array(
        'etunimi'  => (string) $etu,
        'sukunimi' => (string) $suku,
        'nimi'     => trim($etu . ' ' . $suku),
        'tunnus'   => isset($r->tunnus) ? (string) $r->tunnus : '',
        'jasenlaji'=> isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '',
        'jasenluokka' => (isset($r->jasenlaji) && jasrek_laji_nimi((string) $r->jasenlaji) !== '') ? jasrek_laji_nimi((string) $r->jasenlaji) : 'Jäsen',
    );
}

function jasrek_invoice_vars($lasku, $r) {
    $s = jasrek_settings();
    $p = jasrek_rivi_person($r);
    return array_merge($p, array(
        'laskun_nimi' => $lasku->nimi,
        // {summa} = jäsenmaksu (ilman paperilaskun postimaksua), {maksettava} = maksettava yhteensä
        'summa'       => jasrek_money(jasrek_rivi_jasenmaksu($r)),
        'maksettava'  => jasrek_money($r->summa),
        'jasenmaksu'  => jasrek_money(jasrek_rivi_jasenmaksu($r)),
        'postimaksu'  => jasrek_money(jasrek_rivi_postimaksu($r)),
        'viite'       => jasrek_viite_format($r->viite),
        'erapaiva'    => jasrek_date(jasrek_rivi_era($lasku, $r)),
        'laskupvm'    => jasrek_date($lasku->laskupvm),
        'iban'        => jasrek_iban_format($s['iban']),
        'saaja'       => $s['yhdistys'],
        'yhdistys'    => $s['yhdistys'],
    ));
}

/**
 * Postimaksun selite saatteen perään, jos laskussa on postimaksu eikä saate mainitse sitä
 * paikkamerkeillä {postimaksu} tai {maksettava}.
 */
function jasrek_postimaksu_note($text, $r, $vars) {
    if (jasrek_rivi_postimaksu($r) <= 0) return '';
    if (strpos($text, '{postimaksu}') !== false || strpos($text, '{maksettava}') !== false) return '';
    return sprintf('Paperilaskun postimaksu %s on lisätty jäsenmaksuun %s. Maksettava yhteensä %s.', $vars['postimaksu'], $vars['jasenmaksu'], $vars['maksettava']);
}

/**
 * @param string $img 'cid' = kuvat upotetaan sähköpostin liitteinä, 'data' = esikatselu selaimessa.
 */
function jasrek_invoice_html($lasku, $r, $reminder = false, $img = 'cid') {
    $s    = jasrek_settings();
    if ($s['sposti_malli'] === 'tilisiirto') return jasrek_invoice_html_tilisiirto($lasku, $r, $reminder, $img);
    $vars = jasrek_invoice_vars($lasku, $r);
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);

    $row = function ($label, $value, $strong = false) {
        return '<tr><td style="padding:8px 0;color:#6b7785;border-bottom:1px solid #e6e9ee;width:42%;">' . esc_html($label) . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #e6e9ee;' . ($strong ? 'font-weight:bold;' : '') . '">' . $value . '</td></tr>';
    };

    $jasen = esc_html($vars['nimi']) . (jasrek_tunnus_on() && $vars['tunnus'] !== '' ? ' (' . esc_html($vars['tunnus']) . ')' : '');

    $html  = '<p style="margin:0 0 20px;">' . jasrek_fill($text, $vars) . '</p>';
    $note  = jasrek_postimaksu_note($text, $r, $vars);
    if ($note !== '') $html .= '<p style="margin:0 0 20px;font-weight:bold;">' . esc_html($note) . '</p>';
    $html .= '<div style="font-size:12px;letter-spacing:.08em;color:#6b7785;text-transform:uppercase;margin:0 0 4px;">'
           . ($reminder ? 'Maksumuistutus' : 'Lasku') . '</div>';
    $html .= '<div style="font-size:20px;font-weight:bold;margin:0 0 12px;">' . esc_html($lasku->nimi) . '</div>';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">';
    $html .= $row('Jäsen', $jasen);
    $luokka = isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '';
    if ($luokka !== '') $html .= $row('Jäsenluokka', esc_html($luokka));
    $html .= $row('Laskun päiväys', esc_html($vars['laskupvm']));
    $html .= $row('Saaja', esc_html($s['yhdistys']));
    $html .= $row('Saajan tilinumero (IBAN)', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['iban']) . '</span>', true);
    if ($s['bic'] !== '') $html .= $row('BIC', esc_html($s['bic']));
    $html .= $row('Viitenumero', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['viite']) . '</span>', true);
    $html .= $row('Eräpäivä', esc_html($vars['erapaiva']), true);
    if (jasrek_rivi_postimaksu($r) > 0) {
        $html .= $row('Jäsenmaksu', esc_html($vars['jasenmaksu']));
        $html .= $row('Postimaksu (paperilasku)', esc_html($vars['postimaksu']));
    }
    $html .= '</table>';

    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;background:#eaf2fb;border-radius:6px;">'
           . '<tr><td style="padding:14px 18px;font-size:15px;">Maksettava</td>'
           . '<td align="right" style="padding:14px 18px;font-size:22px;font-weight:bold;color:#1d3557;">' . esc_html($vars['maksettava']) . '</td></tr></table>';

    // QR-koodi ja/tai pankkiviivakoodi asetusten mukaan
    $images = jasrek_invoice_images($lasku, $r);
    $src = function ($cid) use ($images, $img) {
        return $img === 'data' ? 'data:image/png;base64,' . base64_encode($images[$cid]) : 'cid:' . $cid;
    };
    if (isset($images['jrqr'])) {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 0;"><tr>'
               . '<td style="padding:0 16px 0 0;vertical-align:middle;"><img src="' . esc_attr($src('jrqr')) . '" width="150" height="150" alt="Maksun QR-koodi" style="display:block;width:150px;height:150px;"></td>'
               . '<td style="vertical-align:middle;font-size:13px;color:#6b7785;">Maksa helposti: avaa pankkisovellus ja skannaa QR-koodi. Saaja, tilinumero, summa ja viite täyttyvät valmiiksi.</td>'
               . '</tr></table>';
    }
    if (isset($images['jrbar'])) {
        // Kuva on piirretty 2× tarkkuudella; näytetään puolikkaana, jotta palkit ovat teräviä (skaalaus 2:1).
        $bw = (int) (unpack('N', substr($images['jrbar'], 16, 4))[1] / 2);
        $html .= '<div style="margin:18px 0 0;"><img src="' . esc_attr($src('jrbar')) . '" width="' . $bw . '" height="50" alt="Pankkiviivakoodi" style="display:block;width:' . $bw . 'px;height:50px;"></div>';
    }

    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    if ($vvk !== '') {
        $html .= '<div style="margin:22px 0 0;padding:14px 16px;border:1px dashed #b8c2cc;border-radius:6px;">'
               . '<div style="font-size:13px;color:#6b7785;margin:0 0 6px;">Virtuaaliviivakoodi – kopioi pankkisovellukseen, niin maksun tiedot täyttyvät valmiiksi:</div>'
               . '<div style="font-family:Consolas,Menlo,monospace;font-size:14px;word-break:break-all;">' . esc_html($vvk) . '</div></div>';
    }

    $html .= '<p style="margin:20px 0 0;font-size:13px;color:#6b7785;">Käytäthän maksaessasi viitenumeroa (ei viestiä), jotta maksu kohdistuu sinulle.</p>';

    return jasrek_email_layout($lasku->nimi, $html);
}

/** Sähköpostilaskun kuvat valitun mallin mukaan (upotetaan viestiin cid-kuvina). */
function jasrek_invoice_email_images($lasku, $r) {
    $s = jasrek_settings();
    if ($s['sposti_malli'] !== 'tilisiirto') return jasrek_invoice_images($lasku, $r);
    if ($s['sposti_qr'] !== '1') return array(); // tilisiirtomalli: ei kuvia eikä liitteitä
    $png = jasrek_qr_png(jasrek_epc_payload($lasku, $r));
    return $png !== '' ? array('jrqr' => $png) : array();
}

/**
 * Sähköpostilasku tilisiirtolomakkeella: sama ulkoasu kuin paperilaskussa, mutta lomake on HTML-taulukko
 * (inline-tyylit, toimii Gmailissa, Outlookissa ja puhelimella). Ei kuvia, ellei QR-koodia ole valittu.
 */
function jasrek_invoice_html_tilisiirto($lasku, $r, $reminder = false, $img = 'cid') {
    $s    = jasrek_settings();
    $vars = jasrek_invoice_vars($lasku, $r);
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);
    $images = jasrek_invoice_email_images($lasku, $r);
    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    $saaja = array_merge(array($s['yhdistys']), array_filter(array_map('trim', preg_split('/\s*[,\n]\s*/', (string) $s['postiosoite']))));
    $maksaja = jasrek_address_lines($r);
    $br = function ($lines) { return implode('<br>', array_map('esc_html', $lines)); };

    $B  = '2px solid #000';   // lomakkeen paksu viiva
    $b  = '1px solid #000';   // ohut viiva
    $th = 'font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:1.25;color:#000;text-align:right;vertical-align:top;padding:5px 6px;font-weight:normal;';
    $td = 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;color:#000;vertical-align:top;padding:5px 7px;';
    $sm = '<span style="display:block;font-size:10px;line-height:1.25;color:#000;">';

    $html  = '<p style="margin:0 0 18px;">' . jasrek_fill($text, $vars) . '</p>';
    $note  = jasrek_postimaksu_note($text, $r, $vars);
    if ($note !== '') $html .= '<p style="margin:0 0 18px;font-weight:bold;">' . esc_html($note) . '</p>';

    // Laskun tiedot
    $html .= '<div style="font-size:12px;letter-spacing:.08em;color:#6b7785;text-transform:uppercase;margin:0 0 4px;">' . ($reminder ? 'Maksumuistutus' : 'Lasku') . '</div>';
    $html .= '<div style="font-size:20px;font-weight:bold;margin:0 0 10px;">' . esc_html($lasku->nimi) . '</div>';
    $info = array(array('Päiväys', $reminder ? jasrek_date(current_time('Y-m-d')) : $vars['laskupvm']));
    if (jasrek_tunnus_on() && $vars['tunnus'] !== '') $info[] = array(jasrek_tunnus_nimi(), $vars['tunnus']);
    $luokka = isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '';
    if ($luokka !== '') $info[] = array('Jäsenluokka', $luokka);
    if (jasrek_rivi_postimaksu($r) > 0) {
        $info[] = array('Jäsenmaksu', $vars['jasenmaksu']);
        $info[] = array('Postimaksu', $vars['postimaksu']);
    }
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:0 0 14px;">';
    foreach ($info as $i) $html .= '<tr><td style="padding:2px 18px 2px 0;color:#6b7785;">' . esc_html($i[0]) . '</td><td style="padding:2px 0;">' . esc_html(html_entity_decode($i[1])) . '</td></tr>';
    $html .= '</table>';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background:#eaf2fb;border-radius:6px;">'
           . '<tr><td style="padding:12px 16px;font-size:15px;">Maksettava viimeistään ' . esc_html($vars['erapaiva']) . '</td>'
           . '<td align="right" style="padding:12px 16px;font-size:22px;font-weight:bold;color:#1d3557;white-space:nowrap;">' . esc_html($vars['maksettava']) . '</td></tr></table>';

    // QR-koodi (valinnainen) lomakkeen yläpuolella
    if (isset($images['jrqr'])) {
        $src = $img === 'data' ? 'data:image/png;base64,' . base64_encode($images['jrqr']) : 'cid:jrqr';
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;"><tr>'
               . '<td style="padding:0 14px 0 0;vertical-align:middle;"><img src="' . esc_attr($src) . '" width="130" height="130" alt="Maksun QR-koodi" style="display:block;width:130px;height:130px;"></td>'
               . '<td style="vertical-align:middle;font-size:13px;color:#6b7785;">Maksa helposti: avaa pankkisovellus ja skannaa QR-koodi. Saaja, tilinumero, summa ja viite täyttyvät valmiiksi.</td></tr></table>';
    }

    // Tilisiirtolomake: kaksi puoliskoa, jotka ovat leveällä näytöllä vierekkäin ja puhelimessa allekkain
    // (inline-block, ei mediakyselyjä – toimii myös Gmailissa ja Outlookissa). Rivikorkeudet täsmäävät puoliskojen välillä.
    $cell = function ($label, $value, $h, $extra = '') use ($th, $td, $B) {
        return '<tr><th style="' . $th . 'width:30%;height:' . $h . 'px;border-bottom:' . $B . ';">' . $label . '</th>'
             . '<td style="' . $td . 'border-left:' . $B . ';border-bottom:' . $B . ';' . $extra . '">' . $value . '</td></tr>';
    };
    $half = 'display:inline-block;width:100%;max-width:272px;vertical-align:top;font-size:14px;';
    $tbl  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:' . $B . ';">';
    $html .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:bold;letter-spacing:.06em;color:#000;margin:0 0 3px;">TILISIIRTO · GIRERING</div>';
    $html .= '<div style="font-size:0;line-height:0;">';
    // Vasen puolisko: saajan tilinumero, saaja, maksaja ja allekirjoitus, tililtä nro
    $html .= '<div style="' . $half . '">' . $tbl
           . $cell('Saajan tilinumero<br>Mottagarens kontonummer', $sm . 'IBAN</span><span style="font-size:15px;white-space:nowrap;">' . esc_html($vars['iban']) . '</span>', 54)
           . $cell('Saaja<br>Mottagare', $br($saaja), 70)
           . $cell('Maksaja<br>Betalare<br><br>Allekirjoitus<br>Underskrift', $br($maksaja) . '<div style="border-bottom:1px solid #000;margin:14px 8px 2px 0;height:1px;line-height:1px;font-size:1px;">&nbsp;</div>', 96)
           . $cell('Tililtä nro<br>Från konto nr', '&nbsp;', 40)
           . '</table></div>';
    // Oikea puolisko: BIC, viesti, viitenumero, eräpäivä ja summa
    $html .= '<div style="' . $half . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:' . $B . ';border-left:' . $B . ';">'
           . '<tr><td colspan="4" style="' . $td . 'height:54px;border-bottom:' . $B . ';">' . $sm . 'BIC</span>' . esc_html($s['bic']) . '</td></tr>'
           . '<tr><td colspan="4" style="' . $td . 'height:126px;border-bottom:' . $B . ';">' . $sm . 'Viesti / Meddelande</span></td></tr>'
           . '<tr><th style="' . $th . 'width:19%;height:40px;border-bottom:' . $B . ';vertical-align:middle;">Viitenro<br>Ref.nr</th>'
           . '<td colspan="3" style="' . $td . 'border-left:' . $b . ';border-bottom:' . $B . ';vertical-align:middle;font-size:15px;white-space:nowrap;"><strong>' . esc_html($vars['viite']) . '</strong></td></tr>'
           . '<tr><th style="' . $th . 'width:19%;height:40px;border-bottom:' . $B . ';vertical-align:middle;">Eräpäivä<br>Förf.dag</th>'
           . '<td style="' . $td . 'border-left:' . $b . ';border-bottom:' . $B . ';vertical-align:middle;font-size:13px;white-space:nowrap;padding-right:4px;"><strong>' . esc_html($vars['erapaiva']) . '</strong></td>'
           . '<th style="' . $th . 'width:15%;border-left:' . $b . ';border-bottom:' . $B . ';vertical-align:middle;">Summa</th>'
           . '<td style="' . $td . 'border-left:' . $b . ';border-bottom:' . $B . ';vertical-align:middle;text-align:right;font-size:13px;white-space:nowrap;padding-left:3px;"><strong>' . esc_html(number_format((float) $r->summa, 2, ',', ' ')) . '&nbsp;EUR</strong></td></tr>'
           . '</table></div>';
    $html .= '</div>';

    if ($vvk !== '') {
        $html .= '<div style="margin:16px 0 0;padding:12px 14px;border:1px dashed #b8c2cc;border-radius:6px;">'
               . '<div style="font-size:13px;color:#6b7785;margin:0 0 6px;">Virtuaaliviivakoodi – kopioi verkkopankkiin, niin maksun tiedot täyttyvät valmiiksi:</div>'
               . '<div style="font-family:Consolas,Menlo,monospace;font-size:14px;word-break:break-all;">' . esc_html($vvk) . '</div></div>';
    }
    $html .= '<p style="margin:16px 0 0;font-size:13px;color:#6b7785;">Käytäthän maksaessasi viitenumeroa (ei viestiä), jotta maksu kohdistuu sinulle.</p>';
    return jasrek_email_layout($lasku->nimi, $html);
}
