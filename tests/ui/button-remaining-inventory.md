# B18 – Vollständiger Quellenanhang zur Button-Restinventur

Stand: 20.11.2026, Basis `7ceb5e6b`, vor B18 erzeugter Rohscan plus explizit erweiterte Laufzeitkontexte. [Gesamtinventur](button-system-inventory.md), [B18-Bericht](standard-area-buttons.md).

Reproduzierbar: `python3 tests/ui/button-inventory.py --remaining --json` und `php tests/ui/button-inventory.php`. Ausschließlich eigene Quellen in den definierten Scanwurzeln; gespeicherte Inhalte, externe Templates und Widgetinnere sind keine vollständig erfasste Grundgesamtheit. Einträge sind Quellen, keine gerenderten Controls und kein Nutzungsnachweis.

## Formular-Konfigurationsstellen

**246 literale Stellen in 50 Dateien:** 66 mit mindestens einem enumerierten SVG-Konstruktorkontext, 180 ohne solchen Treffer. SVG-Felder bleiben außerhalb der freigegebenen Kontexte teilweise Legacy. Fehlender Treffer beweist weder globale Unerreichbarkeit noch eine genaue Live-Legacy-Menge. Keine nicht zugeordnete Formularquelle mit literalem SVG-Opt-in: `[]`.

