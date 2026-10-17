=== OH6KW Jäsenrekisteri ===
Contributors: oh6kw
Donate link: https://ko-fi.com/oh6kw
Tags: membership, members, invoices, association, finland
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.8.1
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Membership register and dues invoicing for Finnish associations: Finnish reference numbers, QR and bank barcodes, bank file import.

== Description ==

OH6KW Jäsenrekisteri ("membership register") is a complete membership register and membership-fee invoicing tool for Finnish associations (yhdistys / ry), built into the WordPress admin. The user interface is in Finnish, because the plugin implements Finnish banking standards.

**Members**

* Your own membership classes with their own fees (e.g. Member 25 €, Youth member 10 €, Honorary member 0 €). Members of a 0 € class are never invoiced but still receive newsletters.
* Search, sorting, bulk actions and a full invoice and payment history for every member.
* Custom fields defined in the settings, such as a clubhouse key number (text) or a yes/no checkbox, optionally shown as a column in the member list.
* CSV import and export (Excel compatible). Columns are recognised from the headers.
* The resignation date is recorded, for accurate yearly member counts.
* Optional milestones: members reaching round birthdays (from an optional birth year or birth date) or membership anniversaries; the years are configurable and the feature can be switched off.

**Invoicing**

* One click sends every member an individual invoice by email, with a unique Finnish reference number (viitenumero, 7-3-1 check digit).
* Every invoice contains an EPC QR code, a Finnish bank barcode (version 4) and a virtual barcode, so paying in a banking app takes seconds.
* Members who prefer paper, or have no email address, get a printable A4 invoice that fits a window envelope (C5/DL). An optional postage fee can be added to paper invoices only. Choose between the standard layout and a traditional Finnish bank transfer form (tilisiirtolomake) at the bottom of the page. Email invoices show a clear payment card (amount, due date, copyable payment details, virtual barcode and QR code) and can optionally include the same A4 invoice as a PDF attachment. Any member's invoice can also be printed or saved as PDF from the invoice page.
* Sending runs in small batches with a progress bar, so large member lists do not time out. Test invoices and previews before sending.
* Manual or automatic payment reminders.
* Members who join mid-season can be added to an invoice that has already been sent, with their own due date.

**Payments**

* Import payments from the bank: Finnish reference payment file (KTL), ISO 20022 camt.054 / camt.053 XML, or a bank statement CSV. Payments are matched by reference number, including RF references.
* A preview shows matching payments, differing amounts and unknown references before anything is saved. The same payment is never recorded twice.
* Excel export of all invoices: a summary, all open payments and one sheet per invoice.

**Reports and audit log**

* Voting register for the general meeting: printable list of members entitled to vote (fee paid or fee-exempt class) with a signature column.
* Annual report for the general meeting: member counts per class at the start and end of the year, joined and resigned members, invoiced, paid and open fees, and fees received during the year. Printable and exportable to Excel.
* Audit log: who changed what and when, including old and new values of member data, payments, sending, settings and exports. The retention period is configurable.

**Backup and moving**

* Privacy (GDPR): a pre-filled privacy notice template for the member register, and support for the WordPress personal data export and erase tools (members are found by email address; erasing anonymises the member and keeps the fee amounts for bookkeeping).
* Full backup in one file (JSON, compressed): members, invoices, payments, settings and optionally the audit log. Restore it on the same or another site, for example when the register moves to a new website. A summary is shown before restoring, the file is checked before anything is changed, and the previous state is kept so a restore can be undone.

**Newsletters**

* Email to all members, one membership class, or members with unpaid invoices. Placeholders such as {etunimi} (first name) personalise every message.

Developed by Jarno Jaskari (oh6kw) together with Claude, an AI assistant made by Anthropic.

The plugin does not use any external services. All data stays in your own WordPress database. QR codes, barcodes, PDF and Excel files are generated locally without extra PHP extensions. Bundled libraries: qrcode-generator by Kazuhiko Arase (MIT) and FPDF by Olivier Plathey (permissive FPDF license), both GPL-compatible.

