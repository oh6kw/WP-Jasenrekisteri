=== Jäsenrekisteri ===
Contributors: oh6kw
Donate link: https://ko-fi.com/oh6kw
Tags: membership, members, invoices, association, finland
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.6.5
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Membership register and dues invoicing for Finnish associations: Finnish reference numbers, QR and bank barcodes, bank file import.

== Description ==

Jäsenrekisteri ("membership register") is a complete membership register and membership-fee invoicing tool for Finnish associations (yhdistys / ry), built into the WordPress admin. The user interface is in Finnish, because the plugin implements Finnish banking standards.

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
* Members who prefer paper, or have no email address, get a printable A4 invoice that fits a window envelope (C5/DL). An optional postage fee can be added to paper invoices only.
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

**Newsletters**

* Email to all members, one membership class, or members with unpaid invoices. Placeholders such as {etunimi} (first name) personalise every message.

The plugin does not use any external services. All data stays in your own WordPress database. QR codes, barcodes and Excel files are generated locally without extra PHP extensions.

Source code, issue tracker and full Finnish documentation: [github.com/oh6kw/WP-Jasenrekisteri](https://github.com/oh6kw/WP-Jasenrekisteri)

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
3. The invoice the member receives by email, with QR code and bank barcode.
4. Paper invoices: print, put in a window envelope and mark as posted.
5. Printable paper invoice for a window envelope.
6. Importing payments from a bank file, with a preview before saving.
7. Annual report for the general meeting.
8. Audit log: who changed what and when.
9. Voting register for the general meeting.

== Changelog ==

= 4.6.5 =
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

= 4.6.5 =
Adds a voting register and optional milestones. No action needed when updating.

= 4.6.2 =
Settings move to new option names automatically. In cover texts, {summa} is now the membership fee without postage; use {maksettava} for the total amount payable.