| Datei | Stellen | Felder mit SVG-Kontext (Quellzeile) | Übrige literale Felder (Quellzeile) |
| --- | ---: | --- | --- |
| `application/forms/Toolbar.php` | 5 |  | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button), pdf:35 (button) |
| `application/forms/Upload.php` | 1 |  | submit:20 (submit) |
| `application/modules/admin/forms/Toolbar.php` | 7 | add:7 (button), edit:16 (button), copy:25 (button), delete:34 (button), filter:43 (button), reset:52 (button), clear:71 (button) |  |
| `application/modules/admin/forms/ToolbarInline.php` | 7 | copy:28 (button) | save:7 (button), view:14 (button), edit:21 (button), delete:35 (button), sortup:46 (button), sortdown:54 (button) |
| `application/modules/campaigns/forms/Toolbar.php` | 7 |  | add:7 (button), edit:16 (button), copy:25 (button), delete:34 (button), clear:53 (button), filter:64 (button), reset:73 (button) |
| `application/modules/campaigns/forms/ToolbarInline.php` | 4 |  | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button) |
| `application/modules/campaigns/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/contacts/forms/Inquiry.php` | 1 | save:17 (submit) |  |
| `application/modules/contacts/forms/InquiryAssignment.php` | 2 |  | assign:28 (submit), remove:35 (submit) |
| `application/modules/contacts/forms/InquiryContact.php` | 1 |  | create:41 (submit) |
| `application/modules/contacts/forms/InquiryToolbar.php` | 3 | filter:7 (button), reset:17 (button), clear:36 (button) |  |
| `application/modules/contacts/forms/InquiryToolbarInline.php` | 2 | view:7 (button), edit:18 (button) |  |
| `application/modules/contacts/forms/Toolbar.php` | 9 | add:12 (button), edit:21 (button), copy:30 (button), delete:39 (button), apply:59 (button), filter:68 (button), reset:77 (button), clear:96 (button) | tags:49 (button) |
| `application/modules/contacts/forms/ToolbarInline.php` | 6 | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button), apply:42 (button) | pdf:35 (button) |
| `application/modules/ebay/forms/Toolbar.php` | 7 |  | add:7 (button), edit:15 (button), save:23 (button), copy:31 (button), delete:39 (button), clear:56 (button), reset:66 (button) |
| `application/modules/ebay/forms/ToolbarInline.php` | 4 |  | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button) |
| `application/modules/items/controllers/StockController.php` | 1 |  | export:59 (button) |
| `application/modules/items/forms/Toolbar.php` | 8 | add:7 (button), edit:16 (button), copy:25 (button), delete:34 (button), apply:43 (button), filter:52 (button), reset:61 (button), clear:80 (button) |  |
| `application/modules/items/forms/ToolbarInline.php` | 5 | edit:7 (button), copy:14 (button), delete:21 (button), apply:35 (button) | pdf:28 (button) |
| `application/modules/items/forms/ToolbarMovements.php` | 2 |  | clear:29 (button), (dynamischer Name):156 (button) |
| `application/modules/items/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/items/forms/ToolbarStock.php` | 4 |  | ledger:22 (button), incoming:36 (button), reservation:51 (button), planning:66 (button) |
| `application/modules/processes/forms/Toolbar.php` | 7 | add:7 (button), edit:16 (button), copy:25 (button), delete:34 (button), filter:43 (button), reset:52 (button), clear:71 (button) |  |
| `application/modules/processes/forms/ToolbarInline.php` | 5 | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button) | pdf:35 (button) |
| `application/modules/processes/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/purchases/forms/Purchaseorder.php` | 1 |  | select:215 (button) |
| `application/modules/purchases/forms/Quoterequest.php` | 1 |  | select:190 (button) |
| `application/modules/purchases/forms/Toolbar.php` | 8 |  | add:7 (button), edit:16 (button), copy:25 (button), cancel:34 (button), delete:43 (button), filter:52 (button), reset:61 (button), clear:80 (button) |
| `application/modules/purchases/forms/ToolbarInline.php` | 5 |  | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button), pdf:35 (button) |
| `application/modules/purchases/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/sales/forms/Creditnote.php` | 1 |  | select:229 (button) |
| `application/modules/sales/forms/Deliveryorder.php` | 1 |  | select:215 (button) |
| `application/modules/sales/forms/Invoice.php` | 1 |  | select:228 (button) |
| `application/modules/sales/forms/Quote.php` | 1 |  | select:181 (button) |
| `application/modules/sales/forms/Reminder.php` | 1 |  | select:215 (button) |
| `application/modules/sales/forms/Salesorder.php` | 1 |  | select:190 (button) |
| `application/modules/sales/forms/Toolbar.php` | 8 |  | add:7 (button), edit:16 (button), copy:25 (button), cancel:34 (button), delete:43 (button), filter:52 (button), reset:61 (button), clear:80 (button) |
| `application/modules/sales/forms/ToolbarInline.php` | 5 |  | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button), pdf:35 (button) |
| `application/modules/sales/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/shops/forms/DynamicForm.php` | 2 |  | back:35 (button), next:40 (submit) |
| `application/modules/shops/forms/OrderToolbar.php` | 3 | filter:7 (button), reset:17 (button), clear:36 (button) |  |
| `application/modules/shops/forms/Toolbar.php` | 7 |  | add:7 (button), edit:15 (button), save:23 (button), copy:31 (button), delete:39 (button), clear:65 (button), reset:72 (button) |
| `application/modules/statistics/forms/Toolbar.php` | 3 | reset:7 (button), filter:28 (button) | export:90 (button) |
| `application/modules/tasks/forms/Toolbar.php` | 7 | add:7 (button), edit:16 (button), copy:25 (button), delete:34 (button), filter:43 (button), reset:52 (button), clear:71 (button) |  |
| `application/modules/tasks/forms/ToolbarInline.php` | 4 | view:7 (button), edit:14 (button), copy:21 (button), delete:28 (button) |  |
| `application/modules/tasks/forms/ToolbarPositions.php` | 14 |  | add-position:23 (button), select-position:32 (button), copy-selected-position:44 (button), delete-selected-position:53 (button), delete-position:62 (button), copy-position:70 (button), sort-position-up:78 (button), sort-position-down:86 (button), add-option:94 (button), add-position-set:102 (button), copy-position-set:111 (button), delete-position-set:120 (button), sort-position-set-up:129 (button), sort-position-set-down:137 (button) |
| `application/modules/users/forms/Login.php` | 1 |  | submit:45 (submit) |
| `application/modules/users/forms/Password.php` | 1 |  | submit:49 (submit) |
| `application/modules/users/forms/User.php` | 1 |  | submit:118 (submit) |
| `library/DEEC/Controller/Action.php` | 1 |  | export:1034 (button) |

Die tatsächlichen Aktivierungsgrenzen jeder zugeordneten Stelle stehen als `svg_activation_contexts` im JSON. Preisregel-/Shop-Felder teilen bestehende Klassen; sie sind keine zusätzlichen Quellfelder. Die drei `Shops_Form_OrderToolbar`-Felder entstanden vor B18 und sind keine B18-Migration.

## Andere Erzeugungsstellen und lexikalische Kandidaten

Alle gefundenen übrigen Quellen, nach Datei und Mechanismus. HTML-Treffer können Kommentare, Factorystrings und Ersetzungsregex enthalten. Linkerkennung ist heuristisch. ZF1-/Helperaufrufe sind eigene Wege; dynamische Ausgaben nicht aus Literalzahlen hochrechnen.

