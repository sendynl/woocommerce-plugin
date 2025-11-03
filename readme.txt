=== Sendy ===
Plugin Name: Sendy
Plugin URI: https://app.sendy.nl/
Description: A WooCommerce plugin that connects your site to the Sendy platform
Version: 3.2.7
Stable tag: 3.2.7
License: MIT
Author: Sendy
Author URI: https://sendy.nl/
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.2
WC tested up to: 9.8.2

Een plugin van Sendy voor WooCommerce waarmee je op eenvoudige wijze labels aan kunt maken voor zendingen.

== Description ==

Koppel je webshop met Sendy en je bestellingen worden automatisch omgezet in verzendlabels. Het installeren van de WooCommerce plug-in om te verbinden met Sendy doe je eenvoudig binnen 2 minuten. Je hebt geen technische kennis nodig.
Met een koppeling bespaar je niet alleen tijd, maar ook frustraties rondom het verzendproces. Al je zendingen staan nu in één overzicht, waardoor jij meer controle hebt over het verzendproces. Je importeert automatisch alle orders direct vanuit je webshop. Kies onder andere uit DHL, PostNL of DPD of combineer vervoerders. Je kunt alle orders in één keer verwerken of per order de verzendopties aanpassen.

Voordelen van de WooCommerce integratie:
- Koppel gemakkelijk zonder het uitwisselen van API-gegevens.
- Eenvoudig zendingen aanmaken en labels printen vanuit de WooCommerce webshop.
- Track en trace codes worden opgeslagen in de WooCommerce webshop.
- Met de parcelshopfinder geef je jouw klant de mogelijkheid om het pakket op te halen bij een parcelshop naar keuze.

Nog geen klant van Sendy? Ga naar www.sendy.nl en maak een gratis online account aan.

== Installation ==

1. Ga bij WooCommerce in de linker werkbalk naar Plugins → Nieuwe plugin toevoegen.
2. Tik in de zoekbalk 'Sendy' in.
3. Installeer en activeer de Sendy plugin.
4. Ga in de back-end van je webshop naar *WooCommerce* -> *Sendy*.
5. Klik op *Verbinden*. Je gaat nu naar je Sendy verzendaccount. Mogelijk moet je hier nog inloggen.
6. Klik op *Toegang geven*.
7. Als alles gelukt is zie je bovenin WooCommerce de melding '*Authenticatie gelukt*'

= Handleiding =

Meer informatie over de werking van de plugin is te vinden in de [kennisbank](https://support.sendy.nl/kennisbank/handleiding-woocommerce).

== External services ==

Deze plugin verbindt met de Sendy API om zendingen aan te maken.

Hierbij worden de adres- en contactgegevens van de je klanten en (optioneel) de bestelde producten doorgestuurd naar Sendy zodra je de zending aanmaakt.

Hierop zijn onze [algemene voorwaarden](https://sendy.nl/algemene-voorwaarden/) en [privacy statement](https://sendy.nl/privacy-statement/) van toepassing.

== Changelog ==

= 3.2.7 =
* Use a singleton for the API client to prevent exceptions regarding revoked tokens

= 3.2.6 =
* Flush the cache when tokens are refreshed

= 3.2.5 =
* Use the order number as reference when creating a shipment

= 3.2.4 =
* Convert the weight to kilograms before usage in the API

= 3.2.3 =
* Fix an issue where the webhook was not using the correct URL
* Respect the weight defined on the shipping preference in the portal instead of using 1kg

= 3.2.2 =
* Fix an issue where the order status was not updated correctly

= 3.2.1 =
* Restore the field for free shipping for the pick-up point delivery shipping method

= 3.2.0 =
* Add a different shipping method

= 3.1.3 =
* Add validation for pick-up points in the checkout
* Update the order status when a shipment is created via smart rules

= 3.1.2 =
* Fix an error when creating a shipment
* Fix an issue where the webhook was not installed properly
* Use the order number as reference when creating a shipment
* Use a different method for determining the country when opening the pick-up point finder

= 3.1.1 =
* Fix an error message when synchronizing shipping methods

= 3.1.0 =
* Add support for a different method of creating shipments

= 3.0.9 =
* Fix an issue where plug-in was logged out when using multiple domains

= 3.0.8 =
* Fix an issue where the country was not used when selecting a pick-up point

= 3.0.7 =
* Reduce calls to the API when migrating legacy data
* Improve performance on the overview pages
* Fix an error on the checkout page
* Fix an error where the address was not used when selecting a pick-up point

= 3.0.6 =
* Fix an issue where migrating data to the new format caused an error

= 3.0.4 =
* Prevent excessive requests to the API

= 3.0.3 =
* Fix an issue where labels could not be downloaded
* Fix an error in the order overview tables
* Migrate data to show the shipments created by the legacy plug-in

= 3.0.2 =
* Fix an issue where labels could not be downloaded

= 3.0.1 =
* Update the tested-up-to versions for WordPress and WooCommerce

= 3.0 =
* Initial release of the new Sendy plug-in
