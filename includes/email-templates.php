<?php
if (!defined('ABSPATH')) exit;

/** Yhteinen sähköpostipohja (inline-tyylit, toimii myös Outlookissa ja puhelimella). */
function jr_email_layout($title, $inner) {
    $s = jr_settings();
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
function jr_rivi_person($r) {
    $etu  = isset($r->etunimi) && $r->etunimi !== null ? $r->etunimi : strtok((string) $r->nimi, ' ');
    $suku = isset($r->sukunimi) && $r->sukunimi !== null ? $r->sukunimi : trim(substr((string) $r->nimi, strlen((string) $etu)));
    return array(
        'etunimi'  => (string) $etu,
        'sukunimi' => (string) $suku,
        'nimi'     => trim($etu . ' ' . $suku),
        'tunnus'   => isset($r->tunnus) ? (string) $r->tunnus : '',
        'jasenlaji'=> isset($r->jasenlaji) ? jr_laji_nimi((string) $r->jasenlaji) : '',
        'jasenluokka' => (isset($r->jasenlaji) && jr_laji_nimi((string) $r->jasenlaji) !== '') ? jr_laji_nimi((string) $r->jasenlaji) : 'Jäsen',
    );
}

function jr_invoice_vars($lasku, $r) {
    $s = jr_settings();
    $p = jr_rivi_person($r);
    return array_merge($p, array(
        'laskun_nimi' => $lasku->nimi,
        'summa'       => jr_money($r->summa),
        'viite'       => jr_viite_format($r->viite),
        'erapaiva'    => jr_date(jr_rivi_era($lasku, $r)),
        'laskupvm'    => jr_date($lasku->laskupvm),
        'iban'        => jr_iban_format($s['iban']),
        'saaja'       => $s['yhdistys'],
        'yhdistys'    => $s['yhdistys'],
    ));
}

/**
 * @param string $img 'cid' = kuvat upotetaan sähköpostin liitteinä, 'data' = esikatselu selaimessa.
 */
function jr_invoice_html($lasku, $r, $reminder = false, $img = 'cid') {
    $s    = jr_settings();
    $vars = jr_invoice_vars($lasku, $r);
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);

    $row = function ($label, $value, $strong = false) {
        return '<tr><td style="padding:8px 0;color:#6b7785;border-bottom:1px solid #e6e9ee;width:42%;">' . esc_html($label) . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #e6e9ee;' . ($strong ? 'font-weight:bold;' : '') . '">' . $value . '</td></tr>';
    };

    $jasen = esc_html($vars['nimi']) . (jr_tunnus_on() && $vars['tunnus'] !== '' ? ' (' . esc_html($vars['tunnus']) . ')' : '');

    $html  = '<p style="margin:0 0 20px;">' . jr_fill($text, $vars) . '</p>';
    $html .= '<div style="font-size:12px;letter-spacing:.08em;color:#6b7785;text-transform:uppercase;margin:0 0 4px;">'
           . ($reminder ? 'Maksumuistutus' : 'Lasku') . '</div>';
    $html .= '<div style="font-size:20px;font-weight:bold;margin:0 0 12px;">' . esc_html($lasku->nimi) . '</div>';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">';
    $html .= $row('Jäsen', $jasen);
    $luokka = isset($r->jasenlaji) ? jr_laji_nimi((string) $r->jasenlaji) : '';
    if ($luokka !== '') $html .= $row('Jäsenluokka', esc_html($luokka));
    $html .= $row('Laskun päiväys', esc_html($vars['laskupvm']));
    $html .= $row('Saaja', esc_html($s['yhdistys']));
    $html .= $row('Saajan tilinumero (IBAN)', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['iban']) . '</span>', true);
    if ($s['bic'] !== '') $html .= $row('BIC', esc_html($s['bic']));
    $html .= $row('Viitenumero', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['viite']) . '</span>', true);
    $html .= $row('Eräpäivä', esc_html($vars['erapaiva']), true);
    $html .= '</table>';

    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;background:#eaf2fb;border-radius:6px;">'
           . '<tr><td style="padding:14px 18px;font-size:15px;">Maksettava</td>'
           . '<td align="right" style="padding:14px 18px;font-size:22px;font-weight:bold;color:#1d3557;">' . esc_html($vars['summa']) . '</td></tr></table>';

    // QR-koodi ja/tai pankkiviivakoodi asetusten mukaan
    $images = jr_invoice_images($lasku, $r);
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

    $vvk = jr_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jr_rivi_era($lasku, $r));
    if ($vvk !== '') {
        $html .= '<div style="margin:22px 0 0;padding:14px 16px;border:1px dashed #b8c2cc;border-radius:6px;">'
               . '<div style="font-size:13px;color:#6b7785;margin:0 0 6px;">Virtuaaliviivakoodi – kopioi pankkisovellukseen, niin maksun tiedot täyttyvät valmiiksi:</div>'
               . '<div style="font-family:Consolas,Menlo,monospace;font-size:14px;word-break:break-all;">' . esc_html($vvk) . '</div></div>';
    }

    $html .= '<p style="margin:20px 0 0;font-size:13px;color:#6b7785;">Käytäthän maksaessasi viitenumeroa (ei viestiä), jotta maksu kohdistuu sinulle.</p>';

    return jr_email_layout($lasku->nimi, $html);
}