Source code, issue tracker and full Finnish documentation: [github.com/oh6kw/oh6kw-jasenrekisteri](https://github.com/oh6kw/oh6kw-jasenrekisteri)

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the zip file, and activate it.
2. Open Jäsenrekisteri → Asetukset (Settings) and enter the association name, bank account (IBAN) and sender email address.
3. Add membership classes and their fees in the settings.
4. Add members manually or import them from a CSV file (Jäsenet → Tuo CSV).
5. Install an SMTP plugin (for example WP Mail SMTP or FluentSMTP) so that invoices are sent through the association's real email account and do not end up in spam.
6. Create an invoice (Laskutus → Uusi lasku), send a test invoice to yourself and then send the invoices.

== Frequently Asked Questions ==

= Does it work outside Finland? =

The plugin is designed for Finnish associations: it uses Finnish reference numbers, the Finnish bank barcode and Finnish bank file formats, and the user interface is in Finnish. The EPC QR code works with SEPA banks in general.

= Who can use the plugin? =

Only administrators (users with the manage_options capability). Members never see the register.

= Is the member data sent anywhere? =

No. All data stays in your WordPress database. Emails are sent through your site's own email settings. The only outgoing message the plugin can send to the developer is the optional feedback form on the "Tietoja ja palaute" page, and only when you fill it in and press send yourself.

= What happens to the data if I delete the plugin? =

When you deactivate the plugin you are asked whether the data should be kept or removed when the plugin is deleted. By default all data is kept. The choice can also be changed in the settings.

= Which bank files are supported? =

The Finnish reference payment file (KTL / viitesiirtoaineisto), ISO 20022 camt.054 and camt.053 XML files, and bank statement CSV files with reference and amount columns.

== Screenshots ==

1. Member list with membership classes as tabs and a custom field column.
2. Invoice page: sending, reminders and payment status.
3. The email invoice as a payment card: amount and due date, copyable payment details, QR code and virtual barcode.
4. Paper invoices: print, put in a window envelope and mark as posted.
5. Printable paper invoice for a window envelope, with the Finnish bank transfer form. The optional PDF attachment of email invoices looks the same.
6. Importing payments from a bank file, with a preview before saving.
7. Annual report for the general meeting.
8. Audit log: who changed what and when.
9. Voting register for the general meeting.
10. Backup and restore in the settings.
11. The email invoice on a phone.

== Changelog ==

= 4.8.1 =
* Email invoice: the bank transfer form layout is replaced by a payment card that works on phones and computers (amount and due date, copyable payment details, virtual barcode, QR code). Sites that used the old layout switch automatically.
* Invoice page: the PDF button for email recipients is now a Print button that opens the same print view as paper invoices (print or save as PDF). Overdue unpaid invoices get a reminder version.
* Live preview on WordPress.org (WordPress Playground) with fictional demo members.

= 4.8.0 =
* New: optional PDF attachment for email invoices and reminders – the same A4 invoice as the paper invoice, with the bank transfer form, QR code and bank barcode.
* The member identifier setting (member number / call sign) moved to Settings → association details, with a shortcut from the member form.

= 4.7.2 =
* New: separate official association address (Settings → association details), used for the payee on the bank transfer form and in the privacy notice. The envelope return address stays a separate setting.
* Paper invoice: the bank transfer form no longer extends past its area at the bottom of the page; long payee and payer addresses no longer stretch the form.
* Paper invoice: the return address is shown on up to two lines and shrinks to fit; a warning is shown if an address does not fit the envelope window.

= 4.7.1 =
* Email invoice with the bank transfer form now includes the bank barcode (and QR code) according to the payment code setting, and is wider.

= 4.7.0 =
* New: full backup and restore (Settings → Backup and moving), for example for moving the register to another site. The previous state is kept so a restore can be undone.
* New: optional paper invoice layout with the traditional Finnish bank transfer form at the bottom of the page.
* New: optional email invoice layout with the same bank transfer form as HTML, without attachments or images (QR code optional).
* New: privacy notice template and support for the WordPress personal data export and erase tools.

= 4.6.5 =
* Renamed to OH6KW Jäsenrekisteri (slug oh6kw-jasenrekisteri).
* Print pages load their styles and scripts with wp_register_style / wp_print_styles instead of inline tags.
* Works without the PHP mbstring extension.
* New: voting register for the general meeting.
* New: optional milestones (round birthdays and membership anniversaries) with configurable years; optional birth year or date for members; new custom field type Date.

= 4.6.4 =
* Paper invoice: no empty trailing page with Letter paper or large print margins.

= 4.6.3 =
* Paper invoice: fixed an empty second page when printing, and a more compact invoice details box.

= 4.6.2 =
* Paper invoices: the membership fee and the postage fee are itemised in the invoice header and after the cover text. {summa} is now the membership fee without postage, and the new {maksettava} placeholder is the total amount payable.
* Optional return address for window envelopes.
* Prepared for the WordPress.org plugin directory: unique jasrek_ prefix (settings are migrated automatically), English readme, stricter escaping and prepared SQL queries.

= 4.6.1 =
* Optional postage fee added to paper invoices only.

= 4.6.0 =
* Paper invoices for window envelopes, custom member fields, audit log and annual report.

The full Finnish changelog is in CHANGELOG.md.

== Upgrade Notice ==

= 4.7.0 =
Adds backup and restore, privacy tools and the Finnish bank transfer form layout, which is now the default for paper and email invoices (the previous layout can be chosen in the settings).

= 4.6.5 =
Renamed to OH6KW Jäsenrekisteri with a new plugin folder. When updating from an earlier version installed from a zip, deactivate the old plugin (keep data), then activate the new one. Data is kept. Adds a voting register and optional milestones.

= 4.6.2 =
Settings move to new option names automatically. In cover texts, {summa} is now the membership fee without postage; use {maksettava} for the total amount payable.