| Datei | Mechanismus → Quellzeilen |
| --- | --- |
| `application/layouts/scripts/site.phtml` | html-button-lexical: 64, 81, 147, 167, 168, 170 |
| `application/modules/admin/forms/Pageblock.php` | add-element-other-argument: 34 |
| `application/modules/admin/views/helpers/Menuitems.php` | html-button-link-lexical: 45 |
| `application/modules/admin/views/helpers/Pageblocks.php` | html-button-link-lexical: 77 |
| `application/modules/admin/views/helpers/SiteMenus.php` | html-button-link-lexical: 46 |
| `application/modules/admin/views/helpers/SitePages.php` | html-button-link-lexical: 46 |
| `application/modules/admin/views/scripts/export/index.phtml` | html-button-lexical: 110, 113, 116, 161, 164; html-button-link-lexical: 22, 39 |
| `application/modules/admin/views/scripts/menuitem/edit.phtml` | html-button-link-lexical: 8 |
| `application/modules/admin/views/scripts/page/edit.phtml` | html-button-link-lexical: 17 |
| `application/modules/admin/views/scripts/settings/clients.phtml` | html-button-lexical: 24, 25 |
| `application/modules/admin/views/scripts/settings/countries.phtml` | legacy-button-helper: 36, 37, 38, 39, 57 |
| `application/modules/admin/views/scripts/settings/manufacturers.phtml` | legacy-button-helper: 29, 30, 31, 32, 46 |
| `application/modules/admin/views/scripts/settings/paymentmethods.phtml` | legacy-button-helper: 29, 30, 31, 32, 46 |
| `application/modules/admin/views/scripts/shop/_redirects.phtml` | html-button-lexical: 35, 62, 82, 100, 101 |
| `application/modules/calendar/views/scripts/index/edit.phtml` | html-button-lexical: 10 |
| `application/modules/calendar/views/scripts/index/index.phtml` | html-button-lexical: 42; html-button-link-lexical: 25, 26, 27, 30, 32 |
| `application/modules/calendar/views/scripts/index/participants.phtml` | html-button-lexical: 15, 24, 28, 36, 40, 43, 45, 56 |
| `application/modules/calendar/views/scripts/index/view.phtml` | html-button-lexical: 29; html-button-link-lexical: 25 |
| `application/modules/campaigns/models/List/Recipients.php` | html-button-lexical: 124 |
| `application/modules/campaigns/views/helpers/Errors.php` | html-button-lexical: 84 |
| `application/modules/campaigns/views/scripts/unsubscribe/index.phtml` | html-button-lexical: 23 |
| `application/modules/contacts/forms/Download.php` | zf1-control: 40 |
| `application/modules/contacts/forms/Email.php` | html-button-lexical: 32 |
| `application/modules/contacts/forms/Import.php` | zf1-control: 31 |
| `application/modules/contacts/views/helpers/HistoryToolbar.php` | html-button-lexical: 34 |
| `application/modules/contacts/views/scripts/attachment/upload.phtml` | html-button-lexical: 36 |
| `application/modules/contacts/views/scripts/contact/edit.phtml` | html-button-lexical: 61 |
| `application/modules/contacts/views/scripts/contact/import.phtml` | html-button-lexical: 108, 112 |
| `application/modules/contacts/views/scripts/contact/index.phtml` | html-button-lexical: 32, 35, 48, 51, 54; html-button-link-lexical: 10 |
| `application/modules/contacts/views/scripts/contact/map.phtml` | html-button-lexical: 14, 22, 32; html-button-link-lexical: 13 |
| `application/modules/contacts/views/scripts/email/index.phtml` | html-button-lexical: 120 |
| `application/modules/contacts/views/scripts/inquiry/save.phtml` | html-button-link-lexical: 11 |
| `application/modules/contacts/views/scripts/inquiry/technical-comparison.phtml` | html-button-lexical: 36; html-button-link-lexical: 30, 56 |
| `application/modules/contacts/views/scripts/inquiry/technical-entry.phtml` | html-button-link-lexical: 8, 10 |
| `application/modules/contacts/views/scripts/inquiry/technical-reviews.phtml` | html-button-lexical: 19; html-button-link-lexical: 44, 45, 54 |
| `application/modules/contacts/views/scripts/inquiry/technical.phtml` | html-button-lexical: 46, 95, 125; html-button-link-lexical: 27, 29 |
| `application/modules/contacts/views/scripts/inquiry/workflow.phtml` | html-button-lexical: 48, 78 |
| `application/modules/ebay/forms/Account.php` | zf1-control: 59 |
| `application/modules/ebay/forms/Item.php` | zf1-control: 236 |
| `application/modules/ebay/forms/Listing.php` | zf1-control: 74 |
| `application/modules/ebay/forms/Order.php` | zf1-control: 44 |
| `application/modules/ebay/views/scripts/account/index.phtml` | legacy-button-helper: 57, 58 |
| `application/modules/ebay/views/scripts/listing/index.phtml` | legacy-button-helper: 75, 76 |
| `application/modules/ebay/views/scripts/order/index.phtml` | legacy-button-helper: 45, 46 |
| `application/modules/items/forms/Import.php` | zf1-control: 33 |
| `application/modules/items/forms/Itemlist.php` | zf1-control: 68 |
| `application/modules/items/views/helpers/Images.php` | html-button-lexical: 94 |
| `application/modules/items/views/scripts/item/variant-options.phtml` | html-button-lexical: 82 |
| `application/modules/items/views/scripts/item/variants.phtml` | html-button-lexical: 67 |
| `application/modules/items/views/scripts/ledger/add.phtml` | html-button-lexical: 93, 105, 112, 188 |
| `application/modules/items/views/scripts/ledger/position.phtml` | html-button-lexical: 31 |
| `application/modules/items/views/scripts/stockplanning/edit.phtml` | html-button-lexical: 59, 63, 68 |
| `application/modules/purchases/views/helpers/Actions.php` | html-button-link-lexical: 9, 10, 11, 13, 14, 15 |
| `application/modules/purchases/views/helpers/Finish.php` | html-button-lexical: 80; html-button-link-lexical: 83, 86 |
| `application/modules/sales/views/helpers/Actions.php` | html-button-link-lexical: 10, 11, 12, 13, 14, 16, 17, 18, 19, 20, 21, 23, 24, 25, 26, 27, 28, 29, 31, 32, 33, 34, 36, 37, 38, 39 |
| `application/modules/sales/views/helpers/Finish.php` | html-button-lexical: 86; html-button-link-lexical: 90, 94 |
| `application/modules/sales/views/scripts/invoice/correction.phtml` | html-button-lexical: 34, 50 |
| `application/modules/sales/views/scripts/invoice/xrechnung.phtml` | html-button-lexical: 23, 26 |
| `application/modules/shops/forms/Account.php` | zf1-control: 53 |
| `application/modules/shops/forms/Checkout.php` | add-element-other-argument: 13, 22; zf1-control: 197 |
| `application/modules/shops/forms/Contact.php` | add-element-other-argument: 13, 34, 44; zf1-control: 109 |
| `application/modules/shops/forms/DynamicForm.php` | add-element-other-argument: 32 |
| `application/modules/shops/forms/Item.php` | zf1-control: 32 |
| `application/modules/shops/forms/Order.php` | zf1-control: 44 |
| `application/modules/shops/views/helpers/RenderCategoryNavigation.php` | html-button-lexical: 56, 66 |
| `application/modules/shops/views/helpers/RenderMenuItems.php` | html-button-lexical: 33 |
| `application/modules/shops/views/helpers/RenderSubCategory.php` | html-button-lexical: 28 |
| `application/modules/shops/views/helpers/Slide.php` | html-button-lexical: 27, 33, 81, 86 |
| `application/modules/shops/views/scripts/account/index.phtml` | legacy-button-helper: 55, 56 |
| `application/modules/shops/views/scripts/cart/index.phtml` | html-button-lexical: 37, 48, 49 |
| `application/modules/shops/views/scripts/category/index.phtml` | html-button-lexical: 234, 297, 303 |
| `application/modules/shops/views/scripts/checkout/error.phtml` | html-button-lexical: 28 |
| `application/modules/shops/views/scripts/checkout/index.phtml` | html-button-lexical: 47 |
| `application/modules/shops/views/scripts/checkout/success.phtml` | html-button-lexical: 31 |
| `application/modules/shops/views/scripts/contact/error.phtml` | html-button-lexical: 36 |
| `application/modules/shops/views/scripts/contact/success.phtml` | html-button-lexical: 42 |
| `application/modules/shops/views/scripts/error/site-error.phtml` | html-button-lexical: 14; html-button-link-lexical: 7 |
| `application/modules/shops/views/scripts/inquiry/catalog.phtml` | html-button-lexical: 34, 39 |
| `application/modules/shops/views/scripts/inquiry/index.phtml` | html-button-lexical: 101 |
| `application/modules/shops/views/scripts/inquiry/success.phtml` | html-button-lexical: 47 |
| `application/modules/shops/views/scripts/inquiry/variants.phtml` | html-button-lexical: 35, 62 |
| `application/modules/shops/views/scripts/item/_leasing.phtml` | html-button-lexical: 30 |
| `application/modules/shops/views/scripts/item/index.phtml` | html-button-lexical: 88, 113, 137, 141, 207, 212 |
| `application/modules/shops/views/scripts/order/view.phtml` | html-button-lexical: 76, 89, 98; html-button-link-lexical: 61 |
| `application/modules/shops/views/scripts/page/blocks/cards.phtml` | html-button-link-lexical: 63 |
| `application/modules/shops/views/scripts/page/blocks/cta.phtml` | html-button-link-lexical: 38, 42 |
| `application/modules/shops/views/scripts/page/blocks/hero.phtml` | html-button-link-lexical: 50, 54 |
| `application/modules/shops/views/scripts/search/index.phtml` | html-button-lexical: 31; html-button-link-lexical: 50, 54 |
| `application/modules/statistics/views/scripts/reporting/index.phtml` | html-button-lexical: 29; html-button-link-lexical: 34 |
| `application/modules/tasks/views/scripts/task/edit.phtml` | html-button-lexical: 10 |
| `application/modules/users/views/scripts/user/edit.phtml` | html-button-lexical: 24 |
| `application/modules/users/views/scripts/user/password.phtml` | html-button-lexical: 16 |
| `application/views/helpers/Media.php` | html-button-lexical: 131 |
| `application/views/helpers/Messages.php` | html-button-lexical: 38 |
| `application/views/helpers/MultiForm.php` | html-button-lexical: 180, 196; html-button-link-lexical: 168 |
| `application/views/helpers/Toolbar.php` | html-button-lexical: 270 |
| `application/views/scripts/feedback/index.phtml` | html-button-lexical: 59 |
| `application/views/scripts/partials/calendar-reminders.phtml` | html-button-lexical: 12 |
| `application/views/scripts/partials/document-original-review.phtml` | html-button-lexical: 22, 41 |
| `application/views/scripts/partials/document-revisions.phtml` | html-button-lexical: 24 |
| `application/views/scripts/partials/downloads.phtml` | html-button-lexical: 40, 50 |
| `application/views/scripts/partials/email-messages.phtml` | html-button-lexical: 64, 85 |
| `application/views/scripts/partials/invoice-corrections.phtml` | html-button-lexical: 20 |
| `install/templates/database.php` | html-button-lexical: 57, 66; html-button-link-lexical: 52, 61 |
| `install/templates/filePermissions.php` | html-button-lexical: 38, 46; html-button-link-lexical: 30 |
| `install/templates/header.php` | html-button-lexical: 30 |
| `install/templates/importSQL.php` | html-button-lexical: 28, 36; html-button-link-lexical: 20 |
| `install/templates/introduction.php` | html-button-lexical: 8 |
| `install/templates/license.php` | html-button-lexical: 15; html-button-link-lexical: 8 |
| `install/templates/requirements.php` | html-button-lexical: 61, 69; html-button-link-lexical: 53 |
| `js/calendar-reminders.js` | html-button-lexical: 37, 40 |
| `js/contacts-map.js` | html-button-lexical: 79 |
| `js/global.js` | html-button-lexical: 146, 2218, 2550 |
| `js/shops.js` | html-button-lexical: 52 |
| `library/DEEC/Form.php` | html-button-lexical: 2235; html-button-link-lexical: 1996 |
| `library/DEEC/List.php` | html-button-lexical: 1043, 1082, 1083 |

## Nicht als migriert zu interpretieren

- Sechs Positionsformulare, Positions-/Sethelper und deren Partials: Parent-/Set-/Status-/Bestandsverträge bleiben separat.
- Application-/Dashboard-Legacy, Mail-/Dokument-/PDF-/Zahlungs-/Buchungsaktionen, Tags/Export/Pin, History-/Downloads-/Medienpartials und dynamische MultiForm-/JS-Fabriken bleiben getrennt.
- Interne Shopbestellungen: drei bereits bestehende SVG-Toolbarfelder; Status-/ERP-Erzeugungs-Submits und normale Navigationslinks bleiben fachliche Sonderfälle. Öffentliche Shops/CMS/Checkout/Inquiry, gespeichertes HTML und Drittanbieter-Widgets gehören nicht zum B18-Migrationsscope.
- 36 Toolbarformulardateien und acht Toolbarhelperdateien sind Quellenfamilien; leere/abgeleitete Formulare sind nicht automatisch ungenutzt. 51 Listenmodelle, davon 44 mit deklarierter Actionszelle; 13 Modelle haben mindestens ein in den geprüften Kontexten migriertes Aktionsfeld, 31 Actionsmodelle liegen außerhalb dieser Zuordnung.
- Unveränderte historische Assetkandidaten und deren Beweisgrenzen siehe Gesamtinventur. Keine Assetentfernung aufgrund statischer Nulltreffer.
